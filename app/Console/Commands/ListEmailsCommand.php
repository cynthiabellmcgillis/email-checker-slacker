<?php

namespace App\Console\Commands;

use App\Services\HubSpotService;
use Illuminate\Console\Command;

class ListEmailsCommand extends Command
{
    protected $signature = 'email:list
                            {--limit=50 : Number of emails to retrieve}';

    protected $description = 'List marketing emails from HubSpot';

    public function handle(HubSpotService $hubSpot): int
    {
        if (!$hubSpot->isConfigured()) {
            $this->error('HubSpot is not configured. Please set HUBSPOT_ACCESS_TOKEN in your .env file.');
            return self::FAILURE;
        }

        $this->info('Fetching emails from HubSpot...');

        try {
            $emails = $hubSpot->listEmails((int) $this->option('limit'));

            if (empty($emails)) {
                $this->warn('No emails found in HubSpot.');
                return self::SUCCESS;
            }

            $this->table(
                ['ID', 'Name', 'Subject', 'Status', 'Updated'],
                array_map(function ($email) {
                    return [
                        $email['id'],
                        $this->truncate($email['name'], 30),
                        $this->truncate($email['subject'], 40),
                        $email['state'],
                        $email['updatedAt'] ? date('Y-m-d H:i', strtotime($email['updatedAt'])) : '-',
                    ];
                }, $emails)
            );

            $this->newLine();
            $this->info("Found " . count($emails) . " email(s).");
            $this->line("Use: php artisan email:check {ID} to analyze a specific email.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to fetch emails: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}
