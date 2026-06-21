<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeProfile;
use App\Models\HcmManualActivity;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class HcmTeamController extends Controller
{
    use ChecksPermissions;

    /**
     * List teams with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensureTeamManagePermission($request);
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $perPage = (int) ($request->query('perPage', 20));
        $perPage = min($perPage, 100);
        $page = (int) ($request->query('page', 1));
        $search = $request->query('search', '');
        $status = $request->query('status', 'all');

        $query = Team::query()
            ->with(['department', 'teamLead:id,name'])
            ->withCount('memberProfiles')
            ->where('company_id', $activeCompanyId);

        if ($search !== '') {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $total = $query->count();
        $teams = $query
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $teams->map(fn (Team $team) => $this->serializeTeam($team))->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $teams->currentPage(),
                'perPage' => $teams->perPage(),
                'total' => $total,
            ],
        ]);
    }

    /**
     * Create a new team.
     */
    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensureTeamManagePermission($request);
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'team_lead_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $team = Team::create([
            'company_id' => $activeCompanyId,
            'name' => $validated['name'],
            'department_id' => $validated['department_id'],
            'team_lead_id' => $validated['team_lead_id'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->serializeTeam($team),
        ], 201);
    }

    /**
     * Get a single team by ID.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $forbidden = $this->ensureTeamManagePermission($request);
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $query = Team::query()
            ->with(['department', 'teamLead:id,name'])
            ->withCount('memberProfiles')
            ->where('company_id', $activeCompanyId)
            ->where(function (Builder $q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            });

        $team = $query->first();
        if (! $team) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_NOT_FOUND',
                    'message' => 'Team not found.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeTeam($team),
        ]);
    }

    /**
     * Update a team.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $forbidden = $this->ensureTeamManagePermission($request);
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $query = Team::query()
            ->with(['department', 'teamLead:id,name'])
            ->where('company_id', $activeCompanyId)
            ->where(function (Builder $q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            });

        $team = $query->first();
        if (! $team) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_NOT_FOUND',
                    'message' => 'Team not found.',
                ],
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'team_lead_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $team->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->serializeTeam($team),
        ]);
    }

    /**
     * Delete a team (safe delete - blocks if members exist).
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $forbidden = $this->ensureTeamManagePermission($request);
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $query = Team::query()
            ->where('company_id', $activeCompanyId)
            ->where(function (Builder $q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            });

        $team = $query->first();
        if (! $team) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_NOT_FOUND',
                    'message' => 'Team not found.',
                ],
            ], 404);
        }

        // Check if team has members
        $memberCount = EmployeeProfile::query()
            ->where('team_id', $team->id)
            ->count();

        if ($memberCount > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_DELETION_BLOCKED',
                    'message' => "Cannot delete team with active members ($memberCount). Reassign members first.",
                ],
            ], 409);
        }

        $team->delete();

        return response()->json(['success' => true], 204);
    }

    /**
     * Bulk reassign employees to a target team (or unassign team).
     */
    public function reassignMembers(Request $request): JsonResponse
    {
        $forbidden = $this->ensureTeamManagePermission($request);
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1', 'max:200'],
            'employee_ids.*' => ['required', 'integer', 'distinct'],
            'target_team_id' => ['nullable', 'integer'],
            'source_team_id' => ['nullable', 'integer'],
        ]);

        $employeeIds = array_values(array_unique(array_map('intval', Arr::get($validated, 'employee_ids', []))));
        $sourceTeamId = Arr::get($validated, 'source_team_id');

        $targetTeam = null;
        $targetTeamId = Arr::get($validated, 'target_team_id');
        if ($targetTeamId !== null) {
            $targetTeam = Team::query()
                ->where('company_id', $activeCompanyId)
                ->where('id', (int) $targetTeamId)
                ->first();

            if (! $targetTeam) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TEAM_NOT_FOUND',
                        'message' => 'Target team not found.',
                    ],
                ], 404);
            }

            if (! (bool) $targetTeam->is_active) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TEAM_INACTIVE_NOT_ASSIGNABLE',
                        'message' => 'Inactive team cannot receive member assignments.',
                    ],
                ], 422);
            }
        }

        $employeesQuery = EmployeeProfile::query()
            ->where('company_id', $activeCompanyId)
            ->whereIn('id', $employeeIds);

        if ($sourceTeamId !== null) {
            $employeesQuery->where('team_id', (int) $sourceTeamId);
        }

        $existingIds = $employeesQuery->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $missingIds = array_values(array_diff($employeeIds, $existingIds));

        if ($missingIds !== []) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMPLOYEE_SCOPE_MISMATCH',
                    'message' => 'Some employees are missing, out of scope, or do not match source_team_id filter.',
                    'details' => [
                        'missing_employee_ids' => $missingIds,
                    ],
                ],
            ], 422);
        }

        $updated = EmployeeProfile::query()
            ->where('company_id', $activeCompanyId)
            ->whereIn('id', $existingIds)
            ->update([
                'team_id' => $targetTeam?->id,
                'team' => $targetTeam?->name,
                'updated_at' => now(),
            ]);

        // Keep current normalized assignment in sync for compatibility with assignment consumers.
        EmployeeAssignment::query()
            ->whereIn('employee_id', $existingIds)
            ->where('is_primary', true)
            ->where(function (Builder $query): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now()->toDateString());
            })
            ->update([
                'team_id' => $targetTeam?->id,
                'team_name' => $targetTeam?->name,
                'updated_at' => now(),
            ]);

        $actorUserId = (int) ($request->user()?->id ?? 0);
        $sourceLabel = $sourceTeamId !== null ? ('team #'.(int) $sourceTeamId) : 'mixed teams';
        $targetLabel = $targetTeam ? $targetTeam->name : 'unassigned';
        HcmManualActivity::query()->create([
            'company_id' => $activeCompanyId,
            'title' => sprintf(
                'Bulk team reassign: %d employee(s) from %s to %s',
                $updated,
                $sourceLabel,
                $targetLabel
            ),
            'activity_kind' => 'team_mutation',
            'status' => 'done',
            'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'requested_count' => count($employeeIds),
                'affected_count' => $updated,
                'source_team_id' => $sourceTeamId !== null ? (int) $sourceTeamId : null,
                'target_team' => $targetTeam
                    ? [
                        'id' => (int) $targetTeam->id,
                        'name' => (string) $targetTeam->name,
                    ]
                    : null,
            ],
        ]);
    }

    /**
     * List members of a team.
     */
    public function members(Request $request, int|string $id): JsonResponse
    {
        $activeCompanyId = $this->activeCompanyId($request);

        $team = Team::query()
            ->with(['department', 'teamLead:id,name'])
            ->withCount('memberProfiles')
            ->where('company_id', $activeCompanyId)
            ->where(function (Builder $q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })
            ->first();

        if (! $team) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_NOT_FOUND',
                    'message' => 'Team not found.',
                ],
            ], 404);
        }

        $user = $request->user();
        $canManageMembers = $this->hasAnyPermission($request, ['team.manage', 'employee.manage']);
        $isTeamLeadForRequestedTeam = (int) ($user?->id ?? 0) > 0
            && (int) ($team->team_lead_id ?? 0) === (int) $user->id;
        $canView = $canManageMembers
            || ($this->hasPermission($request, 'team.lead') && $isTeamLeadForRequestedTeam);

        if (! $canView) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Forbidden.',
                ],
            ], 403);
        }

        $perPage = (int) $request->query('perPage', 20);
        $perPage = max(1, min($perPage, 100));
        $page = (int) $request->query('page', 1);
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'all'));

        $query = EmployeeProfile::query()
            ->with([
                'user:id,name,email',
                'department:id,name',
                'designationRef:id,name',
            ])
            ->where('company_id', $activeCompanyId)
            ->where('team_id', $team->id);

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->whereHas('user', function (Builder $uq) use ($search): void {
                    $uq->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                })->orWhere('nik', 'LIKE', "%{$search}%");
            });
        }

        if ($status === 'active' || $status === 'inactive' || $status === 'probation') {
            $query->where('employment_status', $status);
        }

        $members = $query
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $members->map(function (EmployeeProfile $profile): array {
            $designationName = (string) ($profile->designationRef?->name ?? $profile->designation ?? '—');

            return [
                'employee_id' => $profile->id,
                'user_id' => $profile->user_id,
                'name' => (string) ($profile->user?->name ?? '—'),
                'email' => (string) ($profile->user?->email ?? ''),
                'nik' => (string) ($profile->nik ?? ''),
                'department_name' => (string) ($profile->department?->name ?? '—'),
                'designation_name' => $designationName,
                'employment_status' => (string) ($profile->employment_status ?? 'unknown'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'team' => $this->serializeTeam($team),
                'members' => $data,
            ],
            'meta' => [
                'page' => $members->currentPage(),
                'perPage' => $members->perPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    private function ensureTeamManagePermission(Request $request): ?JsonResponse
    {
        // Keep transition fallback to employee.manage until all tenant roles are backfilled.
        return $this->ensureAnyPermission($request, ['team.manage', 'employee.manage']);
    }

    /**
     * Serialize a team for response.
     */
    private function serializeTeam(Team $team): array
    {
        $memberCount = $team->member_profiles_count;
        if ($memberCount === null) {
            $memberCount = EmployeeProfile::query()
                ->where('team_id', $team->id)
                ->count();
        }

        return [
            'id' => $team->id,
            'uuid' => $team->uuid,
            'company_id' => $team->company_id,
            'name' => $team->name,
            'department_id' => $team->department_id,
            'department_name' => $team->department?->name ?? '—',
            'team_lead_id' => $team->team_lead_id,
            'team_lead_name' => $team->teamLead?->name,
            'member_count' => (int) $memberCount,
            'is_active' => $team->is_active,
            'created_at' => $team->created_at?->toIso8601String(),
            'updated_at' => $team->updated_at?->toIso8601String(),
        ];
    }
}
