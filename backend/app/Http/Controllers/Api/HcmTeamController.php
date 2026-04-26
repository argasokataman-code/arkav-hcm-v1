<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Department;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmTeamController extends Controller
{
    use ChecksPermissions;

    /**
     * List teams with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'employee.manage');
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
        $forbidden = $this->ensurePermission($request, 'employee.manage');
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
        $forbidden = $this->ensurePermission($request, 'employee.manage');
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
        $forbidden = $this->ensurePermission($request, 'employee.manage');
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
        $forbidden = $this->ensurePermission($request, 'employee.manage');
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
        $memberCount = EmployeeAssignment::query()
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
     * List members of a team.
     */
    public function members(Request $request, int|string $id): JsonResponse
    {
        $activeCompanyId = $this->activeCompanyId($request);

        $team = Team::query()
            ->with(['department', 'teamLead:id,name'])
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
        $canView = $this->hasPermission($request, 'employee.manage')
            || ((int) ($user?->id ?? 0) > 0 && (int) ($team->team_lead_id ?? 0) === (int) $user->id);

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

    /**
     * Serialize a team for response.
     */
    private function serializeTeam(Team $team): array
    {
        $memberCount = EmployeeAssignment::query()
            ->where('team_id', $team->id)
            ->count();

        return [
            'id' => $team->id,
            'uuid' => $team->uuid,
            'company_id' => $team->company_id,
            'name' => $team->name,
            'department_id' => $team->department_id,
            'department_name' => $team->department?->name ?? '—',
            'team_lead_id' => $team->team_lead_id,
            'team_lead_name' => $team->teamLead?->name,
            'member_count' => $memberCount,
            'is_active' => $team->is_active,
            'created_at' => $team->created_at?->toIso8601String(),
            'updated_at' => $team->updated_at?->toIso8601String(),
        ];
    }
}
