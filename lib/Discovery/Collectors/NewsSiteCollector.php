<?php
declare(strict_types=1);

/**
 * Collects and stores article-like content from news/blog pages.
 */
namespace Lib\Discovery\Collectors;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class NewsSiteCollector
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
    public function collect(array $source, ?string $html = null, ?string $url = null, ?int $jobId = null): array
    {
        $sourceId = (int)($source['id'] ?? 0);
        $platform = strtolower(trim((string)($source['platform'] ?? 'news')));

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

        $targetUrl = trim((string)($url ?? ''));
        if ($targetUrl === '') {
            $targetUrl = trim((string)($source['target_url'] ?? ''));
        }
        if ($targetUrl === '') {
            $targetUrl = trim((string)($source['base_url'] ?? ''));
        }
        if ($targetUrl === '') {
            $summary['errors'][] = 'No target URL provided for news/blog collection.';
            return $summary;
        }

        try {
            if ($html === null) {
                $html = $this->fetchHtml($targetUrl);
            }
        } catch (Throwable $e) {
            $summary['errors'][] = 'fetch: ' . $e->getMessage();
            error_log('NewsSiteCollector fetch error: ' . $e->getMessage());
            return $summary;
        }

        $summary['fetched'] = 1;

        try {
            $parsed = $this->parseArticle($html, $targetUrl, $source);
            $normalized = $this->normalizeArticle($parsed, $source);
            $result = $this->saveNormalizedItem($normalized, $sourceId, $jobId, $parsed['raw_payload'] ?? $parsed);
            $summary['items'][] = $result;

            if ($result['status'] === 'inserted') {
                $summary['inserted'] = 1;
            } elseif ($result['status'] === 'duplicate') {
                $summary['duplicates'] = 1;
            }
        } catch (Throwable $e) {
            $summary['errors'][] = 'parse_or_save: ' . $e->getMessage();
            $summary['items'][] = [
                'status' => 'error',
                'external_id' => null,
                'source_url' => $targetUrl,
                'raw_item_id' => null,
            ];
            error_log('NewsSiteCollector parse/save error: ' . $e->getMessage());
        }

        return $summary;
    }

    public function fetchHtml(string $url): string
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
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $error !== '') {
            throw new RuntimeException('Unable to fetch HTML: ' . $error);
        }
        if ($status >= 400) {
            throw new RuntimeException('Page returned HTTP ' . $status);
        }

        return (string)$body;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function parseArticle(string $html, string $url, array $source): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $xp = new DOMXPath($dom);

        $title = $this->firstNonEmptyString([
            $this->metaProperty($xp, 'og:title'),
            $this->metaProperty($xp, 'twitter:title'),
            $this->nodeText($xp->query('//title')->item(0)),
            $this->nodeText($xp->query('//h1')->item(0)),
        ]);

        $author = $this->firstNonEmptyString([
            $this->metaName($xp, 'author'),
            $this->metaProperty($xp, 'article:author'),
            $this->metaProperty($xp, 'og:article:author'),
        ]);

        $postedAt = $this->firstNonEmptyString([
            $this->metaProperty($xp, 'article:published_time'),
            $this->metaName($xp, 'pubdate'),
            $this->metaName($xp, 'date'),
            $this->metaProperty($xp, 'og:pubdate'),
        ]);

        $body = $this->extractBodyText($xp);

        $media = [];
        $ogImage = $this->metaProperty($xp, 'og:image');
        if ($ogImage !== null && $ogImage !== '') {
            $media[] = ['url' => trim($ogImage), 'type' => 'image'];
        }

        $imgNodes = $xp->query('//article//img | //main//img | //img');
        $count = 0;
        foreach ($imgNodes as $img) {
            $src = trim((string)$img->getAttribute('src'));
            if ($src === '') {
                continue;
            }
            $media[] = ['url' => $src, 'type' => 'image'];
            $count++;
            if ($count >= 10) {
                break;
            }
        }

        return [
            'external_id' => null,
            'url' => $url,
            'title' => $title,
            'body' => $body,
            'author_name' => $author,
            'posted_at' => $postedAt,
            'media' => $media,
            'raw_payload' => [
                'html' => $html,
                'url' => $url,
                'source' => $source,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $parsed
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function normalizeArticle(array $parsed, array $source): array
    {
        $platform = strtolower(trim((string)($source['platform'] ?? 'news')));
        if ($platform === '') {
            $platform = 'news';
        }

        return [
            'external_id' => $this->nullableTrim($parsed['external_id'] ?? null),
            'source_platform' => $platform,
            'source_url' => trim((string)($parsed['url'] ?? $source['base_url'] ?? '')),
            'author_name' => $this->nullableTrim($parsed['author_name'] ?? null),
            'author_handle' => null,
            'title' => $this->nullableTrim($parsed['title'] ?? null),
            'body' => $this->nullableTrim($parsed['body'] ?? null),
            'media' => $this->normalizeMedia(is_array($parsed['media'] ?? null) ? $parsed['media'] : []),
            'posted_at' => $this->normalizeDateTime($this->nullableTrim($parsed['posted_at'] ?? null)),
            'raw_payload' => $parsed['raw_payload'] ?? $parsed,
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     * @param mixed $rawPayload
     * @return array<string, mixed>
     */
    public function saveNormalizedItem(array $normalized, int $sourceId, ?int $jobId, $rawPayload): array
    {
        $platform = strtolower(trim((string)($normalized['source_platform'] ?? 'news')));
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

    private function extractBodyText(DOMXPath $xp): ?string
    {
        $candidates = [
            '//article//p',
            '//main//p',
            '//p',
        ];

        foreach ($candidates as $query) {
            $nodes = $xp->query($query);
            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            $parts = [];
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if ($text !== '') {
                    $parts[] = preg_replace('/\s+/', ' ', $text);
                }
                if (count($parts) >= 40) {
                    break;
                }
            }

            if ($parts !== []) {
                return trim(implode("\n\n", $parts));
            }
        }

        return null;
    }

    private function metaProperty(DOMXPath $xp, string $property): ?string
    {
        $propertyEscaped = addslashes($property);
        $node = $xp->query("//meta[@property='{$propertyEscaped}']/@content")->item(0);
        return $this->nodeText($node);
    }

    private function metaName(DOMXPath $xp, string $name): ?string
    {
        $nameEscaped = addslashes($name);
        $node = $xp->query("//meta[@name='{$nameEscaped}']/@content")->item(0);
        return $this->nodeText($node);
    }

    private function nodeText($node): ?string
    {
        if ($node === null) {
            return null;
        }
        $text = trim((string)$node->nodeValue);
        return $text !== '' ? $text : null;
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
            $url = null;
            $type = 'image';

            if (is_string($item)) {
                $url = trim($item);
            } elseif (is_array($item)) {
                $url = $this->nullableTrim($item['url'] ?? null);
                $type = strtolower(trim((string)($item['type'] ?? 'image')));
            }

            if ($url === null || $url === '') {
                continue;
            }

            $key = $url . '|' . $type;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $normalized[] = ['url' => $url, 'type' => $type !== '' ? $type : 'image'];
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

    /**
     * @param array<int, mixed> $values
     */
    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            $text = $this->nullableTrim($value);
            if ($text !== null) {
                return $text;
            }
        }
        return null;
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
