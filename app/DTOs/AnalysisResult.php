<?php

namespace App\DTOs;

class AnalysisResult
{
    public const STATUS_PASS = 'pass';
    public const STATUS_WARN = 'warn';
    public const STATUS_FAIL = 'fail';

    public const VERDICT_SHIP = 'ship';
    public const VERDICT_NEEDS_FIXES = 'needs_fixes';
    public const VERDICT_DO_NOT_SHIP = 'do_not_ship';

    public function __construct(
        public readonly array $checks,
        public readonly array $brokenLinks,
        public readonly array $utmIssues,
        public readonly array $forbiddenLinks = [],
        public readonly ?string $aiAnalysis = null,
        public readonly string $verdict = self::VERDICT_NEEDS_FIXES,
        public readonly array $aiIssues = [],
        public readonly string $aiSummary = '',
    ) {}

    public function canShip(): bool
    {
        return $this->verdict === self::VERDICT_SHIP;
    }

    public function getPassCount(): int
    {
        return count(array_filter($this->checks, fn($c) => $c['status'] === self::STATUS_PASS));
    }

    public function getWarnCount(): int
    {
        return count(array_filter($this->checks, fn($c) => $c['status'] === self::STATUS_WARN));
    }

    public function getFailCount(): int
    {
        return count(array_filter($this->checks, fn($c) => $c['status'] === self::STATUS_FAIL));
    }

    /**
     * Get all issues combined (AI issues + link/UTM issues) sorted by severity.
     */
    public function getAllIssues(): array
    {
        $issues = $this->aiIssues;

        // Add broken links as critical issues
        foreach ($this->brokenLinks as $link) {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'links',
                'problem' => "Broken link: {$link['url']}",
                'fix' => "Fix or remove the broken link ({$link['error']})",
            ];
        }

        // Add forbidden links as critical issues
        foreach ($this->forbiddenLinks as $link) {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'links',
                'problem' => "Forbidden internal link: {$link['url']}",
                'fix' => $link['reason'],
            ];
        }

        // Add UTM issues as warnings
        foreach ($this->utmIssues as $utmIssue) {
            foreach ($utmIssue['issues'] as $problem) {
                $issues[] = [
                    'severity' => 'warning',
                    'category' => 'links',
                    'problem' => "UTM issue on {$utmIssue['url']}",
                    'fix' => $problem,
                ];
            }
        }

        // Sort by severity: critical first, then warning, then suggestion
        $severityOrder = ['critical' => 0, 'warning' => 1, 'suggestion' => 2];
        usort($issues, fn($a, $b) => ($severityOrder[$a['severity']] ?? 3) <=> ($severityOrder[$b['severity']] ?? 3));

        return $issues;
    }

    /**
     * Get issue counts by severity.
     */
    public function getIssueCounts(): array
    {
        $issues = $this->getAllIssues();

        return [
            'critical' => count(array_filter($issues, fn($i) => $i['severity'] === 'critical')),
            'warning' => count(array_filter($issues, fn($i) => $i['severity'] === 'warning')),
            'suggestion' => count(array_filter($issues, fn($i) => $i['severity'] === 'suggestion')),
        ];
    }

    public function toArray(): array
    {
        return [
            'checks' => $this->checks,
            'broken_links' => $this->brokenLinks,
            'utm_issues' => $this->utmIssues,
            'forbidden_links' => $this->forbiddenLinks,
            'ai_analysis' => $this->aiAnalysis,
            'verdict' => $this->verdict,
            'ai_issues' => $this->aiIssues,
            'ai_summary' => $this->aiSummary,
            'summary' => [
                'passed' => $this->getPassCount(),
                'warnings' => $this->getWarnCount(),
                'failed' => $this->getFailCount(),
            ],
        ];
    }
}
