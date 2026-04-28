<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * CloseMonthlyFinancialReportJob
 *
 * AN-002: Prevents monthly-close race condition via Cache lock + idempotency check.
 * AN-015: Enforces 24-hour grace period after month end before locking the report.
 * AN-016: Warns (does not fail) when no active platform expense tax codes are found.
 *
 * Schedule: First day of each month at 01:00 AM (see routes/console.php).
 */
class CloseMonthlyFinancialReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Maximum seconds job may run before timing out.
     */
    public int $timeout = 120;

    public function __construct(
        public readonly int $year,
        public readonly int $month,
    ) {}

    public function handle(): void
    {
        $year  = $this->year;
        $month = $this->month;

        // AN-015: 24-hour grace period — do not lock until at least 24 hours after month end.
        $monthEnd = Carbon::create($year, $month)->endOfMonth();
        if (now()->lt($monthEnd->copy()->addHours(24))) {
            Log::info('CloseMonthlyFinancialReportJob: grace period not elapsed, re-queuing.', [
                'year' => $year, 'month' => $month,
                'grace_until' => $monthEnd->copy()->addHours(24)->toIso8601String(),
            ]);
            // Re-dispatch after grace period has passed (next 30-minute window)
            static::dispatch($year, $month)->delay(
                now()->diffInSeconds($monthEnd->copy()->addHours(24)) + 60
            );
            return;
        }

        // AN-002: Acquire distributed lock to prevent concurrent close jobs for the same period.
        $lockKey = "monthly_financial_close_{$year}_{$month}";
        $lock    = Cache::lock($lockKey, 120);

        $acquired = $lock->block(30, function () use ($year, $month): void {
            $this->executeClose($year, $month);
        });

        if ($acquired === false) {
            Log::warning('CloseMonthlyFinancialReportJob: could not acquire lock (another job running).', [
                'year' => $year, 'month' => $month,
            ]);
        }
    }

    private function executeClose(int $year, int $month): void
    {
        if (! Schema::hasTable('platform_monthly_financial_summaries')) {
            Log::error('CloseMonthlyFinancialReportJob: platform_monthly_financial_summaries table missing.', [
                'year' => $year, 'month' => $month,
            ]);
            return;
        }

        // AN-002: Idempotency — skip if this period is already locked.
        $existing = DB::table('platform_monthly_financial_summaries')
            ->where('report_year', $year)
            ->where('report_month', $month)
            ->first();

        if ($existing && $existing->report_status === 'locked') {
            Log::info('CloseMonthlyFinancialReportJob: period already locked, skipping.', [
                'year' => $year, 'month' => $month,
            ]);
            return;
        }

        // AN-016: Warn when no active expense tax codes exist — do NOT fail the job.
        $this->warnIfNoActiveTaxCodes($year, $month);

        // Calculate aggregate revenue for the period from cleared transactions.
        $periodStart = Carbon::create($year, $month, 1)->startOfDay()->toDateTimeString();
        $periodEnd   = Carbon::create($year, $month)->endOfMonth()->endOfDay()->toDateTimeString();

        $summary = DB::table('platform_revenue_transactions')
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->whereIn('status', ['posted', 'cleared'])
            ->select([
                DB::raw('SUM(amount) as gross_revenue'),
                DB::raw('SUM(CASE WHEN clearing_status = \'cleared\' THEN amount ELSE 0 END) as cleared_revenue'),
                DB::raw('SUM(CASE WHEN clearing_status = \'uncleared\' THEN amount ELSE 0 END) as uncleared_revenue'),
                DB::raw('SUM(CASE WHEN clearing_status = \'disputed\' THEN amount ELSE 0 END) as disputed_revenue'),
                DB::raw('SUM(CASE WHEN clearing_status = \'reversed\' THEN amount ELSE 0 END) as reversed_revenue'),
                DB::raw('SUM(tax_amount) as tax_amount'),
                DB::raw('SUM(net_amount) as net_revenue'),
            ])
            ->first();

        DB::transaction(function () use ($year, $month, $summary, $existing): void {
            $payload = [
                'gross_revenue'     => (float) ($summary->gross_revenue ?? 0),
                'cleared_revenue'   => (float) ($summary->cleared_revenue ?? 0),
                'uncleared_revenue' => (float) ($summary->uncleared_revenue ?? 0),
                'disputed_revenue'  => (float) ($summary->disputed_revenue ?? 0),
                'reversed_revenue'  => (float) ($summary->reversed_revenue ?? 0),
                'tax_amount'        => (float) ($summary->tax_amount ?? 0),
                'net_revenue'       => (float) ($summary->net_revenue ?? 0),
                'report_status'     => 'locked',
                'locked_at'         => now()->toDateTimeString(),
            ];

            if ($existing) {
                DB::table('platform_monthly_financial_summaries')
                    ->where('report_year', $year)
                    ->where('report_month', $month)
                    ->update(array_merge($payload, ['updated_at' => now()->toDateTimeString()]));
            } else {
                DB::table('platform_monthly_financial_summaries')->insert(array_merge($payload, [
                    'report_year'  => $year,
                    'report_month' => $month,
                    'created_at'   => now()->toDateTimeString(),
                    'updated_at'   => now()->toDateTimeString(),
                ]));
            }

            Log::info('CloseMonthlyFinancialReportJob: period locked successfully.', [
                'year'          => $year,
                'month'         => $month,
                'gross_revenue' => $payload['gross_revenue'],
                'net_revenue'   => $payload['net_revenue'],
            ]);
        });
    }

    /**
     * AN-016: Log warning when platform expense tax codes are absent.
     * This does not throw — the close can proceed; the warning triggers an alert in log aggregators.
     */
    private function warnIfNoActiveTaxCodes(int $year, int $month): void
    {
        try {
            if (! Schema::hasTable('platform_expense_tax_codes')) {
                Log::warning('CloseMonthlyFinancialReportJob: platform_expense_tax_codes table not found (AN-016).', [
                    'year' => $year, 'month' => $month,
                ]);
                return;
            }

            $activeCount = DB::table('platform_expense_tax_codes')
                ->where('is_active', true)
                ->count();

            if ($activeCount === 0) {
                Log::warning('CloseMonthlyFinancialReportJob: no active platform expense tax codes found (AN-016).', [
                    'year' => $year, 'month' => $month,
                    'note' => 'Revenue transactions closed without verified expense tax code coverage.',
                ]);
            }
        } catch (\Throwable $e) {
            // Never fail the job due to tax code check — just log.
            Log::warning('CloseMonthlyFinancialReportJob: tax code check failed (non-fatal).', [
                'year' => $year, 'month' => $month, 'error' => $e->getMessage(),
            ]);
        }
    }
}
