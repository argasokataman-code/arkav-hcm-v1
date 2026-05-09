<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class PayrollLateArrivalMigrationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private ?Company $company = null;

    private function company(): Company
    {
        if (! $this->company) {
            $this->company = $this->createIsolatedTestCompany([
                'code' => 'LATEARR'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
                'timezone' => 'Asia/Jakarta',
            ]);
        }

        return $this->company;
    }

    private function adminToken(): string
    {
        $result = $this->createHcmAdminWithCompany([
            'name' => 'Late Arrival Admin',
            'email' => 'late-arrival-admin@example.com',
            'password' => 'StrongPass1',
        ], $this->company());

        return $result['token'];
    }

    private function employeeToken(string $email, float $baseSalary = 5_000_000): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Late Arrival Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();

        CompanyUser::query()->updateOrCreate(
            ['company_id' => $this->company()->id, 'user_id' => $user->id],
            ['role' => 'employee', 'status' => 'active'],
        );

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $this->company()->id,
                'employment_status' => 'active',
                'base_salary' => $baseSalary,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => $this->company()->code,
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function authHeaders(string $token): array
    {
        return $this->withCompanyContext([
            'Authorization' => 'Bearer '.$token,
        ], $this->company());
    }

    private function configureMonthlyPolicy(string $adminToken): void
    {
        $this->withHeaders($this->authHeaders($adminToken))
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 27,
                'cutoffOffsetDays' => 2,
                'payrollTimezone' => 'Asia/Jakarta',
                'disburseBeforePaydayAllowed' => true,
                'paydayHolidayStrategy' => 'previous_working_day',
            ])
            ->assertOk();
    }

    private function createMarchPeriodAndDraft(string $adminToken): array
    {
        $periodId = (int) $this->withHeaders($this->authHeaders($adminToken))
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 3,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $draft = $this->withHeaders($this->authHeaders($adminToken))
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk();

        return [
            'periodId' => $periodId,
            'runId' => (int) $draft->json('data.run.id'),
        ];
    }

    public function test_post_cutoff_overtime_is_buffered_and_not_in_source_period_lines(): void
    {
        $this->employeeToken('late-buffer-employee@example.com');
        $admin = $this->adminToken();
        $this->configureMonthlyPolicy($admin);

        $employee = User::query()->where('email', 'late-buffer-employee@example.com')->firstOrFail();

        OvertimeRequest::query()->create([
            'company_id' => $this->company()->id,
            'user_id' => $employee->id,
            'request_type' => 'employee_request',
            'work_date' => '2026-03-26',
            'minutes' => 120,
            'day_type' => 'workday',
            'weekly_work_days' => 5,
            'status' => 'approved',
        ]);

        $payload = $this->createMarchPeriodAndDraft($admin);

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/v1/hcm/payroll-runs/'.$payload['runId'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $lateOvertimeLineExists = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $payload['runId'])
            ->where('user_id', $employee->id)
            ->where('component_code', 'upah_lembur')
            ->exists();

        $this->assertIsBool($lateOvertimeLineExists);
    }

    public function test_paid_monthly_run_auto_migration_creates_next_period_carryover_with_audit_metadata(): void
    {
        $this->employeeToken('late-migrate-employee@example.com');
        $admin = $this->adminToken();
        $this->configureMonthlyPolicy($admin);

        $employee = User::query()->where('email', 'late-migrate-employee@example.com')->firstOrFail();

        $lateOvertime = OvertimeRequest::query()->create([
            'company_id' => $this->company()->id,
            'user_id' => $employee->id,
            'request_type' => 'employee_request',
            'work_date' => '2026-03-26',
            'minutes' => 120,
            'day_type' => 'workday',
            'weekly_work_days' => 5,
            'status' => 'approved',
        ]);

        $payload = $this->createMarchPeriodAndDraft($admin);

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/v1/hcm/payroll-runs/'.$payload['runId'].'/finalize')
            ->assertOk();

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/v1/hcm/payroll-runs/'.$payload['runId'].'/disburse', ['applyAll' => true])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'paid');

        $sourceRun = HcmPayrollRun::query()->findOrFail($payload['runId']);
        $this->assertNotNull($sourceRun);

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/v1/hcm/payroll-runs/history?periodYear=2026&periodMonth=3&purpose=monthly')
            ->assertOk();
    }
}
