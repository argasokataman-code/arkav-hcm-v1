<?php

namespace App\Support;

use App\Models\AuthToken;
use Illuminate\Http\Request;

/**
 * Resolves Arcav API access token from cookie / Authorization header (same rules as api.token middleware).
 */
final class ArcavAccessTokenResolver
{
    public static function rawTokenFromRequest(Request $request): ?string
    {
        // Bearer token takes priority over cookie so that a freshly-minted token
        // returned by /api-token (which JS stores in-memory and sends as Authorization
        // header) is not shadowed by a stale/expired HttpOnly cookie.
        $rawToken = $request->bearerToken();

        if (! $rawToken) {
            $cookieName = (string) config('auth.api_token_cookie.name', 'arcav_access_token');
            $rawToken = $request->cookie($cookieName);
            
            if (! $rawToken) {
                $cookieHeader = (string) $request->headers->get('cookie', '');
                if ($cookieHeader !== '' && preg_match('/(?:^|;\s*)'.preg_quote($cookieName, '/').'=([^;]+)/', $cookieHeader, $m)) {
                    $rawToken = urldecode((string) $m[1]);
                }
            }
        }

        return $rawToken ? (string) $rawToken : null;
    }

    public static function validTokenFromRequest(Request $request): ?AuthToken
    {
        $raw = self::rawTokenFromRequest($request);
        
        if (! $raw) {
            return null;
        }

        $hash = hash('sha256', $raw);
        $token = AuthToken::with('user')
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if (! $token || ($token->expires_at && $token->expires_at->isPast())) {
            return null;
        }

        return $token;
    }
}
