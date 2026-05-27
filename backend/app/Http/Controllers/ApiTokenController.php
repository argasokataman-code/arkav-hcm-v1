<?php

namespace App\Http\Controllers;

use App\Models\AuthToken;
use App\Support\ArcavAccessTokenResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $user = $request->user() ?: ($token?->user) ?: Auth::user();

        if (!$user) {
            if (! $request->expectsJson()) {
                return redirect()->guest(url('lock-screen'));
            }

            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
            ], 401);
        }

        // Cache-based dedup: prevents DB churn when multiple JS modules call /api-token
        // concurrently on the same page load (within 10 seconds).
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

        // Cookie token reuse: if the API-login cookie is still valid in DB, return it
        // without deletion. This allows web-page navigation (which relies on cookies,
        // not localStorage) to keep working after T0 expires. No new token until the
        // cookie token actually becomes invalid or expired.
        $cookieName = (string) config('auth.api_token_cookie.name', 'arcav_access_token');
        $cookieRawToken = $request->cookie($cookieName);
        if ($cookieRawToken) {
            $cookieDbToken = AuthToken::where('user_id', $user->id)
                ->where('token_hash', hash('sha256', $cookieRawToken))
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($cookieDbToken) {
                Cache::put($cacheKey, $cookieRawToken, now()->addSeconds(10));

                return response()->json([
                    'success' => true,
                    'data' => [
                        'token' => $cookieRawToken,
                        'expiresAt' => $cookieDbToken->expires_at,
                    ],
                ]);
            }
        }

        // No valid cookie — clean up only already-expired tokens and mint a fresh one.
        // IMPORTANT: Do not delete tokens that are still valid (even if expiring soon).
        // Deleting a valid login token (1-hour TTL) while concurrent API calls are still
        // using it causes a race-condition 401 → redirect-to-login loop.
        AuthToken::where('user_id', $user->id)
            ->where('expires_at', '<=', now())
            ->delete();

        $rawToken = bin2hex(random_bytes(32));
        $createdToken = AuthToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(30),
        ]);

        // Persist raw token in session so subsequent page navigations reuse it.
        // Persist raw token in cache only, not session (session unreliable).
        Cache::put($cacheKey, $rawToken, now()->addSeconds(10));

        // Also refresh the arcav_access_token cookie with the new long-lived token so
        // that server-side web-page requests (HTML navigation) keep resolving the user.
        $cookiePath = (string) config('auth.api_token_cookie.path', '/');
        $cookieDomain = config('auth.api_token_cookie.domain') ?: null;
        $cookieSecure = (bool) config('auth.api_token_cookie.secure', false);
        $cookieSameSite = (string) config('auth.api_token_cookie.same_site', 'lax');
        $cookieMinutes = 30 * 24 * 60; // 30 days

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $rawToken,
                'expiresAt' => $createdToken->expires_at,
            ],
        ])->cookie(
            $cookieName,
            $rawToken,
            $cookieMinutes,
            $cookiePath,
            $cookieDomain,
            $cookieSecure,
            true,   // httpOnly
            false,  // raw
            $cookieSameSite
        );
    }
}
