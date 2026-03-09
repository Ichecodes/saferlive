<?php
declare(strict_types=1);

/**
 * Collects and stores RSS/Atom items into raw_discovery_items.
 */
namespace Lib\Discovery\Collectors;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class RssCollector
{
    private PDO $db;
    private ?string $rawPayloadColumn = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? self::resolveConnection();
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function collect(array $source, ?string $feedXml = null, ?int $jobId = null): array
    {
        $sourceId = (int)($source['id'] ?? 0);
        $platform = strtolower(trim((string)($source['platform'] ?? 'rss')));

        $summary = [
            'source_id' => $sourceId,
            'job_id' => $jobId,
            'platform' => $platform,
            'fetched' => 0,
            'inserted' => 0,
            'duplicates' => 0,
            'errors' => [],
            'items' => [],
        ];

        try {
            if ($feedXml === null) {
                $feedUrl = trim((string)($source['feed_url'] ?? ''));
                if ($feedUrl === '') {
                    throw new RuntimeException('Missing feed_url for RSS collection.');
                }
                $feedXml = $this->fetchFeedXml($feedUrl);
            }
        } catch (Throwable $e) {
            $summary['errors'][] = 'feed_fetch: ' . $e->getMessage();
            error_log('RssCollector feed error: ' . $e->getMessage());
            return $summary;
        }

        $parsedItems = $this->parseFeed($feedXml);

        foreach ($parsedItems as $parsedItem) {
            $summary['fetched']++;

            try {
                $normalized = $this->normalizeFeedItem($parsedItem, $source);
                $result = $this->saveNormalizedItem($normalized, $sourceId, $jobId, $parsedItem);
                $summary['items'][] = $result;

                if ($result['status'] === 'inserted') {
                    $summary['inserted']++;
                } elseif ($result['status'] === 'duplicate') {
                    $summary['duplicates']++;
                }
            } catch (Throwable $e) {
                $summary['errors'][] = 'item_' . $summary['fetched'] . ': ' . $e->getMessage();
                $summary['items'][] = [
                    'status' => 'error',
                    'external_id' => null,
                    'source_url' => null,
                    'raw_item_id' => null,
                ];
                error_log('RssCollector item error: ' . $e->getMessage());
            }
        }

        return $summary;
    }

    /**
     * Parse RSS or Atom XML into a conservative intermediate structure.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseFeed(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($feed === false) {
            return [];
        }

        $items = [];
        $rootName = strtolower($feed->getName());

        if ($rootName === 'rss' || isset($feed->channel)) {
            $channelItems = $feed->channel->item ?? [];
            foreach ($channelItems as $item) {
                $namespaces = $item->getNamespaces(true);
                $contentText = null;
                if (isset($namespaces['content'])) {
                    $contentNode = $item->children($namespaces['content']);
                    $contentText = isset($contentNode->encoded) ? (string)$contentNode->encoded : null;
                }
                $dcAuthor = null;
                if (isset($namespaces['dc'])) {
                    $dcNode = $item->children($namespaces['dc']);
                    $dcAuthor = isset($dcNode->creator) ? (string)$dcNode->creator : null;
                }

                $media = [];
                if (isset($namespaces['media'])) {
                    $mediaNode = $item->children($namespaces['media']);
                    foreach ($mediaNode->content as $mediaContent) {
                        $attrs = $mediaContent->attributes();
                        $media[] = [
                            'url' => isset($attrs['url']) ? (string)$attrs['url'] : null,
                            'type' => isset($attrs['type']) ? (string)$attrs['type'] : 'image',
                        ];
                    }
                    foreach ($mediaNode->thumbnail as $mediaThumb) {
                        $attrs = $mediaThumb->attributes();
                        $media[] = [
                            'url' => isset($attrs['url']) ? (string)$attrs['url'] : null,
                            'type' => 'image',
                        ];
                    }
                }

                $items[] = [
                    'id' => (string)($item->guid ?? ''),
                    'title' => (string)($item->title ?? ''),
                    'link' => (string)($item->link ?? ''),
                    'author' => (string)($item->author ?? $dcAuthor ?? ''),
                    'summary' => (string)($item->description ?? ''),
                    'content' => $contentText,
                    'published_at' => (string)($item->pubDate ?? ''),
                    'media' => $media,
                ];
            }
        } elseif ($rootName === 'feed') {
            $namespaces = $feed->getNamespaces(true);
            $defaultNs = isset($namespaces['']) ? $namespaces[''] : null;
            $entries = $defaultNs !== null ? $feed->children($defaultNs)->entry : $feed->entry;

            foreach ($entries as $entry) {
                $entryNs = $entry->getNamespaces(true);
                $link = '';
                foreach ($entry->link as $ln) {
                    $attrs = $ln->attributes();
                    $rel = isset($attrs['rel']) ? (string)$attrs['rel'] : 'alternate';
                    if ($rel === 'alternate' || $link === '') {
                        $link = isset($attrs['href']) ? (string)$attrs['href'] : $link;
                    }
                }

                $media = [];
                foreach ($entry->link as $ln) {
                    $attrs = $ln->attributes();
                    $rel = isset($attrs['rel']) ? strtolower((string)$attrs['rel']) : '';
                    if ($rel === 'enclosure' && isset($attrs['href'])) {
                        $media[] = [
                            'url' => (string)$attrs['href'],
                            'type' => isset($attrs['type']) ? (string)$attrs['type'] : 'image',
                        ];
                    }
                }

                if (isset($entryNs['media'])) {
                    $mediaNode = $entry->children($entryNs['media']);
                    foreach ($mediaNode->content as $mediaContent) {
                        $attrs = $mediaContent->attributes();
                        $media[] = [
                            'url' => isset($attrs['url']) ? (string)$attrs['url'] : null,
                            'type' => isset($attrs['type']) ? (string)$attrs['type'] : 'image',
                        ];
                    }
                }

                $items[] = [
                    'id' => (string)($entry->id ?? ''),
                    'title' => (string)($entry->title ?? ''),
                    'link' => $link,
                    'author' => isset($entry->author->name) ? (string)$entry->author->name : '',
                    'summary' => (string)($entry->summary ?? ''),
                    'content' => (string)($entry->content ?? ''),
                    'published_at' => (string)($entry->published ?? $entry->updated ?? ''),
                    'media' => $media,
                ];
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $parsedItem
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function normalizeFeedItem(array $parsedItem, array $source): array
    {
        $title = $this->nullableTrim($parsedItem['title'] ?? null);
        $body = $this->nullableTrim($parsedItem['content'] ?? null);
        if ($body === null) {
            $body = $this->nullableTrim($parsedItem['summary'] ?? null);
        }

        $sourceUrl = $this->nullableTrim($parsedItem['link'] ?? null);
        if ($sourceUrl === null) {
            $sourceUrl = $this->nullableTrim($source['feed_url'] ?? null);
        }

        return [
            'external_id' => $this->nullableTrim($parsedItem['id'] ?? null),
            'source_platform' => strtolower(trim((string)($source['platform'] ?? 'rss'))),
            'source_url' => $sourceUrl ?? '',
            'author_name' => $this->nullableTrim($parsedItem['author'] ?? null),
            'author_handle' => null,
            'title' => $title,
            'body' => $body,
            'media' => $this->normalizeMedia(is_array($parsedItem['media'] ?? null) ? $parsedItem['media'] : []),
            'posted_at' => $this->normalizeDateTime($this->nullableTrim($parsedItem['published_at'] ?? null)),
            'raw_payload' => $parsedItem,
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     * @param mixed $rawPayload
     * @return array<string, mixed>
     */
    public function saveNormalizedItem(array $normalized, int $sourceId, ?int $jobId, $rawPayload): array
    {
        $platform = strtolower(trim((string)($normalized['source_platform'] ?? 'rss')));
        $externalId = $this->nullableTrim($normalized['external_id'] ?? null);
        $hash = $this->buildContentHash($normalized);

        if ($externalId !== null && $this->existsByExternalId($platform, $externalId)) {
            return [
                'status' => 'duplicate',
                'external_id' => $externalId,
                'source_url' => $normalized['source_url'] ?? null,
                'raw_item_id' => null,
                'reason' => 'skipped_duplicate_external_id',
            ];
        }

        if ($this->existsByContentHash($hash)) {
            return [
                'status' => 'duplicate',
                'external_id' => $externalId,
                'source_url' => $normalized['source_url'] ?? null,
                'raw_item_id' => null,
                'reason' => 'skipped_duplicate_content_hash',
            ];
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $mediaJson = json_encode($normalized['media'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($mediaJson === false) {
            $mediaJson = '[]';
        }

        $columns = [
            'source_id', 'job_id', 'external_id', 'source_platform', 'source_url',
            'author_name', 'author_handle', 'title', 'body', 'media_json',
            'posted_at', 'fetched_at', 'content_hash', 'is_candidate', 'created_at',
        ];
        $values = [
            ':source_id' => $sourceId > 0 ? $sourceId : null,
            ':job_id' => $jobId,
            ':external_id' => $externalId,
            ':source_platform' => $platform,
            ':source_url' => trim((string)($normalized['source_url'] ?? '')),
            ':author_name' => $this->nullableTrim($normalized['author_name'] ?? null),
            ':author_handle' => $this->nullableTrim($normalized['author_handle'] ?? null),
            ':title' => $this->nullableTrim($normalized['title'] ?? null),
            ':body' => $this->nullableTrim($normalized['body'] ?? null),
            ':media_json' => $mediaJson,
            ':posted_at' => $this->nullableTrim($normalized['posted_at'] ?? null),
            ':fetched_at' => $now,
            ':content_hash' => $hash,
            ':is_candidate' => 0,
            ':created_at' => $now,
        ];

        $rawColumn = $this->detectRawPayloadColumn();
        if ($rawColumn !== null) {
            $columns[] = $rawColumn;
            $rawJson = json_encode($rawPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $values[':raw_payload'] = $rawJson === false ? null : $rawJson;
        } else {
            // TODO: Add raw_payload/raw_json column in raw_discovery_items to preserve exact payload.
        }

        $placeholders = [];
        foreach ($columns as $column) {
            $placeholders[] = ($column === $rawColumn) ? ':raw_payload' : ':' . $column;
        }

        $sql = sprintf(
            'INSERT INTO raw_discovery_items (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        return [
            'status' => 'inserted',
            'external_id' => $externalId,
            'source_url' => $normalized['source_url'] ?? null,
            'raw_item_id' => (int)$this->db->lastInsertId(),
        ];
    }

    public function existsByExternalId(string $platform, string $externalId): bool
    {
        $platform = strtolower(trim($platform));
        $externalId = trim($externalId);
        if ($platform === '' || $externalId === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT id FROM raw_discovery_items WHERE source_platform = :platform AND external_id = :external_id LIMIT 1'
        );
        $stmt->execute([':platform' => $platform, ':external_id' => $externalId]);
        return (bool)$stmt->fetchColumn();
    }

    public function existsByContentHash(string $contentHash): bool
    {
        $contentHash = trim($contentHash);
        if ($contentHash === '') {
            return false;
        }
        $stmt = $this->db->prepare('SELECT id FROM raw_discovery_items WHERE content_hash = :hash LIMIT 1');
        $stmt->execute([':hash' => $contentHash]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $normalized
     */
    public function buildContentHash(array $normalized): string
    {
        $payload = [
            'source_platform' => strtolower(trim((string)($normalized['source_platform'] ?? ''))),
            'source_url' => trim((string)($normalized['source_url'] ?? '')),
            'title' => trim((string)($normalized['title'] ?? '')),
            'body' => trim((string)($normalized['body'] ?? '')),
            'posted_at' => trim((string)($normalized['posted_at'] ?? '')),
            'author_handle' => strtolower(trim((string)($normalized['author_handle'] ?? ''))),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function fetchFeedXml(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'SaferNG-DiscoveryBot/1.0',
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $err !== '') {
            throw new RuntimeException('Unable to fetch RSS feed: ' . $err);
        }
        if ($status >= 400) {
            throw new RuntimeException('RSS feed returned HTTP ' . $status);
        }

        return (string)$body;
    }

    /**
     * @param array<int, mixed> $media
     * @return array<int, array<string, string>>
     */
    private function normalizeMedia(array $media): array
    {
        $normalized = [];
        $seen = [];

        foreach ($media as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = $this->nullableTrim($item['url'] ?? null);
            if ($url === null) {
                continue;
            }
            $type = $this->nullableTrim($item['type'] ?? null) ?? 'image';
            $key = $url . '|' . strtolower($type);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $normalized[] = ['url' => $url, 'type' => strtolower($type)];
        }

        return $normalized;
    }

    private function normalizeDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            $dt = new DateTimeImmutable($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            $ts = strtotime($value);
            return $ts === false ? null : date('Y-m-d H:i:s', $ts);
        }
    }

    /**
     * @param mixed $value
     */
    private function nullableTrim($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }

    private function detectRawPayloadColumn(): ?string
    {
        if ($this->rawPayloadColumn !== null) {
            return $this->rawPayloadColumn !== '' ? $this->rawPayloadColumn : null;
        }

        foreach (['raw_payload', 'raw_json'] as $column) {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM raw_discovery_items LIKE :column_name");
            $stmt->execute([':column_name' => $column]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->rawPayloadColumn = $column;
                return $column;
            }
        }

        $this->rawPayloadColumn = '';
        return null;
    }

    private static function resolveConnection(): PDO
    {
        $configPath = dirname(__DIR__, 3) . '/config/database.php';
        if (is_file($configPath)) {
            require_once $configPath;
        }
        if (!function_exists('getDatabaseConnection')) {
            throw new RuntimeException('Database helper getDatabaseConnection() was not found.');
        }

        try {
            /** @var PDO $pdo */
            $pdo = getDatabaseConnection();
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Unable to obtain database connection.', 0, $e);
        }
    }
}
