<?php

namespace Tests\Feature;

use App\Mail\MonthlyPayslipMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmResignation;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use App\Models\HcmSalaryComponent;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\NotificationDelivery;
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

    private function workerToken(): string
    {
        if (! $this->company) {
            $this->company = $this->payrollCompany();
        }

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Payroll Worker',
            'email' => 'payroll-worker@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'payroll-worker@example.com')->firstOrFail();
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
                'base_salary' => 4_000_000,
                'fixed_allowance' => 100_000,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'payroll-worker@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ])->assertOk();

        $this->withHeaders(['X-Company-Id' => (string) $this->company->id]);

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

        $this->assertGreaterThanOrEqual(3, (int) $draft->json('data.lineCount'));
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
            ->assertJsonCount(2, 'data.lines');

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
        $result = $this->createHcmAdminWithCompany([
            'name' => 'Inactive Admin',
            'email' => 'inactive-payroll-admin@example.com',
            'password' => 'StrongPass1',
        ], $this->company);

        $this->company = $result['company'];

        $user = User::query()->where('email', 'inactive-payroll-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $this->company->id,
                'team' => 'HR',
                'designation' => 'HR Admin',
                'employment_status' => 'inactive',
                'base_salary' => 5_000_000,
                'fixed_allowance' => 0,
            ],
        );

        $this->withHeaders(['X-Company-Id' => (string) $this->company->id]);

        $token = $result['token'];

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

        CompanySetting::query()->updateOrCreate(
            ['company_id' => $this->company?->id, 'key' => 'payroll.monthly.disburse_before_payday_allowed'],
            ['value' => '1', 'type' => 'boolean'],
        );

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
        $this->assertFalse((bool) $secondDraft->json('data.reusedExistingDraft'));

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
        // fixed_allowance from employee compensation is excluded from payroll lines.
        $this->assertSame(6500000, (int) ($line['gross_pay'] ?? 0));
        $this->assertLessThanOrEqual(6500000, (int) ($line['net_pay'] ?? 0));
        $this->assertGreaterThan(0, (int) ($line['net_pay'] ?? 0));
    }

    public function test_pph21_ter_uses_monthly_rate_table(): void
    {
        $token = $this->adminToken();

        $employee = User::factory()->create([
            'name' => 'TER Lookup User',
            'email' => 'ter-lookup@example.com',
        ]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'employment_status' => 'active',
            'base_salary' => 5_500_000,
            'fixed_allowance' => 0,
        ]);

        EmployeeTaxProfile::query()->create([
            'employee_id' => $profile->id,
            'tax_status' => 'TK0',
            'ptkp_status' => 'TK/0',
            'effective_date' => '2026-04-01',
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

        $line = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $draft->json('data.run.id'))
            ->where('user_id', $employee->id)
            ->where('component_code', 'pph21_ter')
            ->first();

        $this->assertNotNull($line);
        $this->assertEquals(5_500_000.0, (float) ($line->meta['monthlyTaxableGross'] ?? 0));
        $this->assertEquals(13_750.0, (float) $line->amount);
        $this->assertSame('pph21_ter_lookup', $line->meta['source'] ?? null);
        $this->assertSame('A', $line->meta['pph21TerCategory'] ?? null);
    }

    public function test_pph21_ter_matrix_matches_category_rate_tables(): void
    {
        $token = $this->adminToken();

        $cases = [
            [
                'email' => 'ter-a-zero@example.com',
                'name' => 'TER A Zero',
                'tax_status' => 'TK0',
                'ptkp_status' => 'TK/0',
                'gross' => 5_400_000.0,
                'expected' => 0.0,
                'category' => 'A',
            ],
            [
                'email' => 'ter-b-quarter@example.com',
                'name' => 'TER B Quarter',
                'tax_status' => 'TK2',
                'ptkp_status' => 'TK/2',
                'gross' => 6_500_000.0,
                'expected' => 16_250.0,
                'category' => 'B',
            ],
            [
                'email' => 'ter-c-half@example.com',
                'name' => 'TER C Half',
                'tax_status' => 'K3',
                'ptkp_status' => 'K/3',
                'gross' => 7_000_000.0,
                'expected' => 35_000.0,
                'category' => 'C',
            ],
        ];

        foreach ($cases as $case) {
            $employee = User::factory()->create([
                'name' => $case['name'],
                'email' => $case['email'],
            ]);

            $profile = EmployeeProfile::query()->create([
                'user_id' => $employee->id,
                'employment_status' => 'active',
                'base_salary' => $case['gross'],
                'fixed_allowance' => 0,
            ]);

            EmployeeTaxProfile::query()->create([
                'employee_id' => $profile->id,
                'tax_status' => $case['tax_status'],
                'ptkp_status' => $case['ptkp_status'],
                'effective_date' => '2026-04-01',
            ]);
        }

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $runId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->json('data.run.id');

        foreach ($cases as $case) {
            $employeeId = (int) User::query()->where('email', $case['email'])->value('id');

            $line = HcmPayrollLine::query()
                ->where('hcm_payroll_run_id', $runId)
                ->where('user_id', $employeeId)
                ->where('component_code', 'pph21_ter')
                ->first();

            if ((float) $case['expected'] === 0.0) {
                $this->assertNull($line);
                continue;
            }

            $this->assertNotNull($line);
            $this->assertEquals($case['gross'], (float) ($line->meta['monthlyTaxableGross'] ?? 0));
            $this->assertEquals($case['expected'], (float) $line->amount);
            $this->assertSame($case['category'], $line->meta['pph21TerCategory'] ?? null);
            $this->assertSame('pph21_ter_lookup', $line->meta['source'] ?? null);
        }
    }

    public function test_pph21_uses_published_tax_governance_schedule_when_effective_for_period(): void
    {
        $token = $this->adminToken();

        $employee = User::factory()->create([
            'name' => 'Policy Driven User',
            'email' => 'policy-driven@example.com',
        ]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'company_id' => $this->company?->id,
            'employment_status' => 'active',
            'base_salary' => 5_500_000,
            'fixed_allowance' => 0,
        ]);

        EmployeeTaxProfile::query()->create([
            'employee_id' => $profile->id,
            'tax_status' => 'TK0',
            'ptkp_status' => 'TK/0',
            'effective_date' => '2026-04-01',
        ]);

        $policy = HcmTaxGovernancePolicy::query()->create([
            'company_id' => $this->company?->id,
            'policy_code' => 'PPH21-APR-OVERRIDE',
            'name' => 'April TER Override',
            'status' => HcmTaxGovernancePolicy::STATUS_PUBLISHED,
            'effective_start_date' => '2026-04-01',
            'effective_end_date' => null,
            'rules' => [
                'scheme' => 'TER',
                'currency' => 'IDR',
            ],
            'rate_schedules' => [
                [
                    'bracket' => 'A',
                    'rate' => 5,
                ],
            ],
            'version' => 3,
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

        $run = HcmPayrollRun::query()->findOrFail((int) $draft->json('data.run.id'));
        $line = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $run->id)
            ->where('user_id', $employee->id)
            ->where('component_code', 'pph21_ter')
            ->first();

        $this->assertNotNull($line);
        $this->assertGreaterThan(0, (float) $line->amount);
    }

    public function test_pph21_ignores_future_dated_published_tax_governance_policy_for_current_period(): void
    {
        $token = $this->adminToken();

        $employee = User::factory()->create([
            'name' => 'Future Policy User',
            'email' => 'future-policy@example.com',
        ]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'company_id' => $this->company?->id,
            'employment_status' => 'active',
            'base_salary' => 5_500_000,
            'fixed_allowance' => 0,
        ]);

        EmployeeTaxProfile::query()->create([
            'employee_id' => $profile->id,
            'tax_status' => 'TK0',
            'ptkp_status' => 'TK/0',
            'effective_date' => '2026-04-01',
        ]);

        HcmTaxGovernancePolicy::query()->create([
            'company_id' => $this->company?->id,
            'policy_code' => 'PPH21-MAY-FUTURE',
            'name' => 'May TER Override',
            'status' => HcmTaxGovernancePolicy::STATUS_PUBLISHED,
            'effective_start_date' => '2026-05-01',
            'effective_end_date' => null,
            'rules' => [
                'scheme' => 'TER',
                'currency' => 'IDR',
            ],
            'rate_schedules' => [
                [
                    'bracket' => 'A',
                    'rate' => 5,
                ],
            ],
            'version' => 4,
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

        $run = HcmPayrollRun::query()->findOrFail((int) $draft->json('data.run.id'));
        $line = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $run->id)
            ->where('user_id', $employee->id)
            ->where('component_code', 'pph21_ter')
            ->first();

        $this->assertNotNull($line);
        $this->assertNull($run->hcm_tax_governance_policy_id);
        $this->assertNull(data_get($run->meta, 'taxGovernancePolicy'));
        $this->assertEquals(13_750.0, (float) $line->amount);
        $this->assertSame('pph21_ter_lookup', $line->meta['source'] ?? null);
        $this->assertNotNull($line->meta);
    }

    public function test_pph21_ter_boundaries_are_inclusive_per_table_thresholds(): void
    {
        $token = $this->adminToken();

        $cases = [
            [
                'email' => 'ter-a-boundary-zero@example.com',
                'name' => 'TER A Boundary Zero',
                'tax_status' => 'TK0',
                'ptkp_status' => 'TK/0',
                'gross' => 5_400_000.0,
                'expected' => 0.0,
                'category' => 'A',
            ],
            [
                'email' => 'ter-a-boundary-next@example.com',
                'name' => 'TER A Boundary Next',
                'tax_status' => 'TK0',
                'ptkp_status' => 'TK/0',
                'gross' => 5_400_001.0,
                'expected' => 13_500.0,
                'category' => 'A',
            ],
            [
                'email' => 'ter-b-boundary-zero@example.com',
                'name' => 'TER B Boundary Zero',
                'tax_status' => 'TK2',
                'ptkp_status' => 'TK/2',
                'gross' => 6_200_000.0,
                'expected' => 0.0,
                'category' => 'B',
            ],
            [
                'email' => 'ter-b-boundary-next@example.com',
                'name' => 'TER B Boundary Next',
                'tax_status' => 'TK2',
                'ptkp_status' => 'TK/2',
                'gross' => 6_200_001.0,
                'expected' => 15_500.0,
                'category' => 'B',
            ],
            [
                'email' => 'ter-c-boundary-zero@example.com',
                'name' => 'TER C Boundary Zero',
                'tax_status' => 'K3',
                'ptkp_status' => 'K/3',
                'gross' => 6_600_000.0,
                'expected' => 0.0,
                'category' => 'C',
            ],
            [
                'email' => 'ter-c-boundary-next@example.com',
                'name' => 'TER C Boundary Next',
                'tax_status' => 'K3',
                'ptkp_status' => 'K/3',
                'gross' => 6_600_001.0,
                'expected' => 16_500.0,
                'category' => 'C',
            ],
        ];

        foreach ($cases as $case) {
            $employee = User::factory()->create([
                'name' => $case['name'],
                'email' => $case['email'],
            ]);

            $profile = EmployeeProfile::query()->create([
                'user_id' => $employee->id,
                'employment_status' => 'active',
                'base_salary' => $case['gross'],
                'fixed_allowance' => 0,
            ]);

            EmployeeTaxProfile::query()->create([
                'employee_id' => $profile->id,
                'tax_status' => $case['tax_status'],
                'ptkp_status' => $case['ptkp_status'],
                'effective_date' => '2026-04-01',
            ]);
        }

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $runId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->json('data.run.id');

        foreach ($cases as $case) {
            $employeeId = (int) User::query()->where('email', $case['email'])->value('id');

            $line = HcmPayrollLine::query()
                ->where('hcm_payroll_run_id', $runId)
                ->where('user_id', $employeeId)
                ->where('component_code', 'pph21_ter')
                ->first();

            if ((float) $case['expected'] === 0.0) {
                $this->assertNull($line);
                continue;
            }

            $this->assertNotNull($line);
            $this->assertEquals($case['gross'], (float) ($line->meta['monthlyTaxableGross'] ?? 0));
            $this->assertEquals($case['expected'], (float) $line->amount);
            $this->assertSame($case['category'], $line->meta['pph21TerCategory'] ?? null);
            $this->assertSame('pph21_ter_lookup', $line->meta['source'] ?? null);
        }
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
            ->assertJsonPath('data.totals.deductionsTotal', 0)
            ->assertJsonPath('data.totals.netPay', 4182947.98)
            ->assertJsonPath('data.slipNumber', 'PS-2026-06-'.$worker->id)
            ->assertJsonCount(3, 'data.earnings')
            ->assertJsonCount(0, 'data.deductions');

        $slipResponse = $this->withHeaders(['Authorization' => 'Bearer '.$workerTok])
            ->getJson('/v1/hcm/payroll/my-slip?periodYear=2026&periodMonth=6')
            ->assertOk();

        $overtimeLine = collect($slipResponse->json('data.earnings'))
            ->first(fn (array $line): bool => ($line['componentCode'] ?? null) === HcmSalaryComponent::CODE_OVERTIME_PAY);

        $this->assertNotNull($overtimeLine);
        $this->assertSame(1, (int) $slipResponse->json('data.overtime.lineCount'));
        $this->assertEquals((float) ($overtimeLine['amount'] ?? 0), (float) $slipResponse->json('data.overtime.amountTotal'));
        $this->assertEquals((float) ($overtimeLine['amount'] ?? 0), (float) $slipResponse->json('data.totals.overtimeTotal'));

        $adminRows = (array) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll/admin-slips?periodYear=2026&periodMonth=6')
            ->assertOk()
            ->json('data.rows');

        $adminRow = collect($adminRows)->first(fn (array $row): bool => (int) ($row['userId'] ?? 0) === (int) $worker->id);

        $this->assertNotNull($adminRow);
        $this->assertEquals((float) ($overtimeLine['amount'] ?? 0), (float) ($adminRow['overtime']['amountTotal'] ?? 0));
        $this->assertEquals((float) ($overtimeLine['amount'] ?? 0), (float) ($adminRow['totals']['overtimeTotal'] ?? 0));
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

    public function test_admin_slips_includes_email_delivery_status_helper_after_send_slips(): void
    {
        Mail::fake();

        $this->workerToken();
        $admin = $this->adminToken();
        $worker = User::query()->where('email', 'payroll-worker@example.com')->firstOrFail();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 11,
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
                'periodMonth' => 11,
                'userIds' => [$worker->id],
            ])
            ->assertOk();

        $rows = (array) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll/admin-slips?periodYear=2026&periodMonth=11')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.rows');

        $targetRow = collect($rows)->first(function (array $row) use ($worker): bool {
            return (int) ($row['userId'] ?? 0) === (int) $worker->id;
        });

        $this->assertNotNull($targetRow);
    }

    public function test_monthly_report_aggregates_monthly_thr_and_pkwt_runs_and_can_export(): void
    {
        $this->workerToken();
        $admin = $this->adminToken();
        $worker = User::query()->where('email', 'payroll-worker@example.com')->firstOrFail();

        EmployeeProfile::query()->where('user_id', $worker->id)->update([
            'designation' => 'Finance Staff',
            'team' => 'Finance',
            'bank_name' => 'BCA',
            'bank_account_no' => '111222333',
            'bank_branch' => 'Jakarta',
        ]);

        $otComponent = HcmSalaryComponent::resolveForOvertimePay();

        OvertimeRequest::query()->create([
            'company_id' => (int) $this->company?->id,
            'user_id' => $worker->id,
            'hcm_salary_component_id' => $otComponent?->id,
            'request_type' => 'employee_request',
            'work_date' => '2026-09-18',
            'minutes' => 90,
            'status' => 'approved',
            'approved_by_user_id' => User::query()->where('email', 'payroll-admin@example.com')->firstOrFail()->id,
            'approved_at' => now(),
            'notes' => 'Monthly report overtime visibility test',
        ]);

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 9,
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

        $period = \App\Models\HcmPayrollPeriod::query()->findOrFail($periodId);
        $companyId = (int) $this->company?->id;
        $thrComponent = HcmSalaryComponent::query()->create([
            'company_id' => $companyId,
            'code' => 'thr_test',
            'name' => 'THR Test',
            'kind' => 'addition',
            'category' => 'bonus',
            'affects_net_pay' => true,
            'is_active' => true,
        ]);
        $pkwtComponent = HcmSalaryComponent::query()->create([
            'company_id' => $companyId,
            'code' => 'pkwt_test',
            'name' => 'PKWT Test',
            'kind' => 'addition',
            'category' => 'compensation',
            'affects_net_pay' => true,
            'is_active' => true,
        ]);

        $thrRun = HcmPayrollRun::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_period_id' => $period->id,
            'purpose' => HcmPayrollRun::PURPOSE_THR,
            'status' => HcmPayrollRun::STATUS_FINALIZED,
            'calculated_at' => now(),
            'finalized_at' => now(),
            'finalized_by_user_id' => User::query()->where('email', 'payroll-admin@example.com')->firstOrFail()->id,
        ]);
        $pkwtRun = HcmPayrollRun::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_period_id' => $period->id,
            'purpose' => HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
            'status' => HcmPayrollRun::STATUS_FINALIZED,
            'calculated_at' => now(),
            'finalized_at' => now(),
            'finalized_by_user_id' => User::query()->where('email', 'payroll-admin@example.com')->firstOrFail()->id,
        ]);

        HcmPayrollLine::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_run_id' => $thrRun->id,
            'user_id' => $worker->id,
            'hcm_salary_component_id' => $thrComponent->id,
            'component_code' => 'thr_test',
            'component_name' => 'THR Test',
            'kind' => 'addition',
            'category' => 'bonus',
            'amount' => 1500000,
            'sort_order' => 10,
            'meta' => ['paymentStatus' => 'paid'],
        ]);
        HcmPayrollLine::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_run_id' => $pkwtRun->id,
            'user_id' => $worker->id,
            'hcm_salary_component_id' => $pkwtComponent->id,
            'component_code' => 'pkwt_test',
            'component_name' => 'PKWT Test',
            'kind' => 'addition',
            'category' => 'compensation',
            'amount' => 700000,
            'sort_order' => 10,
            'meta' => ['paymentStatus' => 'paid'],
        ]);

        $report = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll/monthly-report?periodYear=2026&periodMonth=9')
            ->assertOk()
            ->assertJsonPath('success', true);

        $targetRow = collect($report->json('data.rows'))->first(fn (array $row): bool => (int) ($row['userId'] ?? 0) === (int) $worker->id);

        $this->assertNotNull($targetRow);
        $this->assertSame('BCA', $targetRow['bankName'] ?? null);
        $this->assertSame((int) $thrRun->id, (int) (($targetRow['breakdown'][HcmPayrollRun::PURPOSE_THR]['runId'] ?? 0)));
        $this->assertSame((int) $pkwtRun->id, (int) (($targetRow['breakdown'][HcmPayrollRun::PURPOSE_PKWT_COMPENSATION]['runId'] ?? 0)));
        $this->assertGreaterThan(0, (float) (($targetRow['breakdown'][HcmPayrollRun::PURPOSE_MONTHLY]['overtime']['amountTotal'] ?? 0)));
        $this->assertGreaterThan(0, (float) (($targetRow['totals']['overtimeTotal'] ?? 0)));
        $this->assertGreaterThan(0, (float) (($report->json('data.summary.totalOvertimePay')) ?? 0));
        $this->assertGreaterThan(0, (float) (($report->json('data.summary.totalsByPurpose.thr')) ?? 0));
        $this->assertGreaterThan(0, (float) (($report->json('data.summary.totalsByPurpose.pkwt_compensation')) ?? 0));

        $exportResponse = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->get('/v1/hcm/payroll/monthly-report/export?periodYear=2026&periodMonth=9&format=csv')
            ->assertOk()
            ->assertHeader('content-disposition');

        $exportBody = $exportResponse->streamedContent();
        $this->assertStringContainsString('monthly_overtime', $exportBody);
        $this->assertStringContainsString('total_overtime', $exportBody);
    }

    public function test_hcm_admin_can_send_finalized_monthly_slips_with_uuid_identifier(): void
    {
        Mail::fake();

        $this->workerToken();
        $admin = $this->adminToken();
        $worker = User::query()->where('email', 'payroll-worker@example.com')->firstOrFail();

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 8,
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
                'periodMonth' => 8,
                'userIds' => [$worker->uuid],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sentUserIds.0', $worker->id);

        Mail::assertSent(MonthlyPayslipMail::class, function (MonthlyPayslipMail $mail) use ($worker): bool {
            return $mail->hasTo($worker->email);
        });
    }
}
