<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\ExportReconciliationEvidence;
use App\Models\HcmPayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmPayrollPkwtApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'PKWT Admin',
            'email' => 'pkwt-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'pkwt-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'team' => 'HR',
                'designation' => 'HR Admin',
                'employment_status' => 'active',
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'pkwt-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function workerToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'PKWT Worker',
            'email' => 'pkwt-worker@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'pkwt-worker@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employment_status' => 'active',
                'base_salary' => 4_000_000,
                'fixed_allowance' => 500_000,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'pkwt-worker@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_pkwt_compensation_preview_forbidden_for_non_admin(): void
    {
        $token = $this->workerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/payroll/pkwt-compensations?periodYear=2026&periodMonth=4')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_pkwt_compensation_forbidden_when_switching_to_unowned_company(): void
    {
        $admin = $this->adminToken();

        Company::query()->create([
            'code' => 'pkwt_other_company',
            'name' => 'PKWT Other Company',
            'legal_name' => 'PKWT Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin,
            'X-Company-Code' => 'pkwt_other_company',
        ])->getJson('/v1/hcm/payroll/pkwt-compensations?periodYear=2026&periodMonth=4')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_pkwt_compensation_preview_lists_employees_ending_this_month(): void
    {
        $admin = $this->adminToken();

        $eligibleUser = User::factory()->create(['name' => 'Ayu PKWT', 'email' => 'ayu-pkwt@example.com']);
        EmployeeProfile::query()->create([
            'user_id' => $eligibleUser->id,
            'employment_status' => 'active',
            'designation' => 'Designer',
            'base_salary' => 5_000_000,
            'fixed_allowance' => 500_000,
            'contract_type' => 'contract',
            'contract_start_date' => '2025-04-01',
            'contract_end_date' => '2026-04-20',
        ]);

        $otherMonthUser = User::factory()->create(['name' => 'Bimo Next Month', 'email' => 'bimo-pkwt@example.com']);
        EmployeeProfile::query()->create([
            'user_id' => $otherMonthUser->id,
            'employment_status' => 'active',
            'designation' => 'QA',
            'base_salary' => 4_500_000,
            'fixed_allowance' => 250_000,
            'contract_type' => 'contract',
            'contract_start_date' => '2025-05-01',
            'contract_end_date' => '2026-05-10',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll/pkwt-compensations?periodYear=2026&periodMonth=4')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.preview.summary.totalEmployees', 1)
            ->assertJsonPath('data.preview.summary.eligibleEmployees', 1)
            ->assertJsonPath('data.preview.summary.grandTotal', 5000000)
            ->assertJsonPath('data.preview.lines.0.userId', $eligibleUser->id)
            ->assertJsonPath('data.preview.lines.0.contractType', 'contract')
            ->assertJsonPath('data.preview.lines.0.contractEndDate', '2026-04-20')
            ->assertJsonPath('data.preview.lines.0.monthsOfService', 12)
            ->assertJsonPath('data.preview.lines.0.compensationAmount', 5000000)
            ->assertJsonPath('data.run', null);
    }

    public function test_pkwt_compensation_can_post_to_standalone_payroll_and_appear_in_slip_lines(): void
    {
        $admin = $this->adminToken();

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'PKWT Eligible',
            'email' => 'pkwt-eligible@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $eligibleUser = User::query()->where('email', 'pkwt-eligible@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $eligibleUser->id],
            [
                'employment_status' => 'active',
                'designation' => 'Engineer',
                'base_salary' => 6_000_000,
                'fixed_allowance' => 500_000,
                'contract_type' => 'contract',
                'contract_start_date' => '2025-04-01',
                'contract_end_date' => '2026-04-20',
            ],
        );

        $workerLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'pkwt-eligible@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();
        $workerTok = (string) $workerLogin->json('data.accessToken');

        $post = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/pkwt-compensations/post-payroll', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.run.purpose', 'pkwt_compensation')
            ->assertJsonPath('data.run.status', 'draft');

        $runId = (int) $post->json('data.run.id');
        $this->assertGreaterThan(0, $runId);
        $run = HcmPayrollRun::query()->findOrFail($runId);
        $this->assertSame(HcmPayrollRun::PURPOSE_PKWT_COMPENSATION, $run->purpose);
        $this->assertSame(1, $run->lines()->count());

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/disburse', [
                'applyAll' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.run.purpose', 'pkwt_compensation')
            ->assertJsonPath('data.payment.status', 'paid');

        $this->withHeaders(['Authorization' => 'Bearer '.$workerTok])
            ->getJson('/v1/hcm/payroll/my-slip-lines?periodYear=2026&periodMonth=4')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.runs.0.purpose', 'pkwt_compensation')
            ->assertJsonPath('data.lines.0.componentCode', 'kompensasi_pkwt')
            ->assertJsonPath('data.lines.0.paymentStatus', 'paid');
    }

    public function test_pkwt_post_payroll_requires_reconciliation_when_enforced(): void
    {
        config()->set('hcm.export_reconciliation.enabled', true);
        config()->set('hcm.export_reconciliation.enforce.pkwt_compensation.post_payroll', true);

        $admin = $this->adminToken();
        $adminUser = User::query()->where('email', 'pkwt-admin@example.com')->firstOrFail();
        $adminId = $adminUser->id;
        $companyId = (int) CompanyUser::query()
            ->where('user_id', $adminId)
            ->orderByDesc('id')
            ->value('company_id');

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'PKWT Eligible Enforced',
            'email' => 'pkwt-eligible-enforced@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $eligibleUser = User::query()->where('email', 'pkwt-eligible-enforced@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $eligibleUser->id],
            [
                'employment_status' => 'active',
                'designation' => 'Engineer',
                'base_salary' => 6_000_000,
                'fixed_allowance' => 500_000,
                'contract_type' => 'contract',
                'contract_start_date' => '2025-04-01',
                'contract_end_date' => '2026-04-20',
            ],
        );

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/pkwt-compensations/post-payroll', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'EXPORT_RECON_REQUIRED');

        ExportReconciliationEvidence::query()->create([
            'company_id' => $companyId,
            'feature_key' => 'pkwt_compensation',
            'action_key' => 'post_payroll',
            'scope_ref' => '2026-04',
            'exported_by_user_id' => $adminId,
            'exported_at' => now(),
            'file_format' => 'csv',
            'file_path' => 'reconciliation/pkwt-2026-04.csv',
            'row_count' => 1,
            'filter_payload' => [],
            'dataset_checksum' => hash('sha256', 'pkwt-2026-04'),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/pkwt-compensations/post-payroll', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.run.purpose', 'pkwt_compensation');
    }

    public function test_non_admin_pkwt_post_payroll_is_blocked_before_reconciliation_gate(): void
    {
        config()->set('hcm.export_reconciliation.enabled', true);
        config()->set('hcm.export_reconciliation.enforce.pkwt_compensation.post_payroll', true);

        $worker = $this->workerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$worker])
            ->postJson('/v1/hcm/payroll/pkwt-compensations/post-payroll', [
                'periodYear' => 2026,
                'periodMonth' => 4,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }
}
