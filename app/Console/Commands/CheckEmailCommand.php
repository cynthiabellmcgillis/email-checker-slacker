<?php

namespace App\Console\Commands;

use App\DTOs\AnalysisResult;
use App\Services\EmailAnalyzer;
use App\Services\HubSpotService;
use Illuminate\Console\Command;

class CheckEmailCommand extends Command
{
    protected $signature = 'email:check
                            {email_id : The HubSpot email ID to check}
                            {--json : Output results as JSON}';

    protected $description = 'Check a marketing email against brand guidelines';

    public function handle(HubSpotService $hubSpot, EmailAnalyzer $analyzer): int
    {
        if (!$hubSpot->isConfigured()) {
            $this->error('HubSpot is not configured. Please set HUBSPOT_ACCESS_TOKEN in your .env file.');
            return self::FAILURE;
        }

        $emailId = $this->argument('email_id');
        $this->info("Fetching email {$emailId} from HubSpot...");

        try {
            $email = $hubSpot->getEmail($emailId);
            $this->info("Analyzing: {$email->name}");
            $this->newLine();

            $result = $analyzer->analyze($email);

            if ($this->option('json')) {
                $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            $this->displayReport($email->name, $result);

            return $result->getFailCount() > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Analysis failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function displayReport(string $emailName, AnalysisResult $result): void
    {
        $this->line(str_repeat('=', 60));
        $this->line("Email Check Report: \"{$emailName}\"");
        $this->line(str_repeat('=', 60));
        $this->newLine();

        foreach ($result->checks as $check) {
            $statusBadge = $this->formatStatus($check['status']);
            $this->line(sprintf(
                "%-40s %s",
                strtoupper($check['name']),
                $statusBadge
            ));
            $this->line("  {$check['message']}");
            $this->newLine();
        }

        // Links section
        $this->line(str_repeat('-', 60));
        $this->line('LINKS');
        $this->line(str_repeat('-', 60));

        if (empty($result->brokenLinks)) {
            $this->info('  All links are valid');
        } else {
            $this->error("  Broken links ({$this->count($result->brokenLinks)}):");
            foreach ($result->brokenLinks as $link) {
                $this->line("    - {$link['url']}");
                $this->line("      Error: {$link['error']}");
            }
        }
        $this->newLine();

        // Forbidden links section
        if (!empty($result->forbiddenLinks)) {
            $this->error("  Forbidden internal links ({$this->count($result->forbiddenLinks)}):");
            foreach ($result->forbiddenLinks as $link) {
                $this->line("    - {$link['url']}");
                $this->line("      Reason: {$link['reason']}");
            }
            $this->newLine();
        }

        // UTM Issues
        if (!empty($result->utmIssues)) {
            $this->warn("  UTM Issues ({$this->count($result->utmIssues)}):");
            foreach ($result->utmIssues as $issue) {
                $this->line("    - {$issue['url']}");
                foreach ($issue['issues'] as $problem) {
                    $this->line("      * {$problem}");
                }
            }
            $this->newLine();
        }

        // AI Analysis
        if ($result->aiAnalysis) {
            $this->line(str_repeat('-', 60));
            $this->line('AI ANALYSIS');
            $this->line(str_repeat('-', 60));
            $this->line($result->aiAnalysis);
            $this->newLine();
        }

        // Summary
        $this->line(str_repeat('=', 60));
        $this->line(sprintf(
            "OVERALL: %d passed, %d warnings, %d failed",
            $result->getPassCount(),
            $result->getWarnCount(),
            $result->getFailCount()
        ));
        $this->line(str_repeat('=', 60));
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            AnalysisResult::STATUS_PASS => '<fg=green>[PASS]</>',
            AnalysisResult::STATUS_WARN => '<fg=yellow>[WARN]</>',
            AnalysisResult::STATUS_FAIL => '<fg=red>[FAIL]</>',
            default => "[{$status}]",
        };
    }

    private function count(array $array): int
    {
        return count($array);
    }
}
