<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait EnsuresHcmAdmin
{
    /**
     * Ensure user is a global HCM admin (for endpoints not tied to a specific company).
     */
    protected function ensureHcmAdmin(Request $request): ?JsonResponse
    {
        // If the request already has an active tenant context, treat this as a tenant-aware admin check.
        // This makes "company owner trial" behave as admin for their own tenant while keeping cross-tenant isolation.
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId > 0) {
            return $this->ensureHcmAdminForCompany($request, $activeCompanyId);
        }

        $user = $request->user();
        if ($user && $user->isHcmAdmin()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'Forbidden.',
            ],
        ], 403);
    }

    /**
     * Ensure user is an HCM admin for a specific company (tenant-aware check).
     * Blocks cross-tenant escalation by verifying admin role in the requested company.
     */
    protected function ensureHcmAdminForCompany(Request $request, int $companyId): ?JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isHcmAdminForCompany($companyId)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'Forbidden.',
            ],
        ], 403);
    }
}
