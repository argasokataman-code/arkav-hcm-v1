<?php

use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Services\Hcm\RefreshOpenPayrollDraftsService;
use App\Support\CronjobSettings;
use App\Support\PayrollDraftBuilder;
use App\Jobs\SendPaymentReminder;
use App\Jobs\TerminateExpiredSubscriptionsJob;
use App\Jobs\SuspendServicesForOverdueInvoicesJob;
use App\Jobs\CheckEmployeeCountLimitsJob;
use App\Jobs\ConvertExpiredTrialsToPendingPaymentJob;
use App\Jobs\ProcessRecurringSubscriptionBilling;
use App\Jobs\ReconcilePendingRenewalPayments;
use App\Jobs\ApplySubscriptionChangeJob;
use App\Jobs\ClearRevenueTransactionsJob;
use App\Jobs\CloseMonthlyFinancialReportJob;
use App\Models\HcmSubscriptionChangeRequest;
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
    app(RefreshOpenPayrollDraftsService::class)->refresh();
})->name('hcm-payroll-refresh-open-period')
    ->description('Refresh monthly payroll draft at 00:00 WIB for the active open period.')
    ->timezone((string) ($payrollRefresh['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($payrollRefresh['time'] ?? '00:00'))
    ->withoutOverlapping(60);
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
    ->everyThirtyMinutes()
    ->withoutOverlapping(60);
if (($recurringBilling['enabled'] ?? true) !== true) {
    $recurringBillingTask->skip(fn (): bool => true);
}

$reconcileRenewal = CronjobSettings::get('saas_reconcile_pending_renewals');
$reconcileRenewalTask = Schedule::job(new ReconcilePendingRenewalPayments())
    ->name('saas-reconcile-pending-renewals')
    ->description('Reconcile pending renewal payments against gateway status and surface anomalies')
    ->timezone((string) ($reconcileRenewal['timezone'] ?? 'Asia/Jakarta'))
    ->everyThirtyMinutes()
    ->withoutOverlapping(25);
if (($reconcileRenewal['enabled'] ?? true) !== true) {
    $reconcileRenewalTask->skip(fn (): bool => true);
}

$applyPlanChanges = CronjobSettings::get('saas_apply_subscription_plan_changes');
$applyPlanChangesTask = Schedule::call(function (): void {
    HcmSubscriptionChangeRequest::query()
        ->where('status', HcmSubscriptionChangeRequest::STATUS_APPROVED)
        ->whereIn('action', [
            HcmSubscriptionChangeRequest::ACTION_DOWNGRADE,
            HcmSubscriptionChangeRequest::ACTION_CANCEL,
        ])
        ->where(function ($query): void {
            $query->whereNull('effective_at')
                ->orWhere('effective_at', '<=', now());
        })
        ->orderBy('created_at')
        ->limit(200)
        ->pluck('id')
        ->each(function (string $id): void {
            dispatch(new ApplySubscriptionChangeJob($id));
        });
})->name('saas-apply-subscription-plan-changes')
    ->description('Apply approved tenant subscription change requests whose effective time has arrived')
    ->timezone((string) ($applyPlanChanges['timezone'] ?? 'Asia/Jakarta'))
    ->everyFifteenMinutes()
    ->withoutOverlapping(10);
if (($applyPlanChanges['enabled'] ?? true) !== true) {
    $applyPlanChangesTask->skip(fn (): bool => true);
}

$taxRevenueClearing = CronjobSettings::get('tax_revenue_clearing');
$taxRevenueClearingTask = Schedule::job(new ClearRevenueTransactionsJob())
    ->name('tax-revenue-clearing')
    ->description('Mark posted uncleared platform revenue transactions as cleared after grace window')
    ->timezone((string) ($taxRevenueClearing['timezone'] ?? 'Asia/Jakarta'))
    ->dailyAt((string) ($taxRevenueClearing['time'] ?? '01:10'))
    ->withoutOverlapping(30);
if (($taxRevenueClearing['enabled'] ?? true) !== true) {
    $taxRevenueClearingTask->skip(fn (): bool => true);
}

// AN-002 / AN-015 / AN-016: Lock monthly platform financial summary after 24h grace period.
// Dispatched on the first day of every month at 01:30 AM for the previous month.
$monthlyFinancialClose = CronjobSettings::get('tax_monthly_financial_close');
Schedule::call(function (): void {
    $prevMonth = now()->subMonth();
    dispatch(new CloseMonthlyFinancialReportJob((int) $prevMonth->year, (int) $prevMonth->month));
})->name('tax-monthly-financial-close')
    ->description('Lock monthly platform financial report with 24h grace period (AN-002/AN-015/AN-016)')
    ->timezone((string) ($monthlyFinancialClose['timezone'] ?? 'Asia/Jakarta'))
    ->monthlyOn(1, (string) ($monthlyFinancialClose['time'] ?? '01:30'))
    ->withoutOverlapping(120);

// UU PDP: Purge old completed erasure request records (data minimization — Pasal 43)
Schedule::command('pdp:purge-completed-erasures')
    ->name('pdp-purge-completed-erasures')
    ->description('Purge completed erasure request records older than 90 days (UU PDP data minimization)')
    ->timezone('Asia/Jakarta')
    ->monthly()
    ->withoutOverlapping(60);

// UU PDP M2: AI chat log retention (1 year)
Schedule::command('pdp:purge-ai-chat-logs')
    ->name('pdp-purge-ai-chat-logs')
    ->description('Purge AI chat logs older than configured retention window (default 1 year)')
    ->timezone('Asia/Jakarta')
    ->dailyAt('01:45')
    ->withoutOverlapping(60);

// UU PDP M3: attendance retention (5 years)
Schedule::command('pdp:purge-attendance-records')
    ->name('pdp-purge-attendance-records')
    ->description('Purge attendance records older than configured retention window (default 5 years)')
    ->timezone('Asia/Jakarta')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping(120);
