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
        // Exclude pending incidents from public timeline by default
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

    // 1. Fetch raw data ONLY
    $sql = "SELECT id, start_time FROM incidents WHERE {$whereClause}";
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Count incidents per day in PHP
    $countsByDate = [];

    foreach ($rows as $row) {
        if (empty($row['start_time'])) {
            continue; // skip rows without start_time
        }

        $date = date('Y-m-d', strtotime($row['start_time']));

        if (!isset($countsByDate[$date])) {
            $countsByDate[$date] = 0;
        }

        $countsByDate[$date]++;
    }

    // 3. Format output for frontend
    $timeline = [];

    foreach ($countsByDate as $date => $count) {
        $timeline[] = [
            'date'  => $date,
            'count' => (int) $count
        ];
    }

    echo json_encode([
        'success'  => true,
        'timeline' => $timeline
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
