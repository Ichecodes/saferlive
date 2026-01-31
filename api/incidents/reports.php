<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ─────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────

$BASE = dirname(__DIR__, 2);
require_once $BASE . '/config/database.php';


// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// ─────────────────────────────────────────────
// Request
// ─────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'error' => 'POST only'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    respond(['success' => false, 'error' => 'Invalid JSON payload'], 400);
}

// ─────────────────────────────────────────────
// Validate
// ─────────────────────────────────────────────

$title       = trim($data['title'] ?? '');
$type        = trim($data['type'] ?? '');
$description = trim($data['description'] ?? '');
$state       = trim($data['state'] ?? '');
$lga         = trim($data['lga'] ?? '');
$datetime    = trim($data['datetime'] ?? '');

if (!$title || !$type || !$description || !$state || !$lga || !$datetime) {
    respond(['success' => false, 'error' => 'Missing required fields'], 400);
}

$victims = (int)($data['victims'] ?? 0);
$injured = (int)($data['injured'] ?? 0);
$missing = (int)($data['missing'] ?? 0);
$dead    = (int)($data['dead'] ?? 0);
$location = trim($data['location'] ?? '');

// Fallback coordinates (Nigeria center)
$lat = isset($data['latitude']) ? (float)$data['latitude'] : 9.082;
$lng = isset($data['longitude']) ? (float)$data['longitude'] : 8.6753;

// ─────────────────────────────────────────────
// Insert
// ─────────────────────────────────────────────

try {
    $db = getDatabaseConnection();

    $stmt = $db->prepare("
        INSERT INTO incidents (
            title, type, status, description,
            state, lga, location,
            latitude, longitude,
            start_time,
            victims, injured, missing, casualties,
            created_at
        ) VALUES (
            :title, :type, 'pending', :description,
            :state, :lga, :location,
            :lat, :lng,
            :start_time,
            :victims, :injured, :missing, :dead,
            NOW()
        )
    ");

    $stmt->execute([
        ':title'       => $title,
        ':type'        => $type,
        ':description' => $description,
        ':state'       => $state,
        ':lga'         => $lga,
        ':location'    => $location ?: null,
        ':lat'         => $lat,
        ':lng'         => $lng,
        ':start_time'  => $datetime,
        ':victims'     => $victims,
        ':injured'     => $injured,
        ':missing'     => $missing,
        ':dead'        => $dead
    ]);

    respond([
        'success' => true,
        'incident_id' => (int)$db->lastInsertId()
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(['success' => false, 'error' => 'Unable to create incident'], 500);
}
