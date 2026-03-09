<?php

class HttpClient
{
    private string $userAgent;
    private int $timeout;

    public function __construct(array $appConfig)
    {
        $this->userAgent = (string) ($appConfig['user_agent'] ?? 'IncidentsMini/1.0');
        $this->timeout = max(3, (int) ($appConfig['request_timeout_seconds'] ?? 10));
    }

    public function get(string $url): array
    {
        $ch = curl_init($url);
        if (!$ch) {
            return [
                'ok' => false,
                'status' => 0,
                'content_type' => '',
                'body' => '',
                'final_url' => $url,
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
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: no-cache',
            ],
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($body === false) {
            return [
                'ok' => false,
                'status' => $status,
                'content_type' => $contentType,
                'body' => '',
                'final_url' => $finalUrl ?: $url,
                'error' => $error ?: 'Unknown HTTP error',
            ];
        }

        $isHtml = str_contains(strtolower($contentType), 'text/html') || str_contains(strtolower($contentType), 'application/xhtml+xml');
        $ok = $status >= 200 && $status < 400 && $isHtml;

        return [
            'ok' => $ok,
            'status' => $status,
            'content_type' => $contentType,
            'body' => (string) $body,
            'final_url' => $finalUrl ?: $url,
            'error' => $ok ? '' : ($isHtml ? ('HTTP ' . $status) : 'Non-HTML response'),
        ];
    }
}

