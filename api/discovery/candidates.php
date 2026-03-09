<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/lib/Discovery/CandidateDetector.php';

/**
 * JSON response helper.
 */
function candidatesRespond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

try {
    $db = getDatabaseConnection();
    new \Lib\Discovery\CandidateDetector($db); // Ensures review table exists.

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(200, (int)($_GET['per_page'] ?? 25)));
    $offset = ($page - 1) * $perPage;

    $sourcePlatform = trim((string)($_GET['source_platform'] ?? ''));
    $isCandidate = isset($_GET['is_candidate']) ? trim((string)$_GET['is_candidate']) : '';
    $reviewStatus = trim((string)($_GET['review_status'] ?? ''));
    $minScore = isset($_GET['min_score']) ? (float)$_GET['min_score'] : null;
    $search = trim((string)($_GET['q'] ?? ''));
    $place = trim((string)($_GET['place'] ?? ($_GET['state'] ?? '')));
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));

    $where = ['1=1'];
    $params = [];

    if ($sourcePlatform !== '') {
        $where[] = 'LOWER(r.source_platform) = LOWER(:source_platform)';
        $params[':source_platform'] = $sourcePlatform;
    }

    if ($isCandidate !== '' && in_array($isCandidate, ['0', '1'], true)) {
        $where[] = 'r.is_candidate = :is_candidate';
        $params[':is_candidate'] = (int)$isCandidate;
    }

    if ($reviewStatus !== '') {
        $where[] = 'COALESCE(cr.review_status, :default_review_status) = :review_status';
        $params[':default_review_status'] = 'pending';
        $params[':review_status'] = $reviewStatus;
    }

    if ($minScore !== null && $minScore >= 0) {
        $where[] = 'COALESCE(cr.candidate_score, 0) >= :min_score';
        $params[':min_score'] = $minScore;
    }

    if ($search !== '') {
        $where[] = '(r.title LIKE :search OR r.body LIKE :search OR r.author_name LIKE :search OR r.source_url LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    if ($place !== '') {
        $where[] = '(COALESCE(cr.matched_places_json, "") LIKE :place OR r.body LIKE :place_body OR r.title LIKE :place_title)';
        $params[':place'] = '%' . $place . '%';
        $params[':place_body'] = '%' . $place . '%';
        $params[':place_title'] = '%' . $place . '%';
    }

    if ($dateFrom !== '') {
        $where[] = 'COALESCE(r.posted_at, r.fetched_at, r.created_at) >= :date_from';
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = 'COALESCE(r.posted_at, r.fetched_at, r.created_at) <= :date_to';
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $whereSql = implode(' AND ', $where);

    $countSql = "
        SELECT COUNT(*)
        FROM raw_discovery_items r
        LEFT JOIN discovery_candidate_reviews cr ON cr.raw_item_id = r.id
        WHERE {$whereSql}
    ";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $itemsSql = "
        SELECT
            r.id AS raw_item_id,
            r.source_platform,
            r.source_url,
            r.title,
            r.body,
            r.author_name,
            r.posted_at,
            r.fetched_at,
            r.is_candidate,
            COALESCE(cr.candidate_score, 0) AS candidate_score,
            COALESCE(cr.review_status, 'pending') AS candidate_review_status,
            COALESCE(cr.reason_summary, '') AS reason_summary
        FROM raw_discovery_items r
        LEFT JOIN discovery_candidate_reviews cr ON cr.raw_item_id = r.id
        WHERE {$whereSql}
        ORDER BY r.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $itemsStmt = $db->prepare($itemsSql);
    foreach ($params as $k => $v) {
        $itemsStmt->bindValue($k, $v);
    }
    $itemsStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $itemsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $itemsStmt->execute();
    $rows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = [];
    foreach ($rows as $row) {
        $body = (string)($row['body'] ?? '');
        $row['body_preview'] = mb_substr(trim($body), 0, 180) . (mb_strlen($body) > 180 ? '...' : '');
        unset($row['body']);
        $items[] = $row;
    }

    $summarySql = "
        SELECT
            COUNT(*) AS total_items,
            SUM(CASE WHEN r.is_candidate = 1 THEN 1 ELSE 0 END) AS total_candidates,
            SUM(CASE WHEN r.is_candidate = 0 THEN 1 ELSE 0 END) AS total_non_candidates,
            SUM(CASE WHEN COALESCE(cr.review_status, 'pending') = 'pending' THEN 1 ELSE 0 END) AS pending_review,
            SUM(CASE WHEN COALESCE(cr.review_status, 'pending') = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN COALESCE(cr.review_status, 'pending') = 'rejected' THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN cr.raw_item_id IS NOT NULL THEN 1 ELSE 0 END) AS total_reviewed
        FROM raw_discovery_items r
        LEFT JOIN discovery_candidate_reviews cr ON cr.raw_item_id = r.id
    ";
    $summaryStmt = $db->query($summarySql);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    candidatesRespond([
        'success' => true,
        'data' => [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'summary' => [
                'total_reviewed_items' => (int)($summary['total_reviewed'] ?? 0),
                'total_candidates' => (int)($summary['total_candidates'] ?? 0),
                'total_non_candidates' => (int)($summary['total_non_candidates'] ?? 0),
                'pending_review' => (int)($summary['pending_review'] ?? 0),
                'approved' => (int)($summary['approved'] ?? 0),
                'rejected' => (int)($summary['rejected'] ?? 0),
            ],
        ],
    ]);
} catch (Throwable $e) {
    error_log('candidates.php error: ' . $e->getMessage());
    candidatesRespond(['success' => false, 'error' => 'Server error'], 500);
}
