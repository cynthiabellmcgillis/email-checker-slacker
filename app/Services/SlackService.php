<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackService
{
    private string $botToken;

    public function __construct()
    {
        $this->botToken = config('slack.bot_token', '');
    }

    /**
     * Check if Slack is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken) && !empty(config('slack.signing_secret'));
    }

    /**
     * Send a message to a Slack channel.
     */
    public function sendMessage(string $channel, string $text, array $blocks = []): bool
    {
        $payload = [
            'channel' => $channel,
            'text' => $text, // Fallback for notifications
        ];

        if (!empty($blocks)) {
            $payload['blocks'] = $blocks;
        }

        return $this->post('chat.postMessage', $payload);
    }

    /**
     * Send a threaded reply to a message.
     */
    public function sendThreadReply(string $channel, string $threadTs, string $text, array $blocks = []): bool
    {
        $payload = [
            'channel' => $channel,
            'thread_ts' => $threadTs,
            'text' => $text,
        ];

        if (!empty($blocks)) {
            $payload['blocks'] = $blocks;
        }

        return $this->post('chat.postMessage', $payload);
    }

    /**
     * Add a reaction to a message.
     */
    public function addReaction(string $channel, string $timestamp, string $emoji): bool
    {
        return $this->post('reactions.add', [
            'channel' => $channel,
            'timestamp' => $timestamp,
            'name' => $emoji,
        ]);
    }

    /**
     * Remove a reaction from a message.
     */
    public function removeReaction(string $channel, string $timestamp, string $emoji): bool
    {
        return $this->post('reactions.remove', [
            'channel' => $channel,
            'timestamp' => $timestamp,
            'name' => $emoji,
        ]);
    }

    /**
     * Make a POST request to the Slack API.
     */
    private function post(string $endpoint, array $payload): bool
    {
        try {
            $response = Http::withToken($this->botToken)
                ->post("https://slack.com/api/{$endpoint}", $payload);

            $data = $response->json();

            if (!($data['ok'] ?? false)) {
                Log::error("Slack API error on {$endpoint}", [
                    'error' => $data['error'] ?? 'Unknown error',
                    'payload' => $payload,
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Slack API exception on {$endpoint}", [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return false;
        }
    }
}
