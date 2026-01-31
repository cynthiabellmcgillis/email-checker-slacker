<?php

namespace App\Services;

use App\DTOs\AnalysisResult;

class SlackFormatter
{
    /**
     * Format a short summary message for the main thread reply.
     */
    public function formatShortSummary(string $emailName, AnalysisResult $result): array
    {
        $issueCounts = $result->getIssueCounts();
        $canShip = $result->canShip();

        // Determine verdict text and emoji
        $verdictEmoji = $canShip ? ':white_check_mark:' : ':x:';
        $verdictText = match ($result->verdict) {
            AnalysisResult::VERDICT_SHIP => 'Ready to Ship',
            AnalysisResult::VERDICT_DO_NOT_SHIP => 'Do Not Ship',
            default => 'Needs Fixes',
        };

        // Build counts text
        $countsText = "{$result->getPassCount()} passed";
        if ($issueCounts['warning'] > 0) {
            $countsText .= " | {$issueCounts['warning']} warning" . ($issueCounts['warning'] > 1 ? 's' : '');
        }
        if ($issueCounts['critical'] > 0) {
            $countsText .= " | {$issueCounts['critical']} critical";
        }

        $blocks = [];

        // Header with verdict
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "{$verdictEmoji} *{$verdictText}:* \"{$emailName}\"\n{$countsText}",
            ],
        ];

        // AI Summary (prepend notice if critical issues exist that AI didn't account for)
        if (!empty($result->aiSummary)) {
            $summaryText = $result->aiSummary;

            // If there are critical issues but AI summary sounds positive, prepend a notice
            if ($issueCounts['critical'] > 0) {
                $summaryText = "Has critical issues. " . $summaryText;
            }

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "_" . $summaryText . "_",
                ],
            ];
        }

        // Footer
        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => ':email: Email Checker | ' . now()->format('Y-m-d H:i T'),
                ],
            ],
        ];

        return $blocks;
    }

    /**
     * Format action items for a threaded reply.
     * Returns null if there are no issues to display.
     */
    public function formatActionItems(AnalysisResult $result): ?array
    {
        $issues = $result->getAllIssues();

        if (empty($issues)) {
            return null;
        }

        $blocks = [];

        // Header
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => ":wrench: *Action Items (" . count($issues) . ")*",
            ],
        ];

        // Group issues by severity
        $grouped = [
            'critical' => [],
            'warning' => [],
            'suggestion' => [],
        ];

        foreach ($issues as $issue) {
            $grouped[$issue['severity']][] = $issue;
        }

        // Format each severity group
        if (!empty($grouped['critical'])) {
            $blocks[] = $this->formatIssueGroup('CRITICAL', $grouped['critical'], ':rotating_light:');
        }

        if (!empty($grouped['warning'])) {
            $blocks[] = $this->formatIssueGroup('WARNING', $grouped['warning'], ':warning:');
        }

        if (!empty($grouped['suggestion'])) {
            $blocks[] = $this->formatIssueGroup('SUGGESTION', $grouped['suggestion'], ':bulb:');
        }

        return $blocks;
    }

    /**
     * Format a group of issues by severity.
     */
    private function formatIssueGroup(string $title, array $issues, string $emoji): array
    {
        $text = "{$emoji} *{$title}*\n";

        foreach ($issues as $issue) {
            $category = ucfirst($issue['category']);
            $text .= "• [{$category}] {$issue['problem']}";
            if (!empty($issue['fix'])) {
                $text .= " → _{$issue['fix']}_";
            }
            $text .= "\n";
        }

        return [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => $text,
            ],
        ];
    }

    /**
     * Get the verdict emoji for reactions.
     */
    public function getVerdictReaction(AnalysisResult $result): string
    {
        return match ($result->verdict) {
            AnalysisResult::VERDICT_SHIP => 'white_check_mark',
            AnalysisResult::VERDICT_DO_NOT_SHIP => 'x',
            default => 'warning',
        };
    }

    /**
     * Format a simple text summary for notifications.
     */
    public function formatSummaryText(string $emailName, AnalysisResult $result): string
    {
        $verdictText = match ($result->verdict) {
            AnalysisResult::VERDICT_SHIP => 'SHIP',
            AnalysisResult::VERDICT_DO_NOT_SHIP => 'DO NOT SHIP',
            default => 'NEEDS FIXES',
        };

        return "Email Check [{$verdictText}]: \"{$emailName}\"";
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
     * Format an analysis result into Slack blocks (full verbose format - kept for CLI/debugging).
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
