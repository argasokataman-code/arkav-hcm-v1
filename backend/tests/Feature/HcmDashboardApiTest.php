<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearerToken(string $email, string $designation, bool $isSuperAdmin = false): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Dashboard User',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        if ($isSuperAdmin) {
            $user->forceFill(['is_super_admin' => true])->save();
        }

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => $designation, 'employment_status' => 'active']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_admin_dashboard_summary_endpoint_returns_expected_structure(): void
    {
        $token = $this->bearerToken('admin-dashboard@example.com', 'HR Admin');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'executive' => ['activeEmployees', 'probationEmployees', 'pkwtDueIn30Days', 'attendanceToday', 'pendingLeaveRequests', 'payrollActiveMonth'],
                    'payrollCommandCenter' => ['periodStatus', 'latestRunStatus', 'latestRunPaymentStatus'],
                    'approvalInbox' => ['pendingLeaveRequest', 'pendingOvertimeRequest'],
                    'workforceAndAlerts' => ['joinerThisMonth', 'resignationThisMonth', 'promotionThisMonth', 'attendanceAnomaly'],
                ],
            ]);
    }

    public function test_employee_dashboard_summary_endpoint_returns_expected_structure(): void
    {
        $token = $this->bearerToken('employee-dashboard-api@example.com', 'Staff');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/employee-dashboard-summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'profile' => ['name', 'designation', 'profilePhotoUrl'],
                    'attendanceToday' => ['punchState', 'canPunch', 'summaryTotalWorking'],
                    'attendanceStats' => ['todayHours', 'weekHours', 'monthHours'],
                    'leave' => ['total', 'pending', 'approved', 'declined'],
                    'payroll' => ['latestPeriod', 'latestNetPay', 'paymentStatus'],
                    'ui' => ['referenceDate'],
                ],
            ]);
    }

    public function test_admin_dashboard_summary_export_defaults_to_xlsx_with_csv_fallback(): void
    {
        $token = $this->bearerToken('admin-dashboard-export@example.com', 'HR Admin');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/v1/hcm/dashboard-summary/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $csvResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/v1/hcm/dashboard-summary/export?format=csv');

        $csvResponse->assertOk();
        $csvResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $csvResponse->streamedContent();
        $this->assertStringContainsString('Section,Metric,Value', $content);
        $this->assertStringContainsString('Executive,"Total Employees",', $content);
    }

    public function test_package_compliance_employees_endpoint_requires_global_hcm_admin(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $token = $this->bearerToken('tenant-admin-dashboard@example.com', 'HR Admin', false);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ])->getJson('/v1/hcm/super-admin/package-compliance/'.$company->id.'/employees')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_package_compliance_employees_endpoint_returns_masked_payload(): void
    {
        $owner = User::factory()->create(['name' => 'Ujang Owner']);
        $employee = User::factory()->create(['name' => 'Rina Staff']);

        $company = Company::factory()->create([
            'name' => 'Default Company',
            'status' => 'active',
            'owner_user_id' => $owner->id,
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'designation' => 'Super Admin',
            'employment_status' => 'active',
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'designation' => 'HR Generalist',
            'employment_status' => 'active',
        ]);

        $package = Package::factory()->create([
            'name' => 'Enterprise',
            'code' => 'enterprise-plan',
            'status' => 'active',
        ]);

        DB::table('package_features')->insert([
            'package_uuid' => $package->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Max Employees',
            'limit' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => 'enterprise-plan',
            'status' => 'active',
        ]);

        $token = $this->bearerToken('global-dashboard@example.com', 'Super Admin', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ])->getJson('/v1/hcm/super-admin/package-compliance/'.$company->id.'/employees')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_name', 'Default Company')
            ->assertJsonPath('data.package_name', 'Enterprise')
            ->assertJsonPath('data.actual', 2)
            ->assertJsonPath('data.owner.user_id', $owner->id)
            ->assertJsonPath('data.owner.name_masked', 'U***')
            ->assertJsonPath('data.stats.total', 2)
            ->assertJsonPath('data.stats.active', 2)
            ->assertJsonPath('data.stats.probation', 0)
            ->assertJsonCount(2, 'data.employees')
            ->assertJsonPath('data.employees.0.name_masked', 'U***')
            ->assertJsonPath('data.employees.0.is_owner', true)
            ->assertJsonPath('data.employees.1.name_masked', 'R***')
            ->assertJsonPath('data.employees.1.is_owner', false);
    }
}
