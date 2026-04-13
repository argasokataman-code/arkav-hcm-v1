<?php

namespace App\Support;

use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmResignation;
use App\Models\HcmSalaryComponent;
use App\Models\HcmTermination;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Hcm\EmployeeSnapshotService;
use App\Services\Hcm\OvertimePayCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class PayrollDraftBuilder
{
    public static function rebuildDraftRun(HcmPayrollPeriod $period, ?int $companyId = null): HcmPayrollRun
    {
        return DB::transaction(function () use ($period, $companyId) {
            $companyId = $companyId ?? ($period->company_id ? (int) $period->company_id : null);

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
            $snapshotService = app(EmployeeSnapshotService::class);
            $asOf = Carbon::create($period->period_year, $period->period_month, 1)->endOfMonth();

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

            foreach ($users as $user) {
                $profile = $user->employeeProfile;
                if ($profile === null) {
                    continue;
                }

                $sortOrder = 0;
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
                }

                $approvedOvertime = OvertimeRequest::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->whereYear('work_date', $period->period_year)
                    ->whereMonth('work_date', $period->period_month)
                    ->orderBy('work_date')
                    ->get();

                $overtimeMinutes = (int) $approvedOvertime->sum('minutes');
                $overtimePay = 0.0;
                foreach ($approvedOvertime as $request) {
                    $calc = $overtimeCalculator->calculate($base, $fixed, (int) $request->minutes, 'workday', 5);
                    $overtimePay += (float) ($calc['totalOvertimePay'] ?? 0);
                }

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
                            'calculationMode' => 'workday_default',
                        ],
                    ]);
                }

                $bpjsHealthBase = $base + $fixed;
                $bpjsTkBase = $base + $fixed;
                $taxableGross = $base + $fixed + $overtimePay;
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
                            'source' => 'pph21_annualized_estimate',
                            'userName' => $user->name,
                            'affectsNetPay' => (bool) ($pph21Component->affects_net_pay ?? true),
                            'monthlyTaxableGross' => round($taxableGross, 2),
                            'ptkpAnnual' => 54000000,
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

    private static function calculateMonthlyPph21(float $monthlyTaxableGross, string $taxStatus = 'TK0'): float
    {
        if ($monthlyTaxableGross <= 0) {
            return 0.0;
        }

        $annualizedGross = $monthlyTaxableGross * 12;
        $taxKey = strtoupper(str_replace(['/', ' '], '', trim($taxStatus)));
        if ($taxKey === 'TK') {
            $taxKey = 'TK0';
        }
        if ($taxKey === 'K') {
            $taxKey = 'K0';
        }
        $ptkpAnnual = match ($taxKey) {
            'TK1', 'K0' => 58_500_000.0,
            'TK2', 'K1' => 63_000_000.0,
            'TK3', 'K2' => 67_500_000.0,
            'K3' => 72_000_000.0,
            default => 54_000_000.0,
        };
        $pkp = max(0.0, $annualizedGross - $ptkpAnnual);

        if ($pkp <= 0) {
            return 0.0;
        }

        $brackets = [
            [60_000_000.0, 0.05],
            [190_000_000.0, 0.15],
            [250_000_000.0, 0.25],
            [4_500_000_000.0, 0.30],
            [INF, 0.35],
        ];

        $remaining = $pkp;
        $annualTax = 0.0;
        foreach ($brackets as [$cap, $rate]) {
            if ($remaining <= 0) {
                break;
            }
            $chunk = min($remaining, $cap);
            $annualTax += $chunk * $rate;
            $remaining -= $chunk;
        }

        return round($annualTax / 12, 2);
    }
}
