<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$candidatesFile = __DIR__ . '/../scraper/data/candidates.txt';
$reviewActionsFile = __DIR__ . '/../scraper/data/review_actions.txt';

function readNdjson(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $items = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $items[] = $decoded;
        }
    }

    return $items;
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

$candidates = readNdjson($candidatesFile);
$actions = readNdjson($reviewActionsFile);

$latestActionByCandidate = [];
foreach ($actions as $action) {
    $candidateId = (string) ($action['candidate_id'] ?? '');
    if ($candidateId === '') {
        continue;
    }

    $latestActionByCandidate[$candidateId] = $action;
}

$rows = [];
$seen = [];
foreach ($candidates as $candidate) {
    $candidateId = buildCandidateId($candidate);
    if (isset($seen[$candidateId])) {
        continue;
    }
    $seen[$candidateId] = true;

    $status = (string) (($latestActionByCandidate[$candidateId]['action'] ?? 'new'));

    $rows[] = [
        'candidate_id' => $candidateId,
        'score' => (int) ($candidate['incident_score'] ?? $candidate['score'] ?? 0),
        'incident_type' => (string) ($candidate['matched_incident_type'] ?? $candidate['incident_type'] ?? $candidate['type'] ?? 'unknown'),
        'title' => (string) ($candidate['title'] ?? ''),
        'source_name' => (string) ($candidate['source_name'] ?? ''),
        'state' => (string) ($candidate['state'] ?? ''),
        'lga' => (string) ($candidate['lga'] ?? ''),
        'posted_at' => (string) ($candidate['article_published_at'] ?? $candidate['source_published_at'] ?? $candidate['published_at'] ?? ''),
        'matched_places' => array_values((array) ($candidate['place_keywords_found'] ?? $candidate['matched_places'] ?? [])),
        'source_url' => (string) ($candidate['article_url'] ?? $candidate['source_url'] ?? ''),
        'current_status' => $status,
        'created_at' => (string) ($candidate['created_at'] ?? ''),
    ];
}

usort($rows, static function (array $a, array $b): int {
    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

$summary = [
    'new' => 0,
    'approved' => 0,
    'rejected' => 0,
    'duplicate' => 0,
    'needs_review' => 0,
];

foreach ($rows as $row) {
    $status = (string) ($row['current_status'] ?? 'new');
    if (!isset($summary[$status])) {
        $summary[$status] = 0;
    }
    $summary[$status]++;
}

echo json_encode([
    'success' => true,
    'summary' => $summary,
    'data' => $rows,
], JSON_UNESCAPED_SLASHES);
