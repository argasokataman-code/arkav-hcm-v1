<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportSnapshotApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $email = 'qa.login@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Admin',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $login->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function employeeToken(): string
    {
        $email = 'employee.snapshot@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Regular Employee',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $login->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function activeCompanyIdFor(string $email): int
    {
        $user = User::query()->where('email', $email)->firstOrFail();

        $companyId = CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('company_id');

        $this->assertNotNull($companyId);

        return (int) $companyId;
    }

    private function generateSnapshot(string $token, int $companyId, string $reportType = 'employee'): ReportSnapshot
    {
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/reports/snapshots', [
            'reportType' => $reportType,
            'periodStart' => now()->subDays(30)->toDateString(),
            'periodEnd' => now()->toDateString(),
            'async' => false,
        ])->assertStatus(202);

        return ReportSnapshot::query()->latest('id')->firstOrFail();
    }

    public function test_hcm_admin_can_generate_and_list_employee_snapshots(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('qa.login@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/reports/snapshots', [
            'reportType' => 'employee',
            'periodStart' => now()->subDays(30)->toDateString(),
            'periodEnd' => now()->toDateString(),
            'async' => false,
        ])
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.async', false);

        $list = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->getJson('/v1/hcm/reports/snapshots?reportType=employee');

        $list->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.reportType', 'employee')
            ->assertJsonPath('data.0.status', 'completed');
    }

    public function test_non_admin_is_forbidden_from_generating_snapshots(): void
    {
        $token = $this->employeeToken();
        $companyId = $this->activeCompanyIdFor('employee.snapshot@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/reports/snapshots', [
            'reportType' => 'employee',
            'periodStart' => now()->subDays(7)->toDateString(),
            'periodEnd' => now()->toDateString(),
            'async' => false,
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_hcm_admin_can_show_snapshot_detail(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('qa.login@example.com');
        $snapshot = $this->generateSnapshot($token, $companyId, 'employee');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->getJson('/v1/hcm/reports/snapshots/'.$snapshot->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $snapshot->id)
            ->assertJsonPath('data.reportType', 'employee')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'dataByModule',
                    'filters',
                    'exports',
                ],
            ]);
    }

    public function test_hcm_admin_can_show_snapshot_detail_by_uuid(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('qa.login@example.com');
        $snapshot = $this->generateSnapshot($token, $companyId, 'employee');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->getJson('/v1/hcm/reports/snapshots/'.$snapshot->uuid)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $snapshot->id);
    }

    public function test_hcm_admin_can_export_completed_snapshot_to_real_file_for_all_formats(): void
    {
        Storage::fake('public');

        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('qa.login@example.com');
        $snapshot = $this->generateSnapshot($token, $companyId, 'employee');

        foreach (['csv', 'excel', 'pdf'] as $fileType) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'X-Company-Id' => (string) $companyId,
            ])->postJson('/v1/hcm/reports/snapshots/'.$snapshot->id.'/export', [
                'fileType' => $fileType,
            ]);

            $response
                ->assertStatus(201)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.fileType', $fileType);

            $fileUrl = (string) $response->json('data.fileUrl');
            $storagePath = ltrim(str_replace('/storage/', '', $fileUrl), '/');
            Storage::disk('public')->assertExists($storagePath);
        }
    }

    public function test_export_returns_not_ready_when_snapshot_status_is_not_completed(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('qa.login@example.com');
        $snapshot = ReportSnapshot::query()->create([
            'company_id' => $companyId,
            'report_type' => 'employee',
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->toDateString(),
            'generated_at' => now(),
            'generated_by_user_id' => User::query()->where('email', 'qa.login@example.com')->value('id'),
            'status' => 'processing',
            'meta' => ['row_count' => 0],
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/reports/snapshots/'.$snapshot->id.'/export', [
            'fileType' => 'csv',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SNAPSHOT_NOT_READY');
    }

    public function test_export_returns_not_found_for_unknown_snapshot(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('qa.login@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/reports/snapshots/999999/export', [
            'fileType' => 'csv',
        ])
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SNAPSHOT_NOT_FOUND');
    }

    public function test_snapshot_detail_and_export_do_not_leak_across_companies(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('qa.login@example.com');
        $admin = User::query()->where('email', 'qa.login@example.com')->firstOrFail();

        $otherCompany = Company::query()->create([
            'code' => 'reporting_other_company',
            'name' => 'Reporting Other Company',
            'legal_name' => 'Reporting Other Company PT',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $foreignSnapshot = ReportSnapshot::query()->create([
            'company_id' => $otherCompany->id,
            'report_type' => 'employee',
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->toDateString(),
            'generated_at' => now(),
            'generated_by_user_id' => $admin->id,
            'status' => 'completed',
            'meta' => ['row_count' => 0],
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->getJson('/v1/hcm/reports/snapshots/'.$foreignSnapshot->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SNAPSHOT_NOT_FOUND');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/reports/snapshots/'.$foreignSnapshot->id.'/export', [
            'fileType' => 'csv',
        ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SNAPSHOT_NOT_FOUND');
    }
}
