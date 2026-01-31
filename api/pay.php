<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/../config/database.php';

// optional global error logger
if (file_exists(__DIR__ . '/../error_logger.php')) {
    require_once __DIR__ . '/../error_logger.php';
}

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    $db = getDatabaseConnection();

    if ($method !== 'GET') {
        respond(['success' => false, 'error' => 'Unsupported method'], 405);
    }

    $id = isset($_GET['job_id']) ? trim($_GET['job_id']) : null;
    if (!$id || !ctype_digit($id)) {
        respond(['success' => false, 'error' => 'Missing or invalid job_id'], 400);
    }

    $stmt = $db->prepare('SELECT id, full_name, phone, email, agent_type, job_type, state, lga, address, status, created_at, job_details, number_of_agents, number_of_days, inter_city, foreign_national FROM job_requests WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
    $stmt->execute();
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) respond(['success' => false, 'error' => 'Job not found'], 404);

    // Ensure proper types
    $agents = isset($job['number_of_agents']) ? (int)$job['number_of_agents'] : 1;
    $days = isset($job['number_of_days']) ? (int)$job['number_of_days'] : 1;
    $inter_city = !empty($job['inter_city']) ? true : false;
    $foreign_national = !empty($job['foreign_national']) ? true : false;

    // Read pricing
    $pricingFile = __DIR__ . '/../scripts/pricing.json';
    if (!file_exists($pricingFile)) respond(['success' => false, 'error' => 'Pricing config missing'], 500);
    $pricing = json_decode(file_get_contents($pricingFile), true);
    if (!is_array($pricing)) respond(['success' => false, 'error' => 'Invalid pricing config'], 500);

    $base = (int)($pricing['base_price'] ?? 50000);
    $ic = (int)($pricing['inter_city_fee'] ?? 30000);
    $fn = (int)($pricing['foreign_national_fee'] ?? 30000);

    $unit = $base + ($inter_city ? $ic : 0) + ($foreign_national ? $fn : 0);
    $total = $unit * max(1, $agents) * max(1, $days);

    $job['calculated_price'] = $total;
    $job['unit_price'] = $unit;
    $job['currency'] = $pricing['currency'] ?? 'NGN';

    respond(['success' => true, 'data' => $job], 200);

} catch (Throwable $e) {
    error_log('pay.php error: ' . $e->getMessage());
    if (function_exists('log_error_to_csv')) {
        try { log_error_to_csv(basename(__FILE__), $e->getMessage() . "\n" . $e->getTraceAsString()); } catch (Throwable $_) {}
    }
    respond(['success' => false, 'error' => 'Server error'], 500);
}

?>
