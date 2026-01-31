<?php
declare(strict_types=1);



// code strts here




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

/* =====================================================
   METHOD CHECK
   ===================================================== */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    respond(['success' => false, 'error' => 'Unsupported method'], 405);
}

/* =====================================================
   READ & PARSE BODY
   ===================================================== */
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    respond(['success' => false, 'error' => 'Invalid JSON body'], 400);
}

/* =====================================================
   INPUTS (CURRENT DB STRUCTURE)
   ===================================================== */
$full_name  = trim($body['full_name'] ?? '');
$phone      = trim($body['phone'] ?? '');
$email      = trim($body['email'] ?? '');
$agent_type = trim($body['agent_type'] ?? '');
$job_type   = trim($body['job_type'] ?? '');
$state      = trim($body['state'] ?? '');
$lga        = trim($body['lga'] ?? '');
$address    = trim($body['address'] ?? '');

/* =====================================================
   VALIDATION
   ===================================================== */
if (
    $full_name === '' ||
    $phone === '' ||
    $agent_type === '' ||
    $job_type === '' ||
    $state === '' ||
    $lga === '' ||
    $address === ''
) {
    respond(['success' => false, 'error' => 'Missing required fields'], 400);
}

$allowedAgentTypes = ['bouncers', 'security', 'police', 'nscdc'];
$allowedJobTypes   = ['event', 'trip'];

if (!in_array($agent_type, $allowedAgentTypes, true)) {
    respond(['success' => false, 'error' => 'Invalid agent type'], 400);
}

if (!in_array($job_type, $allowedJobTypes, true)) {
    respond(['success' => false, 'error' => 'Invalid job type'], 400);
}

/* =====================================================
   INSERT INTO DATABASE
   ===================================================== */
try {
    $db = getDatabaseConnection();

    $stmt = $db->prepare(
        'INSERT INTO job_requests (
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
        ) VALUES (
            :full_name,
            :phone,
            :email,
            :agent_type,
            :job_type,
            :state,
            :lga,
            :address,
            :status,
            NOW()
        )'
    );

    $stmt->bindValue(':full_name', $full_name, PDO::PARAM_STR);
    $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
    $stmt->bindValue(
        ':email',
        $email !== '' ? $email : null,
        $email !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
    );
    $stmt->bindValue(':agent_type', $agent_type, PDO::PARAM_STR);
    $stmt->bindValue(':job_type', $job_type, PDO::PARAM_STR);
    $stmt->bindValue(':state', $state, PDO::PARAM_STR);
    $stmt->bindValue(':lga', $lga, PDO::PARAM_STR);
    $stmt->bindValue(':address', $address, PDO::PARAM_STR);
    $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);

    $stmt->execute();

    $id = (int)$db->lastInsertId();

    respond([
        'success' => true,
        'request_id' => $id
    ], 200);

} catch (Throwable $e) {
    error_log('agent-req.php error: ' . $e->getMessage());
    if (function_exists('log_error_to_csv')) {
        try { log_error_to_csv(basename(__FILE__), $e->getMessage() . "\n" . $e->getTraceAsString()); } catch (Throwable $_) {}
    }
    respond(['success' => false, 'error' => 'Unable to save request'], 500);
}
