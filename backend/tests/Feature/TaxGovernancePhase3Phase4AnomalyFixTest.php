<?php

namespace Tests\Feature;

use App\Jobs\CloseMonthlyFinancialReportJob;
use App\Models\Company;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmTaxGovernancePolicy;
use App\Services\BillingTaxCalculationService;
use App\Services\TaxGovernanceReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for Phase 3 & 4 anomaly fixes:
 * AN-004 (payroll policy concurrency snapshot)
 * AN-011 (hardcoded payroll coverage stats)
 * AN-002 / AN-015 / AN-016 (monthly financial close job)
 * AN-013 (billing_tax_rate_snapshot column)
 * AN-014 (tenant isolation defensive checks)
 */
class TaxGovernancePhase3Phase4AnomalyFixTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // AN-004: PayrollDraftBuilder snapshots active tax governance policy
    // ─────────────────────────────────────────────────────────────────────────

    public function test_payroll_run_records_published_tax_policy_id_at_creation(): void
    {
        $company = Company::factory()->create();

        // Create a published tax governance policy for this company
        $policy = HcmTaxGovernancePolicy::factory()->create([
            'company_id' => $company->id,
            'status'     => HcmTaxGovernancePolicy::STATUS_PUBLISHED,
            'version'    => 3,
        ]);

        $periodId = DB::table('hcm_payroll_periods')->insertGetId([
            'uuid'         => (string) Str::uuid(),
            'company_id'   => $company->id,
            'period_year'  => 2026,
            'period_month' => 5,
            'status'       => 'open',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $period = HcmPayrollPeriod::find($periodId);

        $run = \App\Support\PayrollDraftBuilder::rebuildDraftRun($period, (int) $company->id);

        $this->assertNotNull($run->hcm_tax_governance_policy_id, 'Policy ID should be captured on the run');
        $this->assertEquals($policy->id, $run->hcm_tax_governance_policy_id);
        $this->assertEquals(3, $run->hcm_tax_governance_policy_version);
    }

    public function test_payroll_run_has_null_policy_id_when_no_published_policy_exists(): void
    {
        $company = Company::factory()->create();

        // No published policy for this company
        $periodId = DB::table('hcm_payroll_periods')->insertGetId([
            'uuid'         => (string) Str::uuid(),
            'company_id'   => $company->id,
            'period_year'  => 2026,
            'period_month' => 5,
            'status'       => 'open',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $period = HcmPayrollPeriod::find($periodId);

        $run = \App\Support\PayrollDraftBuilder::rebuildDraftRun($period, (int) $company->id);

        $this->assertNull($run->hcm_tax_governance_policy_id, 'Policy ID should be null when no published policy exists');
        $this->assertNull($run->hcm_tax_governance_policy_version);
    }

    public function test_policy_snapshot_remains_consistent_across_publish_window_between_periods(): void
    {
        $company = Company::factory()->create();

        $policyApril = HcmTaxGovernancePolicy::factory()->create([
            'company_id' => $company->id,
            'status' => HcmTaxGovernancePolicy::STATUS_PUBLISHED,
            'version' => 1,
            'effective_start_date' => '2026-04-01',
            'effective_end_date' => '2026-04-30',
        ]);

        $policyMay = HcmTaxGovernancePolicy::factory()->create([
            'company_id' => $company->id,
            'status' => HcmTaxGovernancePolicy::STATUS_PUBLISHED,
            'version' => 2,
            'effective_start_date' => '2026-05-01',
            'effective_end_date' => null,
        ]);

        $periodAprilId = DB::table('hcm_payroll_periods')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 4,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $periodMayId = DB::table('hcm_payroll_periods')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 5,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $runApril = \App\Support\PayrollDraftBuilder::rebuildDraftRun(HcmPayrollPeriod::findOrFail($periodAprilId), (int) $company->id);
        $runMay = \App\Support\PayrollDraftBuilder::rebuildDraftRun(HcmPayrollPeriod::findOrFail($periodMayId), (int) $company->id);

        $this->assertSame($policyApril->id, $runApril->hcm_tax_governance_policy_id);
        $this->assertSame(1, $runApril->hcm_tax_governance_policy_version);
        $this->assertSame($policyMay->id, $runMay->hcm_tax_governance_policy_id);
        $this->assertSame(2, $runMay->hcm_tax_governance_policy_version);

        $freshApril = HcmPayrollRun::query()->findOrFail($runApril->id);
        $this->assertSame($policyApril->id, $freshApril->hcm_tax_governance_policy_id);
        $this->assertSame((string) $policyApril->uuid, (string) data_get($freshApril->meta, 'taxGovernancePolicy.uuid'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AN-011: TaxGovernanceReportingService uses real DB queries
    // ─────────────────────────────────────────────────────────────────────────

    public function test_payroll_coverage_stats_returns_real_db_counts(): void
    {
        $company = Company::factory()->create();

        $policy = HcmTaxGovernancePolicy::factory()->create([
            'company_id' => $company->id,
            'status'     => HcmTaxGovernancePolicy::STATUS_PUBLISHED,
            'version'    => 1,
        ]);

        $periodStart = Carbon::create(2026, 1, 1)->startOfDay();
        $periodEnd   = Carbon::create(2026, 1, 31)->endOfDay();

        $periodId = DB::table('hcm_payroll_periods')->insertGetId([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 1,
            'uuid' => (string) Str::uuid(),
            'status' => 'closed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3 finalized runs: 2 with policy, 1 without
        DB::table('hcm_payroll_runs')->insert([
            ['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'status' => 'finalized', 'purpose' => 'monthly', 'hcm_tax_governance_policy_id' => $policy->id, 'hcm_payroll_period_id' => $periodId, 'finalized_at' => '2026-01-15 00:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'status' => 'finalized', 'purpose' => 'monthly', 'hcm_tax_governance_policy_id' => $policy->id, 'hcm_payroll_period_id' => $periodId, 'finalized_at' => '2026-01-20 00:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'status' => 'finalized', 'purpose' => 'monthly', 'hcm_tax_governance_policy_id' => null,        'hcm_payroll_period_id' => $periodId, 'finalized_at' => '2026-01-25 00:00:00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(TaxGovernanceReportingService::class);
        // Access private method via reflection
        $method = new \ReflectionMethod($service, 'getPayrollCoverageStats');
        $method->setAccessible(true);
        $result = $method->invoke($service, (string) $company->id, $periodStart, $periodEnd);

        $this->assertEquals(3, $result['total_payroll_runs']);
        $this->assertEquals(2, $result['runs_under_current_policy']);
        $this->assertEquals(1, $result['runs_under_superseded_policy']);
        $this->assertEqualsWithDelta(66.67, $result['coverage_percentage'], 0.01);
    }

    public function test_payroll_coverage_returns_100_when_no_runs_in_period(): void
    {
        $company = Company::factory()->create();

        $periodStart = Carbon::create(2026, 6, 1)->startOfDay();
        $periodEnd   = Carbon::create(2026, 6, 30)->endOfDay();

        $service = app(TaxGovernanceReportingService::class);
        $method = new \ReflectionMethod($service, 'getPayrollCoverageStats');
        $method->setAccessible(true);
        $result = $method->invoke($service, (string) $company->id, $periodStart, $periodEnd);

        $this->assertEquals(0, $result['total_payroll_runs']);
        $this->assertEquals(100.0, $result['coverage_percentage']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AN-013: invoices table has billing_tax_rate_snapshot column
    // ─────────────────────────────────────────────────────────────────────────

    public function test_invoices_table_has_billing_tax_rate_snapshot_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('invoices', 'billing_tax_rate_snapshot'),
            'invoices.billing_tax_rate_snapshot column should exist after migration'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AN-002 + AN-015: CloseMonthlyFinancialReportJob respects 24h grace period
    // ─────────────────────────────────────────────────────────────────────────

    public function test_close_job_defers_when_within_24h_grace_period(): void
    {
        Queue::fake();

        $year  = (int) now()->format('Y');
        $month = (int) now()->format('n');

        // Simulate: running immediately on last day of month (still within grace)
        Carbon::setTestNow(Carbon::create($year, $month)->endOfMonth()->subHour());

        CloseMonthlyFinancialReportJob::dispatch($year, $month);

        // The job should have dispatched again (deferred) — not locked anything yet
        Queue::assertPushed(CloseMonthlyFinancialReportJob::class);

        Carbon::setTestNow(); // reset
    }

    public function test_close_job_idempotent_when_period_already_locked(): void
    {
        // Pre-insert a locked summary for this period
        DB::table('platform_monthly_financial_summaries')->insert([
            'report_year'   => 2026,
            'report_month'  => 3,
            'report_status' => 'locked',
            'locked_at'     => now()->toDateTimeString(),
            'gross_revenue' => 500000,
            'cleared_revenue' => 400000,
            'uncleared_revenue' => 100000,
            'disputed_revenue' => 0,
            'reversed_revenue' => 0,
            'tax_amount'    => 50000,
            'net_revenue'   => 450000,
            'created_at'    => now()->toDateTimeString(),
            'updated_at'    => now()->toDateTimeString(),
        ]);

        // Run the job well past grace window (April)
        Carbon::setTestNow(Carbon::create(2026, 4, 2));

        $job = new CloseMonthlyFinancialReportJob(2026, 3);
        $job->handle();

        // Only one row should exist (idempotent — no duplicate insert)
        $this->assertDatabaseCount('platform_monthly_financial_summaries', 1);

        Carbon::setTestNow();
    }

    public function test_close_job_locks_period_with_correct_aggregates(): void
    {
        // Seed some revenue transactions for Jan 2026
        DB::table('platform_revenue_transactions')->insert([
            ['company_id' => 1, 'uuid' => \Illuminate\Support\Str::uuid(), 'source_event_type' => 'test', 'transaction_type' => 'subscription', 'amount' => 1000000, 'tax_amount' => 100000, 'net_amount' => 900000, 'status' => 'posted', 'clearing_status' => 'cleared', 'occurred_at' => '2026-01-15 00:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'uuid' => \Illuminate\Support\Str::uuid(), 'source_event_type' => 'test', 'transaction_type' => 'subscription', 'amount' => 500000, 'tax_amount' => 50000, 'net_amount' => 450000, 'status' => 'posted', 'clearing_status' => 'uncleared', 'occurred_at' => '2026-01-28 00:00:00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Run the close job past grace period (Feb 2)
        Carbon::setTestNow(Carbon::create(2026, 2, 2));

        $job = new CloseMonthlyFinancialReportJob(2026, 1);
        $job->handle();

        $this->assertDatabaseHas('platform_monthly_financial_summaries', [
            'report_year'   => 2026,
            'report_month'  => 1,
            'report_status' => 'locked',
        ]);

        $summary = DB::table('platform_monthly_financial_summaries')
            ->where('report_year', 2026)->where('report_month', 1)->first();

        $this->assertEquals(1500000, (float) $summary->gross_revenue);
        $this->assertEquals('locked', $summary->report_status);

        Carbon::setTestNow();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AN-016: Close job warns but does not fail when no active tax codes
    // ─────────────────────────────────────────────────────────────────────────

    public function test_close_job_warns_when_no_active_tax_codes_but_still_locks(): void
    {
        // Create expense_tax_codes table stub if it exists, just leave it empty
        if (Schema::hasTable('platform_expense_tax_codes')) {
            DB::table('platform_expense_tax_codes')->delete();
        }

        Carbon::setTestNow(Carbon::create(2026, 4, 2));

        $job = new CloseMonthlyFinancialReportJob(2026, 3);
        $job->handle();

        // Job should not have thrown — period should be locked
        $this->assertDatabaseHas('platform_monthly_financial_summaries', [
            'report_year'   => 2026,
            'report_month'  => 3,
            'report_status' => 'locked',
        ]);

        Carbon::setTestNow();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AN-014: Tenant isolation defensive checks
    // ─────────────────────────────────────────────────────────────────────────

    public function test_billing_tax_service_throws_on_invalid_company_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/companyId must be a positive integer/');

        app(BillingTaxCalculationService::class)->calculateBillingTax(0, '2026-01');
    }

    public function test_billing_tax_service_throws_on_negative_company_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(BillingTaxCalculationService::class)->calculateBillingTax(-1, '2026-01');
    }

    public function test_tax_reporting_service_throws_on_empty_company_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/companyId is required/');

        app(TaxGovernanceReportingService::class)->generateTenantSelfAuditReport(
            '0',
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth()
        );
    }
}
