<?php

namespace App\Services;

use App\DTOs\AnalysisResult;

class SlackFormatter
{
    /**
     * Format an analysis result into Slack blocks.
     */
    public function formatAnalysisResult(string $emailName, AnalysisResult $result): array
    {
        $blocks = [];

        // Header
        $blocks[] = [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => "Email Check: {$emailName}",
                'emoji' => true,
            ],
        ];

        // Summary
        $passCount = $result->getPassCount();
        $warnCount = $result->getWarnCount();
        $failCount = $result->getFailCount();

        $summaryEmoji = $failCount > 0 ? ':x:' : ($warnCount > 0 ? ':warning:' : ':white_check_mark:');
        $summaryText = "{$summaryEmoji} *{$passCount} passed* | *{$warnCount} warnings* | *{$failCount} failed*";

        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => $summaryText,
            ],
        ];

        $blocks[] = ['type' => 'divider'];

        // Checks
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => '*Checks*',
            ],
        ];

        $checksText = '';
        foreach ($result->checks as $check) {
            $emoji = $this->getStatusEmoji($check['status']);
            $checksText .= "{$emoji} *{$check['name']}*: {$check['message']}\n";
        }

        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => $checksText,
            ],
        ];

        // Broken Links
        if (!empty($result->brokenLinks)) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ":link: *Broken Links (" . count($result->brokenLinks) . ")*",
                ],
            ];

            $linksText = '';
            foreach (array_slice($result->brokenLinks, 0, 5) as $link) {
                $linksText .= "• `{$link['url']}`\n  Error: {$link['error']}\n";
            }
            if (count($result->brokenLinks) > 5) {
                $linksText .= "_...and " . (count($result->brokenLinks) - 5) . " more_\n";
            }

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $linksText,
                ],
            ];
        }

        // Forbidden Links
        if (!empty($result->forbiddenLinks)) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ":no_entry: *Forbidden Internal Links (" . count($result->forbiddenLinks) . ")*",
                ],
            ];

            $forbiddenText = '';
            foreach ($result->forbiddenLinks as $link) {
                $forbiddenText .= "• `{$link['url']}`\n  Reason: {$link['reason']}\n";
            }

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $forbiddenText,
                ],
            ];
        }

        // UTM Issues
        if (!empty($result->utmIssues)) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ":mag: *UTM Issues (" . count($result->utmIssues) . ")*",
                ],
            ];

            $utmText = '';
            foreach (array_slice($result->utmIssues, 0, 3) as $issue) {
                $utmText .= "• `{$issue['url']}`\n";
                foreach ($issue['issues'] as $problem) {
                    $utmText .= "  - {$problem}\n";
                }
            }
            if (count($result->utmIssues) > 3) {
                $utmText .= "_...and " . (count($result->utmIssues) - 3) . " more_\n";
            }

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $utmText,
                ],
            ];
        }

        // AI Analysis (truncated for Slack)
        if ($result->aiAnalysis) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ':robot_face: *AI Analysis*',
                ],
            ];

            // Truncate AI analysis if too long for Slack (max 3000 chars per block)
            $aiText = $result->aiAnalysis;
            if (strlen($aiText) > 2900) {
                $aiText = substr($aiText, 0, 2900) . "\n\n_...truncated_";
            }

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $aiText,
                ],
            ];
        }

        // Footer
        $blocks[] = ['type' => 'divider'];
        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => ':email: Email Checker | Analysis completed at ' . now()->format('Y-m-d H:i:s T'),
                ],
            ],
        ];

        return $blocks;
    }

    /**
     * Format a simple text summary for notifications.
     */
    public function formatSummaryText(string $emailName, AnalysisResult $result): string
    {
        $passCount = $result->getPassCount();
        $warnCount = $result->getWarnCount();
        $failCount = $result->getFailCount();

        $status = $failCount > 0 ? 'FAILED' : ($warnCount > 0 ? 'WARNINGS' : 'PASSED');

        return "Email Check [{$status}]: \"{$emailName}\" - {$passCount} passed, {$warnCount} warnings, {$failCount} failed";
    }

    /**
     * Format an error message.
     */
    public function formatError(string $message): array
    {
        return [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ":x: *Error*\n{$message}",
                ],
            ],
        ];
    }

    /**
     * Format a processing message.
     */
    public function formatProcessingMessage(string $emailId): array
    {
        return [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ":hourglass_flowing_sand: Analyzing email `{$emailId}`... This may take a moment.",
                ],
            ],
        ];
    }

    /**
     * Get emoji for status.
     */
    private function getStatusEmoji(string $status): string
    {
        return match ($status) {
            AnalysisResult::STATUS_PASS => ':white_check_mark:',
            AnalysisResult::STATUS_WARN => ':warning:',
            AnalysisResult::STATUS_FAIL => ':x:',
            default => ':grey_question:',
        };
    }
}
