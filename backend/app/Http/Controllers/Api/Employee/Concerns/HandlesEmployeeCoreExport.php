<?php

namespace App\Http\Controllers\Api\Employee\Concerns;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Models\HcmRole;
use App\Models\HcmScheduleTiming;
use App\Models\HcmUserRole;
use App\Models\User;
use App\Services\EmployeeCountValidator;
use Database\Seeders\HcmUserManagementSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

trait HandlesEmployeeCoreExport
{    public function exportEmployees(Request $request)
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to export employees.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in($this->directoryStatusOptions())],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'designationId' => ['nullable', 'integer', 'exists:designations,id'],
            'teamId' => ['nullable', 'integer', 'exists:teams,id'],
            'format' => ['nullable', Rule::in(['xlsx', 'csv', 'pdf'])],
        ]);

        $search = $validated['search'] ?? null;
        $statusFilter = $validated['status'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;
        $designationId = $validated['designationId'] ?? null;
        $teamId = $validated['teamId'] ?? null;

        if ($departmentId && ! Department::query()->whereKey((int) $departmentId)->where('company_id', $activeCompanyId)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPARTMENT_NOT_FOUND',
                    'message' => 'Department not found in active company context.',
                ],
            ], 422);
        }

        if ($designationId) {
            $designationInCompany = Designation::query()
                ->whereKey((int) $designationId)
                ->whereHas('department', fn ($query) => $query->where('company_id', $activeCompanyId))
                ->exists();
            if (! $designationInCompany) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DESIGNATION_NOT_FOUND',
                        'message' => 'Designation not found in active company context.',
                    ],
                ], 422);
            }
        }

        if ($teamId && ! DB::table('teams')->where('id', (int) $teamId)->where('company_id', $activeCompanyId)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_NOT_FOUND',
                    'message' => 'Team not found in active company context.',
                ],
            ], 422);
        }

        $query = User::query()
            ->with([
                'employeeProfile' => function ($query) {
                    $query->select(
                        'id',
                        'user_id',
                        'team',
                        'designation',
                        'employment_status',
                        'contract_type',
                        'phone',
                        'department_id',
                        'designation_id',
                        'team_id',
                    )->with([
                        'department:id,name',
                        'designationRef:id,name,department_id',
                        'assignedTeam:id,name,is_active',
                    ]);
                },
            ])
            ->select(['id', 'uuid', 'name', 'email', 'created_at'])
            ->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('company_id', $activeCompanyId));

        if ($search) {
            $term = trim($search);
            $query->where(function ($outer) use ($term): void {
                $outer->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhereHas('employeeProfile', function ($profileQuery) use ($term): void {
                        $profileQuery->where('phone', 'like', '%'.$term.'%')
                            ->orWhere('nik', 'like', '%'.$term.'%');
                    });
            });
        }

        if ($statusFilter === 'active') {
            $query->where(function ($query) {
                $query->whereDoesntHave('employeeProfile')
                    ->orWhereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employment_status', 'active'));
            });
        } elseif ($statusFilter === 'inactive') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employment_status', 'inactive'));
        } elseif ($statusFilter === 'probation') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employment_status', 'probation'));
        } elseif ($statusFilter === 'resigned') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employment_status', 'resigned'));
        } elseif ($statusFilter === 'terminated') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employment_status', 'terminated'));
        }

        if ($departmentId) {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('department_id', (int) $departmentId));
        }

        if ($designationId) {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('designation_id', (int) $designationId));
        }

        if ($teamId) {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('team_id', (int) $teamId));
        }

        $users = $query->orderByDesc('id')->get();

        $headers = ['Employee UUID', 'Name', 'Email', 'Team', 'Phone', 'Department', 'Designation', 'Status', 'Join Date'];
        $rows = $users->map(function (User $user): array {
            $profile = $user->employeeProfile;
            $snapshot = $this->employeeSnapshotService->snapshotForUser($user);
            $teamName = $profile?->assignedTeam?->name ?: ($snapshot['team'] ?: '');

            return [
                (string) $user->uuid,
                (string) $user->name,
                (string) $user->email,
                (string) $teamName,
                (string) ($profile?->phone ?: ''),
                (string) ($snapshot['departmentName'] ?: ''),
                (string) ($snapshot['designation'] ?: 'Employee'),
                (string) ($snapshot['employmentStatus'] ?? 'active'),
                (string) ($this->effectiveJoinDate($user, $profile) ?: ''),
            ];
        })->values()->all();

        $this->logExportAuditTrail($request, 'export_employees', $this->normalizeExportFormat($request), count($rows));

        return $this->exportTabular('employees', $this->normalizeExportFormat($request), $headers, $rows);
    }

}
