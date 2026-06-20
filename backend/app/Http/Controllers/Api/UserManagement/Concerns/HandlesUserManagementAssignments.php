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

trait HandlesUserManagementAssignments
{    public function userRoles(Request $request, string $id): JsonResponse
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

}
