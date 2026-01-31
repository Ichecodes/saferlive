<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ─────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────

$BASE = dirname(__DIR__, 2);
require_once $BASE . '/config/database.php';


// Helper: send JSON response with status code
function respondIncident($data, int $status = 200) {
	http_response_code($status);
	echo json_encode($data);
	exit;
}

$id = isset($_GET['id']) ? trim($_GET['id']) : null;
if ($id === null || $id === '' || !ctype_digit($id) || (int)$id <= 0) {
	respondIncident(['success' => false, 'error' => 'Invalid incident ID'], 400);
}

$incidentId = (int)$id;

try {
	$db = getDatabaseConnection();

	$stmt = $db->prepare('
  SELECT id, title, type, status, description, state, lga,
         latitude, longitude, start_time, end_time,
         victims, casualties, missing, injured, created_at
  FROM incidents
  WHERE id = :id
  LIMIT 1
');
$stmt->bindValue(':id', $incidentId, PDO::PARAM_INT);
	$stmt->execute();
	$incident = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$incident) {
		respondIncident(['success' => false, 'error' => 'Incident not found'], 404);
	}

	// Derived fields (duration, is_closed)
	$start = $incident['start_time'] ?? null;
	$end = $incident['end_time'] ?? null;
	$duration = null;
	if ($start) {
		try {
			$startDt = new DateTime($start);
			$endDt = $end ? new DateTime($end) : new DateTime();
			$interval = $startDt->diff($endDt);
			$parts = [];
			if ($interval->d) $parts[] = $interval->d . 'd';
			if ($interval->h) $parts[] = $interval->h . 'h';
			if ($interval->i) $parts[] = $interval->i . 'm';
			if (empty($parts)) $parts[] = '0m';
			$duration = implode(' ', $parts);
		} catch (Exception $e) {
			$duration = '—';
		}
	} else {
		$duration = '—';
	}

	$isClosed = (isset($incident['status']) && strtolower($incident['status']) === 'closed');

	$response = [
  'success' => true,
  'data' => [
    'id' => (int)$incident['id'],
    'title' => $incident['title'] ?? null,
    'type' => $incident['type'] ?? null,
    'status' => $incident['status'] ?? null,
    'description' => $incident['description'] ?? null,
    'state' => $incident['state'] ?? null,
    'lga' => $incident['lga'] ?? null,
    'latitude' => $incident['latitude'] !== null ? (float)$incident['latitude'] : null,
    'longitude' => $incident['longitude'] !== null ? (float)$incident['longitude'] : null,
    'start_time' => $incident['start_time'] ?? null,
    'end_time' => $incident['end_time'] ?? null,
    'duration' => $duration,
    'victims' => (int)($incident['victims'] ?? 0),
    'injured' => (int)($incident['injured'] ?? 0),
    'missing' => (int)($incident['missing'] ?? 0),
    'casualties' => (int)($incident['casualties'] ?? 0),
    'created_at' => $incident['created_at'] ?? null,
    'is_closed' => $isClosed
  ]
];


	respondIncident($response, 200);

} catch (Exception $e) {
	error_log('incident-detail error: ' . $e->getMessage());
	respondIncident(['success' => false, 'error' => 'Unable to fetch incident details'], 500);
}
