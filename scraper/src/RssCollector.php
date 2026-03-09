<?php

class RssCollector
{
    private string $userAgent;
    private int $timeout;

    public function __construct(array $appConfig)
    {
        $this->userAgent = (string) ($appConfig['user_agent'] ?? 'IncidentsMini/1.0');
        $this->timeout = max(3, (int) ($appConfig['request_timeout_seconds'] ?? 10));
    }

    public function collect(array $source): array
    {
        $items = [];
        $errors = [];
        $fetched = 0;
        $domain = (string) ($source['domain'] ?? '');

        foreach ((array) ($source['rss_urls'] ?? []) as $rssUrl) {
            $rssUrl = trim((string) $rssUrl);
            if ($rssUrl === '') {
                continue;
            }

            $response = $this->fetch($rssUrl);
            $fetched++;

            if (!$response['ok']) {
                $errors[] = 'RSS fetch failed: ' . $rssUrl . ' (' . $response['error'] . ')';
                continue;
            }

            $parsed = $this->parseFeed((string) $response['body'], $rssUrl);
            if (!$parsed['ok']) {
                $errors[] = 'RSS parse failed: ' . $rssUrl . ' (' . $parsed['error'] . ')';
                continue;
            }

            foreach ($parsed['items'] as $item) {
                $articleUrl = trim((string) ($item['article_url'] ?? ''));
                if ($articleUrl === '') {
                    continue;
                }

                if ($domain !== '' && !Helpers::sameDomain($articleUrl, $domain)) {
                    continue;
                }

                $items[] = [
                    'article_url' => $articleUrl,
                    'title' => (string) ($item['title'] ?? ''),
                    'snippet' => (string) ($item['snippet'] ?? ''),
                    'published_at' => Helpers::safeDate((string) ($item['published_at'] ?? '')),
                    'rss_url' => $rssUrl,
                ];
            }
        }

        $unique = [];
        foreach ($items as $item) {
            $unique[$item['article_url']] = $item;
        }

        return [
            'items' => array_values($unique),
            'errors' => $errors,
            'feeds_fetched' => $fetched,
        ];
    }

    private function fetch(string $url): array
    {
        $ch = curl_init($url);
        if (!$ch) {
            return [
                'ok' => false,
                'body' => '',
                'error' => 'Failed to initialize cURL',
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
                'Cache-Control: no-cache',
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return [
                'ok' => false,
                'body' => '',
                'error' => $error ?: 'Unknown HTTP error',
            ];
        }

        if ($status < 200 || $status >= 400) {
            return [
                'ok' => false,
                'body' => '',
                'error' => 'HTTP ' . $status,
            ];
        }

        return [
            'ok' => true,
            'body' => (string) $body,
            'error' => '',
        ];
    }

    private function parseFeed(string $xml, string $baseUrl): array
    {
        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();

        if (!$feed) {
            return [
                'ok' => false,
                'items' => [],
                'error' => 'Invalid XML',
            ];
        }

        $items = [];

        if (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $items[] = [
                    'article_url' => $this->normalizeFeedLink((string) $item->link, $baseUrl),
                    'title' => Helpers::normalizeWhitespace((string) $item->title),
                    'snippet' => Helpers::normalizeWhitespace((string) ($item->description ?? '')),
                    'published_at' => (string) ($item->pubDate ?? ''),
                ];
            }
        } elseif (isset($feed->entry)) {
            foreach ($feed->entry as $entry) {
                $link = '';
                if (isset($entry->link)) {
                    foreach ($entry->link as $entryLink) {
                        $attrs = $entryLink->attributes();
                        if (isset($attrs['href'])) {
                            $link = (string) $attrs['href'];
                            break;
                        }
                    }
                    if ($link === '') {
                        $link = (string) $entry->link;
                    }
                }

                $items[] = [
                    'article_url' => $this->normalizeFeedLink($link, $baseUrl),
                    'title' => Helpers::normalizeWhitespace((string) $entry->title),
                    'snippet' => Helpers::normalizeWhitespace((string) ($entry->summary ?? $entry->content ?? '')),
                    'published_at' => (string) ($entry->published ?? $entry->updated ?? ''),
                ];
            }
        }

        return [
            'ok' => true,
            'items' => $items,
            'error' => '',
        ];
    }

    private function normalizeFeedLink(string $link, string $baseUrl): string
    {
        $link = trim($link);
        if ($link === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $link)) {
            return Helpers::stripUrlFragment($link);
        }

        $normalized = Helpers::normalizeUrl($baseUrl, $link);
        return $normalized ?: '';
    }
}
