<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySlackRequest
{
    /**
     * Verify that the request is from Slack using the signing secret.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle URL verification challenge first (required for Slack app setup)
        $payload = $request->all();
        if (($payload['type'] ?? '') === 'url_verification') {
            return response()->json(['challenge' => $payload['challenge'] ?? '']);
        }

        $signingSecret = config('slack.signing_secret');

        if (empty($signingSecret)) {
            Log::error('Slack signing secret not configured');
            return response()->json(['error' => 'Slack signing secret not configured'], 500);
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (!$timestamp || !$signature) {
            Log::warning('Missing Slack signature headers');
            return response()->json(['error' => 'Missing Slack signature headers'], 401);
        }

        // Protect against replay attacks - reject requests older than 5 minutes
        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning('Slack request timestamp too old', ['timestamp' => $timestamp]);
            return response()->json(['error' => 'Request timestamp too old'], 401);
        }

        // Build the signature base string
        $sigBaseString = "v0:{$timestamp}:{$request->getContent()}";

        // Calculate expected signature
        $expectedSignature = 'v0=' . hash_hmac('sha256', $sigBaseString, $signingSecret);

        // Compare signatures using timing-safe comparison
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Invalid Slack signature', [
                'expected' => substr($expectedSignature, 0, 20) . '...',
                'received' => substr($signature, 0, 20) . '...',
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
