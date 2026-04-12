<?php

namespace App\Http\Middleware;

use App\Support\ArcavAccessTokenResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->attributes->get('traceId');
        $rawToken = ArcavAccessTokenResolver::rawTokenFromRequest($request);

        if (! $rawToken) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_UNAUTHORIZED',
                    'message' => 'Missing authentication token.',
                    'traceId' => $traceId,
                ],
            ], 401);
        }

        $token = ArcavAccessTokenResolver::validTokenFromRequest($request);

        if (! $token) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_UNAUTHORIZED',
                    'message' => 'Invalid or expired token.',
                    'traceId' => $traceId,
                ],
            ], 401);
        }

        $request->attributes->set('authToken', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
