<?php

namespace Tests\Feature\Hcm;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSummaryTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test dashboard summary only returns company-scoped data for a user.
     *
     * @return void
     */
    public function test_dashboard_summary_is_company_scoped()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Scoped Admin',
            'email' => 'dashboard-scope-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $adminUser = User::query()->where('email', 'dashboard-scope-admin@example.com')->firstOrFail();
        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $adminUser->id,
            'role' => 'admin',
            'status' => 'active',
        ]);
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $adminUser->id],
            [
                'company_id' => $companyA->id,
                'designation' => 'HR Admin',
                'employment_status' => 'active',
            ]
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'dashboard-scope-admin@example.com',
            'password' => 'StrongPass1',
            'companyCode' => (string) $companyA->code,
        ])->assertOk();
        $token = (string) $login->json('data.accessToken');

        $companyAUser1 = User::factory()->create();
        EmployeeProfile::query()->create([
            'user_id' => $companyAUser1->id,
            'company_id' => $companyA->id,
            'designation' => 'Staff',
            'employment_status' => 'active',
        ]);

        $companyAUser2 = User::factory()->create();
        EmployeeProfile::query()->create([
            'user_id' => $companyAUser2->id,
            'company_id' => $companyA->id,
            'designation' => 'Staff',
            'employment_status' => 'inactive',
        ]);

        $companyBUser = User::factory()->create();
        EmployeeProfile::query()->create([
            'user_id' => $companyBUser->id,
            'company_id' => $companyB->id,
            'designation' => 'Staff',
            'employment_status' => 'active',
        ]);

        // Act: Hit dashboard summary endpoint as this user
        $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
            'X-Company-Code' => (string) $companyA->code,
            ])
            ->getJson('/v1/hcm/dashboard-summary');
        $response->assertStatus(200);
        $data = $response->json('data.executive');

        // Assert: Data only contains employees from the same company
        // (Assume totalEmployees, activeEmployees, inactiveEmployees exposed)
        $this->assertIsArray($data);
        $this->assertArrayHasKey('totalEmployees', $data);
        $this->assertArrayHasKey('activeEmployees', $data);
        $this->assertArrayHasKey('inactiveEmployees', $data);

        // Optionally: Check that the numbers match the DB for this company
        $dbTotal = EmployeeProfile::where('company_id', $companyA->id)->count();
        $dbActive = EmployeeProfile::where('company_id', $companyA->id)
            ->whereIn('employment_status', ['active', 'probation'])->count();
        $dbInactive = EmployeeProfile::where('company_id', $companyA->id)
            ->where('employment_status', 'inactive')->count();

        $this->assertEquals($dbTotal, $data['totalEmployees'], 'Total employee mismatch');
        $this->assertEquals($dbActive, $data['activeEmployees'], 'Active employee mismatch');
        $this->assertEquals($dbInactive, $data['inactiveEmployees'], 'Inactive employee mismatch');
    }

    public function test_dashboard_department_breakdown_uses_department_assignment(): void
    {
        $company = Company::factory()->create();

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Department Dashboard Admin',
            'email' => 'dashboard-department-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $adminUser = User::query()->where('email', 'dashboard-department-admin@example.com')->firstOrFail();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $adminUser->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $adminUser->id],
            [
                'company_id' => $company->id,
                'designation' => 'HR Admin',
                'employment_status' => 'active',
            ]
        );

        $engineering = Department::query()->create([
            'code' => 'dept-eng-'.uniqid(),
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $finance = Department::query()->create([
            'code' => 'dept-fin-'.uniqid(),
            'name' => 'Finance',
            'is_active' => true,
        ]);

        // 3 active users in Engineering
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create();
            EmployeeProfile::query()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'department_id' => $engineering->id,
                'team' => 'Legacy Team Name '.$i,
                'designation' => 'Engineer',
                'employment_status' => 'active',
            ]);
        }

        // 1 probation user in Finance
        $financeUser = User::factory()->create();
        EmployeeProfile::query()->create([
            'user_id' => $financeUser->id,
            'company_id' => $company->id,
            'department_id' => $finance->id,
            'team' => 'Another Legacy Team',
            'designation' => 'Finance Staff',
            'employment_status' => 'probation',
        ]);

        // 1 active user without department assignment
        $unassignedUser = User::factory()->create();
        EmployeeProfile::query()->create([
            'user_id' => $unassignedUser->id,
            'company_id' => $company->id,
            'department_id' => null,
            'team' => 'Team Should Not Become Department Name',
            'designation' => 'Ops Staff',
            'employment_status' => 'active',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'dashboard-department-admin@example.com',
            'password' => 'StrongPass1',
            'companyCode' => (string) $company->code,
        ])->assertOk();

        $token = (string) $login->json('data.accessToken');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Code' => (string) $company->code,
        ])->getJson('/v1/hcm/dashboard-summary');

        $response->assertOk();

        $breakdown = collect($response->json('data.legacyWidgets.departmentBreakdown'));

        $this->assertSame(3, (int) ($breakdown->firstWhere('name', 'Engineering')['count'] ?? 0));
        $this->assertSame(1, (int) ($breakdown->firstWhere('name', 'Finance')['count'] ?? 0));
        // Includes one regular unassigned profile + the admin profile (also no department assigned).
        $this->assertSame(2, (int) ($breakdown->firstWhere('name', 'Unassigned Department')['count'] ?? 0));
        $this->assertNull($breakdown->firstWhere('name', 'Team Should Not Become Department Name'));
    }
}
