<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

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
require_once $baseDir . '/src/RssCollector.php';
require_once $baseDir . '/src/ScrapeRunner.php';

$appConfig = require $baseDir . '/config/app.php';
$sources = require $baseDir . '/config/sources.php';
$keywords = require $baseDir . '/config/keywords.php';
$places = require $baseDir . '/config/places.php';

$sourceManager = new SourceManager($sources);
$httpClient = new HttpClient($appConfig);
$linkExtractor = new LinkExtractor();
$contentExtractor = new ContentExtractor();
$articleFetcher = new ArticleFetcher($httpClient, $contentExtractor);
$store = new RawStore($baseDir . '/data');
$duplicateDetector = new DuplicateDetector($store, $appConfig);
$candidateDetector = new CandidateDetector($keywords, $places, $appConfig);

$runner = new ScrapeRunner(
    $sourceManager,
    $httpClient,
    $linkExtractor,
    $articleFetcher,
    $store,
    $duplicateDetector,
    $candidateDetector,
    $appConfig
);

$stats = $runner->runOnce();
$status = sprintf(
    'Run completed: sources=%d, raw_saved=%d, candidates=%d, duplicates=%d, errors=%d',
    (int) ($stats['sources_processed'] ?? 0),
    (int) ($stats['raw_saved'] ?? 0),
    (int) ($stats['candidates_saved'] ?? 0),
    (int) ($stats['duplicates_skipped'] ?? 0),
    (int) ($stats['errors'] ?? 0)
);

header('Location: index.php?status=' . urlencode($status));
exit;
