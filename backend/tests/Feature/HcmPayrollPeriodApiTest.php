<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Company;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

/**
 * Comprehensive test suite for Payroll Period operations.
 * 
 * Covers:
 * - index: List payroll periods
 * - store: Create new payroll period
 * - show: Display period details including latest run
 * - active: Resolve current active period or create default
 * - calculateDraft: Rebuild or create draft run for period
 * 
 * Also tests:
 * - Duplicate period prevention
 * - Draft recalculation and line rebuilding
 * - Active period resolution logic
 * - Edge cases (empty periods, dev-mode override for finalized runs)
 */
#[IgnoreDeprecations]
class HcmPayrollPeriodApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Payroll Admin',
            'email' => 'payroll-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'payroll-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'team' => 'HR',
                'designation' => 'HR Admin',
                'employment_status' => 'active',
                'base_salary' => 0,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'payroll-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function employeeToken(string $email = 'employee@example.com', float $baseSalary = 4_000_000): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employment_status' => 'active',
                'base_salary' => $baseSalary,
                'fixed_allowance' => 100_000,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_non_admin_cannot_list_payroll_periods(): void
    {
        $employee = $this->employeeToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->getJson('/v1/hcm/payroll-periods')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_payroll_periods_forbidden_when_switching_to_unowned_company(): void
    {
        $admin = $this->adminToken();

        Company::query()->create([
            'code' => 'payroll_period_other_company',
            'name' => 'Payroll Period Other Company',
            'legal_name' => 'Payroll Period Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin,
            'X-Company-Code' => 'payroll_period_other_company',
        ])->getJson('/v1/hcm/payroll-periods')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_admin_can_list_payroll_periods(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 1,
            ])
            ->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 2,
            ])
            ->assertStatus(201);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods')
            ->assertOk()
            ->assertJsonPath('success', true);

        $periods = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count((array) $periods));
    }

    public function test_admin_can_create_new_payroll_period(): void
    {
        $admin = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 3,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertSame(2026, (int) $response->json('data.periodYear'));
        $this->assertSame(3, (int) $response->json('data.periodMonth'));
        $this->assertSame('open', $response->json('data.status'));
    }

    public function test_admin_cannot_create_duplicate_period(): void
    {
        $admin = $this->adminToken();

        // First creation should succeed
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertStatus(201);

        // Duplicate should fail
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYROLL_PERIOD_EXISTS');
    }

    public function test_non_admin_cannot_create_payroll_period(): void
    {
        $employee = $this->employeeToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 5,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_admin_can_view_period_details_with_latest_run(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 6,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $draft = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $runId = (int) $draft->json('data.run.id');

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods/'.$periodId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('data.latestRun'));
        $this->assertSame($runId, (int) $response->json('data.latestRun.id'));
    }

    public function test_admin_can_calculate_draft_for_period(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 7,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('data.run'));
        $this->assertGreaterThan(0, (int) $response->json('data.run.id'));
        $this->assertSame('draft', $response->json('data.run.status'));
    }

    public function test_admin_can_recalculate_draft_rebuilds_lines(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 8,
            ])
            ->assertStatus(201)
            ->json('data.id');

        // First draft
        $firstDraft = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $firstRunId = (int) $firstDraft->json('data.run.id');
        $firstLineCount = (int) $firstDraft->json('data.lineCount');

        // Recalculate draft
        $secondDraft = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $secondRunId = (int) $secondDraft->json('data.run.id');
        $secondLineCount = (int) $secondDraft->json('data.lineCount');

        // Should get new run (idempotent pattern: old draft discarded, new one created)
        // Line counts should be consistent
        $this->assertSame($firstLineCount, $secondLineCount);
    }

    public function test_admin_calculate_draft_for_empty_period(): void
    {
        // Create admin only (with inactive or zero salary)
        // The draft should have minimal or zero lines for non-active employees
        $admin = $this->adminToken();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 9,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        // Draft should have at least the necessary structure
        // Line count depends on whether admin is included (admin has zero salary, may still generate lines)
        $this->assertIsNumeric($response->json('data.lineCount'));
        $this->assertIsNumeric($response->json('data.employeeCount'));
    }

    public function test_non_admin_cannot_calculate_draft(): void
    {
        $employee = $this->employeeToken();
        $admin = $this->adminToken();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 10,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_active_period_endpoint_resolves_current_or_creates_default(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();

        // First call should create default current-month period
        $response1 = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods/active')
            ->assertOk()
            ->assertJsonPath('success', true);

        $activePeriodId = (int) $response1->json('data.id');
        $this->assertNotNull($activePeriodId);

        // Second call should return same period
        $response2 = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods/active')
            ->assertOk();

        $this->assertSame($activePeriodId, (int) $response2->json('data.id'));
    }

    public function test_non_admin_cannot_get_active_period(): void
    {
        $employee = $this->employeeToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->getJson('/v1/hcm/payroll-periods/active')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_create_period_validates_year_and_month(): void
    {
        $admin = $this->adminToken();

        // Invalid year (too low)
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 1999,
                'periodMonth' => 1,
            ])
            ->assertStatus(422);

        // Invalid month (too high)
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 13,
            ])
            ->assertStatus(422);

        // Valid edge cases
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2000,
                'periodMonth' => 1,
            ])
            ->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2100,
                'periodMonth' => 12,
            ])
            ->assertStatus(201);
    }

    public function test_calculate_draft_handles_finalized_run_in_dev_mode(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 11,
            ])
            ->assertStatus(201)
            ->json('data.id');

        // Calculate and finalize
        $draft = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $runId = (int) $draft->json('data.run.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertOk();

        // Verify period status shows as posted
        $periodResponse = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods/'.$periodId)
            ->assertOk();

        $this->assertSame('posted', $periodResponse->json('data.status'));

        // Recalculate draft in dev mode should allow voiding finalized run
        // (This behavior allows re-running payroll in testing/dev scenarios)
        $newDraft = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $this->assertNotNull($newDraft->json('data.run'));
    }

    public function test_period_status_transitions(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 12,
            ])
            ->assertStatus(201)
            ->json('data.id');

        // Begin as OPEN
        $period = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods/'.$periodId)
            ->assertOk()
            ->json('data');

        $this->assertSame('open', $period['status']);

        // After calculating draft, should still be OPEN
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $period = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods/'.$periodId)
            ->assertOk()
            ->json('data');

        $this->assertSame('open', $period['status']);

        // After finalization, should transition to POSTED
        $runId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->json('data.run.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertOk();

        $period = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods/'.$periodId)
            ->assertOk()
            ->json('data');

        $this->assertSame('posted', $period['status']);
    }

    public function test_payroll_period_show_returns_404_when_not_found(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods/999999')
            ->assertNotFound();
    }
}
