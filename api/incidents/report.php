<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config/database.php';

function jsonResponse($data){ echo json_encode($data); exit; }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'captcha') {
    // generate a simple math captcha and store answer in session
    $a = rand(2,9); $b = rand(2,9);
    $_SESSION['captcha_answer'] = $a + $b;
    jsonResponse(['question' => "$a + $b = ?"]);
}

// handle POST submission (JSON or form)
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
    $data = $_POST; // fallback
}

// basic validation
$required = ['title','type','description','state','lga','datetime','captcha'];
foreach ($required as $r) {
    if (empty($data[$r])) jsonResponse(['success'=>false,'error'=>"Missing field: $r"]);
}

// captcha check
if (empty($_SESSION['captcha_answer']) || intval($data['captcha']) !== intval($_SESSION['captcha_answer'])) {
    jsonResponse(['success'=>false,'error'=>'Invalid CAPTCHA']);
}

// require reporter identity: at least name or phone
if (empty($data['full_name']) && empty($data['phone'])) {
    jsonResponse(['success'=>false,'error'=>'Please provide full name or phone number']);
}

try {
    $db = getDatabaseConnection();

    // ensure reporters table exists
    $db->exec("CREATE TABLE IF NOT EXISTS reporters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255),
        nickname VARCHAR(255),
        phone VARCHAR(100),
        social_handle VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ensure base incidents table exists (use helper if available)
    if (function_exists('createIncidentsTable')) {
        createIncidentsTable();
    }

    // helper to check column existence
    $hasColumn = function($table, $col) use ($db){
        $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE :col");
        $stmt->execute([':col'=>$col]);
        return (bool)$stmt->fetch();
    };

    // add title and reporter_id columns if missing
    if (!$hasColumn('incidents','title')) {
        $db->exec("ALTER TABLE incidents ADD COLUMN title VARCHAR(255) NULL AFTER id");
    }
    if (!$hasColumn('incidents','reporter_id')) {
        $db->exec("ALTER TABLE incidents ADD COLUMN reporter_id INT NULL AFTER status");
    }

    // insert reporter
    $reporter_id = null;
    if (!empty($data['full_name']) || !empty($data['phone'])) {
        $stmt = $db->prepare("INSERT INTO reporters (full_name, phone, social_handle) VALUES (:name, :phone, :social)");
        $stmt->execute([
            ':name'=>substr($data['full_name'] ?? '',0,255),
            ':phone'=>substr($data['phone'] ?? '',0,100),
            ':social'=>substr($data['social_handle'] ?? '',0,255)
        ]);
        $reporter_id = $db->lastInsertId();
    }

    // prepare incident insert
    $sql = "INSERT INTO incidents
        (title, type, description, state, lga, latitude, longitude, start_time, victims, injured, casualties, missing, status, reporter_id)
        VALUES
        (:title, :type, :description, :state, :lga, :lat, :lon, :start_time, :victims, :injured, :casualties, :missing, :status, :reporter_id)
    ";

    $stmt = $db->prepare($sql);
    $dt = date('Y-m-d H:i:s', strtotime($data['datetime']));
    $stmt->execute([
        ':title'=>substr($data['title'],0,255),
        ':type'=>substr($data['type'],0,100),
        ':description'=>$data['description'],
        ':state'=>substr($data['state'],0,100),
        ':lga'=>substr($data['lga'],0,100),
        ':lat'=>!empty($data['latitude'])?floatval($data['latitude']):null,
        ':lon'=>!empty($data['longitude'])?floatval($data['longitude']):null,
        ':start_time'=>$dt,
        ':victims'=>intval($data['victims'] ?? 0),
        ':injured'=>intval($data['injured'] ?? 0),
        ':casualties'=>intval($data['dead'] ?? 0),
        ':missing'=>intval($data['missing'] ?? 0),
        ':status'=>'pending',
        ':reporter_id'=>$reporter_id
    ]);

    $incident_id = $db->lastInsertId();

    // clear captcha
    unset($_SESSION['captcha_answer']);

    $permalink = '/incident-detail.php?id=' . $incident_id;
    jsonResponse(['success'=>true,'incident_id'=>intval($incident_id),'status'=>'pending','permalink'=>$permalink]);

} catch (Exception $e) {
    error_log('Report submission error: ' . $e->getMessage());
    jsonResponse(['success'=>false,'error'=>'Server error']);
}

?>
