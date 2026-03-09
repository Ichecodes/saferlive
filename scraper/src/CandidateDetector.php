<?php

class CandidateDetector
{
    private array $keywordsByType;
    private array $places;
    private int $threshold;

    public function __construct(array $keywordsByType, array $places, array $appConfig)
    {
        $this->keywordsByType = $keywordsByType;
        $this->places = $places;
        $this->threshold = (int) ($appConfig['candidate_threshold'] ?? 60);
    }

    public function detect(array $rawItem): ?array
    {
        $title = mb_strtolower((string) ($rawItem['title'] ?? ''), 'UTF-8');
        $snippet = mb_strtolower((string) ($rawItem['snippet'] ?? ''), 'UTF-8');
        $content = mb_strtolower((string) ($rawItem['content_text'] ?? ''), 'UTF-8');

        $score = 0;
        $matchedKeywords = [];
        $matchedPlaces = [];
        $typeScores = [];

        foreach ($this->keywordsByType as $type => $keywords) {
            $typeScore = 0;
            foreach ($keywords as $keyword) {
                $needle = mb_strtolower($keyword, 'UTF-8');
                if (str_contains($title, $needle)) {
                    $typeScore += 14;
                    $matchedKeywords[$keyword] = true;
                }
                if (str_contains($snippet, $needle) || str_contains($content, $needle)) {
                    $typeScore += 8;
                    $matchedKeywords[$keyword] = true;
                }
            }

            if ($typeScore > 0) {
                $typeScores[$type] = $typeScore;
                $score += min(40, $typeScore);
            }
        }

        foreach ($this->places as $place) {
            $needle = mb_strtolower($place, 'UTF-8');
            if (str_contains($title, $needle)) {
                $score += 12;
                $matchedPlaces[$place] = true;
            }
            if (str_contains($content, $needle)) {
                $score += 6;
                $matchedPlaces[$place] = true;
            }
        }

        foreach (['today', 'yesterday', 'this morning', 'this evening', 'hours ago'] as $hint) {
            if (str_contains($snippet . ' ' . $content, $hint)) {
                $score += 6;
                break;
            }
        }

        $publishedAt = $rawItem['published_at'] ?? null;
        if (is_string($publishedAt) && $publishedAt !== '') {
            try {
                $published = new DateTimeImmutable($publishedAt);
                $now = new DateTimeImmutable('now');
                $days = (int) $published->diff($now)->format('%a');
                if ($days <= 2) {
                    $score += 8;
                }
            } catch (Throwable $e) {
            }
        }

        $strongSources = ['Punch', 'Vanguard', 'Premium Times', 'Nigerian Tribune', 'Daily Trust'];
        if (in_array((string) ($rawItem['source_name'] ?? ''), $strongSources, true)) {
            $score += 5;
        }

        $score = min(100, $score);
        if ($score < $this->threshold) {
            return null;
        }

        $matchedType = 'general_incident';
        if (!empty($typeScores)) {
            arsort($typeScores);
            $matchedType = (string) array_key_first($typeScores);
        }

        return [
            'raw_id' => (string) ($rawItem['id'] ?? ''),
            'article_url' => (string) ($rawItem['article_url'] ?? ''),
            'title' => (string) ($rawItem['title'] ?? ''),
            'source_name' => (string) ($rawItem['source_name'] ?? ''),
            'incident_score' => $score,
            'incident_keywords_found' => array_values(array_keys($matchedKeywords)),
            'place_keywords_found' => array_values(array_keys($matchedPlaces)),
            'matched_incident_type' => $matchedType,
            'reason' => 'incident keywords + Nigeria place match + recency hint',
            'created_at' => Helpers::nowIso(),
        ];
    }
}

