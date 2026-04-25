<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureGlobalHcmApiAdmin
{
    /**
     * Ensure API access is limited to global HCM admin only.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADMIN_REQUIRED',
                    'message' => 'Admin access required.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
