<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__, 2) . '/config/database.php';

/**
 * Emit a JSON response and stop execution.
 */
function respondNeighbor(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
if ($id === '' || !ctype_digit($id) || (int) $id <= 0) {
    respondNeighbor(['success' => false, 'error' => 'Invalid incident ID'], 400);
}

$incidentId = (int) $id;

try {
    $db = getDatabaseConnection();

    // Pick a stable time column for ordering (same "newest first" intent as list.php).
    $colsStmt = $db->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'incidents'"
    );
    $colsStmt->execute();
    $existingCols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
    $existingMap = array_flip($existingCols ?: []);

    $orderExpr = 'id'; // fallback if no timestamp columns are available
    if (isset($existingMap['start_time']) && isset($existingMap['created_at'])) {
        $orderExpr = 'COALESCE(start_time, created_at)';
    } elseif (isset($existingMap['start_time'])) {
        $orderExpr = 'start_time';
    } elseif (isset($existingMap['created_at'])) {
        $orderExpr = 'created_at';
    }

    $currentStmt = $db->prepare("SELECT id, {$orderExpr} AS sort_key FROM incidents WHERE id = :id LIMIT 1");
    $currentStmt->bindValue(':id', $incidentId, PDO::PARAM_INT);
    $currentStmt->execute();
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        respondNeighbor(['success' => false, 'error' => 'Incident not found'], 404);
    }

    $sortKey = $current['sort_key'];

    // "Prev" = newer item in descending order (closest row above current).
    $prevSql = "
        SELECT id
        FROM incidents
        WHERE ({$orderExpr} > :sort_key_gt)
           OR ({$orderExpr} = :sort_key_eq AND id > :id_gt)
        ORDER BY {$orderExpr} ASC, id ASC
        LIMIT 1
    ";
    $prevStmt = $db->prepare($prevSql);
    $prevStmt->bindValue(':sort_key_gt', $sortKey);
    $prevStmt->bindValue(':sort_key_eq', $sortKey);
    $prevStmt->bindValue(':id_gt', $incidentId, PDO::PARAM_INT);
    $prevStmt->execute();
    $prevId = $prevStmt->fetchColumn();

    // "Next" = older item in descending order (closest row below current).
    $nextSql = "
        SELECT id
        FROM incidents
        WHERE ({$orderExpr} < :sort_key_lt)
           OR ({$orderExpr} = :sort_key_eq AND id < :id_lt)
        ORDER BY {$orderExpr} DESC, id DESC
        LIMIT 1
    ";
    $nextStmt = $db->prepare($nextSql);
    $nextStmt->bindValue(':sort_key_lt', $sortKey);
    $nextStmt->bindValue(':sort_key_eq', $sortKey);
    $nextStmt->bindValue(':id_lt', $incidentId, PDO::PARAM_INT);
    $nextStmt->execute();
    $nextId = $nextStmt->fetchColumn();

    respondNeighbor([
        'success' => true,
        'data' => [
            'current_id' => $incidentId,
            'prev_id' => $prevId !== false ? (int) $prevId : null,
            'next_id' => $nextId !== false ? (int) $nextId : null,
        ],
    ]);
} catch (Throwable $e) {
    error_log('neighbor error: ' . $e->getMessage());
    respondNeighbor(['success' => false, 'error' => 'Unable to load incident navigation'], 500);
}
