<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once dirname(__DIR__) . '/config/database.php';

function respond($data, int $status = 200) {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
  respond(['success' => false, 'error' => 'Unsupported method'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
  $data = $_POST;
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
  respond(['success' => false, 'error' => 'Missing incident id'], 400);
}

$fieldMap = [
  'title' => 'title',
  'type' => 'type',
  'description' => 'description',
  'state' => 'state',
  'lga' => 'lga',
  'location' => 'location',
  'latitude' => 'latitude',
  'longitude' => 'longitude',
  'datetime' => 'start_time',
  'start_time' => 'start_time',
  'end_time' => 'end_time',
  'victims' => 'victims',
  'injured' => 'injured',
  'dead' => 'casualties',
  'casualties' => 'casualties',
  'missing' => 'missing',
  'status' => 'status'
];

$updates = [];
$params = [];
foreach ($fieldMap as $inputKey => $col) {
  if (!array_key_exists($inputKey, $data)) continue;
  $val = $data[$inputKey];
  // normalize empty strings to null for optional fields
  if ($val === '') $val = null;
  // cast numeric fields
  if (in_array($col, ['victims','injured','casualties','missing'])) {
    $val = $val === null || $val === '' ? null : (int)$val;
  }
  $updates[$col] = $val;
}

if (count($updates) === 0) {
  respond(['success' => false, 'error' => 'No updatable fields provided'], 400);
}

try {
  $db = getDatabaseConnection();
  $setParts = [];
  foreach ($updates as $col => $val) {
    $ph = ':' . $col;
    $setParts[] = "`$col` = $ph";
    $params[$ph] = $val;
  }

  $sql = 'UPDATE incidents SET ' . implode(', ', $setParts) . ' WHERE id = :_id LIMIT 1';
  $stmt = $db->prepare($sql);
  foreach ($params as $ph => $val) {
    if ($val === null) {
      $stmt->bindValue($ph, null, PDO::PARAM_NULL);
    } elseif (is_int($val)) {
      $stmt->bindValue($ph, $val, PDO::PARAM_INT);
    } else {
      $stmt->bindValue($ph, (string)$val, PDO::PARAM_STR);
    }
  }
  $stmt->bindValue(':_id', $id, PDO::PARAM_INT);
  $stmt->execute();

  respond(['success' => true, 'updated_rows' => $stmt->rowCount()], 200);

} catch (Exception $e) {
  error_log('rprt update error: ' . $e->getMessage());
  respond(['success' => false, 'error' => 'Unable to update incident'], 500);
}

?>
