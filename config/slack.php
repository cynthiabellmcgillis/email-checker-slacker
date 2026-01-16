<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Slack Bot Token
    |--------------------------------------------------------------------------
    |
    | The Bot User OAuth Token from your Slack app. This token is used to
    | send messages back to Slack channels. It starts with "xoxb-".
    |
    */
    'bot_token' => env('SLACK_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Slack Signing Secret
    |--------------------------------------------------------------------------
    |
    | The signing secret from your Slack app's "Basic Information" page.
    | Used to verify that incoming requests are actually from Slack.
    |
    */
    'signing_secret' => env('SLACK_SIGNING_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | HubSpot URL Pattern
    |--------------------------------------------------------------------------
    |
    | Regular expression pattern to extract email IDs from HubSpot URLs.
    | Captures the numeric email ID from various HubSpot email URL formats.
    |
    */
    'hubspot_url_pattern' => '/app\.hubspot\.com\/email\/\d+\/(?:edit|details)\/(\d+)/i',

    /*
    |--------------------------------------------------------------------------
    | Allowed Channels (Optional)
    |--------------------------------------------------------------------------
    |
    | If set, the bot will only respond to messages in these channels.
    | Leave empty to allow all channels where the bot is invited.
    |
    */
    'allowed_channels' => array_filter(explode(',', env('SLACK_ALLOWED_CHANNELS', ''))),
];
