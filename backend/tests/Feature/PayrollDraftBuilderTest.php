<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeEmploymentHistory;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmSalaryComponent;
use App\Models\User;
use App\Support\PayrollDraftBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

/**
 * Test PayrollDraftBuilder::rebuildDraftRun.
 *
 * All HcmSalaryComponent records are pre-seeded by migration.
 * The `default_percent` and `percent_basis` columns were dropped by
 * migration 2026_05_04_001000_drop_unused_columns_from_hcm_salary_components.php,
 * so BPJS deduction lines (which rely on default_percent > 0) are NOT created.
 *
 * Currently generated lines per user:
 *  - upah_pokok (always, even at 0)
 *  - tunjangan_tetap (if fixed_allowance > 0)
 *  - pph21_ter (if taxable gross > 0)
 */
#[IgnoreDeprecations]
class PayrollDraftBuilderTest extends TestCase
{
    use RefreshDatabase;

    private int $queryCount = 0;

    private function startQueryTracking(): void
    {
        $this->queryCount = 0;
        DB::listen(function ($query): void {
            ++$this->queryCount;
        });
    }

    private function assertQueryCountLessThan(int $max, string $label = 'rebuildDraftRun'): void
    {
        $this->assertLessThanOrEqual(
            $max,
            $this->queryCount,
            "Query count exceeded limit for {$label}. Expected ≤{$max}, got {$this->queryCount}. Consider adding ->select(...) to queries in this code path."
        );
    }

    /**
     * Create tunjangan_tetap component if not exist.
     * Seeded fixed_allowance components use codes tunjangan_tetap_transport etc.,
     * so builder's code='tunjangan_tetap' lookup fails and falls back to
     * category='fixed_allowance'. We create 'tunjangan_tetap' explicitly
     * so code-level assertion works.
     */
    private function ensureTunjanganTetapComponent(?int $companyId): HcmSalaryComponent
    {
        $existing = HcmSalaryComponent::query()
            ->where('code', 'tunjangan_tetap')
            ->where('is_active', true)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return HcmSalaryComponent::query()->create([
            'company_id' => $companyId,
            'code' => 'tunjangan_tetap',
            'name' => 'Tunjangan Tetap',
            'kind' => 'addition',
            'category' => 'fixed_allowance',
            'is_active' => true,
            'affects_net_pay' => true,
            'include_pph21_ter_gross' => true,
            'include_bpjs_health_wage_base' => false,
            'include_bpjs_tk_wage_base' => false,
            'include_thr_calculation_base' => false,
            'is_system_locked' => true,
            'sort_order' => 15,
        ]);
    }

    private function createCompany(string $suffix): Company
    {
        $company = Company::query()->create([
            'code' => 'payroll_builder_'.$suffix,
            'name' => 'Payroll Builder '.ucfirst($suffix),
            'legal_name' => 'Payroll Builder '.ucfirst($suffix).' LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'payroll.monthly.payday_day',
            'value' => '28',
            'type' => 'integer',
        ]);
        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'payroll.monthly.cutoff_offset_days',
            'value' => '3',
            'type' => 'integer',
        ]);

        return $company;
    }

    private function createUserWithProfile(Company $company, string $name, string $email, array $overrides = []): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $profileData = array_merge([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'employment_status' => 'active',
            'base_salary' => 5_000_000.0,
            'fixed_allowance' => 500_000.0,
            'hire_date' => '2024-01-01',
        ], $overrides);

        $profile = EmployeeProfile::query()->create($profileData);
        $profile->refresh();

        EmployeeEmploymentHistory::query()->create([
            'employee_id' => $profile->id,
            'employment_status' => 'active',
            'employee_type' => 'permanent',
            'start_date' => '2024-01-01',
        ]);

        EmployeeCompensation::query()->create([
            'employee_id' => $profile->id,
            'salary_type' => 'monthly',
            'base_salary' => $overrides['base_salary'] ?? 5_000_000.0,
            'currency' => 'IDR',
            'effective_date' => '2024-01-01',
        ]);

        return $user;
    }

    // ───────────────────────────────
    //  TESTS
    // ───────────────────────────────

    public function test_builds_draft_run_with_single_user(): void
    {
        $company = $this->createCompany('single_user');
        $this->createUserWithProfile($company, 'Budi Santoso', 'budi@example.com', [
            'base_salary' => 7_000_000.0,
            'fixed_allowance' => 700_000.0,
        ]);
        $this->ensureTunjanganTetapComponent($company->id);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $this->startQueryTracking();
        $run = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $this->assertQueryCountLessThan(80, 'single_user');

        // Verify run created
        $this->assertInstanceOf(HcmPayrollRun::class, $run);
        $this->assertSame(HcmPayrollRun::STATUS_DRAFT, $run->status);
        $this->assertSame(HcmPayrollRun::PURPOSE_MONTHLY, $run->purpose);
        $this->assertSame($period->id, $run->hcm_payroll_period_id);

        // Lines: upah_pokok + tunjangan_tetap + pph21_ter = 3
        // (BPJS deduction lines not created — default_percent column was dropped)
        $lines = $run->lines()->get();
        $this->assertCount(3, $lines, 'Expected 3 payroll lines (upah_pokok, tunjangan_tetap, pph21_ter)');

        // Verify components
        $codes = $lines->pluck('component_code')->values()->all();
        $this->assertContains('upah_pokok', $codes);
        $this->assertContains('tunjangan_tetap', $codes);
        $this->assertContains('pph21_ter', $codes);

        // Check amounts
        $upahLine = $lines->firstWhere('component_code', 'upah_pokok');
        $this->assertEquals(7_000_000.0, $upahLine->amount);

        $tunjanganLine = $lines->firstWhere('component_code', 'tunjangan_tetap');
        $this->assertEquals(700_000.0, $tunjanganLine->amount);

        $pph21Line = $lines->firstWhere('component_code', 'pph21_ter');
        // TK0 → cat A: 7.7M <= 8.55M → rate 1.5% → 115.500
        $this->assertEquals(115_500.0, $pph21Line->amount);

        // Only one draft run after build
        $draftCount = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $period->id)
            ->where('status', HcmPayrollRun::STATUS_DRAFT)
            ->count();
        $this->assertSame(1, $draftCount);
    }

    public function test_builds_draft_run_with_multiple_users(): void
    {
        $company = $this->createCompany('multi_user');
        $this->createUserWithProfile($company, 'User A', 'usera@example.com', [
            'base_salary' => 5_000_000.0,
            'fixed_allowance' => 500_000.0,
        ]);
        $this->createUserWithProfile($company, 'User B', 'userb@example.com', [
            'base_salary' => 8_000_000.0,
            'fixed_allowance' => 1_000_000.0,
        ]);
        $this->ensureTunjanganTetapComponent($company->id);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $this->startQueryTracking();
        $run = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $this->assertQueryCountLessThan(120, 'two_users');

        // 2 users × 3 lines each = 6
        $lines = $run->lines()->get();
        $this->assertCount(6, $lines, 'Expected 6 payroll lines for 2 active users');

        $upahA = $lines->where('component_code', 'upah_pokok')->first(fn ($l) => ($l->meta['userName'] ?? '') === 'User A');
        $this->assertNotNull($upahA);
        $this->assertEquals(5_000_000.0, $upahA->amount);

        $upahB = $lines->where('component_code', 'upah_pokok')->first(fn ($l) => ($l->meta['userName'] ?? '') === 'User B');
        $this->assertNotNull($upahB);
        $this->assertEquals(8_000_000.0, $upahB->amount);
    }

    public function test_skips_users_with_inactive_employment_status(): void
    {
        $company = $this->createCompany('inactive_filter');

        $this->createUserWithProfile($company, 'Active User', 'active@example.com', [
            'base_salary' => 5_000_000.0,
        ]);

        $inactiveUser = $this->createUserWithProfile($company, 'Inactive User', 'inactive@example.com', [
            'base_salary' => 5_000_000.0,
        ]);
        EmployeeEmploymentHistory::query()
            ->where('employee_id', $inactiveUser->employeeProfile->id)
            ->update(['employment_status' => 'resigned']);

        $this->ensureTunjanganTetapComponent($company->id);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $this->startQueryTracking();
        $run = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $this->assertQueryCountLessThan(80, 'inactive_filter');

        $lines = $run->lines()->get();
        $userNames = $lines->pluck('meta')->map(fn ($m) => $m['userName'] ?? '')->unique()->values();
        $this->assertCount(1, $userNames, 'Only active user should have payroll lines');
        $this->assertSame('Active User', $userNames->first());
        $this->assertCount(3, $lines, 'Expected 3 lines for 1 active user');
    }

    public function test_handles_zero_base_salary(): void
    {
        $company = $this->createCompany('zero_salary');
        $this->createUserWithProfile($company, 'Zero Salary', 'zero@example.com', [
            'base_salary' => 0.0,
            'fixed_allowance' => 0.0,
        ]);
        $this->ensureTunjanganTetapComponent($company->id);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $this->startQueryTracking();
        $run = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $this->assertQueryCountLessThan(80, 'zero_salary');

        $lines = $run->lines()->get();
        // Only upah_pokok always created; tunjangan_tetap (0) skipped; pph21 (gross 0) skipped
        $this->assertCount(1, $lines, 'Expected only upah_pokok line for zero-salary user');

        $upahLine = $lines->firstWhere('component_code', 'upah_pokok');
        $this->assertNotNull($upahLine);
        $this->assertEquals(0.0, $upahLine->amount);
    }

    public function test_rebuild_replaces_previous_drafts(): void
    {
        $company = $this->createCompany('rebuild_replace');
        $this->createUserWithProfile($company, 'Rebuild User', 'rebuild@example.com', [
            'base_salary' => 6_000_000.0,
            'fixed_allowance' => 600_000.0,
        ]);
        $this->ensureTunjanganTetapComponent($company->id);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        // First build
        $firstRun = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $firstRunId = $firstRun->id;
        $firstRunLines = $firstRun->lines()->count();
        $this->assertGreaterThan(0, $firstRunLines);

        // Second build replaces previous draft
        $this->startQueryTracking();
        $secondRun = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $this->assertQueryCountLessThan(80, 'rebuild_replace');

        // Old run is deleted, new run exists
        $oldRun = HcmPayrollRun::find($firstRunId);
        $this->assertNull($oldRun, 'Previous draft run should be deleted');

        // Only one draft remains
        $draftCount = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $period->id)
            ->where('status', HcmPayrollRun::STATUS_DRAFT)
            ->count();
        $this->assertSame(1, $draftCount);

        // Lines should be on new run
        $this->assertGreaterThan(0, $secondRun->lines()->count());
    }

    public function test_handles_no_active_users(): void
    {
        $company = $this->createCompany('no_users');
        $this->ensureTunjanganTetapComponent($company->id);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $this->startQueryTracking();
        $run = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $this->assertQueryCountLessThan(50, 'no_users');

        $this->assertInstanceOf(HcmPayrollRun::class, $run);
        $lines = $run->lines()->get();
        $this->assertCount(0, $lines, 'Expected 0 lines when no active users with profiles');
    }

    public function test_handles_company_without_company_id(): void
    {
        $this->ensureTunjanganTetapComponent(null);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => null,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $user = User::query()->create([
            'name' => 'Platform User',
            'email' => 'platform@example.com',
            'password' => bcrypt('password'),
        ]);
        EmployeeProfile::query()->create([
            'user_id' => $user->id,
            'company_id' => null,
            'employment_status' => 'active',
            'base_salary' => 5_000_000.0,
            'hire_date' => '2024-01-01',
        ]);

        $this->startQueryTracking();
        $run = PayrollDraftBuilder::rebuildDraftRun($period);
        $this->assertQueryCountLessThan(80, 'no_company_id');

        $this->assertInstanceOf(HcmPayrollRun::class, $run);
        $this->assertGreaterThan(0, $run->lines()->count());
    }

    public function test_respects_tax_profile_for_pph21(): void
    {
        $company = $this->createCompany('tax_profile');
        $user = $this->createUserWithProfile($company, 'Taxed User', 'taxed@example.com', [
            'base_salary' => 15_000_000.0,
            'fixed_allowance' => 1_500_000.0,
        ]);

        EmployeeTaxProfile::query()->create([
            'employee_id' => $user->employeeProfile->id,
            'tax_status' => 'K3',
            'ptkp_status' => 'K3',
            'effective_date' => '2024-01-01',
        ]);

        $this->ensureTunjanganTetapComponent($company->id);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $this->startQueryTracking();
        $run = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $this->assertQueryCountLessThan(80, 'tax_profile');

        $lines = $run->lines()->get();
        $pph21Line = $lines->firstWhere('component_code', 'pph21_ter');
        $this->assertNotNull($pph21Line, 'PPH21 line should exist for high salary');
        $this->assertGreaterThan(0, $pph21Line->amount);

        // K3 → cat C: 16.5M <= 17.05M → rate 6% → 990.000
        $this->assertEquals(990_000.0, $pph21Line->amount);

        $this->assertSame('K3', $pph21Line->meta['taxStatusUsed'] ?? null);
        $this->assertSame('employee_tax_profiles', $pph21Line->meta['taxStatusSource'] ?? null);
    }

    public function test_skips_pph21_when_taxable_gross_is_zero(): void
    {
        $company = $this->createCompany('zero_pph21');
        $this->createUserWithProfile($company, 'Zero PPH', 'zerotax@example.com', [
            'base_salary' => 0.0,
            'fixed_allowance' => 0.0,
        ]);
        $this->ensureTunjanganTetapComponent($company->id);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $run = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $lines = $run->lines()->get();
        $pph21Line = $lines->firstWhere('component_code', 'pph21_ter');
        $this->assertNull($pph21Line, 'PPH21 line should not exist when taxable gross is 0');
    }

    public function test_late_arrival_buffer_appears_in_meta(): void
    {
        $company = $this->createCompany('late_arrival');
        $this->createUserWithProfile($company, 'Late User', 'late@example.com');
        $this->ensureTunjanganTetapComponent($company->id);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $this->startQueryTracking();
        $run = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $this->assertQueryCountLessThan(80, 'late_arrival');

        $meta = $run->meta;
        $this->assertNotNull($meta);
        $this->assertArrayHasKey('lateArrivalBuffer', $meta);
        $this->assertArrayHasKey('hasLateArrivals', $meta['lateArrivalBuffer']);
        $this->assertFalse($meta['lateArrivalBuffer']['hasLateArrivals'], 'No overtime/assignments → no late arrivals');
    }

    public function test_carries_over_previous_drafts_cleanup(): void
    {
        $company = $this->createCompany('carryover_clean');
        $user = $this->createUserWithProfile($company, 'Clean User', 'clean@example.com', [
            'base_salary' => 5_000_000.0,
        ]);
        $this->ensureTunjanganTetapComponent($company->id);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        // Pre-create stale draft with valid user_id for FK
        $staleRun = HcmPayrollRun::query()->create([
            'company_id' => $company->id,
            'hcm_payroll_period_id' => $period->id,
            'purpose' => HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => HcmPayrollRun::STATUS_DRAFT,
            'calculated_at' => now()->subDay(),
        ]);
        $staleRun->lines()->create([
            'company_id' => $company->id,
            'hcm_payroll_run_id' => $staleRun->id,
            'user_id' => $user->id,
            'component_code' => 'stale',
            'component_name' => 'Stale Line',
            'kind' => 'addition',
            'category' => 'other_addition',
            'amount' => 100.0,
        ]);

        $staleRunId = $staleRun->id;

        $this->startQueryTracking();
        $run = PayrollDraftBuilder::rebuildDraftRun($period, $company->id);
        $this->assertQueryCountLessThan(90, 'carryover_clean');

        // Stale run deleted
        $this->assertNull(HcmPayrollRun::find($staleRunId));
        $this->assertNotNull($run);

        // New run has lines
        $this->assertGreaterThan(0, $run->lines()->count());
    }
}
