<?php

namespace App\Http\Controllers\Api\Payroll\Concerns;

use App\Models\EmployeeBenefit;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmBpjsGovernancePolicy;
use App\Models\HcmEmployeeAllowancePolicy;
use App\Models\HcmEmployeePayrollItemAssignment;
use App\Models\HcmPayrollItem;
use App\Models\HcmSalaryComponent;
use App\Models\HcmSalaryComponentCategory;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait HandlesSalaryComponentEmployeeProfiles
{
public function employeeProfiles(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:500'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $companyId = $this->activeCompanyId($request);
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['perPage'] ?? 200);
        $search = trim((string) ($validated['search'] ?? ''));

        $query = User::query()
            ->with([
                'employeeProfile' => function ($profileQuery) use ($companyId): void {
                    if ($companyId !== null) {
                        $profileQuery->where('company_id', $companyId);
                    }

                    $profileQuery->with(['department:id,name', 'designationRef:id,name']);
                },
            ])
            ->whereHas('companyMemberships', function (Builder $membershipQuery) use ($companyId): void {
                $membershipQuery->where('status', 'active');
                $membershipQuery->where('role', '!=', 'owner');
                if ($companyId !== null) {
                    $membershipQuery->where('company_id', $companyId);
                }
            });

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('employeeProfile', function (Builder $profileQuery) use ($search): void {
                        $profileQuery->where('phone', 'like', "%{$search}%")
                            ->orWhere('designation', 'like', "%{$search}%")
                            ->orWhere('team', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
        $users = $paginator->getCollection();
        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

        $hasActivePph21Policy = $this->hasActivePph21Policy($companyId);
        $hasCompleteBpjsPolicyCoverage = $this->hasCompleteBpjsPolicyCoverage($companyId);
        $activeAllowanceCodeSet = $this->activeAllowanceCodeSet($companyId);
        $hasActiveAllowancePolicy = count($activeAllowanceCodeSet) > 0;

        // Load BPJS employee-party rates for governance-derived component estimation
        $asOf = now()->toDateString();
        $bpjsEmployeeRates = [];
        if ($companyId !== null) {
            $bpjsPolicies = HcmBpjsGovernancePolicy::query()
                ->where('company_id', $companyId)
                ->where('contribution_party', 'employee')
                ->where('is_active', true)
                ->whereDate('effective_start_date', '<=', $asOf)
                ->where(function (Builder $builder) use ($asOf): void {
                    $builder->whereNull('effective_end_date')
                        ->orWhereDate('effective_end_date', '>=', $asOf);
                })
                ->get(['program_code', 'rate_percent', 'wage_base'])
                ->each(function (HcmBpjsGovernancePolicy $p) use (&$bpjsEmployeeRates): void {
                    $bpjsEmployeeRates[$p->program_code] = [
                        'rate' => (float) $p->rate_percent,
                        'wageBase' => (string) ($p->wage_base ?? 'base_salary'),
                    ];
                });
        }

        // Load active PPh21 policy metadata for governance-derived display
        $pph21PolicyMeta = null;
        if ($hasActivePph21Policy && $companyId !== null) {
            $pph21PolicyMeta = HcmTaxGovernancePolicy::query()
                ->where('company_id', $companyId)
                ->where('status', HcmTaxGovernancePolicy::STATUS_PUBLISHED)
                ->whereDate('effective_start_date', '<=', $asOf)
                ->where(function (Builder $builder) use ($asOf): void {
                    $builder->whereNull('effective_end_date')
                        ->orWhereDate('effective_end_date', '>=', $asOf);
                })
                ->first(['policy_code', 'name', 'rules', 'rate_schedules']);
        }

        $profileIds = $users
            ->map(fn (User $user): int => (int) ($user->employeeProfile?->id ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $taxProfileByEmployeeId = EmployeeTaxProfile::query()
            ->whereIn('employee_id', $profileIds)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->first());

        $benefitByEmployeeId = EmployeeBenefit::query()
            ->whereIn('employee_id', $profileIds)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->first());

        $assignmentQuery = HcmEmployeePayrollItemAssignment::query()
            ->with(['payrollItem.salaryComponent'])
            ->whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->orderBy('id');
        $this->applyTenantScope($assignmentQuery, $companyId);
        $assignments = $assignmentQuery->get();

        $assignmentByUser = [];
        foreach ($assignments as $assignment) {
            $uid = (int) $assignment->user_id;
            if (! isset($assignmentByUser[$uid])) {
                $assignmentByUser[$uid] = [];
            }
            $assignmentByUser[$uid][] = $assignment;
        }

        $rows = $users->map(function (User $user) use (
            $assignmentByUser,
            $activeAllowanceCodeSet,
            $hasActivePph21Policy,
            $hasCompleteBpjsPolicyCoverage,
            $hasActiveAllowancePolicy,
            $taxProfileByEmployeeId,
            $benefitByEmployeeId,
            $bpjsEmployeeRates,
            $pph21PolicyMeta
        ): array {
            $profile = $user->employeeProfile;
            $uid = (int) $user->id;
            $employeeCode = 'EMP-'.$uid;
            $employeeProfileId = (int) ($profile?->id ?? 0);
            $taxProfile = $employeeProfileId > 0 ? $taxProfileByEmployeeId->get($employeeProfileId) : null;
            $latestBenefit = $employeeProfileId > 0 ? $benefitByEmployeeId->get($employeeProfileId) : null;

            $baseSalary = $profile?->base_salary !== null ? (float) $profile->base_salary : 0.0;
            $phone = trim((string) ($profile?->phone ?? ''));
            $departmentName = trim((string) ($profile?->department?->name ?? ''));
            $designationName = trim((string) ($profile?->designationRef?->name ?? $profile?->designation ?? ''));

            $identityGaps = [];
            if (trim((string) $user->name) === '') {
                $identityGaps[] = 'fullName';
            }
            if (trim((string) $user->email) === '') {
                $identityGaps[] = 'email';
            }
            if ($phone === '') {
                $identityGaps[] = 'phone';
            }
            if ($departmentName === '') {
                $identityGaps[] = 'department';
            }
            if ($designationName === '') {
                $identityGaps[] = 'designation';
            }
            if ($baseSalary <= 0) {
                $identityGaps[] = 'baseSalary';
            }

            $userAssignments = $assignmentByUser[$uid] ?? [];
            $componentCodes = [];
            $allowanceAssignments = 0;
            $allowanceGovernanceAssignments = 0;
            $sourceModuleCounts = [];
            $totalAdditions = 0.0;
            $totalDeductions = 0.0;
            $terTaxableGross = $baseSalary;
            $componentDetails = [];

            foreach ($userAssignments as $assignment) {
                $item = $assignment->payrollItem;
                $component = $item?->salaryComponent;
                $code = trim((string) ($component?->code ?? $item?->code ?? ''));
                if ($code !== '') {
                    $componentCodes[$code] = $code;
                }

                $sourceModule = trim((string) ($component?->source_module ?? ''));
                if ($sourceModule === '') {
                    $sourceModule = 'manual';
                }
                if (! isset($sourceModuleCounts[$sourceModule])) {
                    $sourceModuleCounts[$sourceModule] = 0;
                }
                $sourceModuleCounts[$sourceModule]++;

                $kind = trim((string) ($component?->kind ?? $item?->kind ?? ''));
                $amount = (float) ($assignment->amount ?? 0);
                if ($kind === 'addition') {
                    $totalAdditions += $amount;
                } elseif ($kind === 'deduction') {
                    $totalDeductions += $amount;
                }

                if (
                    $kind === 'addition'
                    && (bool) ($component?->include_pph21_ter_gross ?? false)
                    && $code !== HcmSalaryComponent::CODE_BASIC_WAGE
                ) {
                    $terTaxableGross += $amount;
                }

                $componentDetails[] = [
                    'code' => $code !== '' ? $code : ($item?->code ?? '-'),
                    'name' => trim((string) ($component?->name ?? $item?->name ?? '-')),
                    'kind' => $kind !== '' ? $kind : 'unknown',
                    'sourceModule' => $sourceModule,
                    'amount' => round($amount, 2),
                ];

                if ($component?->source_module === HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE) {
                    $allowanceAssignments++;
                    if ($code !== '' && isset($activeAllowanceCodeSet[$code])) {
                        $allowanceGovernanceAssignments++;
                    }
                }
            }

            $takeHomePay = round($baseSalary + $totalAdditions - $totalDeductions, 2);

            $hasNpwp = trim((string) ($taxProfile?->npwp ?? '')) !== '';
            $hasTaxStatus = trim((string) ($taxProfile?->tax_status ?? '')) !== '';
            $hasBpjsKesNo = trim((string) ($latestBenefit?->bpjs_kesehatan_no ?? '')) !== '';
            $hasBpjsTkNo = trim((string) ($latestBenefit?->bpjs_ketenagakerjaan_no ?? '')) !== '';

            // Build governance-derived components (BPJS employee portions + PPh21)
            $governanceComponents = [];

            // BPJS Kesehatan employee
            if (isset($bpjsEmployeeRates['bpjs_kesehatan'])) {
                $rate = $bpjsEmployeeRates['bpjs_kesehatan']['rate'];
                $amount = round($baseSalary * $rate / 100, 0);
                $governanceComponents[] = [
                    'code' => 'bpjs_kesehatan_employee',
                    'name' => 'BPJS Kesehatan (Iuran Pekerja)',
                    'kind' => 'deduction',
                    'sourceModule' => 'bpjs',
                    'amount' => $amount,
                    'ratePercent' => $rate,
                    'isEstimated' => true,
                ];
            }

            // JHT employee
            if (isset($bpjsEmployeeRates['jht'])) {
                $rate = $bpjsEmployeeRates['jht']['rate'];
                $amount = round($baseSalary * $rate / 100, 0);
                $governanceComponents[] = [
                    'code' => 'jht_employee',
                    'name' => 'JHT (Iuran Pekerja)',
                    'kind' => 'deduction',
                    'sourceModule' => 'bpjs',
                    'amount' => $amount,
                    'ratePercent' => $rate,
                    'isEstimated' => true,
                ];
            }

            // JP employee
            if (isset($bpjsEmployeeRates['jp'])) {
                $rate = $bpjsEmployeeRates['jp']['rate'];
                $amount = round($baseSalary * $rate / 100, 0);
                $governanceComponents[] = [
                    'code' => 'jp_employee',
                    'name' => 'Jaminan Pensiun (Iuran Pekerja)',
                    'kind' => 'deduction',
                    'sourceModule' => 'bpjs',
                    'amount' => $amount,
                    'ratePercent' => $rate,
                    'isEstimated' => true,
                ];
            }

            // PPh21 TER estimation aligned to calculator lookup table / published schedule
            if ($hasActivePph21Policy) {
                $taxStatus = trim((string) ($taxProfile?->tax_status ?: $taxProfile?->ptkp_status ?: ''));
                $pph21Estimate = null;
                if ($taxStatus !== '') {
                    $pph21Estimate = $this->resolveMonthlyPph21Estimate($terTaxableGross, $taxStatus, $pph21PolicyMeta);
                }

                $governanceComponents[] = [
                    'code' => 'pph21',
                    'name' => 'PPh 21 (Potongan Pajak)',
                    'kind' => 'deduction',
                    'sourceModule' => 'pph21',
                    'amount' => $pph21Estimate !== null ? round((float) ($pph21Estimate['amount'] ?? 0), 2) : null,
                    'ratePercent' => $pph21Estimate !== null
                        ? round((float) ($pph21Estimate['rate'] ?? 0) * 100, 4)
                        : null,
                    'isEstimated' => true,
                    'note' => $taxStatus === ''
                        ? 'Metode TER · Status PTKP belum diisi'
                        : 'Metode TER' .
                            ' · Status: '.$this->normalizeTaxStatus($taxStatus).
                            ($pph21Estimate !== null ? ' · Kategori: '.($pph21Estimate['category'] ?? 'A') : '').
                            ' · Basis Gross: '.round($terTaxableGross, 2),
                ];
            }

            $pph21Ready = $hasActivePph21Policy && $hasNpwp && $hasTaxStatus;
            $bpjsReady = $hasCompleteBpjsPolicyCoverage && $hasBpjsKesNo && $hasBpjsTkNo;
            $allowanceReady = $hasActiveAllowancePolicy && $allowanceGovernanceAssignments > 0;
            $payrollAssignmentReady = count($userAssignments) > 0;

            $integrationGaps = [];
            if (! $pph21Ready) {
                $integrationGaps[] = ! $hasActivePph21Policy
                    ? 'pph21Policy'
                    : (($hasNpwp && $hasTaxStatus) ? 'pph21Profile' : 'pph21Profile');
            }
            if (! $bpjsReady) {
                $integrationGaps[] = ! $hasCompleteBpjsPolicyCoverage
                    ? 'bpjsPolicy'
                    : 'bpjsMembership';
            }
            if (! $allowanceReady) {
                $integrationGaps[] = ! $hasActiveAllowancePolicy
                    ? 'allowancePolicy'
                    : 'allowanceAssignment';
            }
            if (! $payrollAssignmentReady) {
                $integrationGaps[] = 'payrollAssignment';
            }

            $hasCleanIdentity = $identityGaps === [];
            $allChecksReady = $hasCleanIdentity && $pph21Ready && $bpjsReady && $allowanceReady && $payrollAssignmentReady;
            $anyCheckReady = $hasCleanIdentity || $pph21Ready || $bpjsReady || $allowanceReady || $payrollAssignmentReady || $allowanceAssignments > 0;
            $status = $allChecksReady ? 'ready' : ($anyCheckReady ? 'partial' : 'missing');

            return [
                'userId' => $uid,
                'userUuid' => (string) ($user->uuid ?? ''),
                'employeeCode' => $employeeCode,
                'fullName' => (string) $user->name,
                'email' => (string) $user->email,
                'phone' => $phone !== '' ? $phone : null,
                'team' => $profile?->team,
                'departmentName' => $departmentName !== '' ? $departmentName : null,
                'designationName' => $designationName !== '' ? $designationName : null,
                'baseSalary' => round($baseSalary, 2),
                'takeHomePay' => $takeHomePay,
                'totalAdditions' => round($totalAdditions, 2),
                'totalDeductions' => round($totalDeductions, 2),
                'hasCleanIdentity' => $hasCleanIdentity,
                'identityGaps' => $identityGaps,
                'componentDetails' => $componentDetails,
                'governanceComponents' => $governanceComponents,
                'assignmentSummary' => [
                    'totalActiveAssignments' => count($userAssignments),
                    'allowanceAssignments' => $allowanceAssignments,
                    'allowanceGovernanceAssignments' => $allowanceGovernanceAssignments,
                    'sourceModuleCounts' => $sourceModuleCounts,
                    'componentCodes' => array_values($componentCodes),
                ],
                'integrationSummary' => [
                    'checks' => [
                        [
                            'key' => 'pph21',
                            'label' => 'PPh 21 Governance',
                            'ready' => $pph21Ready,
                        ],
                        [
                            'key' => 'bpjs',
                            'label' => 'BPJS Governance',
                            'ready' => $bpjsReady,
                        ],
                        [
                            'key' => 'allowance',
                            'label' => 'Allowance Governance',
                            'ready' => $allowanceReady,
                        ],
                        [
                            'key' => 'payroll',
                            'label' => 'Payroll Assignment',
                            'ready' => $payrollAssignmentReady,
                        ],
                    ],
                    'gaps' => array_values(array_unique($integrationGaps)),
                ],
                'integrationStatus' => $status,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'rows' => $rows,
            ],
            'meta' => [
                'pagination' => [
                    'currentPage' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
                'statusSummary' => [
                    'ready' => $rows->where('integrationStatus', 'ready')->count(),
                    'partial' => $rows->where('integrationStatus', 'partial')->count(),
                    'missing' => $rows->where('integrationStatus', 'missing')->count(),
                ],
            ],
        ]);
    }

    private function activeAllowanceCodeSet(?int $companyId): array
    {
        if ($companyId === null) {
            return [];
        }

        $asOf = now()->toDateString();

        return HcmEmployeeAllowancePolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function (Builder $builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->pluck('code')
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->mapWithKeys(fn (string $code) => [$code => true])
            ->all();
    }
}
