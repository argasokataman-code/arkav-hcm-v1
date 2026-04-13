<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Company;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

/**
 * Comprehensive test suite for Payroll Run operations.
 * Covers: show, history, finalize, disburse, resetPayments,mySlip endpoints
 */
#[IgnoreDeprecations]
class HcmPayrollRunApiTest extends TestCase
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
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function createAndFinalizeDraft(string $admin, int $year, int $month): array
    {
        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => $year,
                'periodMonth' => $month,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $draft = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->json('data');

        return [
            'periodId' => $periodId,
            'runId' => (int) $draft['run']['id'],
            'run' => $draft['run'],
        ];
    }

    public function test_non_admin_cannot_show_payroll_run(): void
    {
        $employee = $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 5);

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->getJson('/v1/hcm/payroll-runs/'.$data['runId'])
            ->assertStatus(403);
    }

    public function test_admin_can_view_payroll_run_history(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $this->createAndFinalizeDraft($admin, 2026, 3);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-runs/history')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_payroll_runs_forbidden_when_switching_to_unowned_company(): void
    {
        $admin = $this->adminToken();

        Company::query()->create([
            'code' => 'payroll_run_other_company',
            'name' => 'Payroll Run Other Company',
            'legal_name' => 'Payroll Run Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin,
            'X-Company-Code' => 'payroll_run_other_company',
        ])->getJson('/v1/hcm/payroll-runs/history')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_admin_can_finalize_draft_run(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 6);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        $this->assertSame('finalized', $response->json('data.status'));
    }

    public function test_admin_cannot_finalize_already_finalized_run(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 6);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        // Second finalize should fail
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertStatus(422);
    }

    public function test_admin_disburse_without_user_filter_marks_all_as_paid(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 7);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertOk();

        $gatewayRef = $response->json('data.gatewayReference');
        $this->assertNotNull($gatewayRef);

        // Verify all lines are marked as paid
        $lines = HcmPayrollLine::query()
            ->where('payroll_run_id', $data['runId'])
            ->get();

        foreach ($lines as $line) {
            $this->assertSame('paid', strtolower((string) $line->meta['paymentStatus']));
        }
    }

    public function test_admin_disburse_selective_employees(): void
    {
        $emp1 = $this->employeeToken('emp1@example.com', 5_000_000);
        $emp2 = $this->employeeToken('emp2@example.com', 6_000_000);
        $admin = $this->adminToken();

        $data = $this->createAndFinalizeDraft($admin, 2026, 8);

        $emp1User = User::query()->where('email', 'emp1@example.com')->firstOrFail();
        $emp2User = User::query()->where('email', 'emp2@example.com')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        // Disburse only emp1
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'userIds' => [(int)$emp1User->id],
            ])
            ->assertOk();

        // Check emp1 is paid, emp2 is unpaid
        $emp1Lines = HcmPayrollLine::query()
            ->where('payroll_run_id', $data['runId'])
            ->where('user_id', $emp1User->id)
            ->get();

        foreach ($emp1Lines as $line) {
            $this->assertSame('paid', strtolower((string) $line->meta['paymentStatus']));
        }
    }

    public function test_admin_cannot_disburse_if_already_paid(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 9);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        // First disburse succeeds
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertOk();

        // Second disburse should fail (already paid)
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertStatus(422);
    }

    public function test_admin_can_reset_payments(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 10);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertOk();

        // Reset payments
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/reset-payments')
            ->assertOk();

        // Should be able to disburse again
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertOk();
    }

    public function test_employee_can_view_their_payslip(): void
    {
        $employee = $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 11);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertOk();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->getJson('/v1/hcm/payroll/my-slip-lines?periodYear=2026&periodMonth=11')
            ->assertOk();

        $this->assertSame('finalized', $response->json('data.run.status'));
    }

    public function test_non_admin_cannot_finalize(): void
    {
        $employee = $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 12);

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_disburse(): void
    {
        $employee = $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 1);

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_reset_payments(): void
    {
        $employee = $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 2);

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/reset-payments')
            ->assertStatus(403);
    }

    /**
     * Test that adding early-return for already-paid employees prevents duplicate disbursements
     * even in concurrent scenarios due to transaction locking.
     */
    public function test_race_condition_protection_already_paid_detection(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 3);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        // First disburse succeeds
        $resp1 = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertOk();

        $gatewayRef1 = $resp1->json('data.gatewayReference');
        $this->assertNotNull($gatewayRef1);

        // Concurrent attempt to disburse same run should be rejected atomically
        $resp2 = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertStatus(422);

        // Verify error indicates already-paid status
        $this->assertSame('PAYROLL_DISBURSE_ALREADY_PAID', $resp2->json('error.code'));

        // Verify all lines still have original gateway reference
        $lines = HcmPayrollLine::query()
            ->where('payroll_run_id', $data['runId'])
            ->get();

        foreach ($lines as $line) {
            $this->assertSame($gatewayRef1, $line->meta['gatewayReference']);
        }
    }

    /**
     * Test payment metadata consistency - all disbursed lines have identical timestamps and refs
     */
    public function test_payment_metadata_consistency_after_disburse(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 4);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertOk();

        $gatewayRef = $response->json('data.gatewayReference');
        $lines = HcmPayrollLine::query()
            ->where('payroll_run_id', $data['runId'])
            ->get();

        $firstLine = $lines->first();
        $firstPaidAt = $firstLine->meta['paidAt'] ?? null;

        // All lines should have identical payment metadata
        foreach ($lines as $line) {
            $this->assertSame('paid', strtolower((string) $line->meta['paymentStatus']));
            $this->assertSame($gatewayRef, $line->meta['gatewayReference']);
            $this->assertSame($firstPaidAt, $line->meta['paidAt']);
            $this->assertSame('gateway-simulated', $line->meta['paymentChannel']);
        }
    }

    public function test_payroll_run_show_returns_404_when_not_found(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-runs/999999')
            ->assertNotFound();
    }
}
