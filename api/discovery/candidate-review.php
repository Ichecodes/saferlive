<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/lib/Discovery/CandidateDetector.php';

/**
 * JSON response helper.
 */
function candidateReviewRespond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

try {
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $rawItemId = (int)($payload['raw_item_id'] ?? 0);
    $reviewStatus = trim((string)($payload['review_status'] ?? ''));
    $note = trim((string)($payload['note'] ?? ''));
    $checkedBy = trim((string)($payload['checked_by'] ?? 'admin'));

    if ($rawItemId <= 0) {
        candidateReviewRespond(['success' => false, 'error' => 'Invalid raw_item_id'], 400);
    }

    $allowed = ['pending', 'checked', 'approved', 'rejected'];
    if (!in_array($reviewStatus, $allowed, true)) {
        candidateReviewRespond(['success' => false, 'error' => 'Invalid review_status'], 400);
    }

    $db = getDatabaseConnection();
    $detector = new \Lib\Discovery\CandidateDetector($db); // Ensures table exists.

    // Ensure baseline detection record exists before review update.
    $existsStmt = $db->prepare(
        'SELECT id FROM discovery_candidate_reviews WHERE raw_item_id = :raw_item_id LIMIT 1'
    );
    $existsStmt->execute([':raw_item_id' => $rawItemId]);
    if (!$existsStmt->fetchColumn()) {
        $detector->processItem($rawItemId);
    }

    $checkedAt = null;
    if ($reviewStatus !== 'pending') {
        $checkedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    $sql = "
        UPDATE discovery_candidate_reviews
        SET
            review_status = :review_status,
            note = :note,
            checked_by = :checked_by,
            checked_at = :checked_at,
            updated_at = :updated_at
        WHERE raw_item_id = :raw_item_id
        LIMIT 1
    ";
    $stmt = $db->prepare($sql);
    $ok = $stmt->execute([
        ':review_status' => $reviewStatus,
        ':note' => ($note !== '' ? $note : null),
        ':checked_by' => ($checkedBy !== '' ? $checkedBy : null),
        ':checked_at' => $checkedAt,
        ':updated_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ':raw_item_id' => $rawItemId,
    ]);

    if (!$ok) {
        candidateReviewRespond(['success' => false, 'error' => 'Unable to update review status'], 500);
    }

    candidateReviewRespond([
        'success' => true,
        'data' => [
            'raw_item_id' => $rawItemId,
            'review_status' => $reviewStatus,
            'note' => ($note !== '' ? $note : null),
            'checked_by' => ($checkedBy !== '' ? $checkedBy : null),
            'checked_at' => $checkedAt,
        ],
    ]);
} catch (Throwable $e) {
    error_log('candidate-review.php error: ' . $e->getMessage());
    candidateReviewRespond(['success' => false, 'error' => 'Server error'], 500);
}
