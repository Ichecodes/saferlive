<?php
declare(strict_types=1);

/**
 * Data access layer for discovery_sources.
 */
namespace Lib\Discovery;

use DateTimeInterface;
use PDO;
use PDOException;
use RuntimeException;

class SourceRepository
{
    private PDO $db;

    /**
     * @param PDO|null $db Optional PDO connection. Falls back to project DB helper.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? self::resolveConnection();
    }

    /**
     * Return all active sources, optionally filtered by platform.
     * Ordered by trust_score (high -> low), then never-polled first, then oldest poll first.
     *
     * @param string|null $platform
     * @return array<int, array<string, mixed>>
     */
    public function getActiveSources(?string $platform = null): array
    {
        $sql = "
            SELECT *
            FROM discovery_sources
            WHERE is_active = 1
        ";
        $params = [];

        if ($platform !== null && trim($platform) !== '') {
            $sql .= " AND LOWER(platform) = LOWER(:platform)";
            $params[':platform'] = trim($platform);
        }

        $sql .= "
            ORDER BY
                COALESCE(trust_score, 0) DESC,
                CASE WHEN last_polled_at IS NULL THEN 0 ELSE 1 END ASC,
                last_polled_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Return active sources due for polling.
     *
     * Due means:
     * - never polled, OR
     * - minutes since last_polled_at >= poll_interval_minutes
     *
     * @param DateTimeInterface $now
     * @param string|null $platform
     * @return array<int, array<string, mixed>>
     */
    public function getDueSources(DateTimeInterface $now, ?string $platform = null): array
    {
        $sql = "
            SELECT *
            FROM discovery_sources
            WHERE
                is_active = 1
                AND (
                    last_polled_at IS NULL
                    OR TIMESTAMPDIFF(MINUTE, last_polled_at, :now) >= COALESCE(poll_interval_minutes, 0)
                )
        ";
        $params = [
            ':now' => $now->format('Y-m-d H:i:s'),
        ];

        if ($platform !== null && trim($platform) !== '') {
            $sql .= " AND LOWER(platform) = LOWER(:platform)";
            $params[':platform'] = trim($platform);
        }

        $sql .= "
            ORDER BY
                COALESCE(trust_score, 0) DESC,
                CASE WHEN last_polled_at IS NULL THEN 0 ELSE 1 END ASC,
                last_polled_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Update last_polled_at for a source.
     */
    public function touchLastPolled(int $sourceId, DateTimeInterface $time): bool
    {
        if ($sourceId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE discovery_sources SET last_polled_at = :time WHERE id = :id LIMIT 1"
        );

        $stmt->execute([
            ':time' => $time->format('Y-m-d H:i:s'),
            ':id' => $sourceId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Find one source by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM discovery_sources WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Resolve a PDO connection from the existing project helper.
     */
    private static function resolveConnection(): PDO
    {
        $rootConfig = dirname(__DIR__, 2) . '/config/database.php';
        if (is_file($rootConfig)) {
            require_once $rootConfig;
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
