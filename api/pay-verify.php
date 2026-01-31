<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once dirname(__DIR__) . '/config/database.php';
// optional global error logger
if (file_exists(dirname(__DIR__) . '/error_logger.php')) {
    require_once dirname(__DIR__) . '/error_logger.php';
}

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') respond(['success' => false, 'error' => 'Unsupported method'], 405);

$job_id = isset($_REQUEST['job_id']) ? (int)$_REQUEST['job_id'] : 0;
$reference = isset($_REQUEST['reference']) ? trim($_REQUEST['reference']) : '';
if ($job_id <= 0 || $reference === '') respond(['success' => false, 'error' => 'Missing parameters'], 400);

// Paystack secret must be set as env var PAYSTACK_SECRET_KEY
$secret = getenv('PAYSTACK_SECRET_KEY') ?: ($_SERVER['PAYSTACK_SECRET_KEY'] ?? '');
if (!$secret) respond(['success' => false, 'error' => 'Payment gateway not configured'], 500);

try {
    // Verify via Paystack API
    $ch = curl_init();
    $url = 'https://api.paystack.co/transaction/verify/' . urlencode($reference);
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secret,
            'Accept: application/json'
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        error_log('Paystack verify curl error: ' . $err);
        respond(['success' => false, 'error' => 'Gateway communication error'], 502);
    }
    curl_close($ch);

    $data = json_decode($resp, true);
    if (!is_array($data) || !isset($data['status'])) {
        respond(['success' => false, 'error' => 'Invalid gateway response'], 502);
    }
    if ($data['status'] !== true || !isset($data['data'])) {
        respond(['success' => false, 'error' => 'Payment not verified by gateway', 'gateway' => $data], 400);
    }

    $t = $data['data'];
    if (!isset($t['status']) || $t['status'] !== 'success') {
        // still record failed attempt
        $paid = false;
        $status = $t['status'] ?? 'failed';
    } else {
        $paid = true;
        $status = 'success';
    }

    $amount_kobo = isset($t['amount']) ? (int)$t['amount'] : 0;
    $currency = $t['currency'] ?? 'NGN';
    $amount_naira = $amount_kobo / 100.0;
    $paid_at = $t['paid_at'] ?? date('Y-m-d H:i:s');

    $db = getDatabaseConnection();
    $db->beginTransaction();

    // Insert payment record
    $sql = 'INSERT INTO payments (job_id, paystack_reference, amount, currency, status, paid_at, created_at) VALUES (:job_id, :ref, :amount, :currency, :status, :paid_at, NOW())';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':job_id', $job_id, PDO::PARAM_INT);
    $stmt->bindValue(':ref', $reference, PDO::PARAM_STR);
    $stmt->bindValue(':amount', number_format((float)$amount_naira, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':currency', $currency, PDO::PARAM_STR);
    $stmt->bindValue(':status', $paid ? 'success' : ($status ?: 'failed'), PDO::PARAM_STR);
    $stmt->bindValue(':paid_at', $paid_at, PDO::PARAM_STR);
    $stmt->execute();

    // If successful, mark job_requests.status = 'paid'
    if ($paid) {
        $u = $db->prepare('UPDATE job_requests SET status = :status WHERE id = :id');
        $u->bindValue(':status', 'paid', PDO::PARAM_STR);
        $u->bindValue(':id', $job_id, PDO::PARAM_INT);
        $u->execute();
    }

    $db->commit();

    respond(['success' => true, 'verified' => $paid, 'reference' => $reference, 'amount' => $amount_naira, 'currency' => $currency]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('pay-verify error: ' . $e->getMessage());
    if (function_exists('log_error_to_csv')) {
        try { log_error_to_csv(basename(__FILE__), $e->getMessage() . "\n" . $e->getTraceAsString()); } catch (Throwable $_) {}
    }
    respond(['success' => false, 'error' => 'Server error'], 500);
}

?>
