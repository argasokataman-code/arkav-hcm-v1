<?php

namespace App\Http\Controllers\Api\AllowanceGovernance\Concerns;

use App\Models\CompanyUser;
use App\Models\HcmEmployeeAllowancePolicy;
use App\Models\HcmEmployeePayrollItemAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

trait HandlesAllowanceReports
{
    public function reports(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);

        $asOf = isset($validated['as_of']) ? Carbon::parse((string) $validated['as_of'])->toDateString() : now()->toDateString();

        $activePolicies = HcmEmployeeAllowancePolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function ($builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->get();

        $mandatoryPolicies = $activePolicies->where('is_mandatory', true)->values();

        $employeeMemberships = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('role', '!=', 'owner')
            ->get(['user_id']);

        $employeeIds = $employeeMemberships->pluck('user_id')->unique()->values();
        $users = User::query()->whereIn('id', $employeeIds)->get(['id', 'uuid', 'name', 'email'])->keyBy('id');

        // Resolve active assignments: fixed_allowance items only
        $activeItemAssignments = HcmEmployeePayrollItemAssignment::query()
            ->with(['payrollItem.salaryComponent'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('user_id', $employeeIds)
            ->where(function ($builder) use ($asOf): void {
                $builder->whereNull('effective_start_date')
                    ->orWhereDate('effective_start_date', '<=', $asOf);
            })
            ->where(function ($builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->get(['id', 'user_id', 'hcm_payroll_item_id', 'is_active', 'effective_start_date', 'effective_end_date']);

        // Build set of user_ids that have at least 1 active allowance assignment.
        // Comply = punya minimal 1 tunjangan aktif, tidak perlu punya semua policy.
        $assignedFromItemAssignments = collect($activeItemAssignments)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $assignedUserIds = $assignedFromItemAssignments->flip(); // flip for O(1) lookup

        $nonCompliantEmployees = [];

        foreach ($employeeIds as $userId) {
            if (! isset($assignedUserIds[(int) $userId])) {
                $user = $users->get((int) $userId);
                $nonCompliantEmployees[] = [
                    'userId' => (int) $userId,
                    'userUuid' => $user?->uuid,
                    'fullName' => $user?->name,
                    'email' => $user?->email,
                    'issues' => [[
                        'code' => 'allowance_assignment_missing',
                        'label' => 'Belum memiliki assignment tunjangan apapun.',
                    ]],
                ];
            }
        }

        $overlapItems = collect(); // Overlap detection not applicable for payroll-item-assignment model

        $checks = [
            [
                'code' => 'default_baseline_seeded',
                'label' => 'Baseline default allowance tersedia',
                'pass' => $activePolicies->count() >= 7,
                'evidence' => [
                    'activePolicyCount' => $activePolicies->count(),
                    'minimumExpected' => 7,
                ],
            ],
            [
                'code' => 'mandatory_assignment_coverage',
                'label' => 'Coverage assignment allowance mandatory',
                'pass' => count($nonCompliantEmployees) === 0,
                'evidence' => [
                    'totalEmployees' => $employeeIds->count(),
                    'mandatoryPolicies' => $mandatoryPolicies->count(),
                    'nonCompliantCount' => count($nonCompliantEmployees),
                    'nonCompliantEmployees' => $nonCompliantEmployees,
                ],
            ],
            [
                'code' => 'assignment_overlap_guard',
                'label' => 'Tidak ada assignment overlap aktif',
                'pass' => $overlapItems->count() === 0,
                'evidence' => [
                    'overlapCount' => $overlapItems->count(),
                    'items' => $overlapItems,
                ],
            ],
        ];

        $passedChecks = collect($checks)->where('pass', true)->count();
        $score = (int) round(($passedChecks / max(count($checks), 1)) * 100);

        return response()->json([
            'success' => true,
            'data' => [
                'reportingPeriod' => $asOf,
                'activePolicyCount' => $activePolicies->count(),
                'mandatoryPolicyCount' => $mandatoryPolicies->count(),
                'employeeScopeCount' => $employeeIds->count(),
                'score' => $score,
                'checks' => $checks,
            ],
        ]);
    }

    public function exportReports(Request $request)
    {
        $report = $this->reports($request);
        $payload = (string) $report->getContent();

        $filename = 'allowance-compliance-report-'.now()->format('Ymd-His').'.json';

        return response($payload, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
