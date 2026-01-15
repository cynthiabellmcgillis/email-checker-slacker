<?php

namespace App\Services;

use App\DTOs\EmailContent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HubSpotService
{
    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::baseUrl(config('email-checker.hubspot.base_url'))
            ->withToken(config('email-checker.hubspot.token'))
            ->acceptJson();
    }

    public function listEmails(int $limit = 50): array
    {
        $response = $this->client->get('/marketing/v3/emails', [
            'limit' => $limit,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Failed to fetch emails from HubSpot: ' . $response->body()
            );
        }

        $data = $response->json();

        return array_map(function ($email) {
            return [
                'id' => $email['id'],
                'name' => $email['name'] ?? 'Untitled',
                'subject' => $email['content']['subject'] ?? '',
                'state' => $email['state'] ?? 'unknown',
                'updatedAt' => $email['updatedAt'] ?? null,
            ];
        }, $data['results'] ?? []);
    }

    public function getEmail(string $emailId): EmailContent
    {
        $response = $this->client->get("/marketing/v3/emails/{$emailId}");

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Failed to fetch email {$emailId} from HubSpot: " . $response->body()
            );
        }

        return EmailContent::fromHubSpot($response->json());
    }

    public function isConfigured(): bool
    {
        return !empty(config('email-checker.hubspot.token'));
    }
}
