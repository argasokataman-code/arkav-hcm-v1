<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetPayrollData extends Command
{
    protected $signature = 'payroll:reset {--year= : Tahun spesifik (opsional)} {--month= : Bulan spesifik (opsional)}';

    protected $description = 'Reset semua data payroll (monthly + THR) untuk testing';

    public function handle(): int
    {
        $year = $this->option('year');
        $month = $this->option('month');

        $this->info('=== RESET PAYROLL DATA ===');

        if ($year) {
            $this->info("Filter: Tahun $year".($month ? " Bulan $month" : ''));
        } else {
            $this->warn('⚠️  HAPUS SEMUA DATA PAYROLL!');
        }

        if (! $this->confirm('Lanjutkan?')) {
            $this->info('Dibatalkan.');

            return 0;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        // Count before
        $before = [
            'periods' => DB::table('hcm_payroll_periods')->count(),
            'runs' => DB::table('hcm_payroll_runs')->count(),
            'lines' => DB::table('hcm_payroll_lines')->count(),
            'thr_batches' => DB::table('hcm_thr_batches')->count(),
        ];

        $this->info("\nData sebelum:");
        foreach ($before as $key => $count) {
            $this->info("  - $key: $count");
        }

        // Build query conditions
        $periodIds = [];
        if ($year) {
            $query = DB::table('hcm_payroll_periods')->where('period_year', $year);
            if ($month) {
                $query->where('period_month', $month);
            }
            $periodIds = $query->pluck('id')->toArray();
        }

        // Delete payroll lines
        if (! empty($periodIds)) {
            $deletedLines = DB::table('hcm_payroll_lines')
                ->whereIn('hcm_payroll_run_id', function ($q) use ($periodIds) {
                    $q->select('id')->from('hcm_payroll_runs')
                        ->whereIn('hcm_payroll_period_id', $periodIds);
                })->delete();
        } else {
            $deletedLines = DB::table('hcm_payroll_lines')->delete();
        }
        $this->info("\n✓ Deleted payroll lines: $deletedLines");

        // Delete payroll runs
        if (! empty($periodIds)) {
            $deletedRuns = DB::table('hcm_payroll_runs')
                ->whereIn('hcm_payroll_period_id', $periodIds)->delete();
        } else {
            $deletedRuns = DB::table('hcm_payroll_runs')->delete();
        }
        $this->info("✓ Deleted payroll runs: $deletedRuns");

        // Delete payroll periods
        if (! empty($periodIds)) {
            $deletedPeriods = DB::table('hcm_payroll_periods')
                ->whereIn('id', $periodIds)->delete();
        } else {
            $deletedPeriods = DB::table('hcm_payroll_periods')->delete();
        }
        $this->info("✓ Deleted payroll periods: $deletedPeriods");

        // Reset THR batches
        $thrQuery = DB::table('hcm_thr_batches');
        if ($year) {
            $thrQuery->where('calendar_year', $year);
        }
        $updatedThr = $thrQuery->update([
            'status' => 'draft',
            'assigned_at' => null,
            'assigned_by_user_id' => null,
            'hcm_payroll_period_id' => null,
            'hcm_payroll_run_id' => null,
        ]);
        $this->info("✓ Reset THR batches: $updatedThr");

        // Delete THR disbursements
        $disbQuery = DB::table('hcm_thr_disbursements');
        if ($year) {
            $disbQuery->whereIn('hcm_thr_batch_id', function ($q) use ($year) {
                $q->select('id')->from('hcm_thr_batches')->where('calendar_year', $year);
            });
        }
        $deletedDisb = $disbQuery->delete();
        $this->info("✓ Deleted THR disbursements: $deletedDisb");

        // Reset THR batch lines
        $linesQuery = DB::table('hcm_thr_batch_lines');
        if ($year) {
            $linesQuery->whereIn('hcm_thr_batch_id', function ($q) use ($year) {
                $q->select('id')->from('hcm_thr_batches')->where('calendar_year', $year);
            });
        }
        $updatedThrLines = $linesQuery->update([
            'payment_status' => 'unpaid',
            'payment_failure_reason' => null,
            'payment_gateway_ref' => null,
            'paid_at' => null,
            'slip_storage_path' => null,
            'slip_generated_at' => null,
            'slip_notify_sent_at' => null,
            'last_disbursement_id' => null,
        ]);
        $this->info("✓ Reset THR batch lines: $updatedThrLines");

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        // Count after
        $after = [
            'periods' => DB::table('hcm_payroll_periods')->count(),
            'runs' => DB::table('hcm_payroll_runs')->count(),
            'lines' => DB::table('hcm_payroll_lines')->count(),
            'thr_batches_draft' => DB::table('hcm_thr_batches')->where('status', 'draft')->count(),
        ];

        $this->info("\nData setelah:");
        foreach ($after as $key => $count) {
            $this->info("  - $key: $count");
        }

        $this->info("\n✅ Reset selesai!");

        return 0;
    }
}
