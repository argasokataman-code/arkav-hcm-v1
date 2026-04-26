<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Package;
use App\Models\PackageFeature;
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
        $response->assertDontSee('href="'.url('custom-css').'"', false);
        $response->assertDontSee('href="'.url('clear-cache').'"', false);

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/assets')
            ->assertRedirect(url('employee-dashboard'));

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/asset-categories')
            ->assertRedirect(url('employee-dashboard'));
    }

    public function test_qa_super_admin_does_not_see_asset_menu_when_feature_disabled_in_tenant_context(): void
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
        // When in tenant context, even QA/global admin must respect the tenant's subscription.
        // Asset feature is not in this tenant's package, so asset menu must NOT appear.
        $response->assertDontSee('href="'.url('assets').'"', false);
        $response->assertDontSee('href="'.url('asset-categories').'"', false);
        // System/settings menus are still visible (not feature-gated)
        $response->assertSee('href="'.url('email-settings').'"', false);
        $response->assertSee('href="'.url('business-settings').'"', false);
        $response->assertSee('href="'.url('currencies').'"', false);
        $response->assertSee('href="'.url('language').'"', false);
        $response->assertSee('href="'.url('authentication-settings').'"', false);
        $response->assertSee('href="'.url('ai-settings').'"', false);

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/assets')
            ->assertOk();

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/asset-categories')
            ->assertOk();
    }

    public function test_secondary_hcm_admin_sees_hcm_menus_without_global_super_admin_hub(): void
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

        $subscription = Subscription::query()
            ->where('company_id', $company->id)
            ->latest('id')
            ->firstOrFail();

        PackageFeature::query()->create([
            'package_uuid' => $subscription->package_uuid,
            'feature_code' => 'payroll',
            'feature_name' => 'Payroll',
            'limit' => null,
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
        $response->assertDontSee('href="'.url('email-settings').'"', false);
        $response->assertDontSee('href="'.url('business-settings').'"', false);
        $response->assertDontSee('href="'.url('seo-settings').'"', false);
        $response->assertDontSee('href="'.url('localization-settings').'"', false);
        $response->assertDontSee('href="'.url('currencies').'"', false);
        $response->assertDontSee('href="'.url('payment-gateways').'"', false);
        $response->assertDontSee('href="'.url('language').'"', false);
        $response->assertDontSee('href="'.url('authentication-settings').'"', false);
        $response->assertDontSee('href="'.url('ai-settings').'"', false);
        $response->assertDontSee('href="'.url('custom-css').'"', false);
        $response->assertDontSee('href="'.url('clear-cache').'"', false);
        $response->assertDontSee('title="Super Admin"', false);
        $response->assertDontSee('data-bs-target="#super-admin"', false);
        $response->assertDontSee('href="#menu-superadmin"', false);
        $response->assertDontSee('<span>SUPER ADMIN</span>', false);
        $response->assertDontSee('href="'.url('deals-dashboard').'"', false);
        $response->assertDontSee('href="'.url('refferals').'"', false);
        $response->assertSee('href="'.url('pages').'"', false);
        $response->assertDontSee('href="'.url('login').'"', false);
    }

    public function test_regular_employee_does_not_see_payroll_menu_even_when_payroll_feature_enabled(): void
    {
        $company = $this->createCompanyWithActiveSubscriptionWithoutAssetFeature();

        $subscription = Subscription::query()
            ->where('company_id', $company->id)
            ->latest('id')
            ->firstOrFail();

        PackageFeature::query()->create([
            'package_uuid' => $subscription->package_uuid,
            'feature_code' => 'payroll',
            'feature_name' => 'Payroll',
            'limit' => null,
        ]);

        $user = User::query()->create([
            'name' => 'Regular Employee',
            'email' => 'regular.employee@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Operations',
            'nik' => 'EMP-304',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'employee',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/employee-dashboard');

        $response->assertOk();
        $response->assertDontSee('FINANCE & ACCOUNTS', false);
        $response->assertDontSee('Process Monthly Payroll', false);
        $response->assertDontSee('THR Payroll', false);

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/payroll')
            ->assertRedirect(url('employee-dashboard'));
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
            'package_uuid' => $package->uuid,
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
