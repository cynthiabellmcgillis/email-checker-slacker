# Email Checker Slacker

A Laravel 12 application that validates marketing emails against brand guidelines using HubSpot, Claude AI, and Slack integrations.

## Quick Reference

```bash
# Development (runs all services concurrently)
composer dev

# Run tests
composer test

# Check a specific email via CLI
php artisan email:check <email-id>
php artisan email:check <email-id> --json

# List emails from HubSpot
php artisan email:list --limit=50
```

## Architecture

**Data Flow:** Slack message with HubSpot link → Queue job → Fetch email from HubSpot → Analyze with Claude AI → Post results back to Slack

### Key Directories

- `app/Services/` - Core business logic (EmailAnalyzer, HubSpotService, LinkChecker, SlackService)
- `app/DTOs/` - Data transfer objects (EmailContent, AnalysisResult)
- `app/Jobs/` - Async queue jobs (AnalyzeEmailJob)
- `app/Http/Controllers/` - Slack webhook handler
- `resources/prompts/` - Claude prompt templates
- `storage/guidelines/` - Brand guidelines markdown
- `config/email-checker.php` - Validation rules and API settings

### External Services

- **HubSpot Marketing API v3** - Fetches email content
- **Anthropic Claude API** - AI-powered email analysis (uses Sonnet 4)
- **Slack Events API** - Webhook handling and message posting

## Environment Variables

Required in `.env`:
```
HUBSPOT_ACCESS_TOKEN=
ANTHROPIC_API_KEY=
SLACK_BOT_TOKEN=xoxb-...
SLACK_SIGNING_SECRET=
```

## Code Patterns

- Constructor-based dependency injection for services
- DTOs for type-safe data passing (EmailContent, AnalysisResult)
- Queue-based async processing for Slack responsiveness
- HMAC-SHA256 verification for Slack webhooks

## Customization Points

| File | What to customize |
|------|-------------------|
| `storage/guidelines/email-guidelines.md` | Brand guidelines for analysis |
| `resources/prompts/email-analysis.md` | Claude prompt template |
| `config/email-checker.php` | UTM rules, timeouts, thresholds |
| `app/Services/LinkChecker.php` | Forbidden domains list |

## How AI Analysis Works

The email analyzer uses Claude AI to interpret `storage/guidelines/email-guidelines.md`. Understanding this helps you write effective guidelines:

**Key behaviors:**
- AI interprets guidelines with context — words like "etc" give it latitude to flag similar patterns not explicitly listed
- Guidelines marked as **mandatory** produce `critical` severity issues
- Best practices produce `warning` severity issues
- The prompt template (`resources/prompts/email-analysis.md`) defines issue categories and severity levels

**Writing effective guidelines:**
- Be explicit about what's allowed vs prohibited (e.g., "casual greetings like 'hey' are prohibited, but 'Welcome to [Product]' is acceptable")
- Avoid vague terms like "etc" unless you want AI to use judgment
- Use **bold** or "mandatory" to indicate critical rules
- Provide examples of both good and bad patterns

## Testing

```bash
composer test    # Runs PHPUnit with config cache clear
```

Tests use PHPUnit 11 with Mockery for mocking external services.
