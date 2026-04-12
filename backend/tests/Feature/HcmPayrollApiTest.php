<?php

namespace Tests\Feature;

use App\Mail\MonthlyPayslipMail;
use App\Models\EmployeeProfile;
use App\Models\HcmResignation;
use App\Models\HcmPayrollRun;
use App\Models\HcmSalaryComponent;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmPayrollApiTest extends TestCase
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

    private function workerToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Payroll Worker',
            'email' => 'payroll-worker@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'payroll-worker@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employment_status' => 'active',
                'base_salary' => 4_000_000,
                'fixed_allowance' => 100_000,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'payroll-worker@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_payroll_periods_require_hcm_admin(): void
    {
        $worker = $this->workerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$worker])
            ->getJson('/v1/hcm/payroll-periods')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_draft_finalize_and_employee_slip_lines(): void
    {
        $workerTok = $this->workerToken();
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.periodYear', 2026)
            ->assertJsonPath('data.periodMonth', 4);

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods')
            ->assertOk()
            ->json('data.0.id');

        $draft = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(6, (int) $draft->json('data.lineCount'));
        $this->assertSame(2, (int) $draft->json('data.employeeCount'));
        $this->assertNotNull($draft->json('data.anomalies.missingTaxProfileUserCount'));

        $runId = (int) $draft->json('data.run.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$workerTok])
            ->getJson('/v1/hcm/payroll/my-slip-lines?periodYear=2026&periodMonth=4')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.run', null)
            ->assertJsonCount(0, 'data.lines');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYROLL_RUN_NOT_DRAFT');

        $this->withHeaders(['Authorization' => 'Bearer '.$workerTok])
            ->getJson('/v1/hcm/payroll/my-slip-lines?periodYear=2026&periodMonth=4')
            ->assertOk()
            ->assertJsonPath('data.run.status', 'finalized')
            ->assertJsonPath('data.run.purpose', 'monthly')
            ->assertJsonCount(5, 'data.lines');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods/'.$periodId)
            ->assertOk()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_finalize_rejects_empty_payroll_run(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Inactive Admin',
            'email' => 'inactive-payroll-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'inactive-payroll-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'team' => 'HR',
                'designation' => 'HR Admin',
                'employment_status' => 'inactive',
                'base_salary' => 5_000_000,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'inactive-payroll-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = (string) $login->json('data.accessToken');

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 7,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $draft = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $this->assertSame(0, (int) $draft->json('data.lineCount'));
        $this->assertSame(0, (int) $draft->json('data.employeeCount'));

        $runId = (int) $draft->json('data.run.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYROLL_RUN_EMPTY');
    }

    public function test_active_period_endpoint_and_history_listing(): void
    {
        $this->workerToken();
        $admin = $this->adminToken();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                    'periodMonth' => 4,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $runId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->json('data.run.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll-periods/active')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $periodId);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
                ->getJson('/v1/hcm/payroll-runs/history?periodYear=2026&periodMonth=4')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $runId);
    }

    public function test_admin_slips_returns_cross_period_rows_without_server_error(): void
    {
        $this->workerToken();
        $admin = $this->adminToken();

        $periodA = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 9,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $runA = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodA.'/calculate-draft')
            ->assertOk()
            ->json('data.run.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runA.'/finalize')
            ->assertOk();

        $periodB = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 10,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodB.'/calculate-draft')
            ->assertOk();

        $resp = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll/admin-slips')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThan(0, count((array) $resp->json('data.rows')));
        $this->assertNotNull($resp->json('data.summary.totalRows'));
    }

    public function test_monthly_draft_rebuild_and_disburse_are_idempotent(): void
    {
        $this->workerToken();
        $admin = $this->adminToken();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 8,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $firstDraft = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $secondDraft = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $this->assertSame(
            1,
            HcmPayrollRun::query()
                ->where('hcm_payroll_period_id', $periodId)
                ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
                ->where('status', HcmPayrollRun::STATUS_DRAFT)
                ->count()
        );

        $runId = (int) $secondDraft->json('data.run.id');
        $selectedUserId = (int) collect($secondDraft->json('data.run.details', []))
            ->pluck('employee_id')
            ->filter()
            ->first();

        $this->assertGreaterThan(0, $selectedUserId);
        $this->assertNotSame((int) $firstDraft->json('data.run.id'), $runId);

        $disburse = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/disburse', [
                'userIds' => [$selectedUserId],
            ]);

        $disburse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.run.status', 'finalized')
            ->assertJsonPath('data.payment.status', 'partial')
            ->assertJsonPath('data.selectedUserIds.0', $selectedUserId);

        $gatewayReference = (string) $disburse->json('data.gatewayReference');
        $this->assertNotSame('', $gatewayReference);
        $this->assertDatabaseHas('hcm_payroll_periods', [
            'id' => $periodId,
            'status' => 'posted',
        ]);

        $repeat = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/disburse', [
                'userIds' => [$selectedUserId],
            ]);

        $repeat->assertOk()
            ->assertJsonPath('data.gatewayReference', $gatewayReference)
            ->assertJsonPath('data.payment.status', 'partial');
    }

    public function test_approved_resigned_employee_is_excluded_from_monthly_payroll_draft(): void
    {
        $workerTok = $this->workerToken();
        $admin = $this->adminToken();

        $worker = User::query()->where('email', 'payroll-worker@example.com')->firstOrFail();
        HcmResignation::query()->create([
            'user_id' => $worker->id,
            'department' => 'Operations',
            'reason' => 'Resign test',
            'notice_date' => '2026-04-01',
            'resignation_date' => '2026-04-15',
            'status' => 'approved',
            'notes' => null,
        ]);

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $draft = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $details = collect($draft->json('data.run.details', []));
        $this->assertFalse($details->pluck('employee_id')->contains($worker->id));

        $this->withHeaders(['Authorization' => 'Bearer '.$workerTok])
            ->getJson('/v1/hcm/payroll/my-slip-lines?periodYear=2026&periodMonth=4')
            ->assertOk()
            ->assertJsonPath('data.run', null)
            ->assertJsonCount(0, 'data.lines');
    }

    public function test_payroll_uses_latest_effective_compensation_history(): void
    {
        $token = $this->adminToken();

        $employee = User::factory()->create([
            'name' => 'History Salary User',
            'email' => 'history-salary@example.com',
        ]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'employment_status' => 'inactive',
            'base_salary' => 100000,
            'fixed_allowance' => 0,
        ]);

        DB::table('employee_employment_history')->insert([
            'employee_id' => $profile->id,
            'employment_status' => 'active',
            'employee_type' => 'permanent',
            'start_date' => '2026-04-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employee_compensations')->insert([
            'employee_id' => $profile->id,
            'salary_type' => 'monthly',
            'base_salary' => 6500000,
            'fixed_allowance' => 500000,
            'effective_date' => '2026-04-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $draft = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        $details = collect($draft->json('data.run.details', []));
        $line = $details->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($line);
        $this->assertSame(7000000, (int) ($line['gross_pay'] ?? 0));
        $this->assertLessThanOrEqual(7000000, (int) ($line['net_pay'] ?? 0));
        $this->assertGreaterThan(0, (int) ($line['net_pay'] ?? 0));
    }

    public function test_monthly_payslip_summary_includes_overtime_and_deductions(): void
    {
        $workerTok = $this->workerToken();
        $admin = $this->adminToken();

        $worker = User::query()->where('email', 'payroll-worker@example.com')->firstOrFail();
        $otComponent = HcmSalaryComponent::resolveForOvertimePay();

        OvertimeRequest::query()->create([
            'user_id' => $worker->id,
            'hcm_salary_component_id' => $otComponent?->id,
            'request_type' => 'employee_request',
            'work_date' => '2026-06-12',
            'minutes' => 120,
            'status' => 'approved',
            'approved_by_user_id' => 1,
            'approved_at' => now(),
            'notes' => 'Overtime for payroll summary test',
        ]);

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 6,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $runId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->json('data.run.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$workerTok])
            ->getJson('/v1/hcm/payroll/my-slip?periodYear=2026&periodMonth=6')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period.periodYear', 2026)
            ->assertJsonPath('data.period.periodMonth', 6)
            ->assertJsonPath('data.totals.earningsTotal', 4182947.98)
            ->assertJsonPath('data.totals.deductionsTotal', 164000)
            ->assertJsonPath('data.totals.netPay', 4018947.98)
            ->assertJsonPath('data.slipNumber', 'PS-2026-06-1')
            ->assertJsonCount(3, 'data.earnings')
            ->assertJsonCount(3, 'data.deductions');
    }

    public function test_finalized_monthly_payslip_pdf_can_be_downloaded(): void
    {
        $workerTok = $this->workerToken();
        $admin = $this->adminToken();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 5,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $runId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->json('data.run.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertOk();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$workerTok])
            ->get('/v1/hcm/payroll/my-slip-pdf?periodYear=2026&periodMonth=5');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('%PDF', $response->streamedContent());
    }

    public function test_hcm_admin_can_send_finalized_monthly_slips_via_email(): void
    {
        Mail::fake();

        $this->workerToken();
        $admin = $this->adminToken();
        $worker = User::query()->where('email', 'payroll-worker@example.com')->firstOrFail();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 7,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $runId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->json('data.run.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/send-slips', [
                'periodYear' => 2026,
                'periodMonth' => 7,
                'userIds' => [$worker->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sentUserIds.0', $worker->id);

        Mail::assertSent(MonthlyPayslipMail::class, function (MonthlyPayslipMail $mail) use ($worker): bool {
            return $mail->hasTo($worker->email);
        });
    }
}
