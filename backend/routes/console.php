<?php

use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Support\CronjobSettings;
use App\Support\PayrollDraftBuilder;
use App\Jobs\SendPaymentReminder;
use App\Jobs\TerminateExpiredSubscriptionsJob;
use App\Jobs\SuspendServicesForOverdueInvoicesJob;
use App\Jobs\CheckEmployeeCountLimitsJob;
use App\Jobs\ConvertExpiredTrialsToPendingPaymentJob;
use App\Jobs\ProcessRecurringSubscriptionBilling;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$paymentReminder = CronjobSettings::get('payment_reminder');
$paymentReminderTask = Schedule::job(new SendPaymentReminder())
    ->name('cronjob-send-payment-reminder')
    ->description('Dispatch SendPaymentReminder job.')
    ->timezone((string) ($paymentReminder['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($paymentReminder['time'] ?? '08:00'));
if (($paymentReminder['enabled'] ?? true) !== true) {
    $paymentReminderTask->skip(fn (): bool => true);
}

$wilayahSync = CronjobSettings::get('wilayah_sync');
$wilayahSyncTask = Schedule::command('wilayah:sync')
    ->name('cronjob-wilayah-sync')
    ->description('Sync wilayah.id master data to local DB.')
    ->timezone((string) ($wilayahSync['timezone'] ?? 'Asia/Jakarta'))
    ->monthlyOn((int) ($wilayahSync['dayOfMonth'] ?? 1), (string) ($wilayahSync['time'] ?? '01:00'));
if (($wilayahSync['enabled'] ?? true) !== true) {
    $wilayahSyncTask->skip(fn (): bool => true);
}

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

// ============ SAAS SUBSCRIPTION MANAGEMENT ============

// Convert ended trials into pending_payment + invoice
$convertTrials = CronjobSettings::get('saas_convert_ended_trials');
$convertTrialsTask = Schedule::call(function () {
    dispatch(new ConvertExpiredTrialsToPendingPaymentJob());
})->name('saas-convert-ended-trials')
    ->description('Convert ended trials into pending_payment and generate invoices')
    ->timezone((string) ($convertTrials['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($convertTrials['time'] ?? '00:20'));
if (($convertTrials['enabled'] ?? true) !== true) {
    $convertTrialsTask->skip(fn (): bool => true);
}

// Auto-terminate subscriptions whose end_date has passed
$terminateExpired = CronjobSettings::get('saas_terminate_expired_subscriptions');
$terminateExpiredTask = Schedule::call(function () {
    dispatch(new TerminateExpiredSubscriptionsJob());
})->name('saas-terminate-expired-subscriptions')
    ->description('Auto-terminate subscriptions with expired end_date')
    ->timezone((string) ($terminateExpired['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($terminateExpired['time'] ?? '00:30'));
if (!(config('app.saas.auto_termination_enabled', true)) || (($terminateExpired['enabled'] ?? true) !== true)) {
    $terminateExpiredTask->skip(fn (): bool => true);
}

// Suspend services for overdue invoices (grace window in job)
$suspendOverdue = CronjobSettings::get('saas_suspend_overdue_services');
$suspendOverdueTask = Schedule::call(function () {
    dispatch(new SuspendServicesForOverdueInvoicesJob());
})->name('saas-suspend-overdue-services')
    ->description('Auto-suspend services with overdue unpaid invoices')
    ->timezone((string) ($suspendOverdue['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($suspendOverdue['time'] ?? '06:00'));
if (!(config('app.saas.auto_suspension_enabled', true)) || (($suspendOverdue['enabled'] ?? true) !== true)) {
    $suspendOverdueTask->skip(fn (): bool => true);
}

// Monitor employee count violations against plan limits
$checkEmployeeCount = CronjobSettings::get('saas_check_employee_count_limits');
$checkEmployeeCountTask = Schedule::call(function () {
    dispatch(new CheckEmployeeCountLimitsJob());
})->name('saas-check-employee-count-limits')
    ->description('Monitor and enforce employee count limits against subscription plans')
    ->timezone((string) ($checkEmployeeCount['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($checkEmployeeCount['time'] ?? '01:00'));
if (!(config('app.saas.employee_limit_enforcement_enabled', true)) || (($checkEmployeeCount['enabled'] ?? true) !== true)) {
    $checkEmployeeCountTask->skip(fn (): bool => true);
}

$recurringBilling = CronjobSettings::get('saas_recurring_billing');
$recurringBillingTask = Schedule::job(new ProcessRecurringSubscriptionBilling())
    ->name('saas-recurring-billing')
    ->description('Process subscription renewals and recurring billing tasks')
    ->timezone((string) ($recurringBilling['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($recurringBilling['time'] ?? '06:00'))
    ->withoutOverlapping(60);
if (($recurringBilling['enabled'] ?? true) !== true) {
    $recurringBillingTask->skip(fn (): bool => true);
}
