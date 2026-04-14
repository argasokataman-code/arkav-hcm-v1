<?php

use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Support\CronjobSettings;
use App\Support\PayrollDraftBuilder;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$payrollRefresh = CronjobSettings::get('payroll_refresh_open_period');

$payrollRefreshTask = Schedule::call(function (): void {
    $period = HcmPayrollPeriod::query()
        ->where('status', HcmPayrollPeriod::STATUS_OPEN)
        ->orderByDesc('period_year')
        ->orderByDesc('period_month')
        ->first();

    if ($period === null) {
        return;
    }

    $finalizedExists = HcmPayrollRun::query()
        ->where('hcm_payroll_period_id', $period->id)
        ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
        ->where('status', HcmPayrollRun::STATUS_FINALIZED)
        ->exists();

    if ($finalizedExists) {
        return;
    }

    PayrollDraftBuilder::rebuildDraftRun($period);
})->name('hcm-payroll-refresh-open-period')
    ->description('Refresh monthly payroll draft at 00:00 WIB for the active open period.')
    ->timezone((string) ($payrollRefresh['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($payrollRefresh['time'] ?? '00:00'));
if (($payrollRefresh['enabled'] ?? true) !== true) {
    $payrollRefreshTask->skip(fn (): bool => true);
}

$leaveMonthly = CronjobSettings::get('leave_monthly_accrual');
$leaveMonthlyTask = Schedule::command('hcm:leave-maintenance --mode=monthly-accrual')
    ->name('hcm-leave-monthly-accrual')
    ->description('Post monthly earned-leave accrual on end of month.')
    ->timezone((string) ($leaveMonthly['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($leaveMonthly['time'] ?? '00:10'));
if (($leaveMonthly['enabled'] ?? true) !== true) {
    $leaveMonthlyTask->skip(fn (): bool => true);
}

$leaveYearly = CronjobSettings::get('leave_yearly_carry');
$leaveYearlyTask = Schedule::command('hcm:leave-maintenance --mode=yearly-carry')
    ->name('hcm-leave-yearly-carry')
    ->description('Run yearly carry-forward on Jan 1.')
    ->timezone((string) ($leaveYearly['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($leaveYearly['time'] ?? '00:15'));
if (($leaveYearly['enabled'] ?? true) !== true) {
    $leaveYearlyTask->skip(fn (): bool => true);
}

$leaveDailyExpire = CronjobSettings::get('leave_daily_expire');
$leaveDailyExpireTask = Schedule::command('hcm:leave-maintenance --mode=daily-expire')
    ->name('hcm-leave-daily-expire')
    ->description('Expire carry-forward balances after policy cutoff.')
    ->timezone((string) ($leaveDailyExpire['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($leaveDailyExpire['time'] ?? '00:20'));
if (($leaveDailyExpire['enabled'] ?? true) !== true) {
    $leaveDailyExpireTask->skip(fn (): bool => true);
}
