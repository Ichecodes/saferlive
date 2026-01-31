<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__, 3) . '/config/database.php';

try {
    $db = getDatabaseConnection();

    $category = $_GET['category'] ?? '';
    $lga = $_GET['lga'] ?? '';
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($category) {
        $where[] = "category = :category";
        $params[':category'] = $category;
    }
    if ($lga) {
        $where[] = "lga = :lga";
        $params[':lga'] = $lga;
    }
    if ($type) {
        $where[] = "type = :type";
        $params[':type'] = $type;
    }
    if ($status) {
        $where[] = "status = :status";
        $params[':status'] = $status;
    } else {
        // Exclude pending incidents from public victim counts by default
        $where[] = "status IN ('open','closed')";
    }
    if ($startDate) {
        $where[] = "start_time >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if ($endDate) {
        $where[] = "start_time <= :end_date";
        $params[':end_date'] = $endDate . ' 23:59:59';
    }
    if ($search) {
        $where[] = "(type LIKE :search OR state LIKE :search OR lga LIKE :search OR description LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $db->prepare("SELECT COALESCE(SUM(victims), 0) as tv FROM incidents WHERE {$whereClause}");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalVictims = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(casualties), 0) as tc FROM incidents WHERE {$whereClause}");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalCasualties = (int) $stmt->fetchColumn();

    // Injured: prefer explicit column if present, otherwise derive from victims - casualties
    $hasInjured = (bool) $db->query("SHOW COLUMNS FROM incidents LIKE 'injured'")->fetch();
    if ($hasInjured) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(injured), 0) as ti FROM incidents WHERE {$whereClause}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $totalInjured = (int) $stmt->fetchColumn();
    } else {
        $totalInjured = max(0, $totalVictims - $totalCasualties);
    }

    // Missing: use column if present else 0
    $hasMissing = (bool) $db->query("SHOW COLUMNS FROM incidents LIKE 'missing'")->fetch();
    if ($hasMissing) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(missing), 0) as tm FROM incidents WHERE {$whereClause}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $totalMissing = (int) $stmt->fetchColumn();
    } else {
        $totalMissing = 0;
    }

    echo json_encode([
        'success' => true,
        'total_victims' => intval($totalVictims),
        'total_casualties' => intval($totalCasualties),
        'total_injured' => intval($totalInjured),
        'total_missing' => intval($totalMissing)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

