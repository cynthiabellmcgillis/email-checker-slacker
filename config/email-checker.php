<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Anthropic API Configuration
    |--------------------------------------------------------------------------
    */
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | HubSpot Configuration
    |--------------------------------------------------------------------------
    */
    'hubspot' => [
        'token' => env('HUBSPOT_ACCESS_TOKEN'),
        'base_url' => 'https://api.hubapi.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guidelines Path
    |--------------------------------------------------------------------------
    */
    'guidelines_path' => storage_path('guidelines/email-guidelines.md'),

    /*
    |--------------------------------------------------------------------------
    | Link Checker Configuration
    |--------------------------------------------------------------------------
    */
    'link_checker' => [
        'timeout' => 10,
        'concurrent_requests' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | UTM Validation Rules
    |--------------------------------------------------------------------------
   
    'utm_rules' => [
        'required_params' => ['utm_source', 'utm_medium', 'utm_campaign'],
        'valid_sources' => ['email'],
        'valid_mediums' => ['newsletter', 'lifecycle', 'onboarding'],
    ], */
];
