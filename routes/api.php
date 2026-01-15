<?php

use App\Http\Controllers\SlackController;
use App\Http\Middleware\VerifySlackRequest;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Slack Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhooks from Slack. All routes are
| protected by the VerifySlackRequest middleware which validates
| the Slack signing secret.
|
*/

Route::prefix('slack')->middleware(VerifySlackRequest::class)->group(function () {
    // Event subscriptions (messages, link_shared, etc.)
    Route::post('/events', [SlackController::class, 'handleEvents']);

    // Slash commands (/email-check)
    Route::post('/commands', [SlackController::class, 'handleCommand']);
});
