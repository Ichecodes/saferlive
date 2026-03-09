<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: candidates.html');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$candidatesFile = __DIR__ . '/../scraper/data/candidates.txt';
$reviewActionsFile = __DIR__ . '/../scraper/data/review_actions.txt';

function redirectToCandidates(string $message, string $type = 'info'): void
{
    header('Location: candidates.html?msg=' . urlencode($message) . '&type=' . urlencode($type));
    exit;
}

function readNdjson(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $rows = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $rows[] = $decoded;
        }
    }

    return $rows;
}

function buildCandidateId(array $candidate): string
{
    if (!empty($candidate['candidate_id'])) {
        return (string) $candidate['candidate_id'];
    }

    $seed = strtolower(trim(
        (string) ($candidate['raw_id'] ?? '') . '|' .
        (string) ($candidate['article_url'] ?? $candidate['source_url'] ?? '') . '|' .
        (string) ($candidate['title'] ?? '') . '|' .
        (string) ($candidate['source_name'] ?? '')
    ));

    return 'cand_' . substr(sha1($seed), 0, 16);
}

function appendReviewAction(string $path, array $payload): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (!is_file($path)) {
        @file_put_contents($path, '');
    }

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $fp = @fopen($path, 'ab');
    if (!$fp) {
        return false;
    }

    $ok = false;
    if (@flock($fp, LOCK_EX)) {
        $ok = @fwrite($fp, $json . PHP_EOL) !== false;
        @fflush($fp);
        @flock($fp, LOCK_UN);
    }
    @fclose($fp);

    return $ok;
}

function normalizeTitle(string $title): string
{
    $title = mb_strtolower($title, 'UTF-8');
    $title = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $title) ?? $title;
    $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
    return trim($title);
}

function parseDateTime(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function findCandidateById(string $candidateId, array $candidates): ?array
{
    foreach ($candidates as $candidate) {
        if (buildCandidateId($candidate) === $candidateId) {
            return $candidate;
        }
    }

    return null;
}

function latestActionByCandidate(array $actions): array
{
    $map = [];
    foreach ($actions as $action) {
        $id = (string) ($action['candidate_id'] ?? '');
        if ($id === '') {
            continue;
        }
        $map[$id] = $action;
    }
    return $map;
}

function findDuplicateIncidentId(PDO $db, array $candidate): ?int
{
    $sourceUrl = trim((string) ($candidate['article_url'] ?? $candidate['source_url'] ?? ''));
    if ($sourceUrl !== '') {
        $stmt = $db->prepare('SELECT id FROM incidents WHERE source_url = :source_url ORDER BY id DESC LIMIT 1');
        $stmt->execute([':source_url' => $sourceUrl]);
        $found = $stmt->fetch();
        if ($found && !empty($found['id'])) {
            return (int) $found['id'];
        }
    }

    $title = normalizeTitle((string) ($candidate['title'] ?? ''));
    if ($title === '') {
        return null;
    }

    $state = trim((string) ($candidate['state'] ?? ''));
    if ($state !== '') {
        $stmt = $db->prepare('SELECT id, title FROM incidents WHERE state = :state AND start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY start_time DESC LIMIT 150');
        $stmt->execute([':state' => $state]);
    } else {
        $stmt = $db->query('SELECT id, title FROM incidents WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY start_time DESC LIMIT 150');
    }

    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($rows as $row) {
        $existingTitle = normalizeTitle((string) ($row['title'] ?? ''));
        if ($existingTitle === '') {
            continue;
        }

        similar_text($title, $existingTitle, $percent);
        if ($percent >= 90.0) {
            return (int) $row['id'];
        }
    }

    return null;
}

$candidateId = trim((string) ($_POST['candidate_id'] ?? ''));
$reviewNote = trim((string) ($_POST['review_note'] ?? ''));
$stateOverride = trim((string) ($_POST['state_override'] ?? ''));
$lgaOverride = trim((string) ($_POST['lga_override'] ?? ''));
$latitudeOverrideRaw = trim((string) ($_POST['latitude_override'] ?? ''));
$longitudeOverrideRaw = trim((string) ($_POST['longitude_override'] ?? ''));

if ($candidateId === '') {
    redirectToCandidates('Candidate ID is required.', 'error');
}

if ($stateOverride === '' || $lgaOverride === '') {
    redirectToCandidates('State and LGA are required for promotion.', 'error');
}

$latitudeOverride = is_numeric($latitudeOverrideRaw) ? (float) $latitudeOverrideRaw : null;
$longitudeOverride = is_numeric($longitudeOverrideRaw) ? (float) $longitudeOverrideRaw : null;

$candidates = readNdjson($candidatesFile);
$actions = readNdjson($reviewActionsFile);
$candidate = findCandidateById($candidateId, $candidates);

if ($candidate === null) {
    redirectToCandidates('Candidate not found.', 'error');
}

$latestActions = latestActionByCandidate($actions);
$currentAction = (string) ($latestActions[$candidateId]['action'] ?? 'new');
if ($currentAction === 'approved') {
    redirectToCandidates('Candidate already approved.', 'warning');
}

try {
    $db = getDatabaseConnection();

    $candidateForDuplicateCheck = $candidate;
    $candidateForDuplicateCheck['state'] = $stateOverride;

    $duplicateId = findDuplicateIncidentId($db, $candidateForDuplicateCheck);
    if ($duplicateId !== null) {
        redirectToCandidates('Possible duplicate incident found (#' . $duplicateId . '). Use Mark Duplicate instead.', 'warning');
    }

    $type = trim((string) ($candidate['type'] ?? $candidate['matched_incident_type'] ?? $candidate['incident_type'] ?? 'general_incident'));
    $title = trim((string) ($candidate['title'] ?? 'Untitled Candidate'));
    $state = $stateOverride;
    $lga = $lgaOverride;

    $places = array_values((array) ($candidate['place_keywords_found'] ?? []));
    $location = trim((string) ($candidate['location'] ?? ''));
    if ($location === '') {
        $location = $lga !== '' ? $lga : (string) ($places[0] ?? '');
    }

    $candidateLat = is_numeric($candidate['latitude'] ?? null) ? (float) $candidate['latitude'] : null;
    $candidateLng = is_numeric($candidate['longitude'] ?? null) ? (float) $candidate['longitude'] : null;

    $latitude = $latitudeOverride ?? $candidateLat ?? 9.082;
    $longitude = $longitudeOverride ?? $candidateLng ?? 8.6753;

    $startTime = parseDateTime((string) ($candidate['incident_time'] ?? $candidate['article_published_at'] ?? $candidate['created_at'] ?? ''));
    if ($startTime === null) {
        $startTime = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    $sourcePublishedAt = parseDateTime((string) ($candidate['article_published_at'] ?? $candidate['source_published_at'] ?? $candidate['published_at'] ?? ''));
    $sourceName = trim((string) ($candidate['source_name'] ?? ''));
    $sourceUrl = trim((string) ($candidate['article_url'] ?? $candidate['source_url'] ?? ''));

    $description = trim((string) ($candidate['summary'] ?? $candidate['reason'] ?? 'Candidate promoted from discovery review.'));

    $victims = (int) ($candidate['victims'] ?? 0);
    $casualties = (int) ($candidate['casualties'] ?? 0);
    $missing = (int) ($candidate['missing'] ?? 0);
    $injured = (int) ($candidate['injured'] ?? 0);

    $stmt = $db->prepare(
        'INSERT INTO incidents (
            title, type, state, lga, location,
            latitude, longitude,
            start_time,
            victims, casualties, missing, injured,
            description,
            source_name, source_url, source_published_at,
            status, created_at, updated_at
        ) VALUES (
            :title, :type, :state, :lga, :location,
            :latitude, :longitude,
            :start_time,
            :victims, :casualties, :missing, :injured,
            :description,
            :source_name, :source_url, :source_published_at,
            :status, NOW(), NOW()
        )'
    );

    $stmt->execute([
        ':title' => $title,
        ':type' => $type,
        ':state' => $state,
        ':lga' => $lga,
        ':location' => $location !== '' ? $location : null,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':start_time' => $startTime,
        ':victims' => $victims,
        ':casualties' => $casualties,
        ':missing' => $missing,
        ':injured' => $injured,
        ':description' => $description,
        ':source_name' => $sourceName !== '' ? $sourceName : null,
        ':source_url' => $sourceUrl !== '' ? $sourceUrl : null,
        ':source_published_at' => $sourcePublishedAt,
        ':status' => 'pending',
    ]);

    $incidentId = (int) $db->lastInsertId();

    $actionLogged = appendReviewAction($reviewActionsFile, [
        'candidate_id' => $candidateId,
        'action' => 'approved',
        'target_incident_id' => $incidentId,
        'review_note' => $reviewNote,
        'reviewed_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
    ]);

    if (!$actionLogged) {
        redirectToCandidates('Incident created, but failed to log review action.', 'warning');
    }

    redirectToCandidates('Candidate approved and promoted as incident #' . $incidentId . '.', 'success');
} catch (Throwable $e) {
    error_log('Candidate promotion error: ' . $e->getMessage());
    redirectToCandidates('Failed to promote candidate.', 'error');
}
