# Email Checker

A Laravel-based marketing email validation and analysis tool that integrates with HubSpot and Claude AI to automatically review marketing emails against brand guidelines.

## What It Does

This tool analyzes marketing emails for:

- **Brand compliance** - Checks against configurable brand guidelines
- **Link validation** - Verifies all links are working and not pointing to internal tools
- **UTM parameter validation** - Ensures proper tracking parameters are present
- **AI-powered analysis** - Uses Claude AI to assess subject lines, preview text, CTAs, tone, and overall quality

## How It Works

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

## Usage

### List emails from HubSpot

```bash
php artisan email:list
php artisan email:list --limit=100
```

### Check a specific email

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
│   └── Services/             # Business logic (EmailAnalyzer, LinkChecker, HubSpotService)
├── config/
│   └── email-checker.php     # Application configuration
├── resources/
│   ├── prompts/              # AI prompt templates
│   └── views/                # Web interface templates
├── storage/
│   └── guidelines/           # Brand guidelines markdown
└── tests/                    # Test files
```

## Tech Stack

- PHP 8.2+ / Laravel 12
- Anthropic Claude API
- HubSpot Marketing API v3
- Tailwind CSS 4 / Vite
