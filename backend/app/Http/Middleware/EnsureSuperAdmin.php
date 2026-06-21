<?php

namespace App\Http\Middleware;

use App\Services\HcmRbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureSuperAdmin
{
    protected HcmRbacService $rbacService;

    public function __construct(HcmRbacService $rbacService)
    {
        $this->rbacService = $rbacService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = $request->user();

        if (! $user || ! $this->rbacService->isGlobalAdmin($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUPER_USER_REQUIRED',
                    'message' => 'This action requires super administrator privileges.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
