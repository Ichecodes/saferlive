<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once dirname(__DIR__, 2) . '/config/database.php';

// optional global error logger
if (file_exists(dirname(__DIR__, 2) . '/error_logger.php')) {
    require_once dirname(__DIR__, 2) . '/error_logger.php';
}

/**
 * Send JSON response and exit
 */
function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $db = getDatabaseConnection();

    /* =====================================================
       GET REQUESTS
       ===================================================== */
    if ($method === 'GET') {

        // GET /api/jobs/jobs.php?id={id}  -> single job
        // GET /api/jobs/jobs.php          -> all jobs
        $id = isset($_GET['id']) ? trim($_GET['id']) : null;

        if ($id !== null && $id !== '') {
            if (!ctype_digit($id)) {
                respond(['success' => false, 'error' => 'Invalid id'], 400);
            }

            $stmt = $db->prepare(
                'SELECT
                    id,
                    full_name,
                    phone,
                    email,
                    agent_type,
                    job_type,
                    state,
                    lga,
                    address,
                    status,
                    created_at
                 FROM job_requests
                 WHERE id = :id
                 LIMIT 1'
            );

            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();

            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                respond(['success' => false, 'error' => 'Not found'], 404);
            }

            respond(['success' => true, 'data' => $job], 200);
        }

        // Get all jobs
        $stmt = $db->query(
            'SELECT
                id,
                full_name,
                phone,
                email,
                agent_type,
                job_type,
                state,
                lga,
                address,
                status,
                created_at
             FROM job_requests
             ORDER BY created_at DESC'
        );

        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        respond(['success' => true, 'data' => $jobs], 200);
    }

    /* =====================================================
       POST REQUESTS
       ===================================================== */
    if ($method === 'POST') {

        $rawBody = file_get_contents('php://input');
        $body = json_decode($rawBody, true);

        if (!is_array($body)) {
            respond(['success' => false, 'error' => 'Invalid JSON body'], 400);
        }

        // Update job status
        if (isset($body['id'], $body['status'])) {

            $id = (string)$body['id'];
            $status = trim((string)$body['status']);

            $allowedStatuses = ['pending', 'reviewed', 'assigned', 'cancelled'];

            if (!ctype_digit($id) || (int)$id <= 0) {
                respond(['success' => false, 'error' => 'Invalid id'], 400);
            }

            if (!in_array($status, $allowedStatuses, true)) {
                respond(['success' => false, 'error' => 'Invalid status'], 400);
            }

            $stmt = $db->prepare(
                'UPDATE job_requests
                 SET status = :status
                 WHERE id = :id'
            );

            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();

            respond(['success' => true], 200);
        }

        respond(['success' => false, 'error' => 'Unsupported action'], 400);
    }

    respond(['success' => false, 'error' => 'Method not allowed'], 405);

} catch (Throwable $e) {
    error_log('jobs.php error: ' . $e->getMessage());
    if (function_exists('log_error_to_csv')) {
        try { log_error_to_csv(basename(__FILE__), $e->getMessage() . "\n" . $e->getTraceAsString()); } catch (Throwable $_) {}
    }
    respond(['success' => false, 'error' => 'Server error'], 500);
}
