<?php
declare(strict_types=1);

/**
 * Collects and stores manually supplied/curated items into raw_discovery_items.
 */
namespace Lib\Discovery\Collectors;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class ManualFeedCollector
{
    private PDO $db;
    private ?string $rawPayloadColumn = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? self::resolveConnection();
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function collect(array $source, array $items = [], ?int $jobId = null): array
    {
        $sourceId = (int)($source['id'] ?? 0);
        $platform = strtolower(trim((string)($source['platform'] ?? 'manual')));

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

        if ($items === [] && isset($source['items']) && is_array($source['items'])) {
            $items = $source['items'];
        }

        foreach ($items as $rawItem) {
            $summary['fetched']++;
            try {
                $normalized = $this->normalizeManualItem($rawItem, $source);
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
                error_log('ManualFeedCollector item error: ' . $e->getMessage());
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function normalizeManualItem(array $item, array $source): array
    {
        $platform = strtolower(trim((string)($source['platform'] ?? 'manual')));
        if ($platform === '') {
            $platform = 'manual';
        }

        $sourceUrl = $this->firstNonEmptyString([
            $item['source_url'] ?? null,
            $item['url'] ?? null,
            $item['link'] ?? null,
            $source['base_url'] ?? null,
        ]);

        $title = $this->nullableTrim($item['title'] ?? null);
        $body = $this->nullableTrim($item['body'] ?? $item['content'] ?? $item['summary'] ?? null);

        if ($sourceUrl === null) {
            // Minimal identifying fallback when manual item has no URL.
            if ($title === null && $body === null) {
                throw new RuntimeException('Manual item must include source_url or at least title/body.');
            }
            $sourceUrl = 'manual://source/' . (int)($source['id'] ?? 0) . '/' . sha1(($title ?? '') . '|' . ($body ?? ''));
        }

        $mediaRaw = $item['media'] ?? [];
        if (is_string($mediaRaw)) {
            $decoded = json_decode($mediaRaw, true);
            $mediaRaw = is_array($decoded) ? $decoded : [];
        }

        return [
            'external_id' => $this->firstNonEmptyString([$item['external_id'] ?? null, $item['id'] ?? null]),
            'source_platform' => $platform,
            'source_url' => $sourceUrl,
            'author_name' => $this->nullableTrim($item['author_name'] ?? $item['author'] ?? null),
            'author_handle' => $this->nullableTrim($item['author_handle'] ?? null),
            'title' => $title,
            'body' => $body,
            'media' => $this->normalizeMedia(is_array($mediaRaw) ? $mediaRaw : []),
            'posted_at' => $this->normalizeDateTime($this->nullableTrim($item['posted_at'] ?? $item['created_at'] ?? null)),
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
        $platform = strtolower(trim((string)($normalized['source_platform'] ?? 'manual')));
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
