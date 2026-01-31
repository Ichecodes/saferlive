<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Ensure correct path to project config (three levels up from this file)
require_once dirname(__DIR__, 3) . '/config/database.php';

try {
    $db = getDatabaseConnection();
    
    // Get recent incidents for summary
    $sql = "SELECT 
                type, state, lga, status, start_time, victims, casualties
            FROM incidents
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY start_time DESC
            LIMIT 50";
    
    $stmt = $db->query($sql);
    $recentIncidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate simple summary (can be enhanced with AI integration)
    $summary = generateAISummary($recentIncidents);
    
    echo json_encode([
        'success' => true,
        'summary' => $summary
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

function generateAISummary($incidents) {
    if (empty($incidents)) {
        return "No recent incidents reported in the last 7 days.";
    }
    
    $total = count($incidents);
    $openCount = count(array_filter($incidents, fn($i) => strtolower($i['status']) === 'open'));
    $closedCount = count(array_filter($incidents, fn($i) => strtolower($i['status']) === 'closed'));
    
    // Count by type
    $typeCounts = [];
    foreach ($incidents as $incident) {
        $type = $incident['type'] ?? 'Unknown';
        $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
    }
    arsort($typeCounts);
    $topType = array_key_first($typeCounts);
    
    // Count by state
    $stateCounts = [];
    foreach ($incidents as $incident) {
        $state = $incident['state'] ?? 'Unknown';
        $stateCounts[$state] = ($stateCounts[$state] ?? 0) + 1;
    }
    arsort($stateCounts);
    $topState = array_key_first($stateCounts);
    
    // Total victims
    $totalVictims = array_sum(array_column($incidents, 'victims'));
    $totalCasualties = array_sum(array_column($incidents, 'casualties'));
    
    $summary = "In the last 7 days, {$total} incidents were reported across Nigeria. ";
    $summary .= "{$openCount} incidents remain open, while {$closedCount} have been resolved. ";
    
    if ($topType) {
        $summary .= "The most common incident type was {$topType} with {$typeCounts[$topType]} occurrences. ";
    }
    
    if ($topState) {
        $summary .= "{$topState} state had the highest number of incidents with {$stateCounts[$topState]} reports. ";
    }
    
    if ($totalVictims > 0) {
        $summary .= "Total victims affected: {$totalVictims}, with {$totalCasualties} casualties. ";
    }
    
    $summary .= "Stay vigilant and report any suspicious activities immediately.";
    
    return $summary;
}
?>

