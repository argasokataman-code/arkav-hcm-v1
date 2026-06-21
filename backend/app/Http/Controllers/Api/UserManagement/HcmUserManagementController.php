<?php

namespace App\Http\Controllers\Api\UserManagement;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\HcmUserRoleAudit;
use App\Support\Exports\TabularExportResponse;
use App\Models\User;
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

class HcmUserManagementController extends Controller
{
    use ChecksPermissions;

    private function ensureUserManagementViewPermission(Request $request): ?JsonResponse
    {
        return $this->ensureAnyPermission($request, [
            'user.view',
            'role.view',
            'user_management.view',
        ]);
    }

    private function ensureUserManagementManagePermission(Request $request): ?JsonResponse
    {
        return $this->ensureAnyPermission($request, [
            'user.create',
            'user.update',
            'user.assign_role',
            'role.create',
            'role.update',
            'role.delete',
            'role.sync_permission',
            'user_management.manage',
        ]);
    }

    private function ensureTenantRoleSetupBoundary(Request $request): ?JsonResponse
    {
        // Role setup follows active tenant context + permission checks.
        // Global admins are allowed to manage tenant role/permission setup
        // when operating inside a tenant context.
        return null;
    }

    public function users(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);

        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementViewPermission($request)) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'roleCode' => ['nullable', 'string', 'max:80'],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);
        $status = strtolower((string) ($validated['status'] ?? 'active'));
        $search = trim((string) ($validated['search'] ?? ''));
        $roleCode = strtoupper(trim((string) ($validated['roleCode'] ?? '')));

        $query = User::query()
            ->join('company_users', function ($join) use ($companyId): void {
                $join->on('company_users.user_id', '=', 'users.id')
                    ->where('company_users.company_id', '=', $companyId);
            })
            ->select('users.*', 'company_users.status as membership_status', 'company_users.role as membership_role');

        if ($status !== 'all') {
            $query->where('company_users.status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('users.email', 'like', '%'.$search.'%');
            });
        }

        if ($roleCode !== '') {
            $query->whereExists(function ($subQuery) use ($companyId, $roleCode): void {
                $subQuery->selectRaw('1')
                    ->from('hcm_user_roles')
                    ->join('hcm_roles', 'hcm_roles.id', '=', 'hcm_user_roles.role_id')
                    ->whereColumn('hcm_user_roles.user_id', 'users.id')
                    ->where('hcm_user_roles.company_id', $companyId)
                    ->where('hcm_user_roles.status', 'active')
                    ->where('hcm_roles.code', $roleCode);
            });
        }

        $paginator = $query
            ->orderBy('users.name')
            ->paginate($perPage)
            ->appends($request->query());

        $rows = collect($paginator->items());
        $userIds = $rows->pluck('id')->map(static fn ($id) => (int) $id)->all();

        $roleMap = $this->activeRoleMapForUsers($companyId, $userIds);

        $data = $rows->map(function (User $user) use ($roleMap): array {
            $roles = $roleMap[(int) $user->id] ?? [];

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->membership_status,
                'companyRole' => $user->membership_role,
                'activeRoleCodes' => array_values(array_map(static fn (array $item): string => $item['code'], $roles)),
                'activeRoles' => $roles,
                'createdAt' => $user->created_at?->toIso8601String(),
                'updatedAt' => $user->updated_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function usersExport(Request $request)
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementViewPermission($request)) {
            return $response;
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'roleCode' => ['nullable', 'string', 'max:80'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = strtolower((string) ($validated['status'] ?? 'active'));
        $roleCode = strtoupper(trim((string) ($validated['roleCode'] ?? '')));

        $query = User::query()
            ->join('company_users', function ($join) use ($companyId): void {
                $join->on('company_users.user_id', '=', 'users.id')
                    ->where('company_users.company_id', '=', $companyId);
            })
            ->select('users.id', 'users.name', 'users.email', 'company_users.status as membership_status', 'company_users.role as membership_role')
            ->orderBy('users.name');

        if ($status !== 'all') {
            $query->where('company_users.status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('users.email', 'like', '%'.$search.'%');
            });
        }

        if ($roleCode !== '') {
            $query->whereExists(function ($subQuery) use ($companyId, $roleCode): void {
                $subQuery->selectRaw('1')
                    ->from('hcm_user_roles')
                    ->join('hcm_roles', 'hcm_roles.id', '=', 'hcm_user_roles.role_id')
                    ->whereColumn('hcm_user_roles.user_id', 'users.id')
                    ->where('hcm_user_roles.company_id', $companyId)
                    ->where('hcm_user_roles.status', 'active')
                    ->where('hcm_roles.code', $roleCode);
            });
        }

        $rows = $query->get();
        $roleMap = $this->activeRoleMapForUsers($companyId, $rows->pluck('id')->map(static fn ($id) => (int) $id)->all());

        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));
        $headers = ['User ID', 'Name', 'Email', 'Status', 'Company Role', 'Active Role Codes'];
        $exportRows = [];

        foreach ($rows as $row) {
            $roles = $roleMap[(int) $row->id] ?? [];
            $roleCodes = implode('|', array_values(array_map(static fn (array $item): string => $item['code'], $roles)));

            $exportRows[] = [
                (int) $row->id,
                (string) $row->name,
                (string) $row->email,
                (string) $row->membership_status,
                (string) $row->membership_role,
                $roleCodes,
            ];
        }

        return TabularExportResponse::download(
            headers: $headers,
            rows: $exportRows,
            filenameBase: 'user_management_'.$companyId.'_'.now()->format('Ymd_His'),
            format: $format,
            sheetTitle: 'Users'
        );
    }

    public function userDetail(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementViewPermission($request)) {
            return $response;
        }

        $membership = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', $id)
            ->first();

        if (! $membership) {
            return $this->errorResponse('USER_NOT_FOUND', 'User not found for active company.', 404);
        }

        $user = User::query()->find($id);
        if (! $user) {
            return $this->errorResponse('USER_NOT_FOUND', 'User not found.', 404);
        }

        $assignments = HcmUserRole::query()
            ->with(['role:id,company_id,code,name,status'])
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        $activeRoles = $assignments
            ->filter(fn (HcmUserRole $item): bool => $item->status === 'active' && $this->isAssignmentEffective($item))
            ->values();

        $permissionCodes = HcmPermission::query()
            ->select('hcm_permissions.code')
            ->join('hcm_role_permissions', 'hcm_role_permissions.permission_id', '=', 'hcm_permissions.id')
            ->whereIn('hcm_role_permissions.role_id', $activeRoles->pluck('role_id')->all())
            ->distinct()
            ->orderBy('hcm_permissions.code')
            ->pluck('code')
            ->filter(fn ($code): bool => HcmFeatureEntitlementResolver::isPermissionAllowedForCompany((string) $code, $companyId))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $membership->status,
                    'companyRole' => $membership->role,
                    'createdAt' => $user->created_at?->toIso8601String(),
                    'updatedAt' => $user->updated_at?->toIso8601String(),
                ],
                'roleAssignments' => $assignments->map(fn (HcmUserRole $item): array => [
                    'assignmentId' => $item->id,
                    'status' => $item->status,
                    'effectiveFrom' => $item->effective_from?->format('Y-m-d'),
                    'effectiveUntil' => $item->effective_until?->format('Y-m-d'),
                    'revokedAt' => $item->revoked_at?->toIso8601String(),
                    'role' => [
                        'id' => $item->role?->id,
                        'companyId' => $item->role?->company_id,
                        'code' => $item->role?->code,
                        'name' => $item->role?->name,
                        'status' => $item->role?->status,
                    ],
                ])->values(),
                'permissionCodes' => $permissionCodes,
            ],
        ]);
    }

    public function createUser(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementManagePermission($request)) {
            return $response;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:64'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'roleCodes' => ['nullable', 'array'],
            'roleCodes.*' => ['string', 'max:80'],
        ]);

        $actorId = $request->user()?->id;
        $normalizedRoleCodes = collect($validated['roleCodes'] ?? [])
            ->map(static fn ($value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedRoleCodes->isNotEmpty()) {
            $validCodes = HcmRole::query()
                ->where('company_id', $companyId)
                ->whereIn('code', $normalizedRoleCodes->all())
                ->pluck('code')
                ->map(static fn ($value): string => strtoupper((string) $value))
                ->values();

            if ($validCodes->count() !== $normalizedRoleCodes->count()) {
                return $this->errorResponse('ROLE_NOT_FOUND', 'One or more roleCodes are invalid.', 404);
            }
        }

        $result = DB::transaction(function () use ($validated, $companyId, $actorId): array {
            $user = User::query()->create([
                'name' => trim((string) $validated['name']),
                'email' => strtolower(trim((string) $validated['email'])),
                'password' => Hash::make((string) $validated['password']),
            ]);

            $legacyUserId = $this->resolveLegacyUserIdFromModel($user);
            if (! $legacyUserId) {
                abort(500, 'Failed to resolve legacy user identifier.');
            }

            CompanyUser::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'user_id' => $legacyUserId,
                ],
                [
                    'role' => 'member',
                    'status' => $validated['status'] ?? 'active',
                    'joined_at' => now(),
                    'invited_by_user_id' => $actorId,
                ]
            );

            if (isset($validated['roleCodes']) && is_array($validated['roleCodes'])) {
                $this->assignRoleCodesToUser(
                    userId: $legacyUserId,
                    companyId: $companyId,
                    roleCodes: $validated['roleCodes'],
                    actorUserId: $actorId,
                );
            }

            return ['user' => $user, 'legacyUserId' => $legacyUserId];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $result['legacyUserId'],
                'name' => $result['user']->name,
                'email' => $result['user']->email,
            ],
        ], 201);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementManagePermission($request)) {
            return $response;
        }

        $membership = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', $id)
            ->first();

        if (! $membership) {
            return $this->errorResponse('USER_NOT_FOUND', 'User not found for active company.', 404);
        }

        $user = User::query()->find($id);
        if (! $user) {
            return $this->errorResponse('USER_NOT_FOUND', 'User not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        if (array_key_exists('name', $validated)) {
            $user->name = trim((string) $validated['name']);
        }
        if (array_key_exists('email', $validated)) {
            $user->email = strtolower(trim((string) $validated['email']));
        }

        $user->save();

        if (array_key_exists('status', $validated)) {
            $membership->status = (string) $validated['status'];
            $membership->save();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $membership->status,
            ],
        ]);
    }

    public function deleteUser(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementManagePermission($request)) {
            return $response;
        }

        $actorId = $request->user()?->id;
        if ($actorId && (int) $actorId === $id) {
            return $this->errorResponse('SELF_DELETE_FORBIDDEN', 'You cannot delete your own company membership.', 422);
        }

        $membership = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', $id)
            ->first();

        if (! $membership) {
            return $this->errorResponse('USER_NOT_FOUND', 'User not found for active company.', 404);
        }

        DB::transaction(function () use ($companyId, $id, $actorId, $membership): void {
            HcmUserRole::query()
                ->where('company_id', $companyId)
                ->where('user_id', $id)
                ->where('status', 'active')
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            $membership->delete();

            $this->auditRoleChange(
                companyId: $companyId,
                actorUserId: $actorId,
                targetUserId: $id,
                roleId: null,
                action: 'user_removed_from_company',
                notes: 'Removed from company user management',
                metadata: null
            );
        });

        return response()->json([
            'success' => true,
            'data' => [
                'userId' => $id,
                'companyId' => $companyId,
                'removed' => true,
            ],
        ]);
    }

    public function roles(Request $request): JsonResponse
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

    public function userRoles(Request $request, string $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementViewPermission($request)) {
            return $response;
        }

        $userId = $this->resolveUserIdFromIdentifier($id);
        if (! $userId) {
            return $this->errorResponse('USER_NOT_FOUND', 'User not found for active company.', 404);
        }

        if (! CompanyUser::query()->where('company_id', $companyId)->where('user_id', $userId)->exists()) {
            return $this->errorResponse('USER_NOT_FOUND', 'User not found for active company.', 404);
        }

        $assignments = HcmUserRole::query()
            ->with('role:id,company_id,code,name,status')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assignments->map(fn (HcmUserRole $item): array => [
                'assignmentId' => $item->id,
                'status' => $item->status,
                'effectiveFrom' => $item->effective_from?->format('Y-m-d'),
                'effectiveUntil' => $item->effective_until?->format('Y-m-d'),
                'revokedAt' => $item->revoked_at?->toIso8601String(),
                'role' => [
                    'id' => $item->role?->id,
                    'companyId' => $item->role?->company_id,
                    'code' => $item->role?->code,
                    'name' => $item->role?->name,
                    'status' => $item->role?->status,
                ],
            ])->values(),
        ]);
    }

    public function assignUserRole(Request $request, string $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementManagePermission($request)) {
            return $response;
        }

        $userId = $this->resolveUserIdFromIdentifier($id);
        if (! $userId) {
            return $this->errorResponse('USER_NOT_FOUND', 'User not found for active company.', 404);
        }

        if (! CompanyUser::query()->where('company_id', $companyId)->where('user_id', $userId)->exists()) {
            return $this->errorResponse('USER_NOT_FOUND', 'User not found for active company.', 404);
        }

        $validated = $request->validate([
            'roleCode' => ['required', 'string', 'max:80'],
            'effectiveFrom' => ['nullable', 'date'],
            'effectiveUntil' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $roleCode = strtoupper(trim((string) $validated['roleCode']));
        $role = HcmRole::query()
            ->where('company_id', $companyId)
            ->where('code', $roleCode)
            ->first();

        if (! $role) {
            return $this->errorResponse('ROLE_NOT_FOUND', 'Role not found.', 404);
        }

        $actorId = $request->user()?->id;
        $assignment = HcmUserRole::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'company_id' => $companyId,
                'role_id' => $role->id,
                'status' => 'active',
            ],
            [
                'assigned_by_user_id' => $actorId,
                'effective_from' => $validated['effectiveFrom'] ?? null,
                'effective_until' => $validated['effectiveUntil'] ?? null,
                'revoked_at' => null,
            ]
        );

        $this->auditRoleChange(
            companyId: $companyId,
            actorUserId: $actorId,
            targetUserId: $userId,
            roleId: (int) $role->id,
            action: 'role_assigned',
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
            metadata: ['assignmentId' => $assignment->id]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'assignmentId' => $assignment->id,
                'roleCode' => $role->code,
                'status' => $assignment->status,
                'effectiveFrom' => $assignment->effective_from?->format('Y-m-d'),
                'effectiveUntil' => $assignment->effective_until?->format('Y-m-d'),
            ],
        ], 201);
    }

    public function revokeUserRole(Request $request, string $id, string $assignmentId): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureUserManagementManagePermission($request)) {
            return $response;
        }

        $userId = $this->resolveUserIdFromIdentifier($id);
        if (! $userId) {
            return $this->errorResponse('ROLE_ASSIGNMENT_NOT_FOUND', 'Role assignment not found.', 404);
        }

        $assignmentQuery = HcmUserRole::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId);
        $this->applyAssignmentIdentifierScope($assignmentQuery, $assignmentId);
        $assignment = $assignmentQuery->first();

        if (! $assignment) {
            return $this->errorResponse('ROLE_ASSIGNMENT_NOT_FOUND', 'Role assignment not found.', 404);
        }

        if ($assignment->status !== 'active') {
            return $this->errorResponse('ROLE_ASSIGNMENT_NOT_ACTIVE', 'Role assignment already inactive.', 422);
        }

        $assignment->status = 'revoked';
        $assignment->revoked_at = now();
        $assignment->save();

        $actorId = $request->user()?->id;
        $this->auditRoleChange(
            companyId: $companyId,
            actorUserId: $actorId,
            targetUserId: $userId,
            roleId: (int) $assignment->role_id,
            action: 'role_revoked',
            notes: null,
            metadata: ['assignmentId' => $assignment->id]
        );

        return response()->json(['success' => true]);
    }

    private function activeRoleMapForUsers(int $companyId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = HcmUserRole::query()
            ->select([
                'hcm_user_roles.user_id',
                'hcm_roles.code as role_code',
                'hcm_roles.name as role_name',
            ])
            ->join('hcm_roles', 'hcm_roles.id', '=', 'hcm_user_roles.role_id')
            ->where('hcm_user_roles.company_id', $companyId)
            ->whereIn('hcm_user_roles.user_id', $userIds)
            ->where('hcm_user_roles.status', 'active')
            ->where(function (Builder $query): void {
                $query->whereNull('hcm_user_roles.effective_from')
                    ->orWhere('hcm_user_roles.effective_from', '<=', now()->toDateString());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('hcm_user_roles.effective_until')
                    ->orWhere('hcm_user_roles.effective_until', '>=', now()->toDateString());
            })
            ->orderBy('hcm_roles.code')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            $map[$uid] ??= [];
            $map[$uid][] = [
                'code' => (string) $row->role_code,
                'name' => (string) $row->role_name,
            ];
        }

        return $map;
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

    private function applyAssignmentIdentifierScope(Builder $query, string $identifier): Builder
    {
        if (Schema::hasColumn((new HcmUserRole)->getTable(), 'uuid') && Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->whereRaw('1 = 0');
    }

    private function resolveUserIdFromIdentifier(string $identifier): ?int
    {
        if (Schema::hasColumn((new User)->getTable(), 'uuid') && Str::isUuid($identifier)) {
            $resolved = User::query()->where('uuid', $identifier)->value('id');

            return is_numeric($resolved) ? (int) $resolved : null;
        }

        if (ctype_digit($identifier)) {
            return (int) $identifier;
        }

        return null;
    }

    private function resolveLegacyUserIdFromModel(User $user): ?int
    {
        $id = $user->getAttribute('id');
        if (is_numeric($id)) {
            return (int) $id;
        }

        $uuid = (string) ($user->getAttribute('uuid') ?? '');
        if ($uuid === '' || ! Schema::hasColumn($user->getTable(), 'uuid')) {
            return null;
        }

        $resolved = User::query()->where('uuid', $uuid)->value('id');

        return is_numeric($resolved) ? (int) $resolved : null;
    }

    private function isAssignmentEffective(HcmUserRole $assignment): bool
    {
        $today = now()->toDateString();
        if ($assignment->effective_from && $assignment->effective_from->format('Y-m-d') > $today) {
            return false;
        }

        if ($assignment->effective_until && $assignment->effective_until->format('Y-m-d') < $today) {
            return false;
        }

        return true;
    }

    private function assignRoleCodesToUser(int $userId, int $companyId, array $roleCodes, ?int $actorUserId): void
    {
        $normalizedCodes = collect($roleCodes)
            ->map(static fn ($value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedCodes->isEmpty()) {
            return;
        }

        $roles = HcmRole::query()
            ->where('company_id', $companyId)
            ->whereIn('code', $normalizedCodes->all())
            ->get();

        foreach ($roles as $role) {
            $assignment = HcmUserRole::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'status' => 'active',
                ],
                [
                    'assigned_by_user_id' => $actorUserId,
                    'revoked_at' => null,
                ]
            );

            $this->auditRoleChange(
                companyId: $companyId,
                actorUserId: $actorUserId,
                targetUserId: $userId,
                roleId: (int) $role->id,
                action: 'role_assigned',
                notes: 'Assigned during user creation',
                metadata: ['assignmentId' => $assignment->id]
            );
        }
    }

    private function auditRoleChange(
        int $companyId,
        ?int $actorUserId,
        int $targetUserId,
        ?int $roleId,
        string $action,
        ?string $notes,
        ?array $metadata = null
    ): void {
        HcmUserRoleAudit::query()->create([
            'company_id' => $companyId,
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'role_id' => $roleId,
            'action' => $action,
            'notes' => $notes,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
