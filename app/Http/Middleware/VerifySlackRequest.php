<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySlackRequest
{
    /**
     * Verify that the request is from Slack using the signing secret.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signingSecret = config('slack.signing_secret');

        if (empty($signingSecret)) {
            return response()->json(['error' => 'Slack signing secret not configured'], 500);
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (!$timestamp || !$signature) {
            return response()->json(['error' => 'Missing Slack signature headers'], 401);
        }

        // Protect against replay attacks - reject requests older than 5 minutes
        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json(['error' => 'Request timestamp too old'], 401);
        }

        // Build the signature base string
        $sigBaseString = "v0:{$timestamp}:{$request->getContent()}";

        // Calculate expected signature
        $expectedSignature = 'v0=' . hash_hmac('sha256', $sigBaseString, $signingSecret);

        // Compare signatures using timing-safe comparison
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
