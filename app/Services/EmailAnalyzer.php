<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use App\DTOs\AnalysisResult;
use App\DTOs\EmailContent;
use Illuminate\Support\Facades\File;

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
        $aiAnalysis = $this->runAiAnalysis($email);

        return new AnalysisResult(
            checks: array_merge($checks, $this->parseAiChecks($aiAnalysis)),
            brokenLinks: $linkResults['broken'],
            utmIssues: $linkResults['utm_issues'],
            forbiddenLinks: $linkResults['forbidden'],
            aiAnalysis: $aiAnalysis,
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

        // Check for "Dear" greeting
        $hasDear = stripos($email->bodyText, 'dear ') !== false ||
                   stripos($email->bodyText, 'dear,') !== false;
        $checks['no_dear'] = [
            'name' => 'No "Dear" Greeting',
            'status' => $hasDear ? AnalysisResult::STATUS_FAIL : AnalysisResult::STATUS_PASS,
            'message' => $hasDear ? 'Found "Dear" greeting (should use "Hi" instead)' : 'No "Dear" greeting found',
        ];

        // Check for spam triggers
        $spamTriggers = $this->checkSpamTriggers($email->bodyText);
        $checks['spam_triggers'] = [
            'name' => 'Spam Triggers',
            'status' => empty($spamTriggers) ? AnalysisResult::STATUS_PASS : AnalysisResult::STATUS_WARN,
            'message' => empty($spamTriggers) ? 'No spam triggers detected' : 'Found: ' . implode(', ', $spamTriggers),
        ];

        return $checks;
    }

    private function checkSpamTriggers(string $text): array
    {
        $triggers = [];

        // Check for "FREE" in all caps
        if (preg_match('/\bFREE\b/', $text)) {
            $triggers[] = '"FREE" in all caps';
        }

        // Check for excessive dollar signs
        if (preg_match_all('/\$/', $text, $matches) && count($matches[0]) > 3) {
            $triggers[] = 'Multiple $ signs';
        }

        // Check for all caps words (more than 3 consecutive caps words)
        if (preg_match('/\b[A-Z]{3,}\s+[A-Z]{3,}\s+[A-Z]{3,}\b/', $text)) {
            $triggers[] = 'Excessive ALL CAPS';
        }

        return $triggers;
    }

    private function runAiAnalysis(EmailContent $email): string
    {
        $prompt = $this->buildPrompt($email);

        try {
            $response = $this->anthropic->messages->create([
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 2048,
                'system' => 'You are an email marketing expert analyzing emails for brand compliance and best practices.',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            return $response->content[0]->text ?? '';
        } catch (\Exception $e) {
            return 'AI analysis unavailable: ' . $e->getMessage();
        }
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

    private function parseAiChecks(string $aiAnalysis): array
    {
        $checks = [];

        // Parse PS statement check from AI response
        if (stripos($aiAnalysis, 'PS statement') !== false) {
            $hasPs = stripos($aiAnalysis, 'PS statement: Present') !== false ||
                     stripos($aiAnalysis, 'PS: Found') !== false ||
                     stripos($aiAnalysis, 'includes a PS') !== false;

            $checks['ps_statement'] = [
                'name' => 'PS Statement',
                'status' => $hasPs ? AnalysisResult::STATUS_PASS : AnalysisResult::STATUS_FAIL,
                'message' => $hasPs ? 'PS statement found' : 'Missing PS statement (recommended at end of email)',
            ];
        }

        // Parse CTA check from AI response
        if (preg_match('/CTA[s]?:?\s*(\d+)/i', $aiAnalysis, $matches)) {
            $ctaCount = (int) $matches[1];
            $checks['cta_count'] = [
                'name' => 'CTA Count',
                'status' => $ctaCount >= 1 && $ctaCount <= 2 ? AnalysisResult::STATUS_PASS : AnalysisResult::STATUS_WARN,
                'message' => "{$ctaCount} CTA(s)" . ($ctaCount > 2 ? ' (should be 1-2)' : ''),
            ];
        }

        return $checks;
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
