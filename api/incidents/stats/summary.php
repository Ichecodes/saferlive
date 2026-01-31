<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__, 3) . '/config/database.php';



try {
    $db = getDatabaseConnection();

    // Build filters (same as list endpoint)
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
        // Exclude pending incidents from public summary by default
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

    // Total incidents
    $stmt = $db->prepare("SELECT COUNT(*) FROM incidents WHERE {$whereClause}");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalIncidents = $stmt->fetchColumn();

    // Total LGAs affected
    $stmt = $db->prepare("SELECT COUNT(DISTINCT lga) FROM incidents WHERE {$whereClause} AND lga IS NOT NULL AND lga != ''");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalLGAs = $stmt->fetchColumn();

    // Total communities (using LGA as proxy, can be enhanced)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT CONCAT(state, '-', lga)) FROM incidents WHERE {$whereClause} AND state IS NOT NULL AND lga IS NOT NULL");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalCommunities = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'total_incidents' => intval($totalIncidents),
        'total_lgas' => intval($totalLGAs),
        'total_communities' => intval($totalCommunities)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

