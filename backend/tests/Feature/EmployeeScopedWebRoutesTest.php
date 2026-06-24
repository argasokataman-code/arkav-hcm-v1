<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Geofence;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeScopedWebRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_redirected_from_employee_scoped_web_routes(): void
    {
        $company = $this->createCompanyWithActiveSubscriptionFeatures([
            'attendance',
            'leave_management',
            'tickets',
            'overtime',
        ]);

        $owner = User::query()->create([
            'name' => 'Tenant Owner',
            'email' => 'tenant.owner.employee-guard@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'employment_status' => 'active',
            'designation' => 'Owner',
            'team' => 'Management',
            'nik' => 'EMP-EMPGUARD-OWNER',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $headers = ['X-Company-Code' => $company->code];

        $this->actingAs($owner)->withHeaders($headers)->get('/employee-dashboard')->assertRedirect(url('index'));
        $this->actingAs($owner)->withHeaders($headers)->get('/attendance-employee')->assertRedirect(url('attendance-admin'));
        $this->actingAs($owner)->withHeaders($headers)->get('/leaves-employee')->assertRedirect(url('leaves'));
        $this->actingAs($owner)->withHeaders($headers)->get('/tickets-employee')->assertRedirect(url('tickets-admin'));
        $this->actingAs($owner)->withHeaders($headers)->get('/overtime-employee')->assertRedirect(url('overtime'));
    }

    public function test_employee_can_access_employee_scoped_web_routes(): void
    {
        $company = $this->createCompanyWithActiveSubscriptionFeatures([
            'attendance',
            'leave_management',
            'tickets',
            'overtime',
        ]);

        $employee = User::query()->create([
            'name' => 'Tenant Employee',
            'email' => 'tenant.employee.employee-guard@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Operations',
            'nik' => 'EMP-EMPGUARD-EMP',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $headers = ['X-Company-Code' => $company->code];

        $this->actingAs($employee)->withHeaders($headers)->get('/employee-dashboard')->assertOk();
        $this->actingAs($employee)->withHeaders($headers)->get('/attendance-employee')->assertOk();
        $this->actingAs($employee)->withHeaders($headers)->get('/leaves-employee')->assertOk();
        $this->actingAs($employee)->withHeaders($headers)->get('/tickets-employee')->assertOk();
        $this->actingAs($employee)->withHeaders($headers)->get('/overtime-employee')->assertOk();
    }

    public function test_attendance_employee_page_renders_geofence_from_database(): void
    {
        $company = $this->createCompanyWithActiveSubscriptionFeatures(['attendance']);

        $employee = User::query()->create([
            'name' => 'Geo Employee',
            'email' => 'geo.employee@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Operations',
            'nik' => 'EMP-GEO-001',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        Geofence::query()->create([
            'company_id' => $company->id,
            'name' => 'Test Geofence',
            'latitude' => -6.5,
            'longitude' => 107.0,
            'radius_meters' => 300,
            'is_active' => true,
        ]);

        $response = $this->actingAs($employee)
            ->withHeaders(['X-Company-Code' => $company->code])
            ->get('/attendance-employee');

        $response->assertOk();
        $response->assertSee('data-gf-center-lat="-6.5"', false);
        $response->assertSee('data-gf-center-lng="107"', false);
        $response->assertSee('data-gf-radius="300"', false);
    }

    /**
     * @param  array<int, string>  $featureCodes
     */
    private function createCompanyWithActiveSubscriptionFeatures(array $featureCodes): Company
    {
        $company = Company::query()->create([
            'code' => 'cmp_'.strtolower((string) str()->random(8)),
            'name' => 'Employee Guard Co',
            'legal_name' => 'Employee Guard Co Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $package = Package::query()->create([
            'code' => 'pkg_'.strtolower((string) str()->random(8)),
            'name' => 'Employee Guard Package',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        foreach ($featureCodes as $code) {
            PackageFeature::query()->create([
                'package_uuid' => $package->uuid,
                'feature_code' => $code,
                'feature_name' => $code,
                'limit' => null,
            ]);
        }

        return $company;
    }
}
