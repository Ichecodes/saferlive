<?php
declare(strict_types=1);

/**
 * Data access layer for discovery_queries.
 */
namespace Lib\Discovery;

use DateTimeInterface;
use PDO;
use PDOException;
use RuntimeException;

class QueryRepository
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
     * Return active queries, optionally filtered by platform.
     * Ordered by priority (low -> high), then never-run first, then oldest run first.
     *
     * @param string|null $platform
     * @return array<int, array<string, mixed>>
     */
    public function getActiveQueries(?string $platform = null): array
    {
        $sql = "
            SELECT *
            FROM discovery_queries
            WHERE is_active = 1
        ";
        $params = [];

        if ($platform !== null && trim($platform) !== '') {
            $sql .= " AND LOWER(platform) = LOWER(:platform)";
            $params[':platform'] = trim($platform);
        }

        $sql .= "
            ORDER BY
                COALESCE(priority, 999999) ASC,
                CASE WHEN last_run_at IS NULL THEN 0 ELSE 1 END ASC,
                last_run_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Return active queries matching hotspot states and/or LGAs.
     *
     * If both states and LGAs are empty, return an empty list.
     * DISTINCT is used to avoid duplicates when both state and LGA conditions match.
     *
     * @param array<int, string> $states
     * @param array<int, string> $lgas
     * @param string|null $platform
     * @return array<int, array<string, mixed>>
     */
    public function getQueriesForHotspots(array $states = [], array $lgas = [], ?string $platform = null): array
    {
        $states = $this->normalizeFilterValues($states);
        $lgas = $this->normalizeFilterValues($lgas);

        if ($states === [] && $lgas === []) {
            return [];
        }

        $sql = "
            SELECT DISTINCT q.*
            FROM discovery_queries q
            WHERE q.is_active = 1
        ";

        $params = [];

        if ($platform !== null && trim($platform) !== '') {
            $sql .= " AND LOWER(q.platform) = LOWER(:platform)";
            $params[':platform'] = trim($platform);
        }

        $orClauses = [];
        if ($states !== []) {
            $statePlaceholders = [];
            foreach ($states as $idx => $state) {
                $ph = ':state_' . $idx;
                $statePlaceholders[] = $ph;
                $params[$ph] = $state;
            }
            $orClauses[] = 'LOWER(q.state) IN (' . implode(', ', $statePlaceholders) . ')';
        }

        if ($lgas !== []) {
            $lgaPlaceholders = [];
            foreach ($lgas as $idx => $lga) {
                $ph = ':lga_' . $idx;
                $lgaPlaceholders[] = $ph;
                $params[$ph] = $lga;
            }
            $orClauses[] = 'LOWER(q.lga) IN (' . implode(', ', $lgaPlaceholders) . ')';
        }

        $sql .= ' AND (' . implode(' OR ', $orClauses) . ')';
        $sql .= "
            ORDER BY
                COALESCE(q.priority, 999999) ASC,
                CASE WHEN q.last_run_at IS NULL THEN 0 ELSE 1 END ASC,
                q.last_run_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Update last_run_at for a query.
     */
    public function touchLastRun(int $queryId, DateTimeInterface $time): bool
    {
        if ($queryId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE discovery_queries SET last_run_at = :time WHERE id = :id LIMIT 1"
        );
        $stmt->execute([
            ':time' => $time->format('Y-m-d H:i:s'),
            ':id' => $queryId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Find one query by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM discovery_queries WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function normalizeFilterValues(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $value = strtolower(trim((string)$value));
            if ($value === '') {
                continue;
            }
            $out[$value] = $value;
        }

        return array_values($out);
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
