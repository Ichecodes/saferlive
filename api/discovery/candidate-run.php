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
function candidateRunRespond(array $data, int $status = 200): void
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

    $rawItemId = isset($payload['raw_item_id']) ? (int)$payload['raw_item_id'] : null;
    $limit = isset($payload['limit']) ? (int)$payload['limit'] : 100;
    $limit = max(1, min(1000, $limit));

    $db = getDatabaseConnection();
    $detector = new \Lib\Discovery\CandidateDetector($db);

    if ($rawItemId !== null && $rawItemId > 0) {
        $result = $detector->processItem($rawItemId);
        candidateRunRespond([
            'success' => true,
            'data' => [
                'mode' => 'single',
                'processed' => !empty($result['success']) ? 1 : 0,
                'flagged' => !empty($result['is_candidate']) ? 1 : 0,
                'skipped' => empty($result['success']) ? 1 : 0,
                'errors' => !empty($result['success']) ? [] : [($result['error'] ?? 'Failed to process item')],
                'item' => $result,
            ],
        ]);
    }

    $batch = $detector->processBatch($limit);
    candidateRunRespond([
        'success' => true,
        'data' => [
            'mode' => 'batch',
            'processed' => (int)($batch['processed'] ?? 0),
            'flagged' => (int)($batch['flagged'] ?? 0),
            'skipped' => (int)($batch['skipped'] ?? 0),
            'errors' => $batch['errors'] ?? [],
            'items' => $batch['items'] ?? [],
        ],
    ]);
} catch (Throwable $e) {
    error_log('candidate-run.php error: ' . $e->getMessage());
    candidateRunRespond(['success' => false, 'error' => 'Server error'], 500);
}
