<?php

namespace App\Support;

use App\Models\HcmEmployeePayrollItemAssignment;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmResignation;
use App\Models\HcmSalaryComponent;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\HcmTermination;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Hcm\EmployeeSnapshotService;
use App\Services\Hcm\OvertimePayCalculator;
use App\Services\Hcm\PayrollLeaveHolidayAdjuster;
use App\Services\Hcm\PayrollMonthlySettingsService;
use App\Services\Hcm\PayrollWorkRuleResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class PayrollDraftBuilder
{
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

    public static function rebuildDraftRun(HcmPayrollPeriod $period, ?int $companyId = null): HcmPayrollRun
    {
        return DB::transaction(function () use ($period, $companyId) {
            $companyId = $companyId ?? ($period->company_id ? (int) $period->company_id : null);
            $policySnapshot = self::resolvePayrollPolicySnapshot($period, $companyId);
            $taxGovernancePolicy = self::resolveTaxGovernancePolicyForPeriod($period, $companyId);
            $runMeta = [
                'policySnapshot' => $policySnapshot,
            ];
            if ($taxGovernancePolicy !== null) {
                $runMeta['taxGovernancePolicy'] = self::serializeTaxGovernancePolicySnapshot($taxGovernancePolicy);
            }

            $payrollTimezone = (string) ($policySnapshot['payrollTimezone'] ?? config('app.timezone', 'UTC'));
            $periodStart = Carbon::create($period->period_year, $period->period_month, 1, 0, 0, 0, $payrollTimezone)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $asOfDate = (string) ($policySnapshot['draftDataAsOfDate'] ?? $periodEnd->toDateString());
            $asOf = Carbon::parse($asOfDate, $payrollTimezone)->endOfDay();

            $draftsQuery = HcmPayrollRun::query()
                ->where('hcm_payroll_period_id', $period->id)
                ->where('status', HcmPayrollRun::STATUS_DRAFT)
                ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY);
            self::applyTenantScope($draftsQuery, $companyId);
            $drafts = $draftsQuery->get();
            foreach ($drafts as $draft) {
                $draft->lines()->delete();
                $draft->delete();
            }

            $run = HcmPayrollRun::query()->create([
                'company_id' => $companyId,
                'hcm_payroll_period_id' => $period->id,
                'purpose' => HcmPayrollRun::PURPOSE_MONTHLY,
                'status' => HcmPayrollRun::STATUS_DRAFT,
                'calculated_at' => now(),
                'hcm_tax_governance_policy_id' => $taxGovernancePolicy?->id,
                'hcm_tax_governance_policy_version' => $taxGovernancePolicy?->version,
                'meta' => $runMeta,
            ]);

            $upahPokokQuery = HcmSalaryComponent::query()
                ->where('code', 'upah_pokok')
                ->where('is_active', true);
            self::applyTenantScope($upahPokokQuery, $companyId);
            $upahPokok = $upahPokokQuery->first();

            $fixedAllowanceQuery = HcmSalaryComponent::query()
                ->where('code', 'tunjangan_tetap')
                ->where('is_active', true);
            self::applyTenantScope($fixedAllowanceQuery, $companyId);
            $fixedAllowanceComponent = $fixedAllowanceQuery->first();

            if ($fixedAllowanceComponent === null) {
                $fixedAllowanceFallbackQuery = HcmSalaryComponent::query()
                    ->where('kind', 'addition')
                    ->where('category', 'fixed_allowance')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id');
                self::applyTenantScope($fixedAllowanceFallbackQuery, $companyId);
                $fixedAllowanceComponent = $fixedAllowanceFallbackQuery->first();
            }

            $overtimeComponent = HcmSalaryComponent::resolveForOvertimePay();
            $bpjsHealthQuery = HcmSalaryComponent::query()->where('code', 'iuran_bpjs_kes_pekerja')->where('is_active', true);
            self::applyTenantScope($bpjsHealthQuery, $companyId);
            $bpjsHealthEmployeeComponent = $bpjsHealthQuery->first();
            $bpjsJhtQuery = HcmSalaryComponent::query()->where('code', 'iuran_jht_pekerja')->where('is_active', true);
            self::applyTenantScope($bpjsJhtQuery, $companyId);
            $bpjsJhtEmployeeComponent = $bpjsJhtQuery->first();
            $bpjsJpQuery = HcmSalaryComponent::query()->where('code', 'iuran_jp_pekerja')->where('is_active', true);
            self::applyTenantScope($bpjsJpQuery, $companyId);
            $bpjsJpEmployeeComponent = $bpjsJpQuery->first();
            $pph21Query = HcmSalaryComponent::query()->where('code', 'pph21_ter')->where('is_active', true);
            self::applyTenantScope($pph21Query, $companyId);
            $pph21Component = $pph21Query->first();
            $overtimeCalculator = app(OvertimePayCalculator::class);
            $overtimeRuleResolver = app(PayrollWorkRuleResolver::class);
            $snapshotService = app(EmployeeSnapshotService::class);
            $leaveHolidayAdjuster = app(PayrollLeaveHolidayAdjuster::class);

            $resignedUserIds = HcmResignation::query()
                ->where('status', 'approved')
                ->whereDate('resignation_date', '<=', $asOf->toDateString())
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $terminatedUserIds = HcmTermination::query()
                ->where('status', 'approved')
                ->whereDate('termination_date', '<=', $asOf->toDateString())
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $blockedUserIds = collect(array_merge($resignedUserIds, $terminatedUserIds))->unique()->values()->all();

            $users = User::query()
                ->with('employeeProfile')
                ->whereHas('employeeProfile', function ($q) use ($companyId): void {
                    if ($companyId !== null) {
                        $q->where(function ($q2) use ($companyId): void {
                            $q2->where('company_id', $companyId)->orWhereNull('company_id');
                        });
                    }
                })
                ->when($blockedUserIds !== [], fn ($q) => $q->whereNotIn('id', $blockedUserIds))
                ->orderBy('id')
                ->get()
                ->filter(function (User $user) use ($snapshotService, $asOf): bool {
                    $profile = $user->employeeProfile;
                    if ($profile === null) {
                        return false;
                    }

                    $employment = $snapshotService->latestEmployment($profile, $asOf);
                    $status = strtolower((string) ($employment?->employment_status ?? $profile->getRawOriginal('employment_status') ?? 'active'));
                    $contract = $snapshotService->latestContract($profile, $asOf);
                    $contractStatus = strtolower((string) ($contract?->status ?? 'active'));

                    if (! in_array($status, ['active', 'probation'], true)) {
                        return false;
                    }

                    return $contract === null || $contractStatus === 'active';
                })
                ->values();

            $assignmentQuery = HcmEmployeePayrollItemAssignment::query()
                ->with(['payrollItem.salaryComponent'])
                ->where('is_active', true)
                ->whereIn('user_id', $users->pluck('id')->all())
                ->where(function ($q) use ($asOf): void {
                    $q->whereNull('effective_start_date')
                        ->orWhereDate('effective_start_date', '<=', $asOf->toDateString());
                })
                ->where(function ($q) use ($asOf): void {
                    $q->whereNull('effective_end_date')
                        ->orWhereDate('effective_end_date', '>=', $asOf->toDateString());
                });
            self::applyTenantScope($assignmentQuery, $companyId);
            $assignmentsByUser = $assignmentQuery
                ->get()
                ->groupBy('user_id');

            $carryoverPayload = self::resolveCarryoverOvertimeForPeriod($period, $asOf, $companyId);
            $carryoverRequestsByUser = collect($carryoverPayload['requestsByUser'] ?? []);
            $carryoverSourceRunByRequestId = is_array($carryoverPayload['sourceRunByRequestId'] ?? null)
                ? $carryoverPayload['sourceRunByRequestId']
                : [];

            $lateOvertimeQuery = OvertimeRequest::query()
                ->whereIn('user_id', $users->pluck('id')->all())
                ->where('status', 'approved')
                ->whereDate('work_date', '>', $asOf->toDateString())
                ->whereDate('work_date', '<=', $periodEnd->toDateString())
                ->orderBy('work_date')
                ->orderBy('id');
            self::applyTenantScope($lateOvertimeQuery, $companyId);

            $lateOvertimeTotalCount = (clone $lateOvertimeQuery)->count();
            $lateOvertimeEntries = $lateOvertimeQuery
                ->limit(200)
                ->get()
                ->map(fn (OvertimeRequest $request): array => [
                    'requestId' => (int) $request->id,
                    'userId' => (int) $request->user_id,
                    'workDate' => $request->work_date?->toDateString(),
                    'minutes' => (int) $request->minutes,
                    'status' => (string) $request->status,
                    'postedAt' => $request->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();

            $lateAssignmentQuery = HcmEmployeePayrollItemAssignment::query()
                ->with(['payrollItem'])
                ->where('is_active', true)
                ->whereIn('user_id', $users->pluck('id')->all())
                ->whereNotNull('effective_start_date')
                ->whereDate('effective_start_date', '>', $asOf->toDateString())
                ->whereDate('effective_start_date', '<=', $periodEnd->toDateString())
                ->orderBy('effective_start_date')
                ->orderBy('id');
            self::applyTenantScope($lateAssignmentQuery, $companyId);

            $lateAssignmentTotalCount = (clone $lateAssignmentQuery)->count();
            $lateAssignmentEntries = $lateAssignmentQuery
                ->limit(200)
                ->get()
                ->map(fn (HcmEmployeePayrollItemAssignment $assignment): array => [
                    'assignmentId' => (int) $assignment->id,
                    'userId' => (int) $assignment->user_id,
                    'payrollItemId' => (int) $assignment->hcm_payroll_item_id,
                    'payrollItemCode' => (string) ($assignment->payrollItem?->code ?? ''),
                    'amount' => round((float) $assignment->amount, 2),
                    'effectiveStartDate' => $assignment->effective_start_date?->toDateString(),
                    'effectiveEndDate' => $assignment->effective_end_date?->toDateString(),
                    'postedAt' => $assignment->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();

            $meta = is_array($run->meta) ? $run->meta : [];
            $meta['lateArrivalBuffer'] = [
                'capturedAt' => now()->toIso8601String(),
                'asOfDate' => $asOf->toDateString(),
                'periodStartDate' => $periodStart->toDateString(),
                'periodEndDate' => $periodEnd->toDateString(),
                'hasLateArrivals' => ($lateOvertimeTotalCount + $lateAssignmentTotalCount) > 0,
                'migrationMode' => 'next-period-date-based-rollover',
                'sources' => [
                    'overtimeRequests' => [
                        'totalCount' => $lateOvertimeTotalCount,
                        'entries' => $lateOvertimeEntries,
                    ],
                    'payrollItemAssignments' => [
                        'totalCount' => $lateAssignmentTotalCount,
                        'entries' => $lateAssignmentEntries,
                    ],
                ],
            ];
            $run->meta = $meta;
            $run->save();

            foreach ($users as $user) {
                $profile = $user->employeeProfile;
                if ($profile === null) {
                    continue;
                }

                $sortOrder = 0;
                $taxableGross = 0.0;
                $compensation = $snapshotService->latestCompensation($profile, $asOf);

                // Selalu satu baris upah pokok agar karyawan eligible tetap muncul di run (termasuk gaji 0).
                $base = max(0.0, (float) ($compensation?->base_salary ?? $profile->getRawOriginal('base_salary') ?? 0));
                HcmPayrollLine::query()->create([
                    'company_id' => $companyId,
                    'hcm_payroll_run_id' => $run->id,
                    'user_id' => $user->id,
                    'hcm_salary_component_id' => $upahPokok?->id,
                    'component_code' => $upahPokok?->code ?? 'upah_pokok',
                    'component_name' => $upahPokok?->name ?? 'Upah pokok',
                    'kind' => 'addition',
                    'category' => $upahPokok?->category ?? 'basic_wage',
                    'amount' => round($base, 2),
                    'sort_order' => $sortOrder++,
                    'meta' => [
                        'source' => 'employee_compensations.base_salary',
                        'userName' => $user->name,
                        'affectsNetPay' => (bool) ($upahPokok?->affects_net_pay ?? true),
                    ],
                ]);

                if ((bool) ($upahPokok?->include_pph21_ter_gross ?? true)) {
                    $taxableGross += $base;
                }

                $fixed = max(0.0, (float) ($compensation?->fixed_allowance ?? $profile->getRawOriginal('fixed_allowance') ?? 0));
                if ($fixed > 0) {
                    HcmPayrollLine::query()->create([
                        'company_id' => $companyId,
                        'hcm_payroll_run_id' => $run->id,
                        'user_id' => $user->id,
                        'hcm_salary_component_id' => $fixedAllowanceComponent?->id,
                        'component_code' => $fixedAllowanceComponent?->code ?? 'tunjangan_tetap',
                        'component_name' => $fixedAllowanceComponent?->name ?? 'Tunjangan tetap',
                        'kind' => 'addition',
                        'category' => $fixedAllowanceComponent?->category ?? 'fixed_allowance',
                        'amount' => round($fixed, 2),
                        'sort_order' => $sortOrder++,
                        'meta' => [
                            'source' => 'employee_compensations.fixed_allowance',
                            'userName' => $user->name,
                            'affectsNetPay' => (bool) ($fixedAllowanceComponent?->affects_net_pay ?? true),
                        ],
                    ]);

                    if ((bool) ($fixedAllowanceComponent?->include_pph21_ter_gross ?? true)) {
                        $taxableGross += $fixed;
                    }
                }

                $customAssignments = collect($assignmentsByUser->get($user->id, []))
                    ->filter(function (HcmEmployeePayrollItemAssignment $assignment): bool {
                        return $assignment->payrollItem !== null
                            && (bool) $assignment->payrollItem->is_active
                            && (float) $assignment->amount > 0;
                    })
                    ->sortBy(fn (HcmEmployeePayrollItemAssignment $assignment) => [
                        (int) ($assignment->payrollItem?->sort_order ?? 0),
                        (int) $assignment->id,
                    ])
                    ->values();

                foreach ($customAssignments as $assignment) {
                    $item = $assignment->payrollItem;
                    if ($item === null) {
                        continue;
                    }

                    $master = $item->salaryComponent;
                    $amount = round((float) $assignment->amount, 2);
                    if ($amount <= 0) {
                        continue;
                    }

                    HcmPayrollLine::query()->create([
                        'company_id' => $companyId,
                        'hcm_payroll_run_id' => $run->id,
                        'user_id' => $user->id,
                        'hcm_salary_component_id' => $item->hcm_salary_component_id,
                        'component_code' => $item->code,
                        'component_name' => $item->name,
                        'kind' => $item->kind,
                        'category' => $item->category,
                        'amount' => $amount,
                        'sort_order' => $sortOrder++,
                        'meta' => [
                            'source' => 'employee_payroll_item_assignments',
                            'assignmentId' => (int) $assignment->id,
                            'payrollItemId' => (int) $item->id,
                            'userName' => $user->name,
                            'affectsNetPay' => (bool) ($master?->affects_net_pay ?? true),
                        ],
                    ]);

                    if ((string) $item->kind === 'addition' && (bool) ($master?->include_pph21_ter_gross ?? false)) {
                        $taxableGross += $amount;
                    }
                }

                $approvedOvertimeQuery = OvertimeRequest::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->whereDate('work_date', '>=', $periodStart->toDateString())
                    ->whereDate('work_date', '<=', $asOf->toDateString())
                    ->orderBy('work_date')
                    ->orderBy('id');
                self::applyTenantScope($approvedOvertimeQuery, $companyId);
                $approvedOvertime = $approvedOvertimeQuery->get();

                $carryoverOvertime = collect($carryoverRequestsByUser->get($user->id, []))
                    ->filter(fn ($request) => $request instanceof OvertimeRequest)
                    ->values();

                $approvedOvertime = $approvedOvertime
                    ->concat($carryoverOvertime)
                    ->unique('id')
                    ->values();

                $overtimeMinutes = (int) $approvedOvertime->sum('minutes');
                $overtimePayRaw = 0.0;
                $overtimeRoundedPerRequestTotal = 0.0;
                $resolvedPolicies = [];
                $carryoverRequestIds = $carryoverOvertime
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
                $carryoverSourceRunIds = collect($carryoverRequestIds)
                    ->map(fn (int $requestId) => isset($carryoverSourceRunByRequestId[$requestId]) ? (int) $carryoverSourceRunByRequestId[$requestId] : null)
                    ->filter(fn ($id) => is_int($id) && $id > 0)
                    ->unique()
                    ->values()
                    ->all();
                foreach ($approvedOvertime as $request) {
                    $resolvedRule = $overtimeRuleResolver->resolveForOvertimeRequest($request, $companyId);
                    $calc = $overtimeCalculator->calculate(
                        $base,
                        $fixed,
                        (int) $request->minutes,
                        (string) ($resolvedRule['dayType'] ?? 'workday'),
                        (int) ($resolvedRule['weeklyWorkDays'] ?? 5),
                        true
                    );
                    $overtimePayRaw += (float) ($calc['totalOvertimePayRaw'] ?? $calc['totalOvertimePay'] ?? 0);
                    $overtimeRoundedPerRequestTotal += (float) ($calc['totalOvertimePay'] ?? 0);
                    $resolvedPolicies[] = [
                        'requestId' => (int) $request->id,
                        'workDate' => $request->work_date?->toDateString(),
                        'minutes' => (int) $request->minutes,
                        'dayType' => (string) ($resolvedRule['dayType'] ?? 'workday'),
                        'weeklyWorkDays' => (int) ($resolvedRule['weeklyWorkDays'] ?? 5),
                        'source' => (string) ($resolvedRule['source'] ?? ''),
                        'isCarryoverLateArrival' => in_array((int) $request->id, $carryoverRequestIds, true),
                        'carryoverSourceRunId' => $carryoverSourceRunByRequestId[(int) $request->id] ?? null,
                    ];
                }

                $overtimePay = round($overtimePayRaw, 2);

                if ($overtimePay > 0) {
                    HcmPayrollLine::query()->create([
                        'company_id' => $companyId,
                        'hcm_payroll_run_id' => $run->id,
                        'user_id' => $user->id,
                        'hcm_salary_component_id' => $overtimeComponent?->id,
                        'component_code' => $overtimeComponent?->code ?? 'upah_lembur',
                        'component_name' => $overtimeComponent?->name ?? 'Upah lembur',
                        'kind' => 'addition',
                        'category' => $overtimeComponent?->category ?? 'overtime',
                        'amount' => round($overtimePay, 2),
                        'sort_order' => $sortOrder++,
                        'meta' => [
                            'source' => 'approved_overtime_requests',
                            'userName' => $user->name,
                            'affectsNetPay' => (bool) ($overtimeComponent?->affects_net_pay ?? true),
                            'approvedRequestIds' => $approvedOvertime->pluck('id')->values()->all(),
                            'approvedMinutes' => $overtimeMinutes,
                            'calculationMode' => 'resolved_request_rules',
                            'roundingStrategy' => 'sum_raw_then_round_once',
                            'rawTotalOvertimePay' => $overtimePayRaw,
                            'roundedPerRequestTotalOvertimePay' => round($overtimeRoundedPerRequestTotal, 2),
                            'resolvedPolicies' => $resolvedPolicies,
                            'carryoverLateArrivalRequestIds' => $carryoverRequestIds,
                            'carryoverLateArrivalSourceRunIds' => $carryoverSourceRunIds,
                        ],
                    ]);

                    if ((bool) ($overtimeComponent?->include_pph21_ter_gross ?? true)) {
                        $taxableGross += $overtimePay;
                    }
                }

                // --- H3: Leave/Holiday integration (off by default via feature flag) ---
                $leaveAdjustment = $leaveHolidayAdjuster->adjust($user, $period, $companyId, $base, $fixed);
                if ($leaveAdjustment['enabled']) {
                    if ($leaveAdjustment['unpaidLeaveAmount'] > 0) {
                        $unpaidComponent = self::resolveOrCreateComponent(
                            $companyId,
                            'potongan_cuti_unpaid',
                            'Potongan cuti tanpa gaji',
                            'deduction',
                            'other_deduction'
                        );
                        if ($unpaidComponent !== null) {
                            HcmPayrollLine::query()->create([
                                'company_id' => $companyId,
                                'hcm_payroll_run_id' => $run->id,
                                'user_id' => $user->id,
                                'hcm_salary_component_id' => $unpaidComponent->id,
                                'component_code' => $unpaidComponent->code,
                                'component_name' => $unpaidComponent->name,
                                'kind' => 'deduction',
                                'category' => $unpaidComponent->category ?? 'other_deduction',
                                'amount' => $leaveAdjustment['unpaidLeaveAmount'],
                                'sort_order' => $sortOrder++,
                                'meta' => [
                                    'source' => 'leave_requests:unpaid',
                                    'userName' => $user->name,
                                    'affectsNetPay' => (bool) ($unpaidComponent->affects_net_pay ?? true),
                                    'unpaidLeaveDays' => $leaveAdjustment['unpaidLeaveDays'],
                                    'workingDaysInMonth' => $leaveAdjustment['workingDaysInMonth'],
                                    'dailyRate' => $leaveAdjustment['dailyRate'],
                                    'unpaidLeaveRequestIds' => $leaveAdjustment['unpaidLeaveRequestIds'],
                                ],
                            ]);
                            // Unpaid leave menurunkan taxable gross (upah yang dibayar berkurang).
                            $taxableGross = max(0.0, $taxableGross - $leaveAdjustment['unpaidLeaveAmount']);
                        }
                    }

                    if ($leaveAdjustment['holidayWorkAmount'] > 0) {
                        $holidayComponent = self::resolveOrCreateComponent(
                            $companyId,
                            'tunjangan_kerja_libur',
                            'Tunjangan kerja hari libur',
                            'addition',
                            'irregular_allowance'
                        );
                        if ($holidayComponent !== null) {
                            HcmPayrollLine::query()->create([
                                'company_id' => $companyId,
                                'hcm_payroll_run_id' => $run->id,
                                'user_id' => $user->id,
                                'hcm_salary_component_id' => $holidayComponent->id,
                                'component_code' => $holidayComponent->code,
                                'component_name' => $holidayComponent->name,
                                'kind' => 'addition',
                                'category' => $holidayComponent->category ?? 'irregular_allowance',
                                'amount' => $leaveAdjustment['holidayWorkAmount'],
                                'sort_order' => $sortOrder++,
                                'meta' => [
                                    'source' => 'attendance_records:holiday_work',
                                    'userName' => $user->name,
                                    'affectsNetPay' => (bool) ($holidayComponent->affects_net_pay ?? true),
                                    'holidayWorkDays' => $leaveAdjustment['holidayWorkDays'],
                                    'holidayDates' => $leaveAdjustment['holidayDates'],
                                    'dailyRate' => $leaveAdjustment['dailyRate'],
                                    'multiplier' => $leaveAdjustment['holidayWorkMultiplier'],
                                ],
                            ]);
                            if ((bool) ($holidayComponent->include_pph21_ter_gross ?? true)) {
                                $taxableGross += $leaveAdjustment['holidayWorkAmount'];
                            }
                        }
                    }
                }

                $bpjsHealthBase = $base + $fixed;
                $bpjsTkBase = $base + $fixed;
                $taxProfile = $snapshotService->latestTaxProfile($profile, $asOf);

                self::addPercentDeductionLine($run->id, $user->id, $sortOrder, $bpjsHealthEmployeeComponent, $bpjsHealthBase, [
                    'source' => 'bpjs_health_employee',
                    'userName' => $user->name,
                    'basisAmount' => round($bpjsHealthBase, 2),
                ], $companyId);
                self::addPercentDeductionLine($run->id, $user->id, $sortOrder, $bpjsJhtEmployeeComponent, $bpjsTkBase, [
                    'source' => 'bpjs_jht_employee',
                    'userName' => $user->name,
                    'basisAmount' => round($bpjsTkBase, 2),
                ], $companyId);
                self::addPercentDeductionLine($run->id, $user->id, $sortOrder, $bpjsJpEmployeeComponent, $bpjsTkBase, [
                    'source' => 'bpjs_jp_employee',
                    'userName' => $user->name,
                    'basisAmount' => round($bpjsTkBase, 2),
                ], $companyId);

                $pph21Amount = self::calculateMonthlyPph21($taxableGross, (string) ($taxProfile?->tax_status ?? 'TK0'));
                if ($pph21Component !== null && $pph21Amount > 0) {
                    $taxStatusUsed = (string) ($taxProfile?->tax_status ?? 'TK0');
                    HcmPayrollLine::query()->create([
                        'company_id' => $companyId,
                        'hcm_payroll_run_id' => $run->id,
                        'user_id' => $user->id,
                        'hcm_salary_component_id' => $pph21Component->id,
                        'component_code' => $pph21Component->code,
                        'component_name' => $pph21Component->name,
                        'kind' => 'deduction',
                        'category' => $pph21Component->category,
                        'amount' => round($pph21Amount, 2),
                        'sort_order' => $sortOrder++,
                        'meta' => [
                            'source' => 'pph21_ter_lookup',
                            'userName' => $user->name,
                            'affectsNetPay' => (bool) ($pph21Component->affects_net_pay ?? true),
                            'monthlyTaxableGross' => round($taxableGross, 2),
                            'pph21TerCategory' => self::resolveTerCategory($taxStatusUsed),
                            'ptkpAnnual' => self::resolvePtkpAnnual($taxStatusUsed),
                            'taxStatusUsed' => $taxStatusUsed,
                            'taxStatusSource' => $taxProfile?->tax_status ? 'employee_tax_profiles' : 'fallback_tk0',
                            'missingTaxProfile' => $taxProfile?->tax_status ? false : true,
                        ],
                    ]);
                }
            }

            return $run->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function addPercentDeductionLine(int $runId, int $userId, int &$sortOrder, ?HcmSalaryComponent $component, float $basisAmount, array $meta = [], ?int $companyId = null): void
    {
        if ($component === null || $basisAmount <= 0) {
            return;
        }

        $percent = (float) ($component->default_percent ?? 0);
        if ($percent <= 0) {
            return;
        }

        $amount = round($basisAmount * ($percent / 100), 2);
        if ($amount <= 0) {
            return;
        }

        HcmPayrollLine::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_run_id' => $runId,
            'user_id' => $userId,
            'hcm_salary_component_id' => $component->id,
            'component_code' => $component->code,
            'component_name' => $component->name,
            'kind' => 'deduction',
            'category' => $component->category,
            'amount' => $amount,
            'sort_order' => $sortOrder++,
            'meta' => array_merge($meta, [
                'affectsNetPay' => (bool) ($component->affects_net_pay ?? true),
                'defaultPercent' => round($percent, 4),
                'percentBasis' => $component->percent_basis,
            ]),
        ]);
    }

    private static function applyTenantScope($query, ?int $companyId): void
    {
        if ($companyId === null) {
            return;
        }

        $query->where(function ($q) use ($companyId): void {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }

    /**
     * Cari komponen salary per tenant berdasarkan code; jika belum ada, buat
     * baris master default. Dipakai oleh integrasi cuti/libur (H3) supaya tenant
     * yang mengaktifkan flag tidak perlu provisioning manual terlebih dahulu.
     */
    private static function resolveOrCreateComponent(
        ?int $companyId,
        string $code,
        string $name,
        string $kind,
        string $category
    ): ?HcmSalaryComponent {
        $query = HcmSalaryComponent::query()
            ->where('code', $code)
            ->where('is_active', true);
        self::applyTenantScope($query, $companyId);
        $existing = $query->first();
        if ($existing !== null) {
            return $existing;
        }

        // Default flags: deduction cuti unpaid & addition tunjangan libur keduanya
        // mempengaruhi net pay dan masuk basis PPh21 TER (gross).
        return HcmSalaryComponent::query()->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'kind' => $kind,
            'category' => $category,
            'is_active' => true,
            'affects_net_pay' => true,
            'include_pph21_ter_gross' => true,
            'include_bpjs_health_wage_base' => false,
            'include_bpjs_tk_wage_base' => false,
            'include_thr_calculation_base' => false,
            'is_system_locked' => false,
            'sort_order' => 9000,
        ]);
    }

    private static function calculateMonthlyPph21(float $monthlyTaxableGross, string $taxStatus = 'TK0'): float
    {
        if ($monthlyTaxableGross <= 0) {
            return 0.0;
        }

        $category = self::resolveTerCategory($taxStatus);
        $table = self::TER_TABLES[$category] ?? self::TER_TABLES['A'];
        $rate = 0.0;

        foreach ($table as [$upperBound, $tableRate]) {
            $rate = $tableRate;
            if ($monthlyTaxableGross <= $upperBound) {
                break;
            }
        }

        return round($monthlyTaxableGross * $rate, 2);
    }

    private static function resolveTerCategory(string $taxStatus): string
    {
        $taxKey = self::normalizeTaxStatus($taxStatus);

        return self::TER_STATUS_TO_CATEGORY[$taxKey] ?? 'A';
    }

    private static function resolvePtkpAnnual(string $taxStatus): float
    {
        $taxKey = self::normalizeTaxStatus($taxStatus);

        return match ($taxKey) {
            'TK1', 'K0' => 58_500_000.0,
            'TK2', 'K1' => 63_000_000.0,
            'TK3', 'K2' => 67_500_000.0,
            'K3' => 72_000_000.0,
            default => 54_000_000.0,
        };
    }

    private static function normalizeTaxStatus(string $taxStatus): string
    {
        $taxKey = strtoupper(str_replace(['/', ' '], '', trim($taxStatus)));

        return match ($taxKey) {
            'TK' => 'TK0',
            'K' => 'K0',
            default => $taxKey,
        };
    }

    /**
     * @return array{requestsByUser:array<int,array<int,OvertimeRequest>>,sourceRunByRequestId:array<int,int>}
     */
    private static function resolveCarryoverOvertimeForPeriod(HcmPayrollPeriod $period, Carbon $asOf, ?int $companyId): array
    {
        $sourceRunsQuery = HcmPayrollRun::query()
            ->with('period')
            ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->where('hcm_payroll_period_id', '!=', $period->id)
            ->orderByDesc('id');
        self::applyTenantScope($sourceRunsQuery, $companyId);

        $sourceRuns = $sourceRunsQuery->get();
        $targetYear = (int) $period->period_year;
        $targetMonth = (int) $period->period_month;

        $carryoverRequestIds = [];
        $sourceRunByRequestId = [];

        foreach ($sourceRuns as $sourceRun) {
            $meta = is_array($sourceRun->meta) ? $sourceRun->meta : [];
            $buffer = is_array($meta['lateArrivalBuffer'] ?? null) ? $meta['lateArrivalBuffer'] : [];
            $migration = is_array($buffer['migration'] ?? null) ? $buffer['migration'] : [];

            if (($migration['targetPeriodYear'] ?? null) !== $targetYear || ($migration['targetPeriodMonth'] ?? null) !== $targetMonth) {
                continue;
            }

            if (! in_array((string) ($migration['status'] ?? ''), ['queued', 'migrated'], true)) {
                continue;
            }

            $entries = $buffer['sources']['overtimeRequests']['entries'] ?? [];
            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                $requestId = (int) ($entry['requestId'] ?? 0);
                if ($requestId <= 0) {
                    continue;
                }

                $carryoverRequestIds[] = $requestId;
                $sourceRunByRequestId[$requestId] = (int) $sourceRun->id;
            }
        }

        $carryoverRequestIds = array_values(array_unique($carryoverRequestIds));
        if ($carryoverRequestIds === []) {
            return [
                'requestsByUser' => [],
                'sourceRunByRequestId' => [],
            ];
        }

        $carryoverRequests = OvertimeRequest::query()
            ->whereIn('id', $carryoverRequestIds)
            ->where('status', 'approved')
            ->whereDate('work_date', '<=', $asOf->toDateString())
            ->orderBy('work_date')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->values()->all())
            ->toArray();

        return [
            'requestsByUser' => $carryoverRequests,
            'sourceRunByRequestId' => $sourceRunByRequestId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolvePayrollPolicySnapshot(HcmPayrollPeriod $period, ?int $companyId): array
    {
        $service = app(PayrollMonthlySettingsService::class);

        return $service->snapshotForPeriod(
            (int) $period->period_year,
            (int) $period->period_month,
            $companyId,
        );
    }

    private static function resolveTaxGovernancePolicyForPeriod(HcmPayrollPeriod $period, ?int $companyId): ?HcmTaxGovernancePolicy
    {
        if ($companyId === null) {
            return null;
        }

        $asOf = Carbon::create((int) $period->period_year, (int) $period->period_month, 1)
            ->endOfMonth()
            ->toDateString();

        return HcmTaxGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->orderByRaw("case status when 'published' then 1 when 'approved' then 2 when 'submitted' then 3 when 'draft' then 4 else 5 end")
            ->orderByDesc('effective_start_date')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeTaxGovernancePolicySnapshot(HcmTaxGovernancePolicy $policy): array
    {
        return [
            'id' => (int) $policy->id,
            'uuid' => $policy->uuid,
            'policyCode' => $policy->policy_code,
            'version' => $policy->version !== null ? (int) $policy->version : null,
            'effectiveStartDate' => $policy->effective_start_date?->toDateString(),
            'effectiveEndDate' => $policy->effective_end_date?->toDateString(),
            'status' => $policy->status,
        ];
    }
}
