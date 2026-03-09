<?php
declare(strict_types=1);

/**
 * Discovery module orchestration:
 * - opens a scheduler run
 * - selects due sources and relevant queries
 * - creates pending discovery_jobs
 * - updates run completion summary
 */
namespace Lib\Discovery;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class DiscoveryScheduler
{
    private PDO $db;
    private SourceRepository $sourceRepository;
    private QueryRepository $queryRepository;
    private CollectorRouter $collectorRouter;

    public function __construct(
        ?PDO $db = null,
        ?SourceRepository $sourceRepository = null,
        ?QueryRepository $queryRepository = null,
        ?CollectorRouter $collectorRouter = null
    ) {
        $this->db = $db ?? self::resolveConnection();
        $this->sourceRepository = $sourceRepository ?? new SourceRepository($this->db);
        $this->queryRepository = $queryRepository ?? new QueryRepository($this->db);
        $this->collectorRouter = $collectorRouter ?? new CollectorRouter();
    }

    /**
     * Create and return a new discovery run ID.
     */
    public function startRun(): int
    {
        return $this->startRunAt(new DateTimeImmutable('now'));
    }

    /**
     * Execute one scheduling cycle.
     *
     * @param array<int, string> $hotspotStates
     * @param array<int, string> $hotspotLgas
     * @return array<string, int|string>
     */
    public function schedule(array $hotspotStates = [], array $hotspotLgas = []): array
    {
        $now = new DateTimeImmutable('now');
        $runId = $this->startRunAt($now);
        $jobsCreated = 0;
        $errorMessages = [];
        $sourcesConsidered = 0;
        $queriesConsidered = 0;
        $finalStatus = 'success';

        try {
            $this->db->beginTransaction();

            $normalizedStates = $this->normalizeHotspots($hotspotStates);
            $normalizedLgas = $this->normalizeHotspots($hotspotLgas);

            $dueSources = $this->sourceRepository->getDueSources($now);
            $dueSources = $this->prioritizeSources($dueSources);
            $sourcesConsidered = count($dueSources);

            $hotspotQueries = $this->queryRepository->getQueriesForHotspots(
                $normalizedStates,
                $normalizedLgas
            );
            $generalQueries = $this->queryRepository->getActiveQueries();

            $queries = $this->mergeQueries($hotspotQueries, $generalQueries);
            $queriesConsidered = count($queries);

            foreach ($dueSources as $source) {
                try {
                    if ($this->insertSourceJob($runId, $source, $now)) {
                        $jobsCreated++;
                        $sourceId = isset($source['id']) ? (int)$source['id'] : 0;
                        if ($sourceId > 0) {
                            $this->sourceRepository->touchLastPolled($sourceId, $now);
                        }
                    }
                } catch (Throwable $e) {
                    $errorMessages[] = 'source_id=' . (string)($source['id'] ?? 'unknown') . ': ' . $e->getMessage();
                }
            }

            foreach ($queries as $query) {
                try {
                    if ($this->insertQueryJob($runId, $query, $now)) {
                        $jobsCreated++;
                        $queryId = isset($query['id']) ? (int)$query['id'] : 0;
                        if ($queryId > 0) {
                            $this->queryRepository->touchLastRun($queryId, $now);
                        }
                    }
                } catch (Throwable $e) {
                    $errorMessages[] = 'query_id=' . (string)($query['id'] ?? 'unknown') . ': ' . $e->getMessage();
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $finalStatus = 'partial';
            $errorMessages[] = 'fatal: ' . $e->getMessage();
        }

        if ($errorMessages !== [] && $finalStatus !== 'partial') {
            $finalStatus = 'partial';
        }

        $notes = $this->buildNotes($errorMessages);
        $this->completeRun($runId, $finalStatus, $jobsCreated, $notes);

        return [
            'run_id' => $runId,
            'sources_considered' => $sourcesConsidered,
            'queries_considered' => $queriesConsidered,
            'jobs_created' => $jobsCreated,
            'status' => $finalStatus,
        ];
    }

    /**
     * Mark a run complete with summary information.
     */
    public function completeRun(
        int $runId,
        string $status,
        int $jobsCreated = 0,
        ?string $notes = null
    ): bool {
        if ($runId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE discovery_runs
             SET completed_at = :completed_at,
                 status = :status,
                 jobs_created = :jobs_created,
                 notes = :notes
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([
            ':completed_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ':status' => $status,
            ':jobs_created' => max(0, $jobsCreated),
            ':notes' => $notes,
            ':id' => $runId,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function startRunAt(DateTimeInterface $time): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO discovery_runs (started_at, status, jobs_created)
             VALUES (:started_at, :status, :jobs_created)"
        );
        $stmt->execute([
            ':started_at' => $time->format('Y-m-d H:i:s'),
            ':status' => 'running',
            ':jobs_created' => 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     * @return array<int, array<string, mixed>>
     */
    private function prioritizeSources(array $sources): array
    {
        usort($sources, static function (array $a, array $b): int {
            $aTrust = (float)($a['trust_score'] ?? 0);
            $bTrust = (float)($b['trust_score'] ?? 0);
            if ($aTrust !== $bTrust) {
                return $aTrust > $bTrust ? -1 : 1;
            }

            $aLast = $a['last_polled_at'] ?? null;
            $bLast = $b['last_polled_at'] ?? null;

            if ($aLast === null && $bLast !== null) {
                return -1;
            }
            if ($aLast !== null && $bLast === null) {
                return 1;
            }
            if ($aLast === null && $bLast === null) {
                return 0;
            }

            $aTs = strtotime((string)$aLast) ?: 0;
            $bTs = strtotime((string)$bLast) ?: 0;

            return $aTs <=> $bTs;
        });

        return $sources;
    }

    /**
     * Merge hotspot-first queries with general queries, de-duplicated by:
     * - query ID
     * - platform + normalized query_text
     *
     * @param array<int, array<string, mixed>> $hotspotQueries
     * @param array<int, array<string, mixed>> $generalQueries
     * @return array<int, array<string, mixed>>
     */
    private function mergeQueries(array $hotspotQueries, array $generalQueries): array
    {
        $merged = [];
        $seenIds = [];
        $seenTextKeys = [];

        $push = static function (
            array $query,
            array &$dest,
            array &$ids,
            array &$textKeys
        ): void {
            $id = isset($query['id']) ? (int)$query['id'] : 0;
            $platform = strtolower(trim((string)($query['platform'] ?? '')));
            $queryText = strtolower(trim((string)($query['query_text'] ?? '')));
            $textKey = $platform . '|' . $queryText;

            if ($id > 0 && isset($ids[$id])) {
                return;
            }
            if ($queryText !== '' && isset($textKeys[$textKey])) {
                return;
            }

            if ($id > 0) {
                $ids[$id] = true;
            }
            if ($queryText !== '') {
                $textKeys[$textKey] = true;
            }

            $dest[] = $query;
        };

        foreach ($hotspotQueries as $query) {
            $push($query, $merged, $seenIds, $seenTextKeys);
        }
        foreach ($generalQueries as $query) {
            $push($query, $merged, $seenIds, $seenTextKeys);
        }

        return $merged;
    }

    /**
     * Insert poll_source job for a source.
     *
     * @param array<string, mixed> $source
     */
    private function insertSourceJob(int $runId, array $source, DateTimeInterface $now): bool
    {
        $sourceId = isset($source['id']) ? (int)$source['id'] : 0;
        if ($sourceId <= 0) {
            return false;
        }

        $platform = strtolower(trim((string)($source['platform'] ?? '')));
        $this->collectorRouter->resolveCollectorForSource($source);

        if ($this->pendingJobExistsForSource($sourceId, 'poll_source', $platform)) {
            return false;
        }

        $targetUrl = null;
        if (!empty($source['feed_url'])) {
            $targetUrl = trim((string)$source['feed_url']);
        } elseif (!empty($source['base_url'])) {
            $targetUrl = trim((string)$source['base_url']);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO discovery_jobs (
                run_id,
                source_id,
                platform,
                job_type,
                target_url,
                status,
                attempts,
                scheduled_at
            ) VALUES (
                :run_id,
                :source_id,
                :platform,
                :job_type,
                :target_url,
                :status,
                :attempts,
                :scheduled_at
            )"
        );

        $stmt->execute([
            ':run_id' => $runId,
            ':source_id' => $sourceId,
            ':platform' => $platform,
            ':job_type' => 'poll_source',
            ':target_url' => $targetUrl,
            ':status' => 'pending',
            ':attempts' => 0,
            ':scheduled_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Insert run_query job for a query.
     *
     * @param array<string, mixed> $query
     */
    private function insertQueryJob(int $runId, array $query, DateTimeInterface $now): bool
    {
        $queryId = isset($query['id']) ? (int)$query['id'] : 0;
        if ($queryId <= 0) {
            return false;
        }

        $platform = strtolower(trim((string)($query['platform'] ?? '')));
        $queryText = trim((string)($query['query_text'] ?? ''));
        if ($queryText === '') {
            return false;
        }

        $this->collectorRouter->resolveCollectorForJob($query);

        if ($this->pendingJobExistsForQuery($queryId, 'run_query', $platform, $queryText)) {
            return false;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO discovery_jobs (
                run_id,
                query_id,
                platform,
                job_type,
                query_text,
                status,
                attempts,
                scheduled_at
            ) VALUES (
                :run_id,
                :query_id,
                :platform,
                :job_type,
                :query_text,
                :status,
                :attempts,
                :scheduled_at
            )"
        );

        $stmt->execute([
            ':run_id' => $runId,
            ':query_id' => $queryId,
            ':platform' => $platform,
            ':job_type' => 'run_query',
            ':query_text' => $queryText,
            ':status' => 'pending',
            ':attempts' => 0,
            ':scheduled_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return $stmt->rowCount() > 0;
    }

    private function pendingJobExistsForSource(int $sourceId, string $jobType, string $platform): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM discovery_jobs
             WHERE source_id = :source_id
               AND job_type = :job_type
               AND LOWER(platform) = LOWER(:platform)
               AND status IN ('pending', 'processing')
             LIMIT 1"
        );
        $stmt->execute([
            ':source_id' => $sourceId,
            ':job_type' => $jobType,
            ':platform' => $platform,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    private function pendingJobExistsForQuery(
        int $queryId,
        string $jobType,
        string $platform,
        string $queryText
    ): bool {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM discovery_jobs
             WHERE query_id = :query_id
               AND job_type = :job_type
               AND LOWER(platform) = LOWER(:platform)
               AND LOWER(query_text) = LOWER(:query_text)
               AND status IN ('pending', 'processing')
             LIMIT 1"
        );
        $stmt->execute([
            ':query_id' => $queryId,
            ':job_type' => $jobType,
            ':platform' => $platform,
            ':query_text' => $queryText,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param array<int, string> $hotspots
     * @return array<int, string>
     */
    private function normalizeHotspots(array $hotspots): array
    {
        $out = [];
        foreach ($hotspots as $hotspot) {
            $value = strtolower(trim((string)$hotspot));
            if ($value === '') {
                continue;
            }
            $out[$value] = $value;
        }

        return array_values($out);
    }

    /**
     * @param array<int, string> $errors
     */
    private function buildNotes(array $errors): ?string
    {
        if ($errors === []) {
            return null;
        }

        $notes = implode(' | ', array_slice($errors, 0, 6));
        if (strlen($notes) > 1000) {
            $notes = substr($notes, 0, 997) . '...';
        }

        return $notes;
    }

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
