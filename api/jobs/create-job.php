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

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Only accept POST for creating a job
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') respond(['success' => false, 'error' => 'Unsupported method'], 405);

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) respond(['success' => false, 'error' => 'Invalid JSON body'], 400);

// Required fields per current schema + new optional fields
$full_name = trim($body['full_name'] ?? '');
$phone = trim($body['phone'] ?? '');
$email = trim($body['email'] ?? '');
$agent_type = trim($body['agent_type'] ?? '');
$job_type = trim($body['job_type'] ?? '');
$state = trim($body['state'] ?? '');
$lga = trim($body['lga'] ?? '');
$address = trim($body['address'] ?? '');

// New fields: job_details, number_of_agents, number_of_days, inter_city, foreign_national
$job_details = trim($body['job_details'] ?? '');
$number_of_agents = isset($body['number_of_agents']) ? (int)$body['number_of_agents'] : 1;
$number_of_days = isset($body['number_of_days']) ? (int)$body['number_of_days'] : 1;
$inter_city = isset($body['inter_city']) ? (bool)$body['inter_city'] : false;
$foreign_national = isset($body['foreign_national']) ? (bool)$body['foreign_national'] : false;

if ($full_name === '' || $phone === '' || $agent_type === '' || $job_type === '' || $state === '' || $lga === '' || $address === '') {
    respond(['success' => false, 'error' => 'Missing required fields'], 400);
}

// Basic phone validation: digits only, 7-15 digits
$digits = preg_replace('/\D+/', '', $phone);
if (strlen($digits) < 7 || strlen($digits) > 15) {
    respond(['success' => false, 'error' => 'Invalid phone number'], 400);
}

// Normalize phone for storage (digits-only)
$phone_digits = $digits;

// Optional email validation
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'error' => 'Invalid email address'], 400);
}

// Validate numeric fields
if ($number_of_agents < 1) respond(['success' => false, 'error' => 'number_of_agents must be >= 1'], 400);
if ($number_of_days < 1) respond(['success' => false, 'error' => 'number_of_days must be >= 1'], 400);

// Read pricing config from scripts/pricing.json (required per spec)
$pricingFile = __DIR__ . '/../../scripts/pricing.json';
if (!file_exists($pricingFile)) {
    error_log('Pricing file missing: ' . $pricingFile);
    respond(['success' => false, 'error' => 'Pricing configuration missing'], 500);
}
$pricing = json_decode(file_get_contents($pricingFile), true);
if (!is_array($pricing)) {
    error_log('Invalid pricing file: ' . $pricingFile);
    respond(['success' => false, 'error' => 'Invalid pricing configuration'], 500);
}

// Calculate price (do not store in DB)
function calculate_price(array $pricing, int $agents, int $days, bool $inter_city, bool $foreign_national): int {
    $base = isset($pricing['base_price']) ? (int)$pricing['base_price'] : 50000;
    $ic = isset($pricing['inter_city_fee']) ? (int)$pricing['inter_city_fee'] : 30000;
    $fn = isset($pricing['foreign_national_fee']) ? (int)$pricing['foreign_national_fee'] : 30000;

    $unit = $base + ($inter_city ? $ic : 0) + ($foreign_national ? $fn : 0);
    $total = $unit * max(1, $agents) * max(1, $days);
    return $total;
}

$calculated_price = calculate_price($pricing, $number_of_agents, $number_of_days, $inter_city, $foreign_national);

try {
    $db = getDatabaseConnection();

    // Insert row; new columns are optional so ALTER TABLE must be run beforehand. We only insert new columns if they exist.
    // Use a safe INSERT that lists all columns including new ones.
    // We'll detect whether the new columns exist and adjust the query accordingly to remain backward compatible.

    // Check for new columns existence
    $cols = ['full_name','phone','email','agent_type','job_type','state','lga','address','status','created_at'];
    $insertCols = ['full_name','phone','email','agent_type','job_type','state','lga','address','status','created_at'];
    $placeholders = [':full_name',':phone',':email',':agent_type',':job_type',':state',':lga',':address',':status','NOW()'];

    // Add optional columns if present in the table
    $tableInfo = $db->query("DESCRIBE job_requests")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('job_details', $tableInfo)) { $insertCols[]='job_details'; $placeholders[]=':job_details'; }
    if (in_array('number_of_agents', $tableInfo)) { $insertCols[]='number_of_agents'; $placeholders[]=':number_of_agents'; }
    if (in_array('number_of_days', $tableInfo)) { $insertCols[]='number_of_days'; $placeholders[]=':number_of_days'; }
    if (in_array('inter_city', $tableInfo)) { $insertCols[]='inter_city'; $placeholders[]=':inter_city'; }
    if (in_array('foreign_national', $tableInfo)) { $insertCols[]='foreign_national'; $placeholders[]=':foreign_national'; }

    $colList = implode(',', $insertCols);
    $phList = implode(',', $placeholders);

    $sql = "INSERT INTO job_requests ({$colList}) VALUES ({$phList})";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':full_name', $full_name, PDO::PARAM_STR);
    // Store normalized phone digits-only
    $stmt->bindValue(':phone', $phone_digits, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email !== '' ? $email : null, $email !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':agent_type', $agent_type, PDO::PARAM_STR);
    $stmt->bindValue(':job_type', $job_type, PDO::PARAM_STR);
    $stmt->bindValue(':state', $state, PDO::PARAM_STR);
    $stmt->bindValue(':lga', $lga, PDO::PARAM_STR);
    $stmt->bindValue(':address', $address, PDO::PARAM_STR);
    $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);

    // Bind optional values if the statement expects them
    if (strpos($sql, ':job_details') !== false) {
        $stmt->bindValue(':job_details', $job_details !== '' ? $job_details : null, $job_details !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    }
    if (strpos($sql, ':number_of_agents') !== false) {
        $stmt->bindValue(':number_of_agents', $number_of_agents, PDO::PARAM_INT);
    }
    if (strpos($sql, ':number_of_days') !== false) {
        $stmt->bindValue(':number_of_days', $number_of_days, PDO::PARAM_INT);
    }
    if (strpos($sql, ':inter_city') !== false) {
        $stmt->bindValue(':inter_city', $inter_city ? 1 : 0, PDO::PARAM_INT);
    }
    if (strpos($sql, ':foreign_national') !== false) {
        $stmt->bindValue(':foreign_national', $foreign_national ? 1 : 0, PDO::PARAM_INT);
    }
    $stmt->execute();

    $id = (int)$db->lastInsertId();
    // Return calculated price in response per requirements (do not store in DB)
    respond(['success' => true, 'request_id' => $id, 'calculated_price' => $calculated_price, 'currency' => ($pricing['currency'] ?? 'NGN')], 200);

} catch (Exception $e) {
    error_log('create-job (jobs) error: ' . $e->getMessage());
    if (function_exists('log_error_to_csv')) {
        try { log_error_to_csv(basename(__FILE__), $e->getMessage() . "\n" . $e->getTraceAsString()); } catch (Throwable $_) {}
    }
    respond(['success' => false, 'error' => 'Unable to save request'], 500);
}

?>
