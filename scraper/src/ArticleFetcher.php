<?php

class ArticleFetcher
{
    private HttpClient $client;
    private ContentExtractor $extractor;

    public function __construct(HttpClient $client, ContentExtractor $extractor)
    {
        $this->client = $client;
        $this->extractor = $extractor;
    }

    public function fetch(string $url): array
    {
        $response = $this->client->get($url);
        if (!$response['ok']) {
            return [
                'ok' => false,
                'error' => (string) ($response['error'] ?? 'Failed to fetch article'),
            ];
        }

        $data = $this->extractor->extract((string) $response['body'], (string) $response['final_url']);

        return [
            'ok' => true,
            'error' => '',
            'final_url' => (string) $response['final_url'],
            'title' => (string) ($data['title'] ?? ''),
            'snippet' => (string) ($data['snippet'] ?? ''),
            'content_text' => (string) ($data['content_text'] ?? ''),
            'published_at' => $data['published_at'] ?? null,
            'language' => (string) ($data['language'] ?? 'unknown'),
            'source_domain' => (string) ($data['source_domain'] ?? Helpers::domainFromUrl($url)),
        ];
    }
}

