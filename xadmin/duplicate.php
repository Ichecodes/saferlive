<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: candidates.html');
    exit;
}

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

$candidateId = trim((string) ($_POST['candidate_id'] ?? ''));
$reviewNote = trim((string) ($_POST['review_note'] ?? ''));
$targetIncidentId = trim((string) ($_POST['target_incident_id'] ?? ''));

if ($candidateId === '') {
    redirectToCandidates('Candidate ID is required.', 'error');
}

$candidates = readNdjson($candidatesFile);
$exists = false;
foreach ($candidates as $candidate) {
    if (buildCandidateId($candidate) === $candidateId) {
        $exists = true;
        break;
    }
}

if (!$exists) {
    redirectToCandidates('Candidate not found.', 'error');
}

$actions = readNdjson($reviewActionsFile);
$latestActions = latestActionByCandidate($actions);
$currentAction = (string) ($latestActions[$candidateId]['action'] ?? 'new');
if ($currentAction === 'approved') {
    redirectToCandidates('Approved candidates cannot be marked duplicate.', 'warning');
}

$target = null;
if ($targetIncidentId !== '' && ctype_digit($targetIncidentId)) {
    $target = (int) $targetIncidentId;
}

$ok = appendReviewAction($reviewActionsFile, [
    'candidate_id' => $candidateId,
    'action' => 'duplicate',
    'target_incident_id' => $target,
    'review_note' => $reviewNote,
    'reviewed_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
]);

if (!$ok) {
    redirectToCandidates('Failed to log duplicate action.', 'error');
}

redirectToCandidates('Candidate marked as duplicate.', 'success');
