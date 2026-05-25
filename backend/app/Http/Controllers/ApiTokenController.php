<?php

namespace App\Http\Controllers;

use App\Models\AuthToken;
use App\Support\ArcavAccessTokenResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiTokenController extends Controller
{
    /**
     * Generate or return an API token for the currently authenticated user.
     *
     * Uses a 10-second deduplication cache to prevent token proliferation when
     * multiple JS modules call /api-token concurrently on the same page load.
     */
    public function getToken(Request $request)
    {
        $token = $request->attributes->get('authToken') ?: ArcavAccessTokenResolver::validTokenFromRequest($request);
        $user = $request->user() ?: ($token?->user);

        if (!$user) {
            if (! $request->expectsJson()) {
                return redirect()->guest(url('lock-screen'));
            }

            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
            ], 404);
        }

        // Return cached raw token for 10-second dedup window.
        // This prevents 4+ concurrent module calls on a single page load from
        // each creating a separate AuthToken record in the database.
        $cacheKey = 'api_token_mint_' . $user->id;
        $cachedRawToken = Cache::get($cacheKey);
        if ($cachedRawToken) {
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $cachedRawToken,
                ],
            ]);
        }

        // Revoke all existing tokens for this user before minting a new one.
        // This enforces a single active token per user — old tokens cannot be reused.
        AuthToken::where('user_id', $user->id)->delete();

        // Mint a new token and cache the raw value for 10 seconds.
        $rawToken = bin2hex(random_bytes(32));
        $createdToken = AuthToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(30),
        ]);

        Cache::put($cacheKey, $rawToken, now()->addSeconds(10));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $rawToken,
                'expiresAt' => $createdToken->expires_at,
            ],
        ]);
    }
}
