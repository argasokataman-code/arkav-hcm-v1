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

trait HandlesUserManagementUsers
{    private function ensureUserManagementViewPermission(Request $request): ?JsonResponse
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
}
