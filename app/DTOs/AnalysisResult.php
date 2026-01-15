<?php

namespace App\DTOs;

class AnalysisResult
{
    public const STATUS_PASS = 'pass';
    public const STATUS_WARN = 'warn';
    public const STATUS_FAIL = 'fail';

    public function __construct(
        public readonly array $checks,
        public readonly array $brokenLinks,
        public readonly array $utmIssues,
        public readonly array $forbiddenLinks = [],
        public readonly ?string $aiAnalysis = null,
    ) {}

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

    public function toArray(): array
    {
        return [
            'checks' => $this->checks,
            'broken_links' => $this->brokenLinks,
            'utm_issues' => $this->utmIssues,
            'forbidden_links' => $this->forbiddenLinks,
            'ai_analysis' => $this->aiAnalysis,
            'summary' => [
                'passed' => $this->getPassCount(),
                'warnings' => $this->getWarnCount(),
                'failed' => $this->getFailCount(),
            ],
        ];
    }
}
