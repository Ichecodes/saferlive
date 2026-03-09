<?php

class ContentExtractor
{
    public function extract(string $html, string $url): array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = @$dom->loadHTML($html);
        libxml_clear_errors();

        if (!$loaded) {
            return [
                'title' => '',
                'snippet' => '',
                'content_text' => '',
                'published_at' => null,
                'language' => 'unknown',
                'source_domain' => Helpers::domainFromUrl($url),
            ];
        }

        $xpath = new DOMXPath($dom);

        $title = $this->firstMetaContent($xpath, [
            "//meta[@property='og:title']/@content",
            "//meta[@name='twitter:title']/@content",
        ]);
        if ($title === '') {
            $titleNode = $dom->getElementsByTagName('title')->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : '';
        }

        $snippet = $this->firstMetaContent($xpath, [
            "//meta[@name='description']/@content",
            "//meta[@property='og:description']/@content",
            "//meta[@name='twitter:description']/@content",
        ]);

        $publishedRaw = $this->firstMetaContent($xpath, [
            "//meta[@property='article:published_time']/@content",
            "//meta[@name='article:published_time']/@content",
            "//meta[@itemprop='datePublished']/@content",
            "//time/@datetime",
        ]);
        $publishedAt = Helpers::safeDate($publishedRaw);

        $this->removeNoise($xpath);

        $bodyNode = $dom->getElementsByTagName('body')->item(0);
        $content = $bodyNode ? Helpers::normalizeWhitespace($bodyNode->textContent) : '';

        return [
            'title' => $title,
            'snippet' => $snippet,
            'content_text' => $content,
            'published_at' => $publishedAt,
            'language' => Helpers::looksLikeEnglish($title . ' ' . $snippet . ' ' . mb_substr($content, 0, 600, 'UTF-8')) ? 'en' : 'unknown',
            'source_domain' => Helpers::domainFromUrl($url),
        ];
    }

    private function firstMetaContent(DOMXPath $xpath, array $queries): string
    {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $text = trim((string) $nodes->item(0)->nodeValue);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private function removeNoise(DOMXPath $xpath): void
    {
        $queries = [
            '//script',
            '//style',
            '//noscript',
            '//nav',
            '//header',
            '//footer',
            '//aside',
            '//form',
            '//svg',
            "//*[contains(@class, 'menu')]",
            "//*[contains(@class, 'nav')]",
            "//*[contains(@class, 'breadcrumb')]",
            "//*[contains(@class, 'related')]",
            "//*[contains(@class, 'comment')]",
            "//*[contains(@id, 'comment')]",
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes || $nodes->length === 0) {
                continue;
            }

            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                if ($node && $node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }
}

