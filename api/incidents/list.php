<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__, 2) . '/config/database.php';

$state = $_GET['state'] ?? '';
$type = $_GET['type'] ?? '';
$year = $_GET['year'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(1, intval($_GET['limit'] ?? 20));
$sortBy = $_GET['sort_by'] ?? 'start_time';
$sortOrder = $_GET['sort_order'] ?? 'desc';

$offset = ($page - 1) * $limit;

try {
    $db = getDatabaseConnection();
    
    // Build WHERE clause (only apply allowed filters when present)
    $where = ['1=1'];
    $params = [];

    if ($type !== '') {
        $where[] = "LOWER(type) = LOWER(:type)";
        $params[':type'] = $type;
    }

    if ($state !== '') {
        $where[] = "LOWER(state) = LOWER(:state)";
        $params[':state'] = $state;
    }

    if ($year !== '') {
        // accept numeric year like 2024
        $y = intval($year);
        if ($y > 0) {
            $where[] = "start_time >= :start_date";
            $where[] = "start_time <= :end_date";
            $params[':start_date'] = sprintf('%04d-01-01', $y);
            $params[':end_date'] = sprintf('%04d-12-31 23:59:59', $y);
        }
    }

    if ($search !== '') {
        $search = trim($search);
        // split into words and match any word in description
        $words = preg_split('/\s+/', $search);
        $wordClauses = [];
        foreach ($words as $i => $w) {
            $w = trim($w);
            if ($w === '') continue;
            $param = ':search' . $i;
            $wordClauses[] = "description LIKE {$param}";
            $params[$param] = "%{$w}%";
        }
        if (count($wordClauses) > 0) {
            $where[] = '(' . implode(' OR ', $wordClauses) . ')';
        }
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Validate sort column
    $allowedSorts = ['start_time', 'end_time', 'victims', 'casualties', 'type', 'state', 'lga'];
    $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'start_time';
    $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM incidents WHERE {$whereClause}";
    $countStmt = $db->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Build SELECT list robustly: use NULL AS col when column is missing
    $colsStmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incidents'");
    $colsStmt->execute();
    $existingCols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
    $existingMap = array_flip($existingCols ?: []);

    $desired = ['id','type','category','state','lga','location','latitude','longitude','status','start_time','end_time','closed_at','victims','casualties','description'];
    $selectParts = [];
    foreach ($desired as $c) {
        if (isset($existingMap[$c])) {
            $selectParts[] = $c;
        } else {
            $selectParts[] = "NULL AS {$c}";
        }
    }
    $selectParts[] = "TIMESTAMPDIFF(HOUR, start_time, COALESCE(end_time, NOW())) as duration_hours";

    $selectList = implode(', ', $selectParts);

    // Get incidents
    $sql = "SELECT {$selectList} FROM incidents WHERE {$whereClause} ORDER BY {$sortBy} {$sortOrder} LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format duration
    foreach ($incidents as &$incident) {
        $hours = intval($incident['duration_hours'] ?? 0);
        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        
        if ($days > 0) {
            $incident['duration'] = "{$days}d {$remainingHours}h";
        } elseif ($hours > 0) {
            $incident['duration'] = "{$hours}h";
        } else {
            $incident['duration'] = "0h";
        }
        
        unset($incident['duration_hours']);
    }
    
    $totalPages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'data' => $incidents,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'items_per_page' => $limit
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

