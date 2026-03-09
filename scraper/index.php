<?php

$baseDir = __DIR__;

require_once $baseDir . '/src/Helpers.php';
require_once $baseDir . '/src/HttpClient.php';
require_once $baseDir . '/src/SourceManager.php';
require_once $baseDir . '/src/LinkExtractor.php';
require_once $baseDir . '/src/ContentExtractor.php';
require_once $baseDir . '/src/ArticleFetcher.php';
require_once $baseDir . '/src/RawStore.php';
require_once $baseDir . '/src/CandidateDetector.php';
require_once $baseDir . '/src/DuplicateDetector.php';
require_once $baseDir . '/src/ScrapeRunner.php';

$appConfig = require $baseDir . '/config/app.php';
$sources = require $baseDir . '/config/sources.php';
$keywords = require $baseDir . '/config/keywords.php';
$places = require $baseDir . '/config/places.php';

$store = new RawStore($baseDir . '/data');
$sourceManager = new SourceManager($sources);

$summary = [
    'configured_sources' => count($sources),
    'enabled_sources' => count(array_filter($sources, static fn ($s) => !empty($s['enabled']))),
    'raw_count' => $store->countRaw(),
    'candidate_count' => $store->countCandidates(),
    'last_run' => $store->latestRunTime(),
];

$rawItems = $store->latestRaw(20);
$candidates = $store->latestCandidates(20);
$runLogLines = $store->latestLogLines(100);

$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incidents Mini Dashboard</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="container">
    <header class="page-header">
        <div>
            <h1>Nigeria Incident Discovery (MVP)</h1>
            <p>Source-based scraper using plain text NDJSON storage.</p>
        </div>
        <div class="actions">
            <form method="post" action="run.php" id="run-form">
                <button type="submit" id="run-btn">Run scraper now</button>
            </form>
            <a href="index.php" class="secondary-btn">Refresh dashboard</a>
        </div>
    </header>

    <?php if ($status !== ''): ?>
        <div class="status-box"><?= Helpers::e($status) ?></div>
    <?php endif; ?>

    <section class="cards">
        <article class="card">
            <h3>Configured Sources</h3>
            <p><?= (int) $summary['enabled_sources'] ?> enabled / <?= (int) $summary['configured_sources'] ?> total</p>
        </article>
        <article class="card">
            <h3>Raw Items</h3>
            <p><?= (int) $summary['raw_count'] ?></p>
        </article>
        <article class="card">
            <h3>Candidate Items</h3>
            <p><?= (int) $summary['candidate_count'] ?></p>
        </article>
        <article class="card">
            <h3>Last Run</h3>
            <p><?= Helpers::e($summary['last_run'] ?? 'Never') ?></p>
        </article>
    </section>

    <section class="panel">
        <details class="accordion-item">
            <summary>
                <span>Configured Sources</span>
                <span class="accordion-meta"><?= (int) $summary['configured_sources'] ?> total</span>
            </summary>
            <div class="accordion-content">
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Domain</th>
                            <th>Enabled</th>
                            <th>List URL Count</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sources as $source): ?>
                            <tr>
                                <td><?= Helpers::e((string) ($source['name'] ?? '')) ?></td>
                                <td><?= Helpers::e((string) ($source['domain'] ?? '')) ?></td>
                                <td>
                                    <span class="badge <?= !empty($source['enabled']) ? 'badge-ok' : 'badge-muted' ?>">
                                        <?= !empty($source['enabled']) ? 'Yes' : 'No' ?>
                                    </span>
                                </td>
                                <td><?= count((array) ($source['list_urls'] ?? [])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    </section>

    <section class="panel">
        <h2>Latest Raw Items</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Fetched At</th>
                    <th>Published At (Source)</th>
                    <th>Source</th>
                    <th>Title</th>
                    <th>Article URL</th>
                    <th>Exact Duplicate</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rawItems)): ?>
                    <tr><td colspan="6">No raw items yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($rawItems as $row): ?>
                        <tr>
                            <td><?= Helpers::e((string) ($row['fetched_at'] ?? '')) ?></td>
                            <td><?= Helpers::e((string) (($row['published_at'] ?? '') !== '' ? $row['published_at'] : '-')) ?></td>
                            <td><?= Helpers::e((string) ($row['source_name'] ?? '')) ?></td>
                            <td><?= Helpers::e((string) ($row['title'] ?? '')) ?></td>
                            <td>
                                <a href="<?= Helpers::e((string) ($row['article_url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= Helpers::e((string) ($row['article_url'] ?? '')) ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge <?= !empty($row['exact_duplicate']) ? 'badge-danger' : 'badge-ok' ?>">
                                    <?= !empty($row['exact_duplicate']) ? 'Yes' : 'No' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Latest Candidates</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Created At</th>
                    <th>Score</th>
                    <th>Incident Type</th>
                    <th>Title</th>
                    <th>Source</th>
                    <th>Article URL</th>
                    <th>Matched Places</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($candidates)): ?>
                    <tr><td colspan="7">No candidate items yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($candidates as $row): ?>
                        <tr>
                            <td><?= Helpers::e((string) ($row['created_at'] ?? '')) ?></td>
                            <td><span class="badge badge-score"><?= (int) ($row['incident_score'] ?? 0) ?></span></td>
                            <td><span class="badge badge-type"><?= Helpers::e((string) ($row['matched_incident_type'] ?? '')) ?></span></td>
                            <td><?= Helpers::e((string) ($row['title'] ?? '')) ?></td>
                            <td><?= Helpers::e((string) ($row['source_name'] ?? '')) ?></td>
                            <td>
                                <a href="<?= Helpers::e((string) ($row['article_url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer">Open</a>
                            </td>
                            <td><?= Helpers::e(implode(', ', (array) ($row['place_keywords_found'] ?? []))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Run Log</h2>
        <div class="log-box">
            <?php if (empty($runLogLines)): ?>
                <p>No run log lines yet.</p>
            <?php else: ?>
                <?php foreach ($runLogLines as $line): ?>
                    <div class="log-line"><?= Helpers::e($line) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<script src="assets/app.js"></script>
</body>
</html>

