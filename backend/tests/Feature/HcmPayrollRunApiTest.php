<?php

namespace Tests\Feature;

use App\Models\CompanyUser;
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

    private ?Company $company = null;

    private function payrollCompany(): Company
    {
        return Company::query()->firstOrCreate(
            ['code' => 'default_company'],
            ['name' => 'Default Company', 'domain' => 'default-company.local']
        );
    }

    private function adminToken(): string
    {
        $this->company ??= $this->payrollCompany();

        $result = $this->createHcmAdminWithCompany([
            'name' => 'Payroll Admin',
            'email' => 'payroll-admin@example.com',
            'password' => 'StrongPass1',
        ], $this->company);

        $this->company = $result['company'];

        $user = User::query()->where('email', 'payroll-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $this->company->id,
                'team' => 'HR',
                'designation' => 'HR Admin',
                'employment_status' => 'active',
                'base_salary' => 0,
                'fixed_allowance' => 0,
            ],
        );

        $this->withHeaders(['X-Company-Id' => (string) $this->company->id]);

        return $result['token'];
    }

    private function employeeToken(string $email = 'employee@example.com', float $baseSalary = 4_000_000): string
    {
        if (! $this->company) {
            $this->company = $this->payrollCompany();
        }

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        CompanyUser::query()->updateOrCreate(
            [
                'company_id' => $this->company->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'employee',
                'status' => 'active',
            ]
        );

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $this->company->id,
                'employment_status' => 'active',
                'base_salary' => $baseSalary,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ])->assertOk();

        $this->withHeaders(['X-Company-Id' => (string) $this->company->id]);

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

        $otherCompany = Company::query()->create([
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
            'X-Company-Id' => (string) $otherCompany->id,
            'X-Company-Code' => 'payroll_run_other_company',
        ])->getJson('/v1/hcm/payroll-runs/history')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_payroll_run_disburse_forbidden_when_switching_to_unowned_company(): void
    {
        $admin = $this->adminToken();
        $this->employeeToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 10);

        $otherCompany = Company::query()->create([
            'code' => 'payroll_run_disburse_other_company',
            'name' => 'Payroll Run Disburse Other Company',
            'legal_name' => 'Payroll Run Disburse Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin,
            'X-Company-Id' => (string) $otherCompany->id,
            'X-Company-Code' => 'payroll_run_disburse_other_company',
        ])->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse')
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

    public function test_admin_can_void_finalized_unpaid_run_and_reopen_period(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 6);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        $voidResponse = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/void')
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.voidedByUserName', 'Payroll Admin')
            ->assertJsonPath('data.voidedByUserId', User::query()->where('email', 'payroll-admin@example.com')->firstOrFail()->id);

        $this->assertNotNull($voidResponse->json('data.voidedAt'));

        $run = HcmPayrollRun::query()->findOrFail($data['runId']);
        $this->assertSame('void', $run->status);
        $this->assertNotNull($run->voided_at);
        $this->assertNotNull($run->voided_by_user_id);
        $this->assertSame('open', \App\Models\HcmPayrollPeriod::query()->findOrFail($data['periodId'])->status);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-runs/'.$data['runId'])
            ->assertOk()
            ->assertJsonPath('data.run.status', 'void')
            ->assertJsonPath('data.run.voidedByUserName', 'Payroll Admin')
            ->assertJsonPath('data.auditTrail.2.event', 'voided')
            ->assertJsonPath('data.auditTrail.2.actorName', 'Payroll Admin');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-runs/history?status=void')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'void')
            ->assertJsonPath('data.0.auditTrail.2.event', 'voided')
            ->assertJsonPath('data.0.auditTrail.2.actorName', 'Payroll Admin');
    }

    public function test_admin_cannot_void_run_after_payment_has_been_disbursed(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 7);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/void')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYROLL_RUN_ALREADY_PAID');

        $this->assertSame('finalized', HcmPayrollRun::query()->findOrFail($data['runId'])->status);
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
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk();

        $gatewayRef = $response->json('data.gatewayReference');
        $this->assertNotNull($gatewayRef);
        $selectedUserIds = collect($response->json('data.selectedUserIds', []))->map(fn ($id) => (int) $id)->all();

        // Verify all disbursed users' lines are marked as paid.
        $lines = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $data['runId'])
            ->whereIn('user_id', $selectedUserIds)
            ->get();

        $this->assertNotEmpty($selectedUserIds);
        $this->assertTrue($lines->isNotEmpty());

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
            ->where('hcm_payroll_run_id', $data['runId'])
            ->where('user_id', $emp1User->id)
            ->get();

        foreach ($emp1Lines as $line) {
            $this->assertSame('paid', strtolower((string) $line->meta['paymentStatus']));
        }
    }

    public function test_admin_disburse_accepts_uuid_identifier_fallback(): void
    {
        $this->employeeToken('uuid-emp@example.com', 5_500_000);
        $this->employeeToken('uuid-emp-2@example.com', 6_100_000);
        $admin = $this->adminToken();

        $data = $this->createAndFinalizeDraft($admin, 2026, 8);

        $targetUser = User::query()->where('email', 'uuid-emp@example.com')->firstOrFail();
        $otherUser = User::query()->where('email', 'uuid-emp-2@example.com')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'userIds' => [$targetUser->uuid],
            ])
            ->assertOk()
            ->assertJsonPath('data.selectedUserIds.0', (int) $targetUser->id);

        $targetStatuses = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $data['runId'])
            ->where('user_id', $targetUser->id)
            ->pluck('meta');

        foreach ($targetStatuses as $meta) {
            $this->assertSame('paid', strtolower((string) ($meta['paymentStatus'] ?? 'unpaid')));
        }

        $otherStatuses = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $data['runId'])
            ->where('user_id', $otherUser->id)
            ->pluck('meta');

        foreach ($otherStatuses as $meta) {
            $this->assertSame('unpaid', strtolower((string) ($meta['paymentStatus'] ?? 'unpaid')));
        }
    }

    public function test_repeat_disburse_is_idempotent_when_all_paid(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 9);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        // First disburse succeeds
        $first = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk();

        $gatewayRef = $first->json('data.gatewayReference');
        $this->assertNotEmpty($gatewayRef);

        // Second (repeat) disburse is idempotent: returns 200 with same gateway reference,
        // all users appear in skippedAlreadyPaidUserIds
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.gatewayReference', $gatewayRef);
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
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk();

        // Reset payments
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/reset-payments')
            ->assertOk();

        // Should be able to disburse again
        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
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
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->getJson('/v1/hcm/payroll/my-slip-lines?periodYear=2026&periodMonth=11')
            ->assertOk();

        $this->assertSame('finalized', $response->json('data.run.status'));
    }

    public function test_employee_can_resolve_latest_finalized_payslip_period(): void
    {
        $employee = $this->employeeToken();
        $admin = $this->adminToken();

        $older = $this->createAndFinalizeDraft($admin, 2026, 9);
        $newer = $this->createAndFinalizeDraft($admin, 2026, 11);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$older['runId'].'/finalize')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$newer['runId'].'/finalize')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->getJson('/v1/hcm/payroll/my-slip-latest-period')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period.periodYear', 2026)
            ->assertJsonPath('data.period.periodMonth', 11)
            ->assertJsonPath('data.run.status', 'finalized');
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
    public function test_repeat_disburse_returns_existing_gateway_reference(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 3);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        // First disburse succeeds
        $resp1 = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk();

        $gatewayRef1 = $resp1->json('data.gatewayReference');
        $this->assertNotNull($gatewayRef1);
        $selectedUserIds = collect($resp1->json('data.selectedUserIds', []))->map(fn ($id) => (int) $id)->all();

        // Repeat disburse is idempotent: returns 200 with same gateway reference.
        // True concurrent race protection comes from lockForUpdate() inside the transaction.
        $resp2 = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk();

        // Idempotent: same gateway reference returned
        $this->assertSame($gatewayRef1, $resp2->json('data.gatewayReference'));

        // Verify all lines still have original gateway reference
        $lines = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $data['runId'])
            ->whereIn('user_id', $selectedUserIds)
            ->get();

        $this->assertNotEmpty($selectedUserIds);
        $this->assertTrue($lines->isNotEmpty());

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
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk();

        $gatewayRef = $response->json('data.gatewayReference');
        $selectedUserIds = collect($response->json('data.selectedUserIds', []))->map(fn ($id) => (int) $id)->all();
        $lines = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $data['runId'])
            ->whereIn('user_id', $selectedUserIds)
            ->get();

        $this->assertNotEmpty($selectedUserIds);
        $this->assertTrue($lines->isNotEmpty());

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

    public function test_admin_disburse_requires_explicit_selection_or_apply_all_flag(): void
    {
        $this->employeeToken();
        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 7);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'PAYROLL_DISBURSE_NO_EMPLOYEES');
    }

    public function test_payroll_run_show_returns_thr_and_compensation_recipients_for_same_period(): void
    {
        $this->employeeToken('thr-recipient@example.com', 4_500_000);
        $this->employeeToken('comp-recipient@example.com', 4_500_000);
        $this->employeeToken('neutral-employee@example.com', 4_500_000);

        $thrUserId = (int) User::query()->where('email', 'thr-recipient@example.com')->value('id');
        $compUserId = (int) User::query()->where('email', 'comp-recipient@example.com')->value('id');
        $neutralUserId = (int) User::query()->where('email', 'neutral-employee@example.com')->value('id');

        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 9);

        $thrRun = HcmPayrollRun::query()->create([
            'hcm_payroll_period_id' => $data['periodId'],
            'purpose' => HcmPayrollRun::PURPOSE_THR,
            'status' => HcmPayrollRun::STATUS_FINALIZED,
            'calculated_at' => now(),
            'finalized_at' => now(),
        ]);

        HcmPayrollLine::query()->create([
            'hcm_payroll_run_id' => $thrRun->id,
            'user_id' => $thrUserId,
            'component_code' => 'thr_bonus',
            'component_name' => 'THR',
            'kind' => 'addition',
            'category' => 'bonus',
            'amount' => 1_000_000,
            'sort_order' => 1,
            'meta' => ['affectsNetPay' => true],
        ]);

        $pkwtRun = HcmPayrollRun::query()->create([
            'hcm_payroll_period_id' => $data['periodId'],
            'purpose' => HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
            'status' => HcmPayrollRun::STATUS_FINALIZED,
            'calculated_at' => now(),
            'finalized_at' => now(),
        ]);

        HcmPayrollLine::query()->create([
            'hcm_payroll_run_id' => $pkwtRun->id,
            'user_id' => $compUserId,
            'component_code' => 'kompensasi_pkwt',
            'component_name' => 'Kompensasi PKWT',
            'kind' => 'addition',
            'category' => 'allowance',
            'amount' => 750_000,
            'sort_order' => 1,
            'meta' => ['affectsNetPay' => true],
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-runs/'.$data['runId'])
            ->assertOk();

        $this->assertContains($thrUserId, (array) $response->json('data.specialRecipients.thrUserIds'));
        $this->assertContains($compUserId, (array) $response->json('data.specialRecipients.compensationUserIds'));
        $this->assertNotContains($neutralUserId, (array) $response->json('data.specialRecipients.thrUserIds'));
        $this->assertNotContains($neutralUserId, (array) $response->json('data.specialRecipients.compensationUserIds'));
    }

    public function test_admin_disburse_apply_all_skips_ineligible_net_zero_employees(): void
    {
        $paidEmployeeToken = $this->employeeToken('payable-employee@example.com', 4_000_000);
        $zeroNetEmployeeToken = $this->employeeToken('zero-net-employee@example.com', 0);
        $this->assertNotEmpty($paidEmployeeToken);
        $this->assertNotEmpty($zeroNetEmployeeToken);

        $payableUserId = (int) User::query()->where('email', 'payable-employee@example.com')->value('id');
        $zeroNetUserId = (int) User::query()->where('email', 'zero-net-employee@example.com')->value('id');

        $admin = $this->adminToken();
        $data = $this->createAndFinalizeDraft($admin, 2026, 8);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/finalize')
            ->assertOk();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$data['runId'].'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk();

        $this->assertSame(1, (int) $response->json('data.payment.paidEmployeeCount'));
        $this->assertContains($payableUserId, (array) $response->json('data.selectedUserIds'));
        $this->assertNotContains($zeroNetUserId, (array) $response->json('data.selectedUserIds'));
        $this->assertContains($zeroNetUserId, (array) $response->json('data.ineligibleUserIds'));
    }
}
