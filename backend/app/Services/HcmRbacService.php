<?php

namespace App\Services;

use App\Models\Company;
use App\Models\HcmRole;
use App\Models\HcmPermission;
use App\Models\HcmRolePermission;
use App\Models\HcmUserRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HcmRbacService
{
    /**
     * Check if user has permission in company context
     */
    public function userHasPermission(User $user, string $permissionCode, ?int $companyId = null): bool
    {
        // Global admin bypass
        if ($this->isGlobalAdmin($user)) {
            return true;
        }

        // Resolve company if not provided
        if ($companyId === null) {
            $companyId = $this->getUserCompanyId($user);
        }

        if (!$companyId) {
            return false;
        }

        // Get user's active roles in this company
        $roleIds = HcmUserRole::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('effective_until')
                      ->orWhere('effective_until', '>=', now()->toDateString());
            })
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return false;
        }

        // Check if any role has the permission
        return HcmRolePermission::whereIn('role_id', $roleIds)
            ->where('company_id', $companyId)
            ->whereHas('permission', function ($query) use ($permissionCode) {
                $query->where('code', $permissionCode)->where('is_active', true);
            })
            ->exists();
    }

    /**
     * Get all permissions for user in company context
     */
    public function getUserPermissions(User $user, ?int $companyId = null): Collection
    {
        // Global admin gets all permissions
        if ($this->isGlobalAdmin($user)) {
            return HcmPermission::where('is_active', true)->pluck('code');
        }

        if ($companyId === null) {
            $companyId = $this->getUserCompanyId($user);
        }

        if (!$companyId) {
            return collect();
        }

        $roleIds = HcmUserRole::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return collect();
        }

        return HcmRolePermission::whereIn('role_id', $roleIds)
            ->where('company_id', $companyId)
            ->with('permission')
            ->get()
            ->pluck('permission.code')
            ->unique();
    }

    /**
     * Check if user is global admin
     */
    public function isGlobalAdmin(User $user): bool
    {
        // Single source of truth: persisted `users.is_super_admin` flag,
        // mirrored by {@see \App\Models\User::isGlobalHcmAdmin()} so that
        // controllers, middlewares, and service-layer checks stay aligned.
        return $user->isGlobalHcmAdmin();
    }

    /**
     * Get user's company ID from membership
     */
    public function getUserCompanyId(User $user): ?int
    {
        // If user has direct company_id (platform user)
        if ($user->company_id) {
            return $user->company_id;
        }

        // Get from company_users membership (current tenant context)
        return DB::table('company_users')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('company_id');
    }

    /**
     * Assign role to user in company
     */
    public function assignRoleToUser(User $user, HcmRole $role, int $companyId, ?User $assignedBy = null): bool
    {
        // Validate role belongs to company (or is platform role)
        if ($role->company_id !== null && $role->company_id !== $companyId) {
            return false;
        }

        return HcmUserRole::updateOrCreate(
            [
                'user_id' => $user->id,
                'company_id' => $companyId,
                'role_id' => $role->id,
            ],
            [
                'assigned_by_user_id' => $assignedBy?->id,
                'status' => 'active',
                'effective_from' => now()->toDateString(),
            ]
        ) instanceof HcmUserRole;
    }

    /**
     * Sync permissions for role in company
     */
    public function syncRolePermissions(HcmRole $role, array $permissionCodes, int $companyId): bool
    {
        // Validate role belongs to company
        if ($role->company_id !== null && $role->company_id !== $companyId) {
            return false;
        }

        $companyUuid = Schema::hasColumn('hcm_role_permissions', 'company_uuid')
            ? Company::query()->where('id', $companyId)->value('uuid')
            : null;

        $permissions = HcmPermission::whereIn('code', $permissionCodes)
            ->where('is_active', true)
            ->pluck('id', 'code');

        $mappings = [];
        foreach ($permissionCodes as $code) {
            if (isset($permissions[$code])) {
                $mappings[] = [
                    'role_id' => $role->id,
                    'permission_id' => $permissions[$code],
                    'company_id' => $companyId,
                    'company_uuid' => $companyUuid,
                ];
            }
        }

        DB::transaction(function () use ($role, $companyId, $mappings) {
            // Remove existing mappings for this role in this company
            HcmRolePermission::where('role_id', $role->id)
                ->where('company_id', $companyId)
                ->delete();

            // Add new mappings
            if (!empty($mappings)) {
                HcmRolePermission::insert($mappings);
            }
        });

        return true;
    }
}