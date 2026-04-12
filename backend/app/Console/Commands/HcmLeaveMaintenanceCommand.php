<?php

namespace App\Console\Commands;

use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeProfile;
use App\Models\LeaveLedger;
use App\Models\LeavePolicyAssignment;
use App\Services\Hcm\LeaveLedgerService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class HcmLeaveMaintenanceCommand extends Command
{
    protected $signature = 'hcm:leave-maintenance
        {--mode=all : all|monthly-accrual|yearly-carry|daily-expire}
        {--date= : Reference date (Y-m-d), default today}
        {--force : Run monthly/yearly flow regardless of cycle date}';

    protected $description = 'Run leave maintenance jobs (monthly accrual, yearly carry-forward, daily expiration).';

    public function __construct(private readonly LeaveLedgerService $ledgerService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $mode = strtolower((string) $this->option('mode'));
        $dateOption = $this->option('date');
        $force = (bool) $this->option('force');

        if (! in_array($mode, ['all', 'monthly-accrual', 'yearly-carry', 'daily-expire'], true)) {
            $this->error('Invalid --mode. Allowed: all|monthly-accrual|yearly-carry|daily-expire');

            return self::FAILURE;
        }

        try {
            $asOf = $dateOption
                ? Carbon::parse((string) $dateOption)->startOfDay()
                : now()->startOfDay();
        } catch (\Throwable $e) {
            $this->error('Invalid --date format. Use Y-m-d.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('leave_types') || ! Schema::hasTable('leave_policies') || ! Schema::hasTable('leave_policy_assignments') || ! Schema::hasTable('employee_leave_balances') || ! Schema::hasTable('leave_ledger')) {
            $this->warn('Leave foundation tables are not available yet. Run migrations first.');

            return self::SUCCESS;
        }

        $summary = [
            'monthly-accrual' => 0,
            'yearly-carry' => 0,
            'daily-expire' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        if ($mode === 'all' || $mode === 'monthly-accrual') {
            if ($force || $asOf->isLastOfMonth()) {
                $summary['monthly-accrual'] = $this->runMonthlyAccrual($asOf);
            } else {
                $summary['skipped']++;
            }
        }

        if ($mode === 'all' || $mode === 'yearly-carry') {
            if ($force || ($asOf->month === 1 && $asOf->day === 1)) {
                $summary['yearly-carry'] = $this->runYearlyCarryForward($asOf);
            } else {
                $summary['skipped']++;
            }
        }

        if ($mode === 'all' || $mode === 'daily-expire') {
            $summary['daily-expire'] = $this->runDailyExpire($asOf);
        }

        $this->info(sprintf(
            'Leave maintenance done (%s): monthly=%d yearly=%d daily=%d skipped=%d errors=%d',
            $asOf->toDateString(),
            $summary['monthly-accrual'],
            $summary['yearly-carry'],
            $summary['daily-expire'],
            $summary['skipped'],
            $summary['errors']
        ));

        return self::SUCCESS;
    }

    private function activeAssignments(Carbon $asOf): Builder
    {
        return LeavePolicyAssignment::query()
            ->with(['policy.leaveType'])
            ->whereDate('effective_date', '<=', $asOf->toDateString())
            ->where(function (Builder $q) use ($asOf): void {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $asOf->toDateString());
            });
    }

    private function runMonthlyAccrual(Carbon $asOf): int
    {
        $processed = 0;
        $monthRef = $asOf->format('Y-m');

        $this->activeAssignments($asOf)
            ->whereHas('policy', function (Builder $q): void {
                $q->where('is_earned_leave', true)->where('days_per_year', '>', 0);
            })
            ->chunkById(200, function ($rows) use (&$processed, $asOf, $monthRef): void {
                foreach ($rows as $assignment) {
                    $policy = $assignment->policy;
                    $leaveType = $policy?->leaveType;
                    if (! $policy || ! $leaveType || ! $leaveType->is_active || ! $leaveType->deduct_from_balance) {
                        continue;
                    }

                    $refId = implode(':', ['monthly-accrual', $monthRef, $policy->id, $assignment->employee_id, $leaveType->id]);
                    $exists = LeaveLedger::query()
                        ->where('reference_type', 'system_monthly_accrual')
                        ->where('reference_id', $refId)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    $profile = EmployeeProfile::query()->where('user_id', $assignment->employee_id)->first();
                    if ($profile && $profile->hire_date) {
                        $serviceMonths = Carbon::parse($profile->hire_date)->startOfDay()->diffInMonths($asOf, false);
                        if ($serviceMonths < (int) $policy->min_service_months) {
                            continue;
                        }
                    }

                    $amount = round(((float) $policy->days_per_year) / 12, 2);
                    if ($amount <= 0) {
                        continue;
                    }

                    $this->ledgerService->post([
                        'companyId' => $assignment->company_id,
                        'employeeId' => (int) $assignment->employee_id,
                        'leaveTypeId' => (int) $leaveType->id,
                        'policyId' => (int) $policy->id,
                        'transactionType' => 'accrual',
                        'amount' => $amount,
                        'occurredOn' => $asOf->toDateString(),
                        'referenceType' => 'system_monthly_accrual',
                        'referenceId' => $refId,
                        'notes' => 'Monthly earned leave accrual',
                        'createdBy' => null,
                    ]);

                    $processed++;
                }
            });

        return $processed;
    }

    private function runYearlyCarryForward(Carbon $asOf): int
    {
        $processed = 0;
        $currentYear = (int) $asOf->year;
        $previousYear = $currentYear - 1;

        $this->activeAssignments($asOf)
            ->whereHas('policy', function (Builder $q): void {
                $q->where('carry_forward', true);
            })
            ->chunkById(200, function ($rows) use (&$processed, $asOf, $currentYear, $previousYear): void {
                foreach ($rows as $assignment) {
                    $policy = $assignment->policy;
                    $leaveType = $policy?->leaveType;
                    if (! $policy || ! $leaveType || ! $leaveType->is_active || ! $leaveType->deduct_from_balance) {
                        continue;
                    }

                    $inRef = implode(':', ['yearly-carry-in', $currentYear, $policy->id, $assignment->employee_id, $leaveType->id]);
                    $outRef = implode(':', ['yearly-carry-out', $previousYear, $policy->id, $assignment->employee_id, $leaveType->id]);
                    $alreadyDone = LeaveLedger::query()
                        ->where('reference_type', 'system_yearly_carry')
                        ->where('reference_id', $inRef)
                        ->exists();
                    if ($alreadyDone) {
                        continue;
                    }

                    $prevBalance = EmployeeLeaveBalance::query()
                        ->where('employee_id', $assignment->employee_id)
                        ->where('leave_type_id', $leaveType->id)
                        ->where('year', $previousYear)
                        ->first();
                    if (! $prevBalance || (float) $prevBalance->balance <= 0) {
                        continue;
                    }

                    $carryCap = $policy->max_carry_days !== null
                        ? (float) $policy->max_carry_days
                        : (float) $prevBalance->balance;
                    $carryAmount = min((float) $prevBalance->balance, max($carryCap, 0));
                    if ($carryAmount <= 0) {
                        continue;
                    }

                    $this->ledgerService->post([
                        'companyId' => $assignment->company_id,
                        'employeeId' => (int) $assignment->employee_id,
                        'leaveTypeId' => (int) $leaveType->id,
                        'policyId' => (int) $policy->id,
                        'transactionType' => 'carry_out',
                        'amount' => -1 * $carryAmount,
                        'occurredOn' => Carbon::create($previousYear, 12, 31)->toDateString(),
                        'referenceType' => 'system_yearly_carry',
                        'referenceId' => $outRef,
                        'notes' => sprintf('Carry-forward outgoing (%d -> %d)', $previousYear, $currentYear),
                        'createdBy' => null,
                        'enforceNoNegative' => true,
                    ]);

                    $this->ledgerService->post([
                        'companyId' => $assignment->company_id,
                        'employeeId' => (int) $assignment->employee_id,
                        'leaveTypeId' => (int) $leaveType->id,
                        'policyId' => (int) $policy->id,
                        'transactionType' => 'carry_forward',
                        'amount' => $carryAmount,
                        'occurredOn' => $asOf->toDateString(),
                        'referenceType' => 'system_yearly_carry',
                        'referenceId' => $inRef,
                        'notes' => sprintf('Carry-forward incoming (%d -> %d)', $previousYear, $currentYear),
                        'createdBy' => null,
                    ]);

                    $processed++;
                }
            });

        return $processed;
    }

    private function runDailyExpire(Carbon $asOf): int
    {
        $processed = 0;
        $currentYear = (int) $asOf->year;

        $this->activeAssignments($asOf)
            ->whereHas('policy', function (Builder $q): void {
                $q->where('carry_forward', true)->whereNotNull('expire_after_days');
            })
            ->chunkById(200, function ($rows) use (&$processed, $asOf, $currentYear): void {
                foreach ($rows as $assignment) {
                    $policy = $assignment->policy;
                    $leaveType = $policy?->leaveType;
                    if (! $policy || ! $leaveType || ! $leaveType->is_active || ! $leaveType->deduct_from_balance) {
                        continue;
                    }

                    $expireOn = Carbon::create($currentYear, 1, 1)
                        ->addDays((int) $policy->expire_after_days)
                        ->startOfDay();
                    if ($asOf->lt($expireOn)) {
                        continue;
                    }

                    $refId = implode(':', ['carry-expire', $currentYear, $policy->id, $assignment->employee_id, $leaveType->id]);
                    $alreadyDone = LeaveLedger::query()
                        ->where('reference_type', 'system_carry_expire')
                        ->where('reference_id', $refId)
                        ->exists();
                    if ($alreadyDone) {
                        continue;
                    }

                    $row = EmployeeLeaveBalance::query()
                        ->where('employee_id', $assignment->employee_id)
                        ->where('leave_type_id', $leaveType->id)
                        ->where('year', $currentYear)
                        ->first();
                    if (! $row || (float) $row->balance <= 0) {
                        continue;
                    }

                    $expirable = min((float) $row->balance, (float) $row->carried_forward);
                    if ($expirable <= 0) {
                        continue;
                    }

                    $this->ledgerService->post([
                        'companyId' => $assignment->company_id,
                        'employeeId' => (int) $assignment->employee_id,
                        'leaveTypeId' => (int) $leaveType->id,
                        'policyId' => (int) $policy->id,
                        'transactionType' => 'expire',
                        'amount' => -1 * $expirable,
                        'occurredOn' => $asOf->toDateString(),
                        'referenceType' => 'system_carry_expire',
                        'referenceId' => $refId,
                        'notes' => sprintf('Expired carry-forward after %d days', (int) $policy->expire_after_days),
                        'createdBy' => null,
                        'enforceNoNegative' => true,
                    ]);

                    $processed++;
                }
            });

        return $processed;
    }
}
