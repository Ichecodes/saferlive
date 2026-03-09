<?php

class Helpers
{
    public static function nowIso(): string
    {
        return (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);
    }

    public static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
    }

    public static function ensureFile(string $path): void
    {
        $dir = dirname($path);
        self::ensureDirectory($dir);
        if (!is_file($path)) {
            @file_put_contents($path, '');
        }
    }

    public static function appendLine(string $path, string $line): bool
    {
        self::ensureFile($path);
        $fp = @fopen($path, 'ab');
        if (!$fp) {
            return false;
        }

        $ok = false;
        if (@flock($fp, LOCK_EX)) {
            $ok = @fwrite($fp, $line . PHP_EOL) !== false;
            @fflush($fp);
            @flock($fp, LOCK_UN);
        }
        @fclose($fp);

        return $ok;
    }

    public static function appendJsonLine(string $path, array $payload): bool
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return self::appendLine($path, $json);
    }

    public static function readJsonLines(string $path, int $limit = 0): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        if ($limit > 0 && count($lines) > $limit) {
            $lines = array_slice($lines, -$limit);
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

    public static function readLines(string $path, int $limit = 0): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $lines = array_values(array_filter($lines, static fn ($line) => trim((string) $line) !== ''));

        if ($limit > 0 && count($lines) > $limit) {
            $lines = array_slice($lines, -$limit);
        }

        return $lines;
    }

    public static function countLines(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }

        $count = 0;
        $fp = @fopen($path, 'rb');
        if (!$fp) {
            return 0;
        }

        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line !== false && trim($line) !== '') {
                $count++;
            }
        }

        @fclose($fp);
        return $count;
    }

    public static function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    public static function normalizeTextForSimilarity(string $text, int $maxLen = 800): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = self::normalizeWhitespace($text);
        return mb_substr($text, 0, $maxLen, 'UTF-8');
    }

    public static function domainFromUrl(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        return strtolower(preg_replace('/^www\./', '', $host) ?? $host);
    }

    public static function sameDomain(string $url, string $domain): bool
    {
        $host = self::domainFromUrl($url);
        $domain = strtolower($domain);
        return $host === $domain || str_ends_with($host, '.' . $domain);
    }

    public static function normalizeUrl(string $baseUrl, string $href): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return self::stripUrlFragment($href);
        }

        $base = parse_url($baseUrl);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        $scheme = $base['scheme'];
        $host = $base['host'];
        $port = isset($base['port']) ? ':' . $base['port'] : '';

        if (str_starts_with($href, '//')) {
            return self::stripUrlFragment($scheme . ':' . $href);
        }

        $path = $base['path'] ?? '/';
        $path = preg_replace('#/[^/]*$#', '/', $path) ?? '/';

        if (str_starts_with($href, '/')) {
            $abs = $scheme . '://' . $host . $port . $href;
            return self::stripUrlFragment($abs);
        }

        $absPath = self::resolvePath($path . $href);
        $abs = $scheme . '://' . $host . $port . $absPath;
        return self::stripUrlFragment($abs);
    }

    public static function stripUrlFragment(string $url): string
    {
        return preg_replace('/#.*$/', '', trim($url)) ?? trim($url);
    }

    public static function resolvePath(string $path): string
    {
        $parts = explode('/', $path);
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($stack);
                continue;
            }
            $stack[] = $part;
        }
        return '/' . implode('/', $stack);
    }

    public static function looksLikeEnglish(string $text): bool
    {
        $sample = mb_strtolower(mb_substr(self::normalizeWhitespace($text), 0, 500, 'UTF-8'), 'UTF-8');
        if ($sample === '') {
            return false;
        }

        $hits = 0;
        foreach ([' the ', ' and ', ' in ', ' to ', ' of ', ' for ', ' with '] as $token) {
            if (str_contains(' ' . $sample . ' ', $token)) {
                $hits++;
            }
        }

        return $hits >= 2;
    }

    public static function safeDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

