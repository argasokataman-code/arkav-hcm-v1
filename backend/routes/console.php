<?php

use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Support\PayrollDraftBuilder;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
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
    ->timezone('Asia/Jakarta')
    ->dailyAt('00:00');

Schedule::command('hcm:leave-maintenance --mode=monthly-accrual')
    ->name('hcm-leave-monthly-accrual')
    ->description('Post monthly earned-leave accrual on end of month.')
    ->timezone('Asia/Jakarta')
    ->dailyAt('00:10');

Schedule::command('hcm:leave-maintenance --mode=yearly-carry')
    ->name('hcm-leave-yearly-carry')
    ->description('Run yearly carry-forward on Jan 1.')
    ->timezone('Asia/Jakarta')
    ->dailyAt('00:15');

Schedule::command('hcm:leave-maintenance --mode=daily-expire')
    ->name('hcm-leave-daily-expire')
    ->description('Expire carry-forward balances after policy cutoff.')
    ->timezone('Asia/Jakarta')
    ->dailyAt('00:20');
