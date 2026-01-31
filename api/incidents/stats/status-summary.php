<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Only GET requests are accepted.'
    ]);
    exit;
}

require_once dirname(__DIR__, 3) . '/config/database.php';

try {
    $db = getDatabaseConnection();
    
    // Support filters
    $category = $_GET['category'] ?? '';
    $lga = $_GET['lga'] ?? '';
    $type = $_GET['type'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = ['status IS NOT NULL'];
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
    if ($statusFilter) {
        $where[] = "status = :status_filter";
        $params[':status_filter'] = $statusFilter;
    } else {
        // Exclude pending incidents from public stats by default
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

    $sql = "SELECT LOWER(status) as status, COUNT(*) as count
            FROM incidents
            WHERE {$whereClause}
            GROUP BY LOWER(status)";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize all statuses to 0
    $statusCounts = [
        'open' => 0,
        'closed' => 0,
        'pending' => 0
    ];
    
    // Populate counts from query results
    foreach ($results as $row) {
        $status = strtolower($row['status']);
        if (isset($statusCounts[$status])) {
            $statusCounts[$status] = intval($row['count']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $statusCounts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

