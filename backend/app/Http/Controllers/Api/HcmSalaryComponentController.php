<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
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

class HcmSalaryComponentController extends Controller
{
    use ChecksPermissions;

    private const TER_STATUS_TO_CATEGORY = [
        'TK0' => 'A',
        'TK1' => 'A',
        'K0' => 'A',
        'TK2' => 'B',
        'K1' => 'B',
        'TK3' => 'B',
        'K2' => 'B',
        'K3' => 'C',
    ];

    private const TER_TABLES = [
        'A' => [
            [5_400_000.0, 0.0],
            [5_650_000.0, 0.0025],
            [5_950_000.0, 0.005],
            [6_300_000.0, 0.0075],
            [6_750_000.0, 0.01],
            [7_500_000.0, 0.0125],
            [8_550_000.0, 0.015],
            [9_650_000.0, 0.0175],
            [10_050_000.0, 0.02],
            [10_350_000.0, 0.0225],
            [10_700_000.0, 0.025],
            [11_050_000.0, 0.03],
            [11_600_000.0, 0.035],
            [12_500_000.0, 0.04],
            [13_750_000.0, 0.05],
            [15_100_000.0, 0.06],
            [16_950_000.0, 0.07],
            [19_750_000.0, 0.08],
            [24_150_000.0, 0.09],
            [26_450_000.0, 0.10],
            [28_000_000.0, 0.11],
            [30_050_000.0, 0.12],
            [32_400_000.0, 0.13],
            [35_400_000.0, 0.14],
            [39_100_000.0, 0.15],
            [43_850_000.0, 0.16],
            [47_800_000.0, 0.17],
            [51_400_000.0, 0.18],
            [56_300_000.0, 0.19],
            [62_200_000.0, 0.20],
            [68_600_000.0, 0.21],
            [77_500_000.0, 0.22],
            [89_000_000.0, 0.23],
            [103_000_000.0, 0.24],
            [125_000_000.0, 0.25],
            [157_000_000.0, 0.26],
            [206_000_000.0, 0.27],
            [337_000_000.0, 0.28],
            [454_000_000.0, 0.29],
            [550_000_000.0, 0.30],
            [695_000_000.0, 0.31],
            [910_000_000.0, 0.32],
            [1_400_000_000.0, 0.33],
            [1.0e30, 0.34],
        ],
        'B' => [
            [6_200_000.0, 0.0],
            [6_500_000.0, 0.0025],
            [6_850_000.0, 0.005],
            [7_300_000.0, 0.0075],
            [9_200_000.0, 0.01],
            [10_750_000.0, 0.015],
            [11_250_000.0, 0.02],
            [11_600_000.0, 0.025],
            [12_600_000.0, 0.03],
            [13_600_000.0, 0.04],
            [14_950_000.0, 0.05],
            [16_400_000.0, 0.06],
            [18_450_000.0, 0.07],
            [21_850_000.0, 0.08],
            [26_000_000.0, 0.09],
            [27_700_000.0, 0.10],
            [29_350_000.0, 0.11],
            [31_450_000.0, 0.12],
            [33_950_000.0, 0.13],
            [37_100_000.0, 0.14],
            [41_100_000.0, 0.15],
            [45_800_000.0, 0.16],
            [49_500_000.0, 0.17],
            [53_800_000.0, 0.18],
            [58_500_000.0, 0.19],
            [64_000_000.0, 0.20],
            [71_000_000.0, 0.21],
            [80_000_000.0, 0.22],
            [93_000_000.0, 0.23],
            [109_000_000.0, 0.24],
            [129_000_000.0, 0.25],
            [163_000_000.0, 0.26],
            [211_000_000.0, 0.27],
            [374_000_000.0, 0.28],
            [459_000_000.0, 0.29],
            [555_000_000.0, 0.30],
            [704_000_000.0, 0.31],
            [957_000_000.0, 0.32],
            [1_405_000_000.0, 0.33],
            [1.0e30, 0.34],
        ],
        'C' => [
            [6_600_000.0, 0.0],
            [6_950_000.0, 0.0025],
            [7_350_000.0, 0.005],
            [7_800_000.0, 0.0075],
            [8_850_000.0, 0.01],
            [9_800_000.0, 0.0125],
            [10_950_000.0, 0.015],
            [11_200_000.0, 0.0175],
            [12_050_000.0, 0.02],
            [12_950_000.0, 0.03],
            [14_150_000.0, 0.04],
            [15_550_000.0, 0.05],
            [17_050_000.0, 0.06],
            [19_500_000.0, 0.07],
            [22_700_000.0, 0.08],
            [26_600_000.0, 0.09],
            [28_100_000.0, 0.10],
            [30_100_000.0, 0.11],
            [32_600_000.0, 0.12],
            [35_400_000.0, 0.13],
            [38_900_000.0, 0.14],
            [43_000_000.0, 0.15],
            [47_400_000.0, 0.16],
            [51_200_000.0, 0.17],
            [55_800_000.0, 0.18],
            [60_400_000.0, 0.19],
            [66_700_000.0, 0.20],
            [74_500_000.0, 0.21],
            [83_200_000.0, 0.22],
            [95_600_000.0, 0.23],
            [110_000_000.0, 0.24],
            [134_000_000.0, 0.25],
            [169_000_000.0, 0.26],
            [221_000_000.0, 0.27],
            [390_000_000.0, 0.28],
            [463_000_000.0, 0.29],
            [561_000_000.0, 0.30],
            [709_000_000.0, 0.31],
            [965_000_000.0, 0.32],
            [1_419_000_000.0, 0.33],
            [1.0e30, 0.34],
        ],
    ];

    public function categories(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $rows = HcmSalaryComponentCategory::query()
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (HcmSalaryComponentCategory $c): array {
                return [
                    'id' => (int) $c->id,
                    'kind' => (string) $c->kind,
                    'code' => (string) $c->code,
                    'name' => (string) $c->name,
                    'description' => $c->description,
                    'isSystem' => (bool) $c->is_system,
                    'isActive' => (bool) $c->is_active,
                    'sortOrder' => (int) $c->sort_order,
                    'usageCount' => HcmSalaryComponent::query()
                        ->where('kind', $c->kind)
                        ->where('category', $c->code)
                        ->count(),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'CATEGORY_MASTER_READ_ONLY',
                'message' => 'Master kategori bersifat global dan tidak dapat diubah dari tenant.',
            ],
        ], 403);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'CATEGORY_MASTER_READ_ONLY',
                'message' => 'Master kategori bersifat global dan tidak dapat diubah dari tenant.',
            ],
        ], 403);
    }

    public function destroyCategory(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'CATEGORY_MASTER_READ_ONLY',
                'message' => 'Master kategori bersifat global dan tidak dapat diubah dari tenant.',
            ],
        ], 403);
    }

    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'kind' => ['nullable', 'string', Rule::in(['addition', 'deduction'])],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $query = $this->scopedComponentQuery($request);

        if (! empty($validated['kind'] ?? null)) {
            $query->where('kind', $validated['kind']);
        }
        if (array_key_exists('isActive', $validated)) {
            $query->where('is_active', (bool) $validated['isActive']);
        }

        $rows = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (HcmSalaryComponent $c) => $this->serialize($c))
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

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

    public function show(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $c = $this->scopedComponentQuery($request)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($c),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'MANUAL_COMPONENT_CREATION_DISABLED',
                'message' => 'Penambahan komponen manual dinonaktifkan. Komponen gaji dikelola otomatis oleh modul governance.',
            ],
        ], 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'kind' => ['required', 'string', Rule::in(['addition', 'deduction'])],
            'category' => [
                'required',
                'string',
                Rule::in(HcmSalaryComponent::allCategoryCodes()),
            ],
            'includeBpjsHealthWageBase' => ['nullable', 'boolean'],
            'includeBpjsTkWageBase' => ['nullable', 'boolean'],
            'includeThrCalculationBase' => ['nullable', 'boolean'],
            'includePph21TerGross' => ['nullable', 'boolean'],
            'includePph21AnnualReconciliation' => ['nullable', 'boolean'],
            'taxTreatmentCode' => ['nullable', 'string', Rule::in(HcmSalaryComponent::TAX_TREATMENT_CODES)],
            'subjectOvertimeRegulation' => ['nullable', 'boolean'],
            'affectsNetPay' => ['nullable', 'boolean'],
            'employerCostLine' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if (! HcmSalaryComponent::isValidCategoryForKind($validated['kind'], $validated['category'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'category does not match kind.',
                ],
            ], 422);
        }

        if ($this->hasDuplicateNameInKindCategory($validated['name'], $validated['kind'], $validated['category'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'name already exists for this kind and category.',
                ],
            ], 422);
        }

        if (! empty($validated['code']) && HcmSalaryComponent::query()->where('code', $validated['code'])->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'code already exists.',
                ],
            ], 422);
        }

        $code = $this->uniqueCode($validated['code'] ?? null, $validated['name']);
        $taxTreatmentCode = $this->resolveTaxTreatmentCodeFromValidated($validated);
        $taxFlags = HcmSalaryComponent::taxFlagsForTreatment($taxTreatmentCode);

        $c = HcmSalaryComponent::query()->create([
            'company_id' => $this->activeCompanyId($request),
            'code' => $code,
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'category' => $validated['category'],
            'include_bpjs_health_wage_base' => (bool) ($validated['includeBpjsHealthWageBase'] ?? false),
            'include_bpjs_tk_wage_base' => (bool) ($validated['includeBpjsTkWageBase'] ?? false),
            'include_thr_calculation_base' => (bool) ($validated['includeThrCalculationBase'] ?? false),
            'include_pph21_ter_gross' => $taxFlags['include_pph21_ter_gross'],
            'include_pph21_annual_reconciliation' => $taxFlags['include_pph21_annual_reconciliation'],
            'tax_treatment_code' => $taxTreatmentCode,
            'subject_overtime_regulation' => (bool) ($validated['subjectOvertimeRegulation'] ?? false),
            'affects_net_pay' => (bool) ($validated['affectsNetPay'] ?? true),
            'employer_cost_line' => (bool) ($validated['employerCostLine'] ?? false),
            'is_system_locked' => false,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $c->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $c = $this->scopedComponentQuery($request)->findOrFail($id);

        if ($c->is_system_locked) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SYSTEM_LOCKED',
                    'message' => 'Komponen ini dikunci oleh modul governance (' . ($c->source_module ?? 'system') . ') dan tidak dapat diubah secara manual. Gunakan modul governance yang bersangkutan untuk mengubah kebijakan terkait.',
                ],
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'kind' => ['required', 'string', Rule::in(['addition', 'deduction'])],
            'category' => [
                'required',
                'string',
                Rule::in(HcmSalaryComponent::allCategoryCodes()),
            ],
            'includeBpjsHealthWageBase' => ['required', 'boolean'],
            'includeBpjsTkWageBase' => ['required', 'boolean'],
            'includeThrCalculationBase' => ['required', 'boolean'],
            'includePph21TerGross' => ['required', 'boolean'],
            'includePph21AnnualReconciliation' => ['required', 'boolean'],
            'taxTreatmentCode' => ['nullable', 'string', Rule::in(HcmSalaryComponent::TAX_TREATMENT_CODES)],
            'subjectOvertimeRegulation' => ['required', 'boolean'],
            'affectsNetPay' => ['required', 'boolean'],
            'employerCostLine' => ['required', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if (! HcmSalaryComponent::isValidCategoryForKind($validated['kind'], $validated['category'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'category does not match kind.',
                ],
            ], 422);
        }

        if ($this->hasDuplicateNameInKindCategory($validated['name'], $validated['kind'], $validated['category'], $c->id)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'name already exists for this kind and category.',
                ],
            ], 422);
        }

        $code = $validated['code'];
        if ($code !== $c->code && HcmSalaryComponent::query()->where('code', $code)->whereKeyNot($c->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'code already exists.',
                ],
            ], 422);
        }

        $taxTreatmentCode = $this->resolveTaxTreatmentCodeFromValidated($validated);
        $taxFlags = HcmSalaryComponent::taxFlagsForTreatment($taxTreatmentCode);

        $c->update([
            'code' => $code,
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'category' => $validated['category'],
            'include_bpjs_health_wage_base' => $validated['includeBpjsHealthWageBase'],
            'include_bpjs_tk_wage_base' => $validated['includeBpjsTkWageBase'],
            'include_thr_calculation_base' => $validated['includeThrCalculationBase'],
            'include_pph21_ter_gross' => $taxFlags['include_pph21_ter_gross'],
            'include_pph21_annual_reconciliation' => $taxFlags['include_pph21_annual_reconciliation'],
            'tax_treatment_code' => $taxTreatmentCode,
            'subject_overtime_regulation' => $validated['subjectOvertimeRegulation'],
            'affects_net_pay' => $validated['affectsNetPay'],
            'employer_cost_line' => $validated['employerCostLine'],
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? $c->sort_order),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $c = $this->scopedComponentQuery($request)->findOrFail($id);

        if ($c->is_system_locked) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SYSTEM_LOCKED',
                    'message' => 'Komponen ini dikunci oleh modul governance (' . ($c->source_module ?? 'system') . ') dan tidak dapat dihapus. Nonaktifkan melalui modul governance yang bersangkutan jika tidak lagi diperlukan.',
                ],
            ], 403);
        }

        $c->delete();

        return response()->json(['success' => true]);
    }

    /**
     * PATCH /salary-components/{id}/tax-flags
     * Update only the PPh21 tax classification flags from the Tax Rate settings page.
     */
    public function patchTaxFlags(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $c = $this->scopedComponentQuery($request)->findOrFail($id);

        $validated = $request->validate([
            'includePph21TerGross' => ['nullable', 'boolean'],
            'includePph21AnnualReconciliation' => ['nullable', 'boolean'],
            'taxTreatmentCode' => ['nullable', 'string', Rule::in(HcmSalaryComponent::TAX_TREATMENT_CODES)],
        ]);

        if (! array_key_exists('includePph21TerGross', $validated) && ! array_key_exists('includePph21AnnualReconciliation', $validated) && ! array_key_exists('taxTreatmentCode', $validated)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'At least one tax classification field is required.'],
            ], 422);
        }

        $updates = [];
        if (array_key_exists('taxTreatmentCode', $validated) && $validated['taxTreatmentCode'] !== null) {
            $updates['tax_treatment_code'] = (string) $validated['taxTreatmentCode'];
            $updates += HcmSalaryComponent::taxFlagsForTreatment($updates['tax_treatment_code']);
        } else {
            $taxTreatmentCode = HcmSalaryComponent::inferTaxTreatmentCode(
                array_key_exists('includePph21TerGross', $validated) && $validated['includePph21TerGross'] !== null
                    ? (bool) $validated['includePph21TerGross']
                    : (bool) $c->include_pph21_ter_gross,
                array_key_exists('includePph21AnnualReconciliation', $validated) && $validated['includePph21AnnualReconciliation'] !== null
                    ? (bool) $validated['includePph21AnnualReconciliation']
                    : (bool) $c->include_pph21_annual_reconciliation,
                (bool) $c->employer_cost_line,
            );

            $updates['tax_treatment_code'] = $taxTreatmentCode;
            $updates += HcmSalaryComponent::taxFlagsForTreatment($taxTreatmentCode);
        }

        if (! empty($updates)) {
            $c->update($updates);
        }

        $c->refresh();

        return response()->json([
            'success' => true,
            'data' => $this->serialize($c),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(HcmSalaryComponent $c): array
    {
        $integrations = $this->integrationEntries($c);

        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'kind' => $c->kind,
            'category' => $c->category,
            'categoryName' => $this->categoryName($c->kind, $c->category),
            'includeBpjsHealthWageBase' => (bool) $c->include_bpjs_health_wage_base,
            'includeBpjsTkWageBase' => (bool) $c->include_bpjs_tk_wage_base,
            'includeThrCalculationBase' => (bool) $c->include_thr_calculation_base,
            'includePph21TerGross' => (bool) $c->include_pph21_ter_gross,
            'includePph21AnnualReconciliation' => (bool) $c->include_pph21_annual_reconciliation,
            'taxTreatmentCode' => $c->tax_treatment_code ?: HcmSalaryComponent::inferTaxTreatmentCode(
                (bool) $c->include_pph21_ter_gross,
                (bool) $c->include_pph21_annual_reconciliation,
                (bool) $c->employer_cost_line,
            ),
            'subjectOvertimeRegulation' => (bool) $c->subject_overtime_regulation,
            'affectsNetPay' => (bool) $c->affects_net_pay,
            'employerCostLine' => (bool) $c->employer_cost_line,
            'isSystemLocked' => (bool) $c->is_system_locked,
            'integrationLocked' => $this->isManagedBySourceModule($c),
            'integrations' => $integrations,
            'sourceModule' => $c->source_module,
            'isActive' => (bool) $c->is_active,
            'sortOrder' => (int) $c->sort_order,
        ];
    }

    private function isManagedBySourceModule(HcmSalaryComponent $c): bool
    {
        return (bool) $c->is_system_locked && in_array((string) $c->source_module, [
            HcmSalaryComponent::SOURCE_MODULE_SYSTEM,
            HcmSalaryComponent::SOURCE_MODULE_BPJS,
            HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE,
            HcmSalaryComponent::SOURCE_MODULE_PPH21,
            HcmSalaryComponent::SOURCE_MODULE_OVERTIME,
            HcmSalaryComponent::SOURCE_MODULE_THR,
            HcmSalaryComponent::SOURCE_MODULE_PKWT,
        ], true);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function integrationEntries(HcmSalaryComponent $c): array
    {
        $entries = [];

        if ((bool) $c->include_bpjs_health_wage_base || (bool) $c->include_bpjs_tk_wage_base) {
            $entries[] = ['key' => 'bpjs', 'label' => 'BPJS Governance'];
        }
        if (in_array((string) $c->code, [
            HcmSalaryComponent::CODE_BASIC_WAGE,
            HcmSalaryComponent::CODE_FIXED_ALLOWANCE,
        ], true)) {
            $entries[] = ['key' => 'employee_salary', 'label' => 'Employee Salary'];
        }
        if ((bool) $c->include_pph21_ter_gross || (bool) $c->include_pph21_annual_reconciliation) {
            $entries[] = ['key' => 'tax', 'label' => 'PPh 21 Governance'];
        }
        if ((bool) $c->include_thr_calculation_base || $c->code === HcmSalaryComponent::CODE_THR) {
            $entries[] = ['key' => 'thr', 'label' => 'Payroll THR'];
        }
        if ((bool) $c->subject_overtime_regulation || $c->code === HcmSalaryComponent::CODE_OVERTIME_PAY) {
            $entries[] = ['key' => 'overtime', 'label' => 'Overtime'];
        }
        if ($c->code === HcmSalaryComponent::CODE_PKWT_COMPENSATION) {
            $entries[] = ['key' => 'pkwt_compensation', 'label' => 'Payroll PKWT Compensation'];
        }

        switch ((string) $c->source_module) {
            case HcmSalaryComponent::SOURCE_MODULE_BPJS:
                $entries[] = ['key' => 'bpjs', 'label' => 'BPJS Governance'];
                break;
            case HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE:
                $entries[] = ['key' => 'allowance', 'label' => 'Allowance Governance'];
                break;
            case HcmSalaryComponent::SOURCE_MODULE_PPH21:
                $entries[] = ['key' => 'tax', 'label' => 'PPh 21 Governance'];
                break;
            case HcmSalaryComponent::SOURCE_MODULE_OVERTIME:
                $entries[] = ['key' => 'overtime', 'label' => 'Overtime'];
                break;
            case HcmSalaryComponent::SOURCE_MODULE_THR:
                $entries[] = ['key' => 'thr', 'label' => 'Payroll THR'];
                break;
            case HcmSalaryComponent::SOURCE_MODULE_PKWT:
                $entries[] = ['key' => 'pkwt_compensation', 'label' => 'Payroll PKWT Compensation'];
                break;
        }

        $unique = [];
        foreach ($entries as $entry) {
            $unique[$entry['key']] = $entry;
        }

        return array_values($unique);
    }

    private function uniqueCode(?string $requested, string $name): string
    {
        $base = $requested ?: Str::slug($name, '_');
        if ($base === '') {
            $base = 'salary_component';
        }
        $base = Str::limit($base, 60, '');
        $code = $base;
        $i = 0;
        while (HcmSalaryComponent::query()->where('code', $code)->exists()) {
            $i++;
            $suffix = '_'.$i;
            $code = Str::limit($base, 64 - strlen($suffix), '').$suffix;
        }

        return $code;
    }

    private function hasDuplicateNameInKindCategory(string $name, string $kind, string $category, ?int $ignoreId = null): bool
    {
        $query = HcmSalaryComponent::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->where('kind', $kind)
            ->where('category', $category);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    private function scopedComponentQuery(Request $request)
    {
        $query = HcmSalaryComponent::query();
        $companyId = $this->activeCompanyId($request);

        $query->where(function ($inner): void {
            $inner->whereNull('source_module')
                ->orWhere('source_module', '!=', HcmSalaryComponent::SOURCE_MODULE_SYSTEM);
        });

        if ($companyId !== null) {
            $query->where(function ($inner) use ($companyId): void {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');
            });

            return $query;
        }

        $query->whereNull('company_id');

        return $query;
    }

    private function categoryName(string $kind, string $code): string
    {
        return (string) (HcmSalaryComponentCategory::query()
            ->where('kind', $kind)
            ->where('code', $code)
            ->value('name') ?? $code);
    }

    /**
     * @return array<string, true>
     */
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

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        return $query->where(function (Builder $inner) use ($companyId): void {
            if ($companyId !== null) {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');

                return;
            }

            $inner->whereNull('company_id');
        });
    }

    private function hasActivePph21Policy(?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        $asOf = now()->toDateString();

        return HcmTaxGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->where('status', HcmTaxGovernancePolicy::STATUS_PUBLISHED)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function (Builder $builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->exists();
    }

    private function hasCompleteBpjsPolicyCoverage(?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        $asOf = now()->toDateString();

        $pairs = HcmBpjsGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function (Builder $builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->get(['program_code', 'contribution_party'])
            ->map(fn (HcmBpjsGovernancePolicy $row) => $row->program_code.'|'.$row->contribution_party)
            ->unique()
            ->values()
            ->all();

        $required = [
            'bpjs_kesehatan|employee',
            'bpjs_kesehatan|employer',
            'jht|employee',
            'jht|employer',
            'jp|employee',
            'jp|employer',
            'jkk|employer',
            'jkm|employer',
        ];

        foreach ($required as $pair) {
            if (! in_array($pair, $pairs, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{amount: float, source: string, category: string, rate: float, rateMode: string}
     */
    private function resolveMonthlyPph21Estimate(float $monthlyTaxableGross, string $taxStatus, ?HcmTaxGovernancePolicy $taxPolicy): array
    {
        $category = $this->resolveTerCategory($taxStatus);

        if ($monthlyTaxableGross <= 0) {
            return [
                'amount' => 0.0,
                'source' => 'pph21_ter_lookup',
                'category' => $category,
                'rate' => 0.0,
                'rateMode' => 'lookup',
            ];
        }

        $policyRate = $this->resolvePolicyScheduleRate($taxPolicy, $monthlyTaxableGross, $category);
        if ($policyRate !== null) {
            return [
                'amount' => round($monthlyTaxableGross * $policyRate['rate'], 2),
                'source' => 'tax_governance_policy_schedule',
                'category' => $category,
                'rate' => $policyRate['rate'],
                'rateMode' => $policyRate['mode'],
            ];
        }

        $table = self::TER_TABLES[$category] ?? self::TER_TABLES['A'];
        $rate = 0.0;

        foreach ($table as [$upperBound, $tableRate]) {
            $rate = $tableRate;
            if ($monthlyTaxableGross <= $upperBound) {
                break;
            }
        }

        return [
            'amount' => round($monthlyTaxableGross * $rate, 2),
            'source' => 'pph21_ter_lookup',
            'category' => $category,
            'rate' => $rate,
            'rateMode' => 'lookup',
        ];
    }

    /**
     * @return array{rate: float, mode: string}|null
     */
    private function resolvePolicyScheduleRate(?HcmTaxGovernancePolicy $taxPolicy, float $monthlyTaxableGross, string $category): ?array
    {
        if ($taxPolicy === null) {
            return null;
        }

        $rules = is_array($taxPolicy->rules) ? $taxPolicy->rules : [];
        $scheme = strtoupper((string) ($rules['scheme'] ?? 'TER'));
        if ($scheme !== 'TER') {
            return null;
        }

        $schedules = is_array($taxPolicy->rate_schedules) ? $taxPolicy->rate_schedules : [];
        if ($schedules === []) {
            return null;
        }

        $matched = [];
        foreach ($schedules as $schedule) {
            if (! is_array($schedule)) {
                continue;
            }

            $scheduleCategory = $this->normalizeScheduleCategory($schedule);
            if ($scheduleCategory !== null && $scheduleCategory !== $category) {
                continue;
            }

            $rate = $this->normalizeScheduleRate($schedule['rate'] ?? $schedule['value'] ?? $schedule['percent'] ?? $schedule['percentage'] ?? null);
            if ($rate === null) {
                continue;
            }

            $upperBound = $this->normalizeScheduleUpperBound($schedule);
            $matched[] = [
                'rate' => $rate,
                'upperBound' => $upperBound,
            ];
        }

        if ($matched === []) {
            return null;
        }

        $bounded = array_values(array_filter($matched, static fn (array $row): bool => $row['upperBound'] !== null));
        if ($bounded !== []) {
            usort($bounded, static fn (array $left, array $right): int => $left['upperBound'] <=> $right['upperBound']);

            $selectedRate = $bounded[array_key_last($bounded)]['rate'];
            foreach ($bounded as $row) {
                $selectedRate = $row['rate'];
                if ($monthlyTaxableGross <= $row['upperBound']) {
                    break;
                }
            }

            return [
                'rate' => $selectedRate,
                'mode' => 'policy_bounded',
            ];
        }

        return [
            'rate' => (float) $matched[0]['rate'],
            'mode' => 'policy_flat',
        ];
    }

    private function resolveTerCategory(string $taxStatus): string
    {
        $taxKey = $this->normalizeTaxStatus($taxStatus);

        return self::TER_STATUS_TO_CATEGORY[$taxKey] ?? 'A';
    }

    private function normalizeTaxStatus(string $taxStatus): string
    {
        $taxKey = strtoupper(str_replace(['/', ' '], '', trim($taxStatus)));

        return match ($taxKey) {
            'TK' => 'TK0',
            'K' => 'K0',
            default => $taxKey,
        };
    }

    private function normalizeScheduleCategory(array $schedule): ?string
    {
        $raw = $schedule['bracket']
            ?? $schedule['category']
            ?? $schedule['terCategory']
            ?? $schedule['taxCategory']
            ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return strtoupper(trim($raw));
    }

    private function normalizeScheduleRate(mixed $rawRate): ?float
    {
        if (! is_numeric($rawRate)) {
            return null;
        }

        $rate = (float) $rawRate;
        if ($rate < 0) {
            return null;
        }

        return $rate > 1 ? ($rate / 100) : $rate;
    }

    private function normalizeScheduleUpperBound(array $schedule): ?float
    {
        foreach (['upperBound', 'maxGross', 'maxGrossMonthly', 'monthlyGrossUpTo', 'threshold'] as $key) {
            if (! array_key_exists($key, $schedule) || ! is_numeric($schedule[$key])) {
                continue;
            }

            return (float) $schedule[$key];
        }

        return null;
    }

    private function resolveTaxTreatmentCodeFromValidated(array $validated): string
    {
        if (! empty($validated['taxTreatmentCode'])) {
            return (string) $validated['taxTreatmentCode'];
        }

        return HcmSalaryComponent::inferTaxTreatmentCode(
            (bool) ($validated['includePph21TerGross'] ?? false),
            (bool) ($validated['includePph21AnnualReconciliation'] ?? false),
            (bool) ($validated['employerCostLine'] ?? false),
        );
    }
}
