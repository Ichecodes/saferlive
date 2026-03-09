<?php

class RawStore
{
    private string $dataDir;
    private string $rawPath;
    private string $candidatePath;
    private string $seenHashesPath;
    private string $seenUrlsPath;
    private string $runLogPath;

    public function __construct(string $dataDir)
    {
        $this->dataDir = rtrim($dataDir, DIRECTORY_SEPARATOR);
        $this->rawPath = $this->dataDir . DIRECTORY_SEPARATOR . 'raw_items.txt';
        $this->candidatePath = $this->dataDir . DIRECTORY_SEPARATOR . 'candidates.txt';
        $this->seenHashesPath = $this->dataDir . DIRECTORY_SEPARATOR . 'seen_hashes.txt';
        $this->seenUrlsPath = $this->dataDir . DIRECTORY_SEPARATOR . 'seen_urls.txt';
        $this->runLogPath = $this->dataDir . DIRECTORY_SEPARATOR . 'run_log.txt';

        Helpers::ensureDirectory($this->dataDir);
        foreach ([$this->rawPath, $this->candidatePath, $this->seenHashesPath, $this->seenUrlsPath, $this->runLogPath] as $path) {
            Helpers::ensureFile($path);
        }
    }

    public function appendRawItem(array $item): bool
    {
        $ok = Helpers::appendJsonLine($this->rawPath, $item);
        if ($ok) {
            Helpers::appendLine($this->seenHashesPath, (string) ($item['exact_hash'] ?? ''));
            Helpers::appendLine($this->seenUrlsPath, (string) ($item['article_url'] ?? ''));
        }

        return $ok;
    }

    public function appendCandidate(array $candidate): bool
    {
        return Helpers::appendJsonLine($this->candidatePath, $candidate);
    }

    public function log(string $message): void
    {
        Helpers::appendLine($this->runLogPath, '[' . Helpers::nowIso() . '] ' . $message);
    }

    public function hasSeenUrl(string $url): bool
    {
        $urls = Helpers::readLines($this->seenUrlsPath);
        $set = array_flip($urls);
        return isset($set[$url]);
    }

    public function hasSeenHash(string $hash): bool
    {
        $hashes = Helpers::readLines($this->seenHashesPath);
        $set = array_flip($hashes);
        return isset($set[$hash]);
    }

    public function countRaw(): int
    {
        return Helpers::countLines($this->rawPath);
    }

    public function countCandidates(): int
    {
        return Helpers::countLines($this->candidatePath);
    }

    public function latestRaw(int $limit = 20): array
    {
        return array_reverse(Helpers::readJsonLines($this->rawPath, $limit));
    }

    public function latestCandidates(int $limit = 20): array
    {
        return array_reverse(Helpers::readJsonLines($this->candidatePath, $limit));
    }

    public function latestLogLines(int $limit = 80): array
    {
        return array_reverse(Helpers::readLines($this->runLogPath, $limit));
    }

    public function latestRunTime(): ?string
    {
        $lines = Helpers::readLines($this->runLogPath, 400);
        $lines = array_reverse($lines);
        foreach ($lines as $line) {
            if (preg_match('/\[(.*?)\]/', $line, $matches)) {
                return Helpers::safeDate($matches[1]);
            }
        }

        return null;
    }

    public function recentFingerprints(int $limit = 300): array
    {
        $items = Helpers::readJsonLines($this->rawPath, $limit);
        $fps = [];
        foreach ($items as $item) {
            $fp = (string) ($item['text_fingerprint'] ?? '');
            if ($fp !== '') {
                $fps[] = $fp;
            }
        }
        return $fps;
    }
}

