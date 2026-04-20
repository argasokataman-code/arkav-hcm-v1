<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ChecksPermissions
{
    /**
     * Check if user has a specific permission for the active company.
     */
    protected function hasPermission(Request $request, string $permissionCode): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        // Global admin has full capability across tenant-scoped features.
        if ($user->isGlobalHcmAdmin()) {
            return true;
        }

        $companyId = $this->activeCompanyId($request);
        if (!$companyId) {
            return $user->isHcmAdmin();
        }

        // Backward compatibility: tenant admins should retain access even before granular permission seeding.
        if ($user->isHcmAdminForCompany($companyId)) {
            return true;
        }

        return $user->hasPermissionForCompany($permissionCode, $companyId);
    }

    /**
     * Check if user has any of the specified permissions for the active company.
     */
    protected function hasAnyPermission(Request $request, array $permissionCodes): bool
    {
        foreach ($permissionCodes as $permissionCode) {
            if ($this->hasPermission($request, $permissionCode)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the specified permissions for the active company.
     */
    protected function hasAllPermissions(Request $request, array $permissionCodes): bool
    {
        foreach ($permissionCodes as $permissionCode) {
            if (!$this->hasPermission($request, $permissionCode)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Ensure user has a specific permission, return error response if not.
     */
    protected function ensurePermission(Request $request, string $permissionCode): ?JsonResponse
    {
        if (!$this->hasPermission($request, $permissionCode)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Forbidden.',
                ],
            ], 403);
        }
        return null;
    }

    /**
     * Ensure user has any of the specified permissions.
     */
    protected function ensureAnyPermission(Request $request, array $permissionCodes): ?JsonResponse
    {
        if (!$this->hasAnyPermission($request, $permissionCodes)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Forbidden.',
                ],
            ], 403);
        }
        return null;
    }

    /**
     * Ensure user has all of the specified permissions.
     */
    protected function ensureAllPermissions(Request $request, array $permissionCodes): ?JsonResponse
    {
        if (!$this->hasAllPermissions($request, $permissionCodes)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Forbidden.',
                ],
            ], 403);
        }
        return null;
    }

    /**
     * Get active company ID from request.
     */
    protected function activeCompanyId(Request $request): ?int
    {
        return $request->attributes->get('activeCompanyId');
    }
}