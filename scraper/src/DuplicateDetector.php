<?php

class DuplicateDetector
{
    private RawStore $store;
    private float $nearThreshold;
    private array $fingerprintCache;

    public function __construct(RawStore $store, array $appConfig)
    {
        $this->store = $store;
        $this->nearThreshold = (float) ($appConfig['near_duplicate_similarity_threshold'] ?? 0.88);
        $this->fingerprintCache = $store->recentFingerprints(300);
    }

    public function computeExactHash(string $url, string $title): string
    {
        return sha1(strtolower(trim($url . '|' . $title)));
    }

    public function computeFingerprint(string $content): string
    {
        return Helpers::normalizeTextForSimilarity($content, 600);
    }

    public function check(string $url, string $title, string $content): array
    {
        $exactHash = $this->computeExactHash($url, $title);
        $fingerprint = $this->computeFingerprint($content);

        if ($this->store->hasSeenUrl($url) || $this->store->hasSeenHash($exactHash)) {
            return [
                'is_exact_duplicate' => true,
                'is_near_duplicate' => false,
                'reason' => 'Seen URL or exact hash',
                'exact_hash' => $exactHash,
                'text_fingerprint' => $fingerprint,
            ];
        }

        if ($fingerprint !== '' && $this->isNearDuplicate($fingerprint)) {
            return [
                'is_exact_duplicate' => false,
                'is_near_duplicate' => true,
                'reason' => 'Near duplicate text fingerprint',
                'exact_hash' => $exactHash,
                'text_fingerprint' => $fingerprint,
            ];
        }

        return [
            'is_exact_duplicate' => false,
            'is_near_duplicate' => false,
            'reason' => '',
            'exact_hash' => $exactHash,
            'text_fingerprint' => $fingerprint,
        ];
    }

    public function registerFingerprint(string $fingerprint): void
    {
        if ($fingerprint !== '') {
            $this->fingerprintCache[] = $fingerprint;
            if (count($this->fingerprintCache) > 500) {
                $this->fingerprintCache = array_slice($this->fingerprintCache, -500);
            }
        }
    }

    private function isNearDuplicate(string $fingerprint): bool
    {
        $sample = mb_substr($fingerprint, 0, 450, 'UTF-8');
        if (mb_strlen($sample, 'UTF-8') < 120) {
            return false;
        }

        foreach ($this->fingerprintCache as $existing) {
            $existingSample = mb_substr($existing, 0, 450, 'UTF-8');
            if ($existingSample === '') {
                continue;
            }

            similar_text($sample, $existingSample, $percent);
            $ratio = $percent / 100;
            if ($ratio >= $this->nearThreshold) {
                return true;
            }
        }

        return false;
    }
}

