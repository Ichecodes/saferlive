<?php

class LinkExtractor
{
    public function extract(string $html, string $baseUrl, array $source): array
    {
        $links = [];

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = @$dom->loadHTML($html);
        libxml_clear_errors();

        if (!$loaded) {
            return [];
        }

        $nodes = $dom->getElementsByTagName('a');
        foreach ($nodes as $node) {
            $href = trim((string) $node->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $normalized = Helpers::normalizeUrl($baseUrl, $href);
            if (!$normalized) {
                continue;
            }

            if (!$this->isAllowed($normalized, $source)) {
                continue;
            }

            $links[$normalized] = true;
        }

        return array_keys($links);
    }

    private function isAllowed(string $url, array $source): bool
    {
        $domain = (string) ($source['domain'] ?? '');
        if ($domain === '' || !Helpers::sameDomain($url, $domain)) {
            return false;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if ($path === '') {
            return false;
        }

        foreach ((array) ($source['blocked_path_hints'] ?? []) as $blocked) {
            if ($blocked !== '' && str_contains($path, strtolower($blocked))) {
                return false;
            }
        }

        foreach (['/login', '/signin', '/gallery', '/photo', '/video', '/wp-json'] as $blockedCommon) {
            if (str_contains($path, $blockedCommon)) {
                return false;
            }
        }

        $allowedHints = (array) ($source['allowed_path_hints'] ?? []);
        if (empty($allowedHints)) {
            return true;
        }

        foreach ($allowedHints as $hint) {
            if ($hint !== '' && str_contains($path, strtolower($hint))) {
                return true;
            }
        }

        return false;
    }
}

