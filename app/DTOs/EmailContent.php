<?php

namespace App\DTOs;

class EmailContent
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $subject,
        public readonly string $previewText,
        public readonly string $bodyHtml,
        public readonly string $bodyText,
        public readonly array $links,
        public readonly ?string $status = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public static function fromHubSpot(array $data): self
    {
        // Extract body HTML from all widgets
        $bodyHtml = self::extractBodyFromWidgets($data['content']['widgets'] ?? []);
        $bodyText = html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $links = self::extractLinks($bodyHtml);

        // Preview text is in a special widget
        $previewText = $data['content']['widgets']['preview_text']['body']['value'] ?? '';

        return new self(
            id: (string) $data['id'],
            name: $data['name'] ?? 'Untitled',
            subject: $data['subject'] ?? '',
            previewText: $previewText,
            bodyHtml: $bodyHtml,
            bodyText: $bodyText,
            links: $links,
            status: $data['state'] ?? null,
            updatedAt: $data['updatedAt'] ?? null,
        );
    }

    private static function extractBodyFromWidgets(array $widgets): string
    {
        $htmlParts = [];

        foreach ($widgets as $key => $widget) {
            // Skip preview_text widget as it's handled separately
            if ($key === 'preview_text') {
                continue;
            }

            // Extract HTML from widget body
            if (isset($widget['body']['html'])) {
                $htmlParts[] = $widget['body']['html'];
            }
        }

        return implode("\n", $htmlParts);
    }

    private static function extractLinks(string $html): array
    {
        $links = [];
        preg_match_all('/href=["\']([^"\']+)["\']/', $html, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $link) {
                if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
                    $links[] = $link;
                }
            }
        }

        return array_unique($links);
    }

    public function getWordCount(): int
    {
        return str_word_count($this->bodyText);
    }

    public function getSubjectLength(): int
    {
        return strlen($this->subject);
    }

    public function getPreviewTextLength(): int
    {
        return strlen($this->previewText);
    }
}
