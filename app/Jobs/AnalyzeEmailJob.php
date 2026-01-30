<?php

namespace App\Jobs;

use App\Services\EmailAnalyzer;
use App\Services\HubSpotService;
use App\Services\SlackFormatter;
use App\Services\SlackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $emailId,
        public string $channel,
        public string $threadTs,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        HubSpotService $hubSpot,
        EmailAnalyzer $analyzer,
        SlackService $slack,
        SlackFormatter $formatter,
    ): void {
        Log::info("Starting email analysis job", [
            'email_id' => $this->emailId,
            'channel' => $this->channel,
        ]);

        try {
            // Check HubSpot configuration
            if (!$hubSpot->isConfigured()) {
                $this->sendError($slack, $formatter, 'HubSpot is not configured. Please set HUBSPOT_ACCESS_TOKEN.');
                return;
            }

            // Fetch email from HubSpot
            $email = $hubSpot->getEmail($this->emailId);

            // Run analysis
            $result = $analyzer->analyze($email);

            // Remove hourglass reaction
            $slack->removeReaction($this->channel, $this->threadTs, 'hourglass_flowing_sand');

            // Add verdict reaction
            $verdictReaction = $formatter->getVerdictReaction($result);
            $slack->addReaction($this->channel, $this->threadTs, $verdictReaction);

            // Post short summary as thread reply
            $summaryBlocks = $formatter->formatShortSummary($email->name, $result);
            $summaryText = $formatter->formatSummaryText($email->name, $result);
            $slack->sendThreadReply($this->channel, $this->threadTs, $summaryText, $summaryBlocks);

            // Post action items as second thread reply if there are issues
            $actionItemsBlocks = $formatter->formatActionItems($result);
            if ($actionItemsBlocks !== null) {
                $slack->sendThreadReply(
                    $this->channel,
                    $this->threadTs,
                    'Action items for this email',
                    $actionItemsBlocks
                );
            }

            Log::info("Email analysis completed", [
                'email_id' => $this->emailId,
                'email_name' => $email->name,
                'verdict' => $result->verdict,
                'can_ship' => $result->canShip(),
                'issue_count' => count($result->getAllIssues()),
            ]);

        } catch (\Exception $e) {
            Log::error("Email analysis failed", [
                'email_id' => $this->emailId,
                'error' => $e->getMessage(),
            ]);

            $this->sendError($slack, $formatter, "Failed to analyze email: {$e->getMessage()}");

            // Update reaction to show failure
            $slack->removeReaction($this->channel, $this->threadTs, 'hourglass_flowing_sand');
            $slack->addReaction($this->channel, $this->threadTs, 'x');
        }
    }

    /**
     * Send an error message to Slack.
     */
    private function sendError(SlackService $slack, SlackFormatter $formatter, string $message): void
    {
        $blocks = $formatter->formatError($message);
        $slack->sendThreadReply($this->channel, $this->threadTs, "Error: {$message}", $blocks);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("AnalyzeEmailJob failed permanently", [
            'email_id' => $this->emailId,
            'channel' => $this->channel,
            'error' => $exception->getMessage(),
        ]);
    }
}
