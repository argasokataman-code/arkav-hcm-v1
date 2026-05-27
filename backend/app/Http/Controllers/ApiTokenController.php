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
        // $request->user() is null for public_paths (middleware skips user resolver).
        // Fall back to Auth::user() which reads from the web session.
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

        // Short-term dedup cache: prevents DB churn when multiple JS modules call
        // /api-token concurrently on the same page load.
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

        // Session-based token reuse: if a valid token was minted in this web session,
        // return it directly instead of deleting and re-creating. This prevents the
        // "token expires on every menu navigation" issue caused by each page load
        // triggering a new token mint after the 10-second cache window expires.
        $sessionRawToken = $request->session()->get('api_token_raw');
        if ($sessionRawToken) {
            $dbToken = AuthToken::where('user_id', $user->id)
                ->where('token_hash', hash('sha256', $sessionRawToken))
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($dbToken) {
                Cache::put($cacheKey, $sessionRawToken, now()->addSeconds(10));

                return response()->json([
                    'success' => true,
                    'data' => [
                        'token' => $sessionRawToken,
                        'expiresAt' => $dbToken->expires_at,
                    ],
                ]);
            }
        }

        // Cookie token reuse: if the API-login cookie is still valid in DB, persist
        // it into the session and return it — no deletion, no new token. This prevents
        // the scenario where /api-token deletes the API-login T0 token (TTL ~1hr, within
        // the 2hr deletion window) and then subsequent web-page navigations fail because
        // the cookie still contains the now-deleted T0.
        $cookieName = (string) config('auth.api_token_cookie.name', 'arcav_access_token');
        $cookieRawToken = $request->cookie($cookieName);
        if ($cookieRawToken) {
            $cookieDbToken = AuthToken::where('user_id', $user->id)
                ->where('token_hash', hash('sha256', $cookieRawToken))
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($cookieDbToken) {
                $request->session()->put('api_token_raw', $cookieRawToken);
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

        // No valid cookie or session token — clean up only expired or short-lived tokens
        // and mint a fresh long-lived (30-day) token. Also refresh the arcav_access_token
        // cookie so web-page navigation (which relies on the cookie, not localStorage)
        // continues to work after T0 expires.
        AuthToken::where('user_id', $user->id)
            ->where('expires_at', '<=', now()->addHours(2))
            ->delete();

        $rawToken = bin2hex(random_bytes(32));
        $createdToken = AuthToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(30),
        ]);

        // Persist raw token in session so subsequent page navigations reuse it.
        $request->session()->put('api_token_raw', $rawToken);
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
