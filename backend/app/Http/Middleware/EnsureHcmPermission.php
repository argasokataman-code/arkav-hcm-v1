<?php

namespace App\Http\Middleware;

use App\Services\HcmRbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureHcmPermission
{
    protected HcmRbacService $rbacService;

    public function __construct(HcmRbacService $rbacService)
    {
        $this->rbacService = $rbacService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): SymfonyResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get company context from request
        $companyId = $this->getCompanyIdFromRequest($request);

        if (! $this->rbacService->userHasPermission($user, $permission, $companyId)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_PERMISSIONS',
                    'message' => 'You do not have permission to perform this action.',
                ],
            ], 403);
        }

        return $next($request);
    }

    /**
     * Extract company_id from request (header, route param, or user context)
     */
    protected function getCompanyIdFromRequest(Request $request): ?int
    {
        // Check header first
        if ($request->hasHeader('X-Company-ID')) {
            return (int) $request->header('X-Company-ID');
        }

        // Check route parameter
        if ($request->route('company_id')) {
            return (int) $request->route('company_id');
        }

        // Fallback to user's company context
        return $this->rbacService->getUserCompanyId($request->user());
    }
}
