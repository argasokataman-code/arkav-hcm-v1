<?php

namespace App\Http\Controllers\ApiserManagement\Concerns;

use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\HcmUserRoleAudit;
use App\Support\Exports\TabularExportResponse;
use App\Modelsser;
use App\Support\Hcm\HcmFeatureEntitlementResolver;
use Database\Seeders\HcmUserManagementSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait HandlesUserManagementRoles
{    public function roles(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementViewPermission($request)) {
            return $response;
        }

        $validated = $request->validate([
            'scope' => ['nullable', Rule::in(['global', 'company'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived', 'all'])],
        ]);

        $scope = (string) ($validated['scope'] ?? 'company');
        $status = (string) ($validated['status'] ?? 'active');

        $query = HcmRole::query();

        if ($scope === 'global') {
            $query->whereNull('company_id');
        } else {
            $query->where('company_id', $companyId);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $roles = $query
            ->with(['permissions:id,code'])
            ->orderBy('name')
            ->get()
            ->map(fn (HcmRole $role): array => [
                'id' => $role->id,
                'companyId' => $role->company_id,
                'code' => $role->code,
                'name' => $role->name,
                'description' => $role->description,
                'status' => $role->status,
                'isSystem' => (bool) $role->is_system,
                'permissionCodes' => $role->permissions
                    ->pluck('code')
                    ->filter(fn ($code): bool => HcmFeatureEntitlementResolver::isPermissionAllowedForCompany((string) $code, $companyId))
                    ->map(static fn ($code): string => (string) $code)
                    ->sort()
                    ->values(),
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function createRole(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementManagePermission($request)) {
            return $response;
        }

        if ($response = $this->ensureTenantRoleSetupBoundary($request)) {
            return $response;
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $code = strtoupper(Str::slug((string) $validated['code'], '_'));

        $role = HcmRole::query()->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => trim((string) $validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'status' => $validated['status'] ?? 'active',
            'is_system' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $role->id,
                'companyId' => $role->company_id,
                'code' => $role->code,
                'name' => $role->name,
                'description' => $role->description,
                'status' => $role->status,
            ],
        ], 201);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementManagePermission($request)) {
            return $response;
        }

        if ($response = $this->ensureTenantRoleSetupBoundary($request)) {
            return $response;
        }

        $roleQuery = HcmRole::query()->where('company_id', $companyId);
        $this->applyRoleIdentifierScope($roleQuery, $id);
        $role = $roleQuery->first();

        if (! $role) {
            return $this->errorResponse('ROLE_NOT_FOUND', 'Role not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])],
        ]);

        if (array_key_exists('name', $validated)) {
            $role->name = trim((string) $validated['name']);
        }

        if (array_key_exists('description', $validated)) {
            $role->description = $validated['description'] !== null ? trim((string) $validated['description']) : null;
        }

        if (array_key_exists('status', $validated)) {
            $role->status = (string) $validated['status'];
        }

        $role->save();

        return response()->json(['success' => true, 'data' => [
            'id' => $role->id,
            'companyId' => $role->company_id,
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'status' => $role->status,
        ]]);
    }

    public function deleteRole(Request $request, string $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementManagePermission($request)) {
            return $response;
        }

        if ($response = $this->ensureTenantRoleSetupBoundary($request)) {
            return $response;
        }

        $roleQuery = HcmRole::query()->where('company_id', $companyId);
        $this->applyRoleIdentifierScope($roleQuery, $id);
        $role = $roleQuery->first();

        if (! $role) {
            return $this->errorResponse('ROLE_NOT_FOUND', 'Role not found.', 404);
        }

        if ($role->is_system) {
            return $this->errorResponse('ROLE_LOCKED', 'System role cannot be deleted.', 422);
        }

        $inUse = HcmUserRole::query()
            ->where('company_id', $companyId)
            ->where('role_id', $role->id)
            ->where('status', 'active')
            ->exists();

        if ($inUse) {
            $role->status = 'archived';
            $role->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $role->id,
                    'status' => $role->status,
                    'archived' => true,
                ],
            ]);
        }

        $role->delete();

        return response()->json(['success' => true]);
    }

    public function permissions(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementViewPermission($request)) {
            return $response;
        }

        $this->ensurePermissionCatalogSeeded();

        $validated = $request->validate([
            'module' => ['nullable', 'string', 'max:80'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $module = strtolower(trim((string) ($validated['module'] ?? '')));
        $search = trim((string) ($validated['search'] ?? ''));

        $query = HcmPermission::query()
            ->where('is_active', true)
            ->orderBy('module')
            ->orderBy('code');

        if (! $request->user()?->isGlobalHcmAdmin()) {
            $query->where('module', '!=', 'system');
        }

        if ($module !== '') {
            $query->whereRaw('LOWER(module) = ?', [$module]);
        }

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        $rows = $query->get()->map(fn (HcmPermission $permission): array => [
            'id' => $permission->id,
            'code' => $permission->code,
            'module' => $permission->module,
            'resource' => $permission->resource,
            'action' => $permission->action,
            'name' => $permission->name,
            'description' => $permission->description,
        ])
            ->filter(fn (array $permission): bool => HcmFeatureEntitlementResolver::isPermissionAllowedForCompany((string) ($permission['code'] ?? ''), $companyId))
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function syncRolePermissions(Request $request, string $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementManagePermission($request)) {
            return $response;
        }

        if ($response = $this->ensureTenantRoleSetupBoundary($request)) {
            return $response;
        }

        $roleQuery = HcmRole::query()->where('company_id', $companyId);
        $this->applyRoleIdentifierScope($roleQuery, $id);
        $role = $roleQuery->first();

        if (! $role) {
            return $this->errorResponse('ROLE_NOT_FOUND', 'Role not found.', 404);
        }

        $validated = $request->validate([
            'permissionCodes' => ['required', 'array', 'min:1'],
            'permissionCodes.*' => ['string', 'max:120'],
        ]);

        $this->ensurePermissionCatalogSeeded();

        $codes = collect($validated['permissionCodes'])
            ->map(static fn ($code): string => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();

        $blockedCodes = $codes
            ->reject(fn (string $code): bool => HcmFeatureEntitlementResolver::isPermissionAllowedForCompany($code, $companyId))
            ->values();

        if ($blockedCodes->isNotEmpty()) {
            return $this->errorResponse(
                'PERMISSION_FEATURE_NOT_ALLOWED',
                'One or more permissions are not allowed by current package features: '.$blockedCodes->implode(', '),
                422,
            );
        }

        $permissions = HcmPermission::query()
            ->whereIn('code', $codes->all())
            ->get();

        if ($permissions->count() !== $codes->count()) {
            return $this->errorResponse('PERMISSION_NOT_FOUND', 'One or more permissions are invalid.', 404);
        }

        $role->syncPermissionsForCompany($permissions->pluck('id')->all());

        return response()->json([
            'success' => true,
            'data' => [
                'roleId' => $role->id,
                'permissionCodes' => $permissions->pluck('code')->sort()->values(),
            ],
        ]);
    }

    private function ensurePermissionCatalogSeeded(): void
    {
        if (HcmPermission::query()->exists()) {
            return;
        }

        app(HcmUserManagementSeeder::class)->run();
    }

    private function applyRoleIdentifierScope(Builder $query, string $identifier): Builder
    {
        if (Schema::hasColumn((new HcmRole)->getTable(), 'uuid') && Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->whereRaw('1 = 0');
    }

}
