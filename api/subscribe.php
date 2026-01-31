<?php
header('Content-Type: application/json; charset=utf-8');
// Allow simple CORS for local testing if needed
if (isset($_SERVER['HTTP_ORIGIN'])) {
  header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
  header('Access-Control-Allow-Credentials: true');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if ($name === '' || $email === '') {
  http_response_code(422);
  echo json_encode(['error' => 'Name and email are required']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(['error' => 'Invalid email address']);
  exit;
}

// Attempt to save to database using project's DB helper. If it fails, fallback to CSV.
$saved = false;
// Load DB helper if available
$dbFile = __DIR__ . '/../config/database.php';
if (file_exists($dbFile)) {
  require_once $dbFile;
  try {
    $db = getDatabaseConnection();

    // Ensure table exists (simple create if missing)
    $createSql = "CREATE TABLE IF NOT EXISTS comm_sub (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(150) NOT NULL,
      whatsapp_number VARCHAR(20) NOT NULL,
      email VARCHAR(255) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_whatsapp (whatsapp_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($createSql);

    $stmt = $db->prepare('INSERT INTO comm_sub (name, whatsapp_number, email) VALUES (:name, :phone, :email)');
    $stmt->execute([':name' => $name, ':phone' => $phone, ':email' => $email]);
    $saved = true;
  } catch (PDOException $e) {
    // handle duplicate key (unique whatsapp_number)
    if ($e->getCode() == '23000') {
      http_response_code(409);
      echo json_encode(['error' => 'Phone number already subscribed']);
      exit;
    }
    // Log and continue to CSV fallback
    error_log('DB save failed: ' . $e->getMessage());
    $saved = false;
  } catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage());
    $saved = false;
  }
}

if (!$saved) {
  // Save subscriber to a CSV file inside the api folder for simplicity.
  $file = __DIR__ . '/subscribers.csv';
  $now = date('c');
  $row = [$now, $name, $email, $phone];

  // Attempt to write - create file if necessary
  try {
    $fp = fopen($file, 'a');
    if (!$fp) throw new Exception('Unable to open file');
    fputcsv($fp, $row);
    fclose($fp);
    $saved = true;
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save subscription']);
    exit;
  }
}

echo json_encode(['success' => true, 'message' => 'Subscribed']);
