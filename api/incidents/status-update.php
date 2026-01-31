<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__, 2) . '/config/database.php';

/* Handle CORS preflight */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* Enforce POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

/* Read JSON body */
$input = json_decode(file_get_contents('php://input'), true);

$id = $input['id'] ?? null;
$status = $input['status'] ?? null;

if (!$id || !$status) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: id and status'
    ]);
    exit;
}

$allowedStatuses = ['open', 'closed', 'pending'];
$status = strtolower($status);

if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid status. Must be: open, closed, or pending'
    ]);
    exit;
}

try {
    $db = getDatabaseConnection();

    $isClosed = ($status === 'closed') ? 1 : 0;
    $closedAt = ($status === 'closed') ? date('Y-m-d H:i:s') : null;

    $sql = "
        UPDATE incidents
        SET
            status = :status,
            closed_at = :closed_at,
            end_time = CASE
                WHEN :is_closed = 1 AND end_time IS NULL THEN NOW()
                ELSE end_time
            END
        WHERE id = :id
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':closed_at', $closedAt, PDO::PARAM_STR);
    $stmt->bindValue(':is_closed', $isClosed, PDO::PARAM_INT);
    $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);

    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Incident not found or status unchanged'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Incident status updated successfully',
        'data' => [
            'id' => (int)$id,
            'status' => $status,
            'closed_at' => $closedAt
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
    ]);
}
