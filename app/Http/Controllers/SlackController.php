<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeEmailJob;
use App\Services\SlackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SlackController extends Controller
{
    public function __construct(
        private SlackService $slack,
    ) {}

    /**
     * Handle incoming Slack events.
     */
    public function handleEvents(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Handle URL verification challenge (required for Slack app setup)
        if (($payload['type'] ?? '') === 'url_verification') {
            return response()->json(['challenge' => $payload['challenge']]);
        }

        // Handle event callbacks
        if (($payload['type'] ?? '') === 'event_callback') {
            $event = $payload['event'] ?? [];

            // Ignore bot messages to prevent loops
            if (isset($event['bot_id']) || ($event['subtype'] ?? '') === 'bot_message') {
                return response()->json(['ok' => true]);
            }

            // Handle message events
            if (($event['type'] ?? '') === 'message') {
                $this->handleMessageEvent($event);
            }

            // Handle link_shared events
            if (($event['type'] ?? '') === 'link_shared') {
                $this->handleLinkSharedEvent($event);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle slash commands (e.g., /email-check 12345).
     */
    public function handleCommand(Request $request): JsonResponse
    {
        $command = $request->input('command');
        $text = trim($request->input('text', ''));
        $channel = $request->input('channel_id');
        $responseUrl = $request->input('response_url');

        Log::info("Received slash command", [
            'command' => $command,
            'text' => $text,
            'channel' => $channel,
        ]);

        // Extract email ID from text (could be just ID or a URL)
        $emailId = $this->extractEmailId($text);

        if (!$emailId) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => "Please provide a HubSpot email ID or URL.\nUsage: `{$command} <email-id>` or `{$command} <hubspot-url>`",
            ]);
        }

        // Send immediate acknowledgment
        $this->slack->sendMessage($channel, ":hourglass_flowing_sand: Analyzing email `{$emailId}`...");

        // Get the message timestamp for threading (we'll use channel message)
        // For slash commands, we'll post to the channel directly
        AnalyzeEmailJob::dispatch($emailId, $channel, '');

        return response()->json([
            'response_type' => 'in_channel',
            'text' => "Starting analysis for email `{$emailId}`...",
        ]);
    }

    /**
     * Handle message events containing HubSpot links.
     */
    private function handleMessageEvent(array $event): void
    {
        $text = $event['text'] ?? '';
        $channel = $event['channel'] ?? '';
        $messageTs = $event['ts'] ?? '';

        // Check if channel is allowed (if restrictions are configured)
        if (!$this->isChannelAllowed($channel)) {
            return;
        }

        // Look for HubSpot email URLs in the message
        $emailId = $this->extractEmailIdFromText($text);

        if ($emailId) {
            Log::info("Found HubSpot email link in message", [
                'email_id' => $emailId,
                'channel' => $channel,
            ]);

            // Add processing reaction
            $this->slack->addReaction($channel, $messageTs, 'hourglass_flowing_sand');

            // Dispatch async job
            AnalyzeEmailJob::dispatch($emailId, $channel, $messageTs);
        }
    }

    /**
     * Handle link_shared events.
     */
    private function handleLinkSharedEvent(array $event): void
    {
        $channel = $event['channel'] ?? '';
        $messageTs = $event['message_ts'] ?? '';
        $links = $event['links'] ?? [];

        foreach ($links as $link) {
            $url = $link['url'] ?? '';
            $emailId = $this->extractEmailIdFromUrl($url);

            if ($emailId) {
                Log::info("Found HubSpot email link in link_shared event", [
                    'email_id' => $emailId,
                    'url' => $url,
                    'channel' => $channel,
                ]);

                // Add processing reaction
                $this->slack->addReaction($channel, $messageTs, 'hourglass_flowing_sand');

                // Dispatch async job
                AnalyzeEmailJob::dispatch($emailId, $channel, $messageTs);

                // Only process first matching link
                break;
            }
        }
    }

    /**
     * Extract email ID from user input (could be ID or URL).
     */
    private function extractEmailId(string $input): ?string
    {
        // If it's just a number, return it
        if (preg_match('/^\d+$/', $input)) {
            return $input;
        }

        // Try to extract from URL
        return $this->extractEmailIdFromUrl($input);
    }

    /**
     * Extract email ID from message text containing URLs.
     */
    private function extractEmailIdFromText(string $text): ?string
    {
        // Slack formats URLs as <url|display_text> or just <url>
        // Extract all URLs from the message
        preg_match_all('/<(https?:\/\/[^|>]+)/', $text, $matches);

        foreach ($matches[1] ?? [] as $url) {
            $emailId = $this->extractEmailIdFromUrl($url);
            if ($emailId) {
                return $emailId;
            }
        }

        // Also try to match URLs without Slack formatting
        $emailId = $this->extractEmailIdFromUrl($text);
        if ($emailId) {
            return $emailId;
        }

        return null;
    }

    /**
     * Extract email ID from a HubSpot URL.
     */
    private function extractEmailIdFromUrl(string $url): ?string
    {
        $pattern = config('slack.hubspot_url_pattern');

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        // Also try alternative URL patterns
        // Pattern: /email/{portal_id}/edit/{email_id}
        if (preg_match('/app\.hubspot\.com\/email\/\d+\/(?:edit|details)\/(\d+)/i', $url, $matches)) {
            return $matches[1];
        }

        // Pattern: /email/{email_id}
        if (preg_match('/app\.hubspot\.com\/email\/(\d+)(?:\/|$)/i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if the channel is allowed for bot responses.
     */
    private function isChannelAllowed(string $channel): bool
    {
        $allowedChannels = config('slack.allowed_channels', []);

        // If no restrictions, allow all
        if (empty($allowedChannels)) {
            return true;
        }

        return in_array($channel, $allowedChannels);
    }
}
