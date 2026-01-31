<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once dirname(__DIR__) . '/config/database.php';

// optional global error logger
if (file_exists(dirname(__DIR__) . '/error_logger.php')) {
    require_once dirname(__DIR__ ) . '/error_logger.php';
}

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') respond(['success' => false, 'error' => 'Unsupported method'], 405);

$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if ($job_id <= 0) respond(['success' => false, 'error' => 'Missing job_id'], 400);

try {
    $db = getDatabaseConnection();
    $stmt = $db->prepare('SELECT * FROM job_requests WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $job_id, PDO::PARAM_INT);
    $stmt->execute();
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) respond(['success' => false, 'error' => 'Job not found'], 404);

    // Read pricing config (scripts/pricing.json expected)
    $pricingFile = __DIR__ . '/../scripts/pricing.json';
    if (!file_exists($pricingFile)) respond(['success' => false, 'error' => 'Pricing configuration missing'], 500);
    $pricing = json_decode(file_get_contents($pricingFile), true);
    if (!is_array($pricing)) respond(['success' => false, 'error' => 'Invalid pricing configuration'], 500);

    // calculate_price mirrors create-job.php logic
    $base = isset($pricing['base_price']) ? (int)$pricing['base_price'] : 50000;
    $ic = isset($pricing['inter_city_fee']) ? (int)$pricing['inter_city_fee'] : 30000;
    $fn = isset($pricing['foreign_national_fee']) ? (int)$pricing['foreign_national_fee'] : 30000;

    $agents = isset($job['number_of_agents']) ? (int)$job['number_of_agents'] : 1;
    $days = isset($job['number_of_days']) ? (int)$job['number_of_days'] : 1;
    $inter_city = isset($job['inter_city']) ? (bool)$job['inter_city'] : false;
    $foreign_national = isset($job['foreign_national']) ? (bool)$job['foreign_national'] : false;

    $unit = $base + ($inter_city ? $ic : 0) + ($foreign_national ? $fn : 0);
    $total_naira = $unit * max(1, $agents) * max(1, $days);

    // amount in kobo for Paystack
    $amount_kobo = (int)round($total_naira * 100);

    // load invoice config to get public key (safe to expose)
    $invoiceFile = __DIR__ . '/../scripts/invoice.json';
    $invoiceCfg = ['paystack_public_key' => ''];
    if (file_exists($invoiceFile)) {
        $inv = json_decode(file_get_contents($invoiceFile), true);
        if (is_array($inv)) $invoiceCfg = $inv;
    }

    respond(['success' => true, 'job_id' => $job_id, 'amount_kobo' => $amount_kobo, 'amount_naira' => $total_naira, 'currency' => ($pricing['currency'] ?? 'NGN'), 'paystack_public_key' => $invoiceCfg['paystack_public_key'] ?? '', 'customer' => ['email' => $job['email'] ?? '', 'name' => $job['full_name'] ?? '']]);

} catch (Exception $e) {
    error_log('pay-init error: ' . $e->getMessage());
    if (function_exists('log_error_to_csv')) {
        try { log_error_to_csv(basename(__FILE__), $e->getMessage() . "\n" . $e->getTraceAsString()); } catch (Throwable $_) {}
    }
    respond(['success' => false, 'error' => 'Server error'], 500);
}

?>
