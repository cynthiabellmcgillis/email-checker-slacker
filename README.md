# Email Checker

A Laravel-based marketing email validation and analysis tool that integrates with HubSpot, Claude AI, and Slack to automatically review marketing emails against brand guidelines.

## What It Does

This tool analyzes marketing emails for:

- **Brand compliance** - Checks against configurable brand guidelines
- **Link validation** - Verifies all links are working and not pointing to internal tools
- **UTM parameter validation** - Ensures proper tracking parameters are present
- **AI-powered analysis** - Uses Claude AI to assess subject lines, preview text, CTAs, tone, and overall quality

## How It Works

### Slack Integration (Recommended)

```
User posts HubSpot email link in Slack
          ▼
┌─────────────────────┐
│   Slack Events API  │  Receives message with link
└─────────┬───────────┘
          ▼
┌─────────────────────┐
│   AnalyzeEmailJob   │  Queued async processing
└─────────┬───────────┘
          ▼
┌─────────────────────┐
│   Email Analyzer    │  Runs all analysis checks
└─────────┬───────────┘
          ▼
┌─────────────────────┐
│   Slack Response    │  Threaded reply with formatted results
└─────────────────────┘
```

### CLI (Alternative)

```
┌─────────────────────┐
│   CLI Command       │  (php artisan email:check <id>)
└─────────┬───────────┘
          ▼
┌─────────────────────┐
│   HubSpot Service   │  Fetches email from HubSpot API
└─────────┬───────────┘
          ▼
┌─────────────────────┐
│   Email Analyzer    │  Runs all analysis checks
│   ├── Basic Checks  │  Subject length, preview text, spam triggers
│   ├── Link Checker  │  URL validity, UTM params, forbidden domains
│   └── AI Analysis   │  Claude API for brand-specific guidance
└─────────┬───────────┘
          ▼
┌─────────────────────┐
│   Analysis Result   │  Color-coded CLI report or JSON output
└─────────────────────┘
```

## Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   npm install
   ```
3. Copy `.env.example` to `.env` and configure:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Set your API keys in `.env`:
   ```
   HUBSPOT_ACCESS_TOKEN=your_hubspot_token
   ANTHROPIC_API_KEY=your_anthropic_key
   ```
5. Run migrations:
   ```bash
   php artisan migrate
   ```

## Slack App Setup

To use the Slack integration, you need to create a Slack app:

### 1. Create a Slack App

1. Go to [api.slack.com/apps](https://api.slack.com/apps)
2. Click "Create New App" → "From scratch"
3. Name your app (e.g., "Email Checker") and select your workspace

### 2. Configure Bot Token Scopes

Go to **OAuth & Permissions** and add these Bot Token Scopes:

| Scope | Purpose |
|-------|---------|
| `chat:write` | Send messages to channels |
| `reactions:write` | Add emoji reactions to messages |
| `reactions:read` | Read reactions (for removing) |
| `links:read` | Detect when HubSpot links are shared |

### 3. Enable Event Subscriptions

Go to **Event Subscriptions**:

1. Toggle "Enable Events" to **On**
2. Set Request URL to: `https://your-domain.com/api/slack/events`
3. Subscribe to these bot events:
   - `message.channels` - Messages in public channels
   - `message.groups` - Messages in private channels
   - `link_shared` - When links are shared (optional)

For `link_shared`, add your HubSpot domain to "App Unfurl Domains": `app.hubspot.com`

### 4. (Optional) Add Slash Command

Go to **Slash Commands** and create a new command:

| Field | Value |
|-------|-------|
| Command | `/email-check` |
| Request URL | `https://your-domain.com/api/slack/commands` |
| Description | Check a HubSpot email against brand guidelines |
| Usage Hint | `[email-id or hubspot-url]` |

### 5. Install App to Workspace

1. Go to **Install App**
2. Click "Install to Workspace"
3. Authorize the requested permissions

### 6. Configure Environment Variables

Add these to your `.env` file:

```
SLACK_BOT_TOKEN=xoxb-your-bot-token
SLACK_SIGNING_SECRET=your-signing-secret
SLACK_ALLOWED_CHANNELS=  # Optional: comma-separated channel IDs to restrict
```

- **Bot Token**: Found in OAuth & Permissions after installing
- **Signing Secret**: Found in Basic Information → App Credentials

### 7. Start the Queue Worker

The Slack integration uses queued jobs for async processing:

```bash
php artisan queue:work
```

Or use the dev script which starts everything:

```bash
composer dev
```

### 8. Expose Your Local Server (Development)

For local development, use ngrok or similar:

```bash
ngrok http 8000
```

Update your Slack app's Request URLs with the ngrok URL.

## Usage

### Via Slack (Recommended)

Simply paste a HubSpot email URL in any channel where the bot is invited:

```
https://app.hubspot.com/email/12345/edit/67890
```

The bot will:
1. React with a hourglass emoji to acknowledge
2. Analyze the email in the background
3. Reply in a thread with the full analysis report
4. Update the reaction to show pass/warn/fail status

You can also use the slash command:

```
/email-check 67890
/email-check https://app.hubspot.com/email/12345/edit/67890
```

### Via CLI

#### List emails from HubSpot

```bash
php artisan email:list
php artisan email:list --limit=100
```

#### Check a specific email

```bash
php artisan email:check <email-id>
php artisan email:check <email-id> --json
```

## Files to Update for Style Guide Changes

### Brand & Content Guidelines

| File | Purpose |
|------|---------|
| `storage/guidelines/email-guidelines.md` | **Main style guide** - Contains all brand guidelines for subject lines, preview text, body content, mandatory copy rules, and formatting standards. Update this file to change what the AI checks against. |
| `resources/prompts/` | **AI prompt templates** - Controls how Claude analyzes emails. Modify these to change AI analysis behavior. |

### Technical Validation Rules

| File | Purpose |
|------|---------|
| `config/email-checker.php` | **UTM and validation rules** - Configure required UTM parameters, valid sources/mediums, link checker timeout, and API settings. |
| `app/Services/EmailAnalyzer.php` | **Basic checks logic** - Modify thresholds for subject length (65 chars), preview text (90 chars), spam triggers, and "Dear" greeting detection. |
| `app/Services/LinkChecker.php` | **Forbidden domains** - Edit the list of blocked internal domains (Notion, Slack, Google Drive, etc.) |

### Code Formatting

| File | Purpose |
|------|---------|
| `.editorconfig` | Editor-level formatting (indentation, line endings, charset) |
| `resources/css/app.css` | Tailwind CSS configuration for web interface styling |

## Key Configuration Options

### config/email-checker.php

```php
'utm_rules' => [
    'required_params' => ['utm_source', 'utm_medium', 'utm_campaign'],
    'valid_sources' => ['email'],
    'valid_mediums' => ['newsletter', 'lifecycle', 'onboarding'],
],
```

### Basic Check Thresholds (in EmailAnalyzer.php)

- Subject line max length: 65 characters
- Preview text target: 90 characters
- Spam triggers: "FREE" (all caps), excessive "$", all caps words

## Project Structure

```
email-checker/
├── app/
│   ├── Console/Commands/     # CLI commands (CheckEmailCommand, ListEmailsCommand)
│   ├── DTOs/                 # Data transfer objects (EmailContent, AnalysisResult)
│   ├── Http/
│   │   ├── Controllers/      # SlackController for webhook handling
│   │   └── Middleware/       # VerifySlackRequest for signature validation
│   ├── Jobs/                 # AnalyzeEmailJob for async processing
│   └── Services/             # Business logic
│       ├── EmailAnalyzer.php
│       ├── HubSpotService.php
│       ├── LinkChecker.php
│       ├── SlackFormatter.php    # Formats results for Slack
│       └── SlackService.php      # Slack API client
├── config/
│   ├── email-checker.php     # HubSpot, AI, and validation config
│   └── slack.php             # Slack app configuration
├── resources/
│   ├── prompts/              # AI prompt templates
│   └── views/                # Web interface templates
├── routes/
│   ├── api.php               # Slack webhook routes
│   └── web.php               # Web routes
├── storage/
│   └── guidelines/           # Brand guidelines markdown
└── tests/                    # Test files
```

## Tech Stack

- PHP 8.2+ / Laravel 12
- Anthropic Claude API
- HubSpot Marketing API v3
- Slack Events API / Web API
- Tailwind CSS 4 / Vite
