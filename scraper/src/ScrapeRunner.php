<?php

class ScrapeRunner
{
    private SourceManager $sourceManager;
    private HttpClient $httpClient;
    private LinkExtractor $linkExtractor;
    private ArticleFetcher $articleFetcher;
    private RawStore $store;
    private DuplicateDetector $duplicateDetector;
    private CandidateDetector $candidateDetector;
    private RssCollector $rssCollector;
    private array $appConfig;

    public function __construct(
        SourceManager $sourceManager,
        HttpClient $httpClient,
        LinkExtractor $linkExtractor,
        ArticleFetcher $articleFetcher,
        RawStore $store,
        DuplicateDetector $duplicateDetector,
        CandidateDetector $candidateDetector,
        array $appConfig
    ) {
        $this->sourceManager = $sourceManager;
        $this->httpClient = $httpClient;
        $this->linkExtractor = $linkExtractor;
        $this->articleFetcher = $articleFetcher;
        $this->store = $store;
        $this->duplicateDetector = $duplicateDetector;
        $this->candidateDetector = $candidateDetector;
        $this->appConfig = $appConfig;
        $this->rssCollector = new RssCollector($appConfig);
    }

    public function runOnce(): array
    {
        $stats = [
            'sources_processed' => 0,
            'listing_pages_fetched' => 0,
            'rss_feeds_fetched' => 0,
            'links_found' => 0,
            'articles_fetched' => 0,
            'raw_saved' => 0,
            'candidates_saved' => 0,
            'duplicates_skipped' => 0,
            'errors' => 0,
        ];

        $this->store->log('Run start');

        $sources = $this->sourceManager->enabled((int) ($this->appConfig['max_sources_per_run'] ?? 5));

        foreach ($sources as $source) {
            $stats['sources_processed']++;
            $sourceName = (string) ($source['name'] ?? 'unknown');
            $this->store->log('Processing source: ' . $sourceName);

            try {
                $collectedLinks = [];
                $linkMeta = [];

                $rssResult = $this->rssCollector->collect($source);
                $stats['rss_feeds_fetched'] += (int) ($rssResult['feeds_fetched'] ?? 0);

                foreach ((array) ($rssResult['errors'] ?? []) as $errorMessage) {
                    $stats['errors']++;
                    $this->store->log((string) $errorMessage);
                }

                foreach ((array) ($rssResult['items'] ?? []) as $rssItem) {
                    $link = (string) ($rssItem['article_url'] ?? '');
                    if ($link === '') {
                        continue;
                    }

                    if (!isset($collectedLinks[$link])) {
                        $collectedLinks[$link] = true;
                        $linkMeta[$link] = [
                            'source_type' => 'rss',
                            'source_ref_url' => (string) ($rssItem['rss_url'] ?? ''),
                            'rss_title' => (string) ($rssItem['title'] ?? ''),
                            'rss_snippet' => (string) ($rssItem['snippet'] ?? ''),
                            'rss_published_at' => (string) ($rssItem['published_at'] ?? ''),
                        ];
                        $stats['links_found']++;
                    }
                }

                foreach ((array) ($source['list_urls'] ?? []) as $listUrl) {
                    $resp = $this->httpClient->get((string) $listUrl);
                    $stats['listing_pages_fetched']++;

                    if (!$resp['ok']) {
                        $stats['errors']++;
                        $this->store->log('Listing fetch failed: ' . $listUrl . ' (' . $resp['error'] . ')');
                        continue;
                    }

                    $this->store->log('Listing fetched: ' . $listUrl);
                    $links = $this->linkExtractor->extract((string) $resp['body'], (string) $resp['final_url'], $source);
                    $this->store->log('Links found from listing: ' . count($links));

                    foreach ($links as $link) {
                        if (!isset($collectedLinks[$link])) {
                            $collectedLinks[$link] = true;
                            $linkMeta[$link] = [
                                'source_type' => 'list_page',
                                'source_ref_url' => (string) $listUrl,
                                'rss_title' => '',
                                'rss_snippet' => '',
                                'rss_published_at' => '',
                            ];
                            $stats['links_found']++;
                        }
                    }
                }

                $maxLinks = (int) ($this->appConfig['max_links_per_source'] ?? 10);
                $linksToProcess = array_slice(array_keys($collectedLinks), 0, $maxLinks);

                foreach ($linksToProcess as $articleUrl) {
                    if ($this->store->hasSeenUrl($articleUrl)) {
                        $stats['duplicates_skipped']++;
                        $this->store->log('Skipped seen URL: ' . $articleUrl);
                        continue;
                    }

                    $meta = (array) ($linkMeta[$articleUrl] ?? []);
                    $article = $this->articleFetcher->fetch($articleUrl);
                    if (!$article['ok']) {
                        $stats['errors']++;
                        $this->store->log('Article fetch failed: ' . $articleUrl . ' (' . $article['error'] . ')');
                        continue;
                    }

                    $stats['articles_fetched']++;

                    $contentText = Helpers::normalizeWhitespace((string) ($article['content_text'] ?? ''));
                    if (mb_strlen($contentText, 'UTF-8') < (int) ($this->appConfig['min_article_text_length'] ?? 400)) {
                        $this->store->log('Skipped tiny article text: ' . $articleUrl);
                        continue;
                    }

                    if (($article['language'] ?? 'unknown') !== 'en') {
                        $this->store->log('Skipped non-English article: ' . $articleUrl);
                        continue;
                    }

                    $title = Helpers::normalizeWhitespace((string) ($article['title'] ?? ''));
                    if ($title === '') {
                        $title = Helpers::normalizeWhitespace((string) ($meta['rss_title'] ?? ''));
                    }
                    if ($title === '') {
                        $title = mb_substr($contentText, 0, 140, 'UTF-8');
                    }

                    $duplicateResult = $this->duplicateDetector->check($articleUrl, $title, $contentText);
                    if ($duplicateResult['is_exact_duplicate'] || $duplicateResult['is_near_duplicate']) {
                        $stats['duplicates_skipped']++;
                        $this->store->log('Duplicate skipped: ' . $articleUrl . ' (' . $duplicateResult['reason'] . ')');
                        continue;
                    }

                    $snippet = Helpers::normalizeWhitespace((string) ($article['snippet'] ?? ''));
                    if ($snippet === '') {
                        $snippet = Helpers::normalizeWhitespace((string) ($meta['rss_snippet'] ?? ''));
                    }

                    $publishedAt = $article['published_at'] ?? null;
                    if (($publishedAt === null || $publishedAt === '') && !empty($meta['rss_published_at'])) {
                        $publishedAt = Helpers::safeDate((string) $meta['rss_published_at']);
                    }

                    try {
                        $id = bin2hex(random_bytes(8));
                    } catch (Throwable $e) {
                        $id = uniqid('raw_', true);
                    }

                    $rawItem = [
                        'id' => $id,
                        'source_name' => (string) ($source['name'] ?? ''),
                        'source_domain' => (string) ($source['domain'] ?? ($article['source_domain'] ?? '')),
                        'source_list_url' => (string) ($meta['source_ref_url'] ?? ''),
                        'source_type' => (string) ($meta['source_type'] ?? 'list_page'),
                        'article_url' => $articleUrl,
                        'title' => $title,
                        'snippet' => $snippet,
                        'content_text' => $contentText,
                        'published_at' => $publishedAt,
                        'fetched_at' => Helpers::nowIso(),
                        'language' => 'en',
                        'query_label' => null,
                        'exact_hash' => (string) $duplicateResult['exact_hash'],
                        'text_fingerprint' => (string) $duplicateResult['text_fingerprint'],
                        'exact_duplicate' => false,
                        'status' => 'new',
                    ];

                    if ($this->store->appendRawItem($rawItem)) {
                        $stats['raw_saved']++;
                        $this->duplicateDetector->registerFingerprint((string) $rawItem['text_fingerprint']);
                        $this->store->log('Raw item saved: ' . $articleUrl . ' (source_type=' . $rawItem['source_type'] . ')');

                        $candidate = $this->candidateDetector->detect($rawItem);
                        if ($candidate !== null && $this->store->appendCandidate($candidate)) {
                            $stats['candidates_saved']++;
                            $this->store->log('Candidate saved: ' . $articleUrl . ' (score: ' . $candidate['incident_score'] . ')');
                        }
                    } else {
                        $stats['errors']++;
                        $this->store->log('Failed to save raw item: ' . $articleUrl);
                    }
                }
            } catch (Throwable $e) {
                $stats['errors']++;
                $this->store->log('Source error [' . $sourceName . ']: ' . $e->getMessage());
            }
        }

        $this->store->log('Run end | raw_saved=' . $stats['raw_saved'] . ', candidates_saved=' . $stats['candidates_saved'] . ', duplicates_skipped=' . $stats['duplicates_skipped'] . ', errors=' . $stats['errors']);

        return $stats;
    }
}
