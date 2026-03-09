<?php
declare(strict_types=1);

/**
 * Collects and stores raw X-like items into raw_discovery_items.
 */
namespace Lib\Discovery\Collectors;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class XSearchCollector
{
    private PDO $db;
    private ?string $rawPayloadColumn = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? self::resolveConnection();
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int|string, mixed>|null $payload
     * @return array<string, mixed>
     */
    public function collect(array $source, ?array $payload = null, ?int $jobId = null): array
    {
        $sourceId = (int)($source['id'] ?? 0);
        $platform = strtolower(trim((string)($source['platform'] ?? 'x')));

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

        $items = $this->extractItemsFromPayload($payload);

        foreach ($items as $rawItem) {
            $summary['fetched']++;

            try {
                if (!is_array($rawItem)) {
                    throw new RuntimeException('Raw item is not a valid array payload.');
                }

                $normalized = $this->normalizeItem($rawItem, $source);
                $result = $this->saveNormalizedItem($normalized, $sourceId, $jobId, $rawItem);
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
                error_log('XSearchCollector item error: ' . $e->getMessage());
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function normalizeItem(array $item, array $source): array
    {
        $externalId = $this->firstNonEmptyString([
            $item['id'] ?? null,
            $item['id_str'] ?? null,
        ]);

        $authorNode = null;
        if (isset($item['author']) && is_array($item['author'])) {
            $authorNode = $item['author'];
        } elseif (isset($item['user']) && is_array($item['user'])) {
            $authorNode = $item['user'];
        }

        $authorName = is_array($authorNode)
            ? $this->firstNonEmptyString([$authorNode['name'] ?? null, $authorNode['display_name'] ?? null])
            : null;

        $authorHandle = is_array($authorNode)
            ? $this->firstNonEmptyString([
                $authorNode['username'] ?? null,
                $authorNode['screen_name'] ?? null,
                $authorNode['handle'] ?? null,
            ])
            : null;

        if ($authorHandle !== null) {
            $authorHandle = ltrim($authorHandle, '@');
        }

        $body = $this->firstNonEmptyString([
            $item['full_text'] ?? null,
            $item['text'] ?? null,
            $item['content'] ?? null,
            $item['body'] ?? null,
        ]);

        $sourceUrl = $this->firstNonEmptyString([
            $item['url'] ?? null,
            $item['link'] ?? null,
            $item['permalink'] ?? null,
        ]);

        if (($sourceUrl === null || $sourceUrl === '') && $externalId !== null && $authorHandle !== null) {
            $sourceUrl = 'https://x.com/' . rawurlencode($authorHandle) . '/status/' . rawurlencode($externalId);
        }

        if (($sourceUrl === null || $sourceUrl === '') && !empty($source['base_url'])) {
            $sourceUrl = trim((string)$source['base_url']);
        }

        $platform = strtolower(trim((string)($source['platform'] ?? 'x')));
        $postedAt = $this->normalizeDateTime($this->firstNonEmptyString([
            $item['created_at'] ?? null,
            $item['published_at'] ?? null,
            $item['timestamp'] ?? null,
        ]));

        $media = [];
        if (isset($item['entities']['media']) && is_array($item['entities']['media'])) {
            $media = array_merge($media, $item['entities']['media']);
        }
        if (isset($item['extended_entities']['media']) && is_array($item['extended_entities']['media'])) {
            $media = array_merge($media, $item['extended_entities']['media']);
        }
        if (isset($item['media']) && is_array($item['media'])) {
            $media = array_merge($media, $item['media']);
        }
        $media = $this->normalizeMedia($media);

        return [
            'external_id' => $externalId,
            'source_platform' => $platform !== '' ? $platform : 'x',
            'source_url' => $sourceUrl ?? '',
            'author_name' => $authorName,
            'author_handle' => $authorHandle,
            'title' => null,
            'body' => $body,
            'media' => $media,
            'posted_at' => $postedAt,
            'raw_payload' => $item,
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     * @param mixed $rawPayload
     * @return array<string, mixed>
     */
    public function saveNormalizedItem(array $normalized, int $sourceId, ?int $jobId, $rawPayload): array
    {
        $platform = strtolower(trim((string)($normalized['source_platform'] ?? 'x')));
        $externalId = trim((string)($normalized['external_id'] ?? ''));
        $externalId = $externalId !== '' ? $externalId : null;

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
            'source_id',
            'job_id',
            'external_id',
            'source_platform',
            'source_url',
            'author_name',
            'author_handle',
            'title',
            'body',
            'media_json',
            'posted_at',
            'fetched_at',
            'content_hash',
            'is_candidate',
            'created_at',
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
            $rawJson = json_encode($rawPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($rawJson === false) {
                $rawJson = null;
            }
            $columns[] = $rawColumn;
            $values[':raw_payload'] = $rawJson;
        } else {
            // TODO: Add a raw payload column (raw_payload/raw_json) on raw_discovery_items to preserve payload exactly.
        }

        $placeholders = [];
        foreach ($columns as $column) {
            if ($column === $rawColumn) {
                $placeholders[] = ':raw_payload';
                continue;
            }
            $placeholders[] = ':' . $column;
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
        $stmt->execute([
            ':platform' => $platform,
            ':external_id' => $externalId,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    public function existsByContentHash(string $contentHash): bool
    {
        $contentHash = trim($contentHash);
        if ($contentHash === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT id FROM raw_discovery_items WHERE content_hash = :content_hash LIMIT 1'
        );
        $stmt->execute([':content_hash' => $contentHash]);

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

    /**
     * @param array<int|string, mixed>|null $payload
     * @return array<int, mixed>
     */
    private function extractItemsFromPayload(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }

        if (isset($payload['items']) && is_array($payload['items'])) {
            return array_values($payload['items']);
        }
        if (isset($payload['data']) && is_array($payload['data'])) {
            return array_values($payload['data']);
        }

        $isList = array_keys($payload) === range(0, count($payload) - 1);
        if ($isList) {
            return array_values($payload);
        }

        return [$payload];
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
                $url = $this->firstNonEmptyString([
                    $item['url'] ?? null,
                    $item['media_url_https'] ?? null,
                    $item['media_url'] ?? null,
                    $item['src'] ?? null,
                ]);
                $candidateType = $this->firstNonEmptyString([
                    $item['type'] ?? null,
                    $item['media_type'] ?? null,
                ]);
                if ($candidateType !== null) {
                    $type = strtolower(trim($candidateType));
                }
            }

            if ($url === null || $url === '') {
                continue;
            }

            $key = $url . '|' . $type;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $normalized[] = [
                'url' => $url,
                'type' => $type !== '' ? $type : 'image',
            ];
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
            if ($ts === false) {
                return null;
            }
            return date('Y-m-d H:i:s', $ts);
        }
    }

    /**
     * @param array<int, mixed> $values
     */
    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            $text = trim((string)$value);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function nullableTrim($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string)$value);
        return $text !== '' ? $text : null;
    }

    private function detectRawPayloadColumn(): ?string
    {
        if ($this->rawPayloadColumn !== null) {
            return $this->rawPayloadColumn !== '' ? $this->rawPayloadColumn : null;
        }

        $candidates = ['raw_payload', 'raw_json'];
        foreach ($candidates as $column) {
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
