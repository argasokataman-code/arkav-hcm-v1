<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PackageFeature;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, company: Company}
     */
    private function makeAdminTenant(string $emailSuffix, array $featureCodes = []): array
    {
        $code = 'TST' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $company = Company::query()->create([
            'code' => $code,
            'name' => 'Web Feature Gate ' . $emailSuffix,
            'legal_name' => 'Web Feature Gate ' . $emailSuffix . ' Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $package = Package::query()->create([
            'code' => 'wfg-' . strtolower($emailSuffix),
            'name' => 'WFG ' . $emailSuffix,
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        foreach ($featureCodes as $featureCode) {
            PackageFeature::query()->create([
                'package_uuid' => $package->uuid,
                'feature_code' => $featureCode,
                'feature_name' => ucfirst($featureCode),
                'limit' => 1,
            ]);
        }

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

        $user = User::query()->create([
            'name' => 'WFG Admin ' . $emailSuffix,
            'email' => 'wfg.' . strtolower($emailSuffix) . '@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        // Tenant admin via HCM RBAC (active role with admin permission marker).
        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'HCM_ADMIN',
            'name' => 'HCM Admin',
            'status' => 'active',
            'is_system' => true,
        ]);
        $permission = HcmPermission::query()->updateOrCreate(
            ['code' => 'hcm.admin'],
            [
                'module' => 'hcm',
                'resource' => 'system',
                'action' => 'admin',
                'name' => 'HCM Admin',
                'is_active' => true,
            ]
        );
        DB::table('hcm_role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'uuid' => (string) Str::uuid(),
        ]);
        HcmUserRole::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return ['user' => $user, 'company' => $company];
    }

    public function test_tickets_web_page_blocked_when_subscription_lacks_tickets_feature(): void
    {
        $tenant = $this->makeAdminTenant('NoTickets', ['employee_management']);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/tickets-admin')
            ->assertRedirect(url('upgrade') . '?blocked=tickets');

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/ticket-master')
            ->assertRedirect(url('upgrade') . '?blocked=tickets');
    }

    public function test_payroll_web_pages_blocked_when_subscription_lacks_payroll_feature(): void
    {
        $tenant = $this->makeAdminTenant('NoPayroll', ['employee_management']);

        foreach (['/payroll', '/payroll-run', '/employee-salary'] as $path) {
            $this->actingAs($tenant['user'])
                ->withHeader('X-Company-Code', $tenant['company']->code)
                ->get($path)
                ->assertRedirect(url('upgrade') . '?blocked=payroll');
        }
    }

    public function test_training_web_pages_blocked_when_subscription_lacks_training_feature(): void
    {
        $tenant = $this->makeAdminTenant('NoTraining', ['employee_management']);

        foreach (['/training', '/trainers', '/training-type'] as $path) {
            $this->actingAs($tenant['user'])
                ->withHeader('X-Company-Code', $tenant['company']->code)
                ->get($path)
                ->assertRedirect(url('upgrade') . '?blocked=training');
        }
    }

    public function test_tickets_web_page_accessible_when_subscription_includes_feature(): void
    {
        $tenant = $this->makeAdminTenant('WithTickets', ['tickets']);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/tickets-admin')
            ->assertOk();
    }

    public function test_upgrade_page_recommendations_hide_global_admin_only_packages_for_tenant(): void
    {
        $tenant = $this->makeAdminTenant('UpgradePage', ['max_employees']);

        $tenantPackage = Package::query()->create([
            'code' => 'tenant-pro-upgrade-page',
            'name' => 'Tenant Pro Upgrade Page',
            'monthly_price' => 299000,
            'yearly_price' => 2990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
        ]);
        $tenantPackage->features()->create([
            'feature_code' => 'employee_management',
            'feature_name' => 'Employee Management',
            'limit' => 100,
        ]);

        $globalPackage = Package::query()->create([
            'code' => 'global-admin-only-upgrade-page',
            'name' => 'Unlimited (Global Admin) Upgrade Page',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => true,
        ]);
        $globalPackage->features()->create([
            'feature_code' => 'employee_management',
            'feature_name' => 'Employee Management',
            'limit' => null,
        ]);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/upgrade?blocked=employee_management')
            ->assertOk()
            ->assertSee('Employee Directory')
            ->assertSee('Tenant Pro Upgrade Page')
            ->assertDontSee('Unlimited (Global Admin) Upgrade Page');
    }

    public function test_upgrade_page_hides_addons_that_are_already_checked_out_by_company(): void
    {
        $tenant = $this->makeAdminTenant('AddonFilter', ['employee_management']);

        $alreadyCheckedOutAddon = PackageAddon::query()->create([
            'code' => 'tickets_addon_filter_checked',
            'name' => 'Checked Out Addon',
            'description' => 'Should be hidden after checkout.',
            'price_per_unit' => 59000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        $availableAddon = PackageAddon::query()->create([
            'code' => 'tickets_addon_filter_available',
            'name' => 'Available Addon',
            'description' => 'Should remain visible in catalog.',
            'price_per_unit' => 69000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $tenant['company']->id,
            'package_addon_id' => $alreadyCheckedOutAddon->id,
            'transaction_type' => 'addon',
            'description' => 'Addon checkout in progress',
            'amount' => 59000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 59000,
            'status' => 'issued',
            'due_date' => now()->addDay(),
        ]);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/upgrade')
            ->assertOk()
            ->assertDontSee($alreadyCheckedOutAddon->name)
            ->assertSee($availableAddon->name);
    }

    public function test_upgrade_page_shows_fallback_alternative_packages_when_feature_match_is_unavailable(): void
    {
        $tenant = $this->makeAdminTenant('FallbackRecommendation', ['employee_management']);

        Package::query()->create([
            'code' => 'fallback-upgrade-option',
            'name' => 'Fallback Upgrade Option',
            'monthly_price' => 259000,
            'yearly_price' => 2590000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
        ]);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/upgrade?blocked=tickets')
            ->assertOk()
            ->assertSee('Alternatif Paket')
            ->assertSee('Fallback Upgrade Option')
            ->assertDontSee('Belum ada paket aktif yang terdeteksi cocok untuk fitur ini. Anda tetap bisa memuat katalog paket di bawah untuk cek opsi lain.');
    }

    public function test_tenant_admin_can_access_tax_governance_web_page(): void
    {
        $tenant = $this->makeAdminTenant('TaxGovernanceRoute', ['tax_governance']);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/tax-employees')
            ->assertOk()
            ->assertSee('Employee Tax')
            ->assertSee('Compliance Overview');

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/taxes')
            ->assertRedirect(route('tax-employees'));
    }
}
