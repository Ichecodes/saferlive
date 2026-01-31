<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__, 3) . '/config/database.php';


try {
    $db = getDatabaseConnection();

    // Filters
    $category = $_GET['category'] ?? '';
    $lga = $_GET['lga'] ?? '';
    $typeFilter = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = ['type IS NOT NULL AND type != ""'];
    $params = [];

    if ($category) {
        $where[] = "category = :category";
        $params[':category'] = $category;
    }
    if ($lga) {
        $where[] = "lga = :lga";
        $params[':lga'] = $lga;
    }
    if ($typeFilter) {
        $where[] = "type = :type";
        $params[':type'] = $typeFilter;
    }
    if ($status) {
        $where[] = "status = :status";
        $params[':status'] = $status;
    } else {
        // Exclude pending incidents from public type counts by default
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

    $sql = "SELECT type, COUNT(*) as count 
            FROM incidents 
            WHERE {$whereClause}
            GROUP BY type 
            ORDER BY count DESC 
            LIMIT 10";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'types' => $types
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

