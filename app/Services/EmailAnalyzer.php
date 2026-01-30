<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use App\DTOs\AnalysisResult;
use App\DTOs\EmailContent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class EmailAnalyzer
{
    private LinkChecker $linkChecker;
    private string $guidelines;
    private AnthropicClient $anthropic;

    public function __construct(LinkChecker $linkChecker)
    {
        $this->linkChecker = $linkChecker;
        $this->guidelines = $this->loadGuidelines();
        $this->anthropic = new AnthropicClient(config('email-checker.anthropic_api_key'));
    }

    public function analyze(EmailContent $email): AnalysisResult
    {
        $checks = $this->runBasicChecks($email);
        $linkResults = $this->linkChecker->checkLinks($email->links);
        $aiResponse = $this->runAiAnalysis($email);
        $parsedAi = $this->parseAiResponse($aiResponse);

        // Determine final verdict considering link issues
        $verdict = $this->determineFinalVerdict(
            $parsedAi['verdict'],
            $linkResults['broken'],
            $linkResults['forbidden']
        );

        return new AnalysisResult(
            checks: $checks,
            brokenLinks: $linkResults['broken'],
            utmIssues: $linkResults['utm_issues'],
            forbiddenLinks: $linkResults['forbidden'],
            aiAnalysis: $aiResponse,
            verdict: $verdict,
            confidence: $parsedAi['confidence'],
            aiIssues: $parsedAi['issues'],
            aiSummary: $parsedAi['summary'],
        );
    }

    private function runBasicChecks(EmailContent $email): array
    {
        $checks = [];

        // Subject line length check (under 65 chars per guidelines)
        $subjectLength = $email->getSubjectLength();
        $checks['subject_length'] = [
            'name' => 'Subject Line Length',
            'status' => $subjectLength <= 65 ? AnalysisResult::STATUS_PASS : AnalysisResult::STATUS_WARN,
            'message' => "{$subjectLength} characters" . ($subjectLength > 65 ? ' (should be < 65)' : ''),
        ];

        // Preview text length check (utilize full 90 chars per guidelines)
        $previewLength = $email->getPreviewTextLength();
        if ($previewLength < 90) {
            $checks['preview_length'] = [
                'name' => 'Preview Text Length',
                'status' => AnalysisResult::STATUS_WARN,
                'message' => "{$previewLength} characters (should utilize full 90 chars)",
            ];
        } else {
            $checks['preview_length'] = [
                'name' => 'Preview Text Length',
                'status' => AnalysisResult::STATUS_PASS,
                'message' => "{$previewLength} characters",
            ];
        }

        return $checks;
    }

    private function runAiAnalysis(EmailContent $email): string
    {
        $prompt = $this->buildPrompt($email);

        try {
            $response = $this->anthropic->messages->create([
                'model' => 'claude-sonnet-4',
                'max_tokens' => 2048,
                'system' => 'You are an email marketing expert analyzing emails for brand compliance and best practices. Always respond with valid JSON only.',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            return $response->content[0]->text ?? '';
        } catch (\Exception $e) {
            Log::error('AI analysis failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    private function parseAiResponse(string $aiResponse): array
    {
        $default = [
            'verdict' => AnalysisResult::VERDICT_NEEDS_FIXES,
            'confidence' => 3,
            'issues' => [],
            'summary' => 'AI analysis unavailable',
        ];

        if (empty($aiResponse)) {
            return $default;
        }

        try {
            // Clean up the response - remove any markdown code blocks if present
            $cleanResponse = $aiResponse;
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $aiResponse, $matches)) {
                $cleanResponse = $matches[1];
            }

            $parsed = json_decode($cleanResponse, true, 512, JSON_THROW_ON_ERROR);

            // Validate and normalize the response
            $verdict = $parsed['verdict'] ?? AnalysisResult::VERDICT_NEEDS_FIXES;
            if (!in_array($verdict, [
                AnalysisResult::VERDICT_SHIP,
                AnalysisResult::VERDICT_NEEDS_FIXES,
                AnalysisResult::VERDICT_DO_NOT_SHIP
            ])) {
                $verdict = AnalysisResult::VERDICT_NEEDS_FIXES;
            }

            $confidence = (int) ($parsed['confidence'] ?? 3);
            $confidence = max(1, min(5, $confidence));

            $issues = $this->normalizeIssues($parsed['issues'] ?? []);
            $summary = $parsed['summary'] ?? 'Analysis complete';

            return [
                'verdict' => $verdict,
                'confidence' => $confidence,
                'issues' => $issues,
                'summary' => $summary,
            ];
        } catch (\JsonException $e) {
            Log::warning('Failed to parse AI response as JSON', [
                'error' => $e->getMessage(),
                'response' => substr($aiResponse, 0, 500),
            ]);
            return $default;
        }
    }

    private function normalizeIssues(array $issues): array
    {
        $normalized = [];
        $validSeverities = ['critical', 'warning', 'suggestion'];
        $validCategories = ['subject', 'preview', 'body', 'cta', 'tone', 'links', 'ps'];

        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }

            $severity = $issue['severity'] ?? 'suggestion';
            if (!in_array($severity, $validSeverities)) {
                $severity = 'suggestion';
            }

            $category = $issue['category'] ?? 'body';
            if (!in_array($category, $validCategories)) {
                $category = 'body';
            }

            $normalized[] = [
                'severity' => $severity,
                'category' => $category,
                'problem' => $issue['problem'] ?? 'Unknown issue',
                'fix' => $issue['fix'] ?? 'Review and fix as needed',
            ];
        }

        return $normalized;
    }

    private function determineFinalVerdict(string $aiVerdict, array $brokenLinks, array $forbiddenLinks): string
    {
        // If there are broken or forbidden links, downgrade verdict if needed
        if (!empty($brokenLinks) || !empty($forbiddenLinks)) {
            if ($aiVerdict === AnalysisResult::VERDICT_SHIP) {
                return AnalysisResult::VERDICT_NEEDS_FIXES;
            }
        }

        return $aiVerdict;
    }

    private function buildPrompt(EmailContent $email): string
    {
        $promptTemplate = File::get(resource_path('prompts/email-analysis.md'));

        return str_replace(
            ['{{SUBJECT}}', '{{PREVIEW}}', '{{BODY}}', '{{GUIDELINES}}'],
            [$email->subject, $email->previewText, $email->bodyText, $this->guidelines],
            $promptTemplate
        );
    }

    private function loadGuidelines(): string
    {
        $path = config('email-checker.guidelines_path');

        if (File::exists($path)) {
            return File::get($path);
        }

        return 'Guidelines file not found.';
    }
}
