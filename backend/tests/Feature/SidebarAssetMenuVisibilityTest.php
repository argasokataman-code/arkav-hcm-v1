<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarAssetMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_qa_hcm_admin_does_not_see_asset_menu_when_feature_disabled(): void
    {
        $company = $this->createCompanyWithActiveSubscriptionWithoutAssetFeature();

        $user = User::query()->create([
            'name' => 'Regular Admin',
            'email' => 'regular.admin@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employment_status' => 'active',
            'designation' => 'Admin',
            'team' => 'HR',
            'nik' => 'EMP-301',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/employees');

        $response->assertOk();
        $response->assertDontSee('href="'.url('assets').'"', false);
        $response->assertDontSee('href="'.url('asset-categories').'"', false);

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/assets')
            ->assertRedirect(url('employee-dashboard'));

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/asset-categories')
            ->assertRedirect(url('employee-dashboard'));
    }

    public function test_qa_super_admin_still_sees_asset_menu_when_feature_disabled(): void
    {
        $company = $this->createCompanyWithActiveSubscriptionWithoutAssetFeature();

        $user = User::query()->create([
            'name' => 'QA Admin',
            'email' => 'qa.login@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Operations',
            'nik' => 'EMP-302',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/employees');

        $response->assertOk();
        $response->assertSee('href="'.url('assets').'"', false);
        $response->assertSee('href="'.url('asset-categories').'"', false);

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/assets')
            ->assertOk();

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/asset-categories')
            ->assertOk();
    }

    public function test_secondary_super_admin_sees_only_active_hcm_menus(): void
    {
        $company = $this->createCompanyWithActiveSubscriptionWithoutAssetFeature();

        $user = User::query()->create([
            'name' => 'Super User 2',
            'email' => 'qa.hcm@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employment_status' => 'active',
            'designation' => 'HCM Admin',
            'team' => 'HCM',
            'nik' => 'EMP-303',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/employees');

        $response->assertOk();
        $response->assertSee('href="'.url('employees').'"', false);
        $response->assertSee('href="'.url('leaves').'"', false);
        $response->assertSee('href="'.url('attendance-admin').'"', false);
        $response->assertSee('href="'.url('employee-salary').'"', false);
        $response->assertSee('href="'.url('payroll-run').'"', false);
        $response->assertSee('href="'.url('users').'"', false);
        $response->assertSee('href="'.url('roles-permissions').'"', false);
        $response->assertDontSee('href="'.url('dashboard').'"', false);
        $response->assertDontSee('href="'.url('companies').'"', false);
        $response->assertDontSee('href="'.url('subscription').'"', false);
        $response->assertDontSee('href="'.url('deals-dashboard').'"', false);
        $response->assertDontSee('href="'.url('refferals').'"', false);
        $response->assertDontSee('href="'.url('pages').'"', false);
        $response->assertDontSee('href="'.url('login').'"', false);
    }

    private function createCompanyWithActiveSubscriptionWithoutAssetFeature(): Company
    {
        $company = Company::query()->create([
            'code' => 'cmp_'.strtolower((string) str()->random(8)),
            'name' => 'Sidebar Visibility Co',
            'legal_name' => 'Sidebar Visibility Co Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $package = Package::query()->create([
            'code' => 'pkg_'.strtolower((string) str()->random(8)),
            'name' => 'No Asset Package',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_id' => $package->id,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        return $company;
    }
}
