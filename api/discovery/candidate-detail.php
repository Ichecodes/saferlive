<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/lib/Discovery/CandidateDetector.php';

/**
 * JSON response helper.
 */
function candidateDetailRespond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        candidateDetailRespond(['success' => false, 'error' => 'Invalid id'], 400);
    }

    $db = getDatabaseConnection();
    new \Lib\Discovery\CandidateDetector($db); // Ensures review table exists.

    $sql = "
        SELECT
            r.*,
            cr.candidate_score,
            cr.threshold,
            cr.matched_keywords_json,
            cr.matched_places_json,
            cr.signals_json,
            cr.reason_summary,
            cr.review_status,
            cr.checked_by,
            cr.checked_at,
            cr.note AS review_note
        FROM raw_discovery_items r
        LEFT JOIN discovery_candidate_reviews cr ON cr.raw_item_id = r.id
        WHERE r.id = :id
        LIMIT 1
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        candidateDetailRespond(['success' => false, 'error' => 'Candidate item not found'], 404);
    }

    $mediaJson = json_decode((string)($row['media_json'] ?? '[]'), true);
    $keywords = json_decode((string)($row['matched_keywords_json'] ?? '[]'), true);
    $places = json_decode((string)($row['matched_places_json'] ?? '[]'), true);
    $signals = json_decode((string)($row['signals_json'] ?? '{}'), true);

    // Raw payload support is optional depending on schema.
    $rawPayload = null;
    if (array_key_exists('raw_payload', $row)) {
        $decoded = json_decode((string)$row['raw_payload'], true);
        $rawPayload = $decoded !== null ? $decoded : $row['raw_payload'];
    } elseif (array_key_exists('raw_json', $row)) {
        $decoded = json_decode((string)$row['raw_json'], true);
        $rawPayload = $decoded !== null ? $decoded : $row['raw_json'];
    }

    candidateDetailRespond([
        'success' => true,
        'data' => [
            'raw_item_id' => (int)$row['id'],
            'source_id' => isset($row['source_id']) ? (int)$row['source_id'] : null,
            'job_id' => isset($row['job_id']) ? (int)$row['job_id'] : null,
            'external_id' => $row['external_id'] ?? null,
            'source_platform' => $row['source_platform'] ?? null,
            'source_url' => $row['source_url'] ?? null,
            'author_name' => $row['author_name'] ?? null,
            'author_handle' => $row['author_handle'] ?? null,
            'title' => $row['title'] ?? null,
            'body' => $row['body'] ?? null,
            'media' => is_array($mediaJson) ? $mediaJson : [],
            'posted_at' => $row['posted_at'] ?? null,
            'fetched_at' => $row['fetched_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'is_candidate' => (int)($row['is_candidate'] ?? 0),
            'candidate_score' => isset($row['candidate_score']) ? (float)$row['candidate_score'] : null,
            'threshold' => isset($row['threshold']) ? (float)$row['threshold'] : null,
            'matched_keywords' => is_array($keywords) ? $keywords : [],
            'matched_places' => is_array($places) ? $places : [],
            'signals' => is_array($signals) ? $signals : [],
            'reason_summary' => $row['reason_summary'] ?? '',
            'candidate_review_status' => $row['review_status'] ?? 'pending',
            'checked_by' => $row['checked_by'] ?? null,
            'checked_at' => $row['checked_at'] ?? null,
            'review_note' => $row['review_note'] ?? null,
            'raw_payload' => $rawPayload,
        ],
    ]);
} catch (Throwable $e) {
    error_log('candidate-detail.php error: ' . $e->getMessage());
    candidateDetailRespond(['success' => false, 'error' => 'Server error'], 500);
}
