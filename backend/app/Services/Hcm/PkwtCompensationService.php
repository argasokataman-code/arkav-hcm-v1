<?php

namespace App\Services\Hcm;

use App\Models\EmployeeContract;
use App\Models\EmployeeProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmSalaryComponent;
use App\Support\WebsiteSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PkwtCompensationService
{
    public const REGULATION_LABEL = 'PP No. 35 Tahun 2021 (ringkas — verifikasi kebijakan internal HR/payroll)';

    /**
     * @return array{
     *     eligible: bool,
     *     status: string,
     *     monthsOfService: int,
     *     multiplier: float,
     *     referenceMonthlyWage: float,
     *     compensationAmount: float,
     *     contractStartDate: string,
     *     contractEndDate: string,
     *     notes: list<string>
     * }
     */
    public function calculate(
        string|\DateTimeInterface $contractStartDate,
        string|\DateTimeInterface $contractEndDate,
        float $baseMonthlySalary,
        float $fixedMonthlyAllowance = 0.0,
    ): array {
        $start = Carbon::parse($contractStartDate)->startOfDay();
        $end = Carbon::parse($contractEndDate)->startOfDay();
        $notes = [
            'Dasar kompensasi = gaji pokok + tunjangan tetap per bulan.',
            'Contract compensation dibayar saat kontrak selesai sesuai kebijakan perusahaan.',
            'Nominal final tetap perlu review HR/payroll sebelum dibayarkan atau diposting ke slip.',
        ];

        if ($end->lt($start)) {
            return [
                'eligible' => false,
                'status' => 'invalid_dates',
                'monthsOfService' => 0,
                'multiplier' => 0.0,
                'referenceMonthlyWage' => round(max(0, $baseMonthlySalary) + max(0, $fixedMonthlyAllowance), 2),
                'compensationAmount' => 0.0,
                'contractStartDate' => $start->toDateString(),
                'contractEndDate' => $end->toDateString(),
                'notes' => array_merge($notes, ['Tanggal akhir kontrak harus sama atau setelah tanggal mulai kontrak.']),
            ];
        }

        $months = $this->wholeMonthsBetween($start, $end);
        $reference = round(max(0, $baseMonthlySalary) + max(0, $fixedMonthlyAllowance), 2);

        if ($months < 1) {
            return [
                'eligible' => false,
                'status' => 'not_eligible',
                'monthsOfService' => $months,
                'multiplier' => 0.0,
                'referenceMonthlyWage' => $reference,
                'compensationAmount' => 0.0,
                'contractStartDate' => $start->toDateString(),
                'contractEndDate' => $end->toDateString(),
                'notes' => array_merge($notes, ['Masa kerja contract kurang dari 1 bulan penuh → kompensasi = 0.']),
            ];
        }

        $multiplier = round($months / 12.0, 6);
        $amount = round($reference * $multiplier, 2);

        return [
            'eligible' => $amount > 0,
            'status' => $months >= 12 ? 'due_full_or_more' : 'due_pro_rata',
            'monthsOfService' => $months,
            'multiplier' => $multiplier,
            'referenceMonthlyWage' => $reference,
            'compensationAmount' => $amount,
            'contractStartDate' => $start->toDateString(),
            'contractEndDate' => $end->toDateString(),
            'notes' => array_merge($notes, [
                sprintf('Formula ringkas: (%d / 12) × upah acuan.', $months),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeProfile(?EmployeeProfile $profile, ?Carbon $period = null): array
    {
        $period = ($period ?: now())->copy()->startOfMonth();
        $contractType = strtolower(trim((string) ($profile?->contract_type ?? 'permanent')));
        if ($contractType === 'pkwtt') {
            $contractType = 'permanent';
        }
        if ($contractType === 'pkwt') {
            $contractType = 'contract';
        }
        $contractType = in_array($contractType, ['contract', 'permanent'], true) ? $contractType : 'permanent';
        $start = $this->resolveContractStartDate($profile);
        $end = $profile?->contract_end_date ? Carbon::parse($profile->contract_end_date)->startOfDay() : null;
        $isDueThisMonth = $contractType === 'contract'
            && $end !== null
            && (int) $end->year === (int) $period->year
            && (int) $end->month === (int) $period->month;

        $calc = null;
        if ($isDueThisMonth && $start !== null && $end !== null) {
            $calc = $this->calculate(
                $start,
                $end,
                (float) ($profile?->base_salary ?? 0),
                (float) ($profile?->fixed_allowance ?? 0),
            );
        }

        return [
            'contractType' => $contractType,
            'contractTypeLabel' => $contractType === 'contract' ? 'Contract' : 'Permanent',
            'contractStartDate' => $start?->toDateString(),
            'contractEndDate' => $end?->toDateString(),
            'isDueThisMonth' => $isDueThisMonth,
            'estimatedCompensationThisMonth' => (float) ($calc['compensationAmount'] ?? 0),
            'monthsOfService' => (int) ($calc['monthsOfService'] ?? 0),
            'multiplier' => (float) ($calc['multiplier'] ?? 0),
            'referenceMonthlyWage' => (float) ($calc['referenceMonthlyWage'] ?? round(max(0, (float) ($profile?->base_salary ?? 0)) + max(0, (float) ($profile?->fixed_allowance ?? 0)), 2)),
        ];
    }

    /**
     * @return array{period: array<string, int>, summary: array<string, int|float>, lines: list<array<string, mixed>>, regulationReference: string}
     */
    public function previewForMonth(int $periodYear, int $periodMonth, ?int $companyId = null): array
    {
        $period = Carbon::create($periodYear, $periodMonth, 1)->startOfMonth();

        $contracts = EmployeeContract::query()
            ->with(['employee.user:id,name,email,created_at', 'employee.designationRef:id,name,department_id'])
            ->when($companyId !== null, function (Builder $query) use ($companyId): void {
                $query->whereHas('employee', function (Builder $profileQuery) use ($companyId): void {
                    $profileQuery->where(function (Builder $inner) use ($companyId): void {
                        $inner->where('company_id', $companyId)->orWhereNull('company_id');
                    });
                });
            })
            ->whereIn('contract_type', ['contract', 'pkwt'])
            ->whereNotNull('end_date')
            ->whereYear('end_date', $periodYear)
            ->whereMonth('end_date', $periodMonth)
            ->orderBy('end_date')
            ->orderBy('employee_id')
            ->get();

        $profiles = $contracts
            ->map(fn (EmployeeContract $contract) => $contract->employee)
            ->filter();

        $legacyFallback = EmployeeProfile::query()
            ->with(['user:id,name,email,created_at', 'designationRef:id,name,department_id'])
            ->when($companyId !== null, function (Builder $query) use ($companyId): void {
                $query->where(function (Builder $inner) use ($companyId): void {
                    $inner->where('company_id', $companyId)->orWhereNull('company_id');
                });
            })
            ->whereIn('contract_type', ['contract', 'pkwt'])
            ->whereNotNull('contract_end_date')
            ->whereYear('contract_end_date', $periodYear)
            ->whereMonth('contract_end_date', $periodMonth)
            ->when($profiles->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $profiles->pluck('id')->all()))
            ->orderBy('contract_end_date')
            ->orderBy('user_id')
            ->get();

        $profiles = $profiles->concat($legacyFallback)->unique('id')->values();

        $lines = $profiles->map(function (EmployeeProfile $profile) use ($period): array {
            $summary = $this->summarizeProfile($profile, $period);
            $calc = null;
            if (
                ($summary['contractType'] ?? 'permanent') === 'contract'
                && $summary['contractStartDate']
                && $summary['contractEndDate']
            ) {
                $calc = $this->calculate(
                    $summary['contractStartDate'],
                    $summary['contractEndDate'],
                    (float) ($profile->base_salary ?? 0),
                    (float) ($profile->fixed_allowance ?? 0),
                );
            }

            return [
                'userId' => $profile->user_id,
                'employeeNo' => sprintf('%s%04d', WebsiteSettings::prefixEmployee(), $profile->user_id),
                'fullName' => $profile->user?->name ?? 'Unknown',
                'email' => $profile->user?->email,
                'designation' => $profile->designationRef?->name ?: ($profile->designation ?: 'Employee'),
                'employmentStatus' => $profile->employment_status ?? 'active',
                'contractType' => $summary['contractType'],
                'contractStartDate' => $summary['contractStartDate'],
                'contractEndDate' => $summary['contractEndDate'],
                'baseSalary' => (float) ($profile->base_salary ?? 0),
                'fixedAllowance' => (float) ($profile->fixed_allowance ?? 0),
                'referenceMonthlyWage' => (float) ($calc['referenceMonthlyWage'] ?? $summary['referenceMonthlyWage']),
                'monthsOfService' => (int) ($calc['monthsOfService'] ?? $summary['monthsOfService']),
                'multiplier' => (float) ($calc['multiplier'] ?? $summary['multiplier']),
                'eligible' => (bool) ($calc['eligible'] ?? false),
                'status' => (string) ($calc['status'] ?? ($summary['isDueThisMonth'] ? 'due_review' : 'not_due')),
                'compensationAmount' => (float) ($calc['compensationAmount'] ?? $summary['estimatedCompensationThisMonth']),
            ];
        })
            ->filter(fn (array $row): bool => ($row['contractType'] ?? 'permanent') === 'contract')
            ->values();

        return [
            'period' => [
                'periodYear' => $periodYear,
                'periodMonth' => $periodMonth,
            ],
            'summary' => [
                'totalEmployees' => $lines->count(),
                'eligibleEmployees' => $lines->where('eligible', true)->count(),
                'grandTotal' => round((float) $lines->sum('compensationAmount'), 2),
            ],
            'lines' => $lines->all(),
            'regulationReference' => self::REGULATION_LABEL,
        ];
    }

    public function wholeMonthsBetween(Carbon $start, Carbon $end): int
    {
        if ($end->lt($start)) {
            return -1;
        }

        $months = ($end->year - $start->year) * 12 + ($end->month - $start->month);
        if ($end->day < $start->day) {
            $months--;
        }

        return max(0, $months);
    }

    public function currentRunForMonth(int $periodYear, int $periodMonth, ?int $companyId = null): ?HcmPayrollRun
    {
        $periodQuery = HcmPayrollPeriod::query()
            ->where('period_year', $periodYear)
            ->where('period_month', $periodMonth);
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->first();

        if ($period === null) {
            return null;
        }

        $runQuery = HcmPayrollRun::query()
            ->with(['period', 'lines.user:id,name'])
            ->where('hcm_payroll_period_id', $period->id)
            ->where('purpose', HcmPayrollRun::PURPOSE_PKWT_COMPENSATION)
            ->orderByRaw("CASE WHEN status = 'finalized' THEN 0 ELSE 1 END")
            ->orderByDesc('id');
        $this->applyTenantScope($runQuery, $companyId);

        return $runQuery->first();
    }

    /**
     * @return array{period:HcmPayrollPeriod,run:HcmPayrollRun,preview:array<string,mixed>}
     */
    public function createOrReplaceDraftRun(int $periodYear, int $periodMonth, ?int $finalizedByUserId = null, ?int $companyId = null): array
    {
        $preview = $this->previewForMonth($periodYear, $periodMonth, $companyId);
        $eligibleRows = collect($preview['lines'] ?? [])
            ->filter(fn (array $row): bool => ($row['contractType'] ?? 'permanent') === 'contract')
            ->filter(fn (array $row): bool => (bool) ($row['eligible'] ?? false) && (float) ($row['compensationAmount'] ?? 0) > 0)
            ->values();

        if ($eligibleRows->isEmpty()) {
            throw new \InvalidArgumentException('PKWT_COMPENSATION_EMPTY');
        }

        $component = $companyId !== null
            ? HcmSalaryComponent::ensurePkwtCompensationComponent($companyId)
            : HcmSalaryComponent::query()
                ->where('code', HcmSalaryComponent::CODE_PKWT_COMPENSATION)
                ->where('is_active', true)
                ->whereNull('company_id')
                ->first();

        if ($component === null) {
            throw new \InvalidArgumentException('PKWT_COMPENSATION_COMPONENT_MISSING');
        }

        return DB::transaction(function () use ($periodYear, $periodMonth, $finalizedByUserId, $eligibleRows, $component, $preview, $companyId): array {
            $period = HcmPayrollPeriod::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'period_year' => $periodYear,
                    'period_month' => $periodMonth,
                ],
                [
                    'company_id' => $companyId,
                    'status' => HcmPayrollPeriod::STATUS_OPEN,
                ],
            );

            $finalizedExists = HcmPayrollRun::query()
                ->where('hcm_payroll_period_id', $period->id)
                ->where('purpose', HcmPayrollRun::PURPOSE_PKWT_COMPENSATION)
                ->where('status', HcmPayrollRun::STATUS_FINALIZED)
                ->lockForUpdate()
                ->exists();

            if ($finalizedExists) {
                throw new \InvalidArgumentException('PKWT_COMPENSATION_FINALIZED_EXISTS');
            }

            $draftRuns = HcmPayrollRun::query()
                ->where('hcm_payroll_period_id', $period->id)
                ->where('purpose', HcmPayrollRun::PURPOSE_PKWT_COMPENSATION)
                ->where('status', HcmPayrollRun::STATUS_DRAFT)
                ->lockForUpdate()
                ->get();

            foreach ($draftRuns as $draft) {
                $draft->lines()->delete();
                $draft->delete();
            }

            $run = HcmPayrollRun::query()->create([
                'company_id' => $companyId,
                'hcm_payroll_period_id' => $period->id,
                'purpose' => HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
                'status' => HcmPayrollRun::STATUS_DRAFT,
                'calculated_at' => now(),
                'finalized_by_user_id' => $finalizedByUserId,
            ]);

            $sortOrder = 0;
            foreach ($eligibleRows as $row) {
                HcmPayrollLine::query()->create([
                    'company_id' => $companyId,
                    'hcm_payroll_run_id' => $run->id,
                    'user_id' => (int) ($row['userId'] ?? 0),
                    'hcm_salary_component_id' => $component->id,
                    'component_code' => $component->code,
                    'component_name' => $component->name,
                    'kind' => 'addition',
                    'category' => $component->category,
                    'amount' => round((float) ($row['compensationAmount'] ?? 0), 2),
                    'sort_order' => $sortOrder++,
                    'meta' => [
                        'source' => 'pkwt_compensation',
                        'userName' => $row['fullName'] ?? null,
                        'employeeNo' => $row['employeeNo'] ?? null,
                        'contractStartDate' => $row['contractStartDate'] ?? null,
                        'contractEndDate' => $row['contractEndDate'] ?? null,
                        'monthsOfService' => (int) ($row['monthsOfService'] ?? 0),
                        'multiplier' => (float) ($row['multiplier'] ?? 0),
                        'referenceMonthlyWage' => round((float) ($row['referenceMonthlyWage'] ?? 0), 2),
                        'affectsNetPay' => true,
                    ],
                ]);
            }

            return [
                'period' => $period->fresh(),
                'run' => $run->fresh(['period', 'lines.user:id,name']),
                'preview' => $preview,
            ];
        });
    }

    private function resolveContractStartDate(?EmployeeProfile $profile): ?Carbon
    {
        if ($profile?->contract_start_date) {
            return Carbon::parse($profile->contract_start_date)->startOfDay();
        }
        if ($profile?->hire_date) {
            return Carbon::parse($profile->hire_date)->startOfDay();
        }
        if ($profile?->user?->created_at) {
            return Carbon::parse($profile->user->created_at)->startOfDay();
        }

        return null;
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
}
