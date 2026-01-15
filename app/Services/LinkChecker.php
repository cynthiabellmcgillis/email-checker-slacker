<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LinkChecker
{
    private int $timeout;

    public function __construct()
    {
        $this->timeout = config('email-checker.link_checker.timeout', 10);
    }

    private const FORBIDDEN_DOMAINS = [
        'notion.so',
        'notion.com',
        'slack.com',
        'docs.google.com',
        'drive.google.com',
        'sheets.google.com',
        'slides.google.com',
    ];

    public function checkLinks(array $links): array
    {
        $results = [
            'total' => count($links),
            'valid' => [],
            'broken' => [],
            'utm_issues' => [],
            'forbidden' => [],
        ];

        foreach ($links as $link) {
            $forbiddenCheck = $this->checkForbiddenDomain($link);
            if (!$forbiddenCheck['valid']) {
                $results['forbidden'][] = [
                    'url' => $link,
                    'reason' => $forbiddenCheck['reason'],
                ];
            }

            $linkResult = $this->checkLink($link);

            if ($linkResult['is_valid']) {
                $results['valid'][] = $link;
            } else {
                $results['broken'][] = [
                    'url' => $link,
                    'error' => $linkResult['error'],
                ];
            }

            $utmCheck = $this->checkUtmParameters($link);
            if (!$utmCheck['valid']) {
                $results['utm_issues'][] = [
                    'url' => $link,
                    'issues' => $utmCheck['issues'],
                ];
            }
        }

        return $results;
    }

    private function checkForbiddenDomain(string $url): array
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';

        foreach (self::FORBIDDEN_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return [
                    'valid' => false,
                    'reason' => "Internal link detected ({$domain})",
                ];
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    private function checkLink(string $url): array
    {
        $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        try {
            // First try HEAD request with browser user-agent
            $response = Http::timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->withUserAgent($userAgent)
                ->head($url);

            if ($response->successful() || $response->redirect()) {
                return ['is_valid' => true, 'error' => null];
            }

            // If HEAD fails with 403/405/406, retry with GET (some servers block HEAD)
            if (in_array($response->status(), [403, 405, 406])) {
                $response = Http::timeout($this->timeout)
                    ->withOptions(['verify' => false])
                    ->withUserAgent($userAgent)
                    ->get($url);

                if ($response->successful() || $response->redirect()) {
                    return ['is_valid' => true, 'error' => null];
                }
            }

            return [
                'is_valid' => false,
                'error' => "HTTP {$response->status()}",
            ];
        } catch (\Exception $e) {
            return [
                'is_valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkUtmParameters(string $url): array
    {
        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['query'])) {
            return [
                'valid' => false,
                'issues' => ['No UTM parameters found'],
            ];
        }

        parse_str($parsedUrl['query'], $params);

        $issues = [];
        $requiredParams = config('email-checker.utm_rules.required_params', []);
        $validSources = config('email-checker.utm_rules.valid_sources', []);
        $validMediums = config('email-checker.utm_rules.valid_mediums', []);

        foreach ($requiredParams as $param) {
            if (!isset($params[$param]) || empty($params[$param])) {
                $issues[] = "Missing {$param}";
            }
        }

        if (isset($params['utm_source']) && !in_array($params['utm_source'], $validSources)) {
            $issues[] = "Invalid utm_source: {$params['utm_source']} (expected: " . implode(', ', $validSources) . ")";
        }

        if (isset($params['utm_medium']) && !in_array($params['utm_medium'], $validMediums)) {
            $issues[] = "Invalid utm_medium: {$params['utm_medium']} (expected: " . implode(', ', $validMediums) . ")";
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }
}
