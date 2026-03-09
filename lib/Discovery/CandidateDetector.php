<?php
declare(strict_types=1);

/**
 * Candidate detection for raw discovery items.
 *
 * This module scores raw items using:
 * - incident keyword signals
 * - Nigeria place mentions
 * - recency
 * - optional media presence
 */
namespace Lib\Discovery;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class CandidateDetector
{
    private const DEFAULT_THRESHOLD = 60.0;

    private PDO $db;
    private float $threshold;

    /**
     * @var array<string, float>
     */
    private array $keywordWeights = [
        'kidnap' => 10, 'kidnapped' => 10, 'abduct' => 9, 'abduction' => 9,
        'robbery' => 8, 'armed robbery' => 11, 'gunmen' => 10, 'shooting' => 10,
        'attack' => 8, 'attacked' => 8, 'killed' => 10, 'murdered' => 10,
        'cult clash' => 10, 'clash' => 7, 'violence' => 7, 'riot' => 8,
        'protest' => 5, 'stampede' => 10, 'missing person' => 9,
        'crash' => 8, 'collision' => 8, 'accident' => 7, 'overturned' => 7,
        'tanker fire' => 10, 'explosion' => 10, 'fire outbreak' => 8, 'inferno' => 9,
        'flood' => 8, 'collapsed building' => 10, 'pipeline explosion' => 10,
    ];

    /**
     * @var array<int, string>
     */
    private array $stateKeywords = [
        'abia','adamawa','akwa ibom','anambra','bauchi','bayelsa','benue','borno','cross river',
        'delta','ebonyi','edo','ekiti','enugu','gombe','imo','jigawa','kaduna','kano','katsina',
        'kebbi','kogi','kwara','lagos','nasarawa','niger','ogun','ondo','osun','oyo','plateau',
        'rivers','sokoto','taraba','yobe','zamfara','abuja','fct',
    ];

    /**
     * @var array<int, string>
     */
    private array $cityKeywords = [
        'port harcourt', 'ph', 'abuja', 'kano', 'kaduna', 'maiduguri', 'enugu',
        'aba', 'ibadan', 'warri', 'uyo', 'onitsha', 'lekki', 'ikeja', 'jos', 'ilorin',
        'benin', 'asaba', 'owerri', 'calabar', 'zaria', 'katsina',
    ];

    public function __construct(?PDO $db = null, ?float $threshold = null)
    {
        $this->db = $db ?? self::resolveConnection();
        $this->threshold = $threshold ?? self::DEFAULT_THRESHOLD;
        $this->ensureCandidateReviewTable();
    }

    /**
     * Evaluate one raw item row and return detailed scoring output.
     *
     * @param array<string, mixed> $rawItem
     * @return array<string, mixed>
     */
    public function evaluate(array $rawItem): array
    {
        $rawItemId = (int)($rawItem['id'] ?? 0);
        $title = (string)($rawItem['title'] ?? '');
        $body = (string)($rawItem['body'] ?? '');
        $fullText = $this->extractSearchableText($rawItem);

        $keyword = $this->scoreKeywordMatch($fullText, $title, $body);
        $place = $this->scoreNigeriaPlaceMatch($fullText);
        $recency = $this->scoreRecency($rawItem);
        $media = $this->scoreMediaPresence($rawItem);

        $candidateScore = $keyword['score'] + $place['score'] + $recency['score'] + $media['score'];
        $isCandidate = $candidateScore >= $this->threshold;

        $reasonParts = [];
        if ($keyword['score'] >= 15) {
            $reasonParts[] = 'strong incident keywords';
        } elseif ($keyword['score'] > 0) {
            $reasonParts[] = 'weak incident keywords';
        }
        if ($place['score'] >= 15) {
            $reasonParts[] = 'clear Nigeria location';
        } elseif ($place['score'] > 0) {
            $reasonParts[] = 'possible Nigeria location';
        }
        if ($recency['score'] >= 14) {
            $reasonParts[] = 'recent timestamp';
        } elseif ($recency['score'] > 0) {
            $reasonParts[] = 'older timestamp';
        }
        if ($media['score'] > 0) {
            $reasonParts[] = 'supporting media';
        }

        $reasonSummary = $reasonParts !== []
            ? ucfirst(implode(' + ', $reasonParts))
            : 'Low incident signal';

        return [
            'raw_item_id' => $rawItemId,
            'is_candidate' => $isCandidate,
            'candidate_score' => round($candidateScore, 2),
            'threshold' => $this->threshold,
            'signals' => [
                'keyword_score' => round($keyword['score'], 2),
                'place_score' => round($place['score'], 2),
                'recency_score' => round($recency['score'], 2),
                'media_score' => round($media['score'], 2),
            ],
            'matched_keywords' => $keyword['matched_keywords'],
            'matched_places' => $place['matched_places'],
            'reason_summary' => $reasonSummary,
            'review_status' => 'pending',
        ];
    }

    /**
     * Evaluate and persist one raw item.
     *
     * @return array<string, mixed>
     */
    public function processItem(int $rawItemId): array
    {
        $rawItem = $this->findRawItemById($rawItemId);
        if ($rawItem === null) {
            return [
                'success' => false,
                'raw_item_id' => $rawItemId,
                'error' => 'Raw item not found',
            ];
        }

        $result = $this->evaluate($rawItem);
        $saved = $this->updateDetectionResult($rawItemId, $result);
        $result['success'] = $saved;

        return $result;
    }

    /**
     * Process a batch of undecided raw items.
     *
     * @return array<string, mixed>
     */
    public function processBatch(int $limit = 100): array
    {
        $limit = max(1, min(1000, $limit));

        $sql = "
            SELECT r.*
            FROM raw_discovery_items r
            LEFT JOIN discovery_candidate_reviews cr ON cr.raw_item_id = r.id
            WHERE cr.raw_item_id IS NULL
            ORDER BY r.id DESC
            LIMIT :limit
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $summary = [
            'processed' => 0,
            'flagged' => 0,
            'skipped' => 0,
            'errors' => [],
            'items' => [],
        ];

        foreach ($rows as $row) {
            $rawId = (int)$row['id'];
            try {
                $result = $this->evaluate($row);
                $ok = $this->updateDetectionResult($rawId, $result);
                if ($ok) {
                    $summary['processed']++;
                    if (!empty($result['is_candidate'])) {
                        $summary['flagged']++;
                    }
                } else {
                    $summary['skipped']++;
                }
                $summary['items'][] = $result;
            } catch (Throwable $e) {
                $summary['errors'][] = 'raw_item_id=' . $rawId . ': ' . $e->getMessage();
                error_log('CandidateDetector batch item error: ' . $e->getMessage());
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $rawItem
     */
    public function extractSearchableText(array $rawItem): string
    {
        $title = trim((string)($rawItem['title'] ?? ''));
        $body = trim((string)($rawItem['body'] ?? ''));

        // Title receives stronger influence by repeating it.
        return trim($title . ' ' . $title . ' ' . $body);
    }

    /**
     * @return array{score: float, matched_keywords: array<int, string>}
     */
    private function scoreKeywordMatch(string $text, string $title = '', string $body = ''): array
    {
        $normText = $this->normalizeText($text);
        $normTitle = $this->normalizeText($title);
        $normBody = $this->normalizeText($body);

        $score = 0.0;
        $matched = [];

        foreach ($this->keywordWeights as $keyword => $weight) {
            $needle = $this->normalizeText($keyword);
            $countBody = $this->countPhraseOccurrences($normBody, $needle);
            $countTitle = $this->countPhraseOccurrences($normTitle, $needle);
            $countAny = $this->countPhraseOccurrences($normText, $needle);

            if ($countAny <= 0) {
                continue;
            }

            $matched[] = $keyword;
            $score += min(1.0, $countBody) * $weight;
            $score += min(1.0, $countTitle) * ($weight * 1.6);

            // Extra boost for repeated occurrences.
            if ($countAny > 1) {
                $score += min(2.5, ($countAny - 1) * 1.0);
            }
        }

        if (count($matched) >= 3) {
            $score += 4.0;
        } elseif (count($matched) === 2) {
            $score += 2.0;
        }

        return [
            'score' => min(50.0, $score),
            'matched_keywords' => array_values(array_unique($matched)),
        ];
    }

    /**
     * @return array{score: float, matched_places: array<int, string>}
     */
    private function scoreNigeriaPlaceMatch(string $text): array
    {
        $norm = $this->normalizeText($text);
        $matchedStates = [];
        $matchedCities = [];
        $matchedNigeria = false;

        if ($this->containsPhrase($norm, 'nigeria') || $this->containsPhrase($norm, 'nigerian')) {
            $matchedNigeria = true;
        }

        foreach ($this->stateKeywords as $state) {
            if ($this->containsPhrase($norm, $state)) {
                $matchedStates[] = $state;
            }
        }

        foreach ($this->cityKeywords as $city) {
            if ($this->containsPhrase($norm, $city)) {
                $matchedCities[] = $city;
            }
        }

        $score = 0.0;
        if ($matchedNigeria) {
            $score += 8.0;
        }
        if ($matchedStates !== []) {
            $score += 12.0;
        }
        if ($matchedCities !== []) {
            $score += 10.0;
        }
        if ($matchedStates !== [] && $matchedCities !== []) {
            $score += 3.0;
        }

        $matchedPlaces = array_values(array_unique(array_merge(
            $matchedStates,
            $matchedCities,
            $matchedNigeria ? ['nigeria'] : []
        )));

        return [
            'score' => min(25.0, $score),
            'matched_places' => $matchedPlaces,
        ];
    }

    /**
     * @param array<string, mixed> $rawItem
     * @return array{score: float, reference_time: string|null}
     */
    private function scoreRecency(array $rawItem): array
    {
        $timeValue = $rawItem['posted_at'] ?? $rawItem['fetched_at'] ?? $rawItem['created_at'] ?? null;
        if ($timeValue === null || trim((string)$timeValue) === '') {
            return ['score' => 0.0, 'reference_time' => null];
        }

        try {
            $then = new DateTimeImmutable((string)$timeValue);
            $now = new DateTimeImmutable('now');
            $diffHours = max(0.0, ($now->getTimestamp() - $then->getTimestamp()) / 3600.0);

            if ($diffHours <= 6) {
                $score = 20.0;
            } elseif ($diffHours <= 24) {
                $score = 18.0;
            } elseif ($diffHours <= 72) {
                $score = 14.0;
            } elseif ($diffHours <= 168) {
                $score = 10.0;
            } elseif ($diffHours <= 720) {
                $score = 5.0;
            } else {
                $score = 2.0;
            }

            return [
                'score' => $score,
                'reference_time' => $then->format('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            return ['score' => 0.0, 'reference_time' => null];
        }
    }

    /**
     * @param array<string, mixed> $rawItem
     * @return array{score: float}
     */
    private function scoreMediaPresence(array $rawItem): array
    {
        $mediaRaw = $rawItem['media_json'] ?? null;
        if ($mediaRaw === null || trim((string)$mediaRaw) === '') {
            return ['score' => 0.0];
        }

        $decoded = json_decode((string)$mediaRaw, true);
        if (!is_array($decoded) || $decoded === []) {
            return ['score' => 0.0];
        }

        $count = count($decoded);
        $score = 2.0 + min(3.0, ($count * 0.75));

        return ['score' => min(5.0, $score)];
    }

    public function normalizeText(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\\s\\-]/', ' ', $text) ?? $text;
        $text = preg_replace('/\\s+/', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function updateDetectionResult(int $rawItemId, array $result): bool
    {
        $this->db->beginTransaction();

        try {
            $stmtRaw = $this->db->prepare(
                "UPDATE raw_discovery_items
                 SET is_candidate = :is_candidate
                 WHERE id = :id
                 LIMIT 1"
            );
            $stmtRaw->execute([
                ':is_candidate' => !empty($result['is_candidate']) ? 1 : 0,
                ':id' => $rawItemId,
            ]);

            $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $signalsJson = json_encode($result['signals'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $keywordsJson = json_encode($result['matched_keywords'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $placesJson = json_encode($result['matched_places'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $sql = "
                INSERT INTO discovery_candidate_reviews (
                    raw_item_id,
                    candidate_score,
                    threshold,
                    matched_keywords_json,
                    matched_places_json,
                    signals_json,
                    reason_summary,
                    review_status,
                    checked_by,
                    checked_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :raw_item_id,
                    :candidate_score,
                    :threshold,
                    :matched_keywords_json,
                    :matched_places_json,
                    :signals_json,
                    :reason_summary,
                    :review_status,
                    :checked_by,
                    :checked_at,
                    :created_at,
                    :updated_at
                )
                ON DUPLICATE KEY UPDATE
                    candidate_score = VALUES(candidate_score),
                    threshold = VALUES(threshold),
                    matched_keywords_json = VALUES(matched_keywords_json),
                    matched_places_json = VALUES(matched_places_json),
                    signals_json = VALUES(signals_json),
                    reason_summary = VALUES(reason_summary),
                    updated_at = VALUES(updated_at)
            ";
            $stmtReview = $this->db->prepare($sql);
            $stmtReview->execute([
                ':raw_item_id' => $rawItemId,
                ':candidate_score' => (float)($result['candidate_score'] ?? 0),
                ':threshold' => (float)($result['threshold'] ?? $this->threshold),
                ':matched_keywords_json' => $keywordsJson ?: '[]',
                ':matched_places_json' => $placesJson ?: '[]',
                ':signals_json' => $signalsJson ?: '{}',
                ':reason_summary' => (string)($result['reason_summary'] ?? ''),
                ':review_status' => 'pending',
                ':checked_by' => null,
                ':checked_at' => null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('CandidateDetector update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRawItemById(int $rawItemId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM raw_discovery_items WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $rawItemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function ensureCandidateReviewTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS discovery_candidate_reviews (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                raw_item_id BIGINT UNSIGNED NOT NULL,
                candidate_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                threshold DECIMAL(5,2) NOT NULL DEFAULT 60.00,
                matched_keywords_json LONGTEXT NULL,
                matched_places_json LONGTEXT NULL,
                signals_json LONGTEXT NULL,
                reason_summary TEXT NULL,
                review_status ENUM('pending','checked','approved','rejected') NOT NULL DEFAULT 'pending',
                checked_by VARCHAR(120) NULL,
                checked_at DATETIME NULL,
                note TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_raw_item_id (raw_item_id),
                INDEX idx_review_status (review_status),
                INDEX idx_candidate_score (candidate_score)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $this->db->exec($sql);
    }

    private function containsPhrase(string $haystack, string $needle): bool
    {
        $needle = $this->normalizeText($needle);
        if ($needle === '') {
            return false;
        }
        return $this->countPhraseOccurrences($haystack, $needle) > 0;
    }

    private function countPhraseOccurrences(string $haystack, string $needle): int
    {
        if ($needle === '') {
            return 0;
        }
        $pattern = '/\\b' . preg_quote($needle, '/') . '\\b/u';
        preg_match_all($pattern, $haystack, $matches);
        return count($matches[0] ?? []);
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
