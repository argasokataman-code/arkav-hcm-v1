<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PackageFeature;
use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Register admin user
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Admin',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        // Login to get token
        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ]);

        $this->token = $loginResponse->json('data.accessToken');
    }

    private function request()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    /**
     * Test: list packages endpoint returns active packages
     */
    public function test_list_packages_returns_active_packages()
    {
        Package::create([
            'code' => 'basic',
            'name' => 'Basic Plan',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        Package::create([
            'code' => 'pro',
            'name' => 'Pro Plan',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        Package::create([
            'code' => 'archived',
            'name' => 'Old Plan',
            'monthly_price' => 50000,
            'yearly_price' => 500000,
            'billing_unit' => 'flat',
            'status' => 'archived',
        ]);

        $response = $this->request()->getJson('/v1/saas/packages');

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('basic', $codes);
        $this->assertContains('pro', $codes);
        $this->assertNotContains('archived', $codes);
    }

    public function test_non_admin_cannot_see_global_admin_only_package_in_list_or_detail(): void
    {
        $public = Package::create([
            'code' => 'starter_public',
            'name' => 'Starter Public',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
        ]);

        $internal = Package::create([
            'code' => 'unlimited_internal',
            'name' => 'Unlimited Internal',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => true,
        ]);

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Tenant User',
            'email' => 'tenant.user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'tenant.user@example.com',
            'password' => 'StrongPass1',
        ]);
        $tenantToken = $login->json('data.accessToken');

        $list = $this->withHeader('Authorization', 'Bearer '.$tenantToken)
            ->getJson('/v1/saas/packages?status=active&per_page=50');

        $list->assertOk();
        $codes = collect($list->json('data'))->pluck('code')->all();
        $this->assertContains($public->code, $codes);
        $this->assertNotContains($internal->code, $codes);

        $detail = $this->withHeader('Authorization', 'Bearer '.$tenantToken)
            ->getJson('/v1/saas/packages/'.$internal->uuid);
        $detail->assertStatus(404);
    }

    public function test_global_admin_can_see_global_admin_only_package(): void
    {
        $internal = Package::create([
            'code' => 'unlimited_visible_admin',
            'name' => 'Unlimited Visible Admin',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => true,
        ]);

        $list = $this->request()->getJson('/v1/saas/packages?status=active&per_page=50');
        $list->assertOk();
        $codes = collect($list->json('data'))->pluck('code')->all();
        $this->assertContains($internal->code, $codes);

        $detail = $this->request()->getJson('/v1/saas/packages/'.$internal->uuid);
        $detail->assertOk();
        $detail->assertJsonPath('data.isGlobalAdminOnly', true);
    }

    public function test_feature_catalog_returns_backend_defined_and_custom_features(): void
    {
        $package = Package::create([
            'code' => 'custom_feature_pack',
            'name' => 'Custom Feature Pack',
            'monthly_price' => 1000,
            'yearly_price' => 12000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        PackageFeature::create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'custom_ai_workflows',
            'feature_name' => 'Custom AI Workflows',
            'limit' => null,
        ]);

        $response = $this->request()->getJson('/v1/saas/packages/feature-catalog');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $groups = collect($response->json('data'));
        $allCodes = $groups->flatMap(function (array $group) {
            return collect($group['features'] ?? [])->pluck('code');
        })->all();

        $this->assertContains('tickets', $allCodes);
        $this->assertContains('asset_management', $allCodes);
        $this->assertContains('custom_ai_workflows', $allCodes);

        $customGroup = $groups->firstWhere('module', 'custom');
        $this->assertNotNull($customGroup);
        $this->assertContains('custom_ai_workflows', collect($customGroup['features'] ?? [])->pluck('code')->all());
    }

    /**
     * Test: list package add-ons returns active add-ons
     */
    public function test_list_package_addons_returns_active_addons()
    {
        PackageAddon::create([
            'code' => 'extra_users',
            'name' => 'Extra Users',
            'description' => 'Extra user seats',
            'price_per_unit' => 25000,
            'unit_name' => 'user / month',
            'status' => 'active',
        ]);

        PackageAddon::create([
            'code' => 'storage',
            'name' => 'Storage Pack',
            'description' => 'Storage add-on',
            'price_per_unit' => 12000,
            'unit_name' => 'GB',
            'status' => 'inactive',
        ]);

        $response = $this->request()->getJson('/v1/saas/package-addons');

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('extra_users', $response->json('data.0.code'));
    }

    /**
     * Test: show package with features
     */
    public function test_show_package_with_features()
    {
        $package = Package::create([
            'code' => 'pro',
            'name' => 'Pro Plan',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'flat',
        ]);

        PackageFeature::create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'employee_management',
            'feature_name' => 'Employee Management',
            'limit' => 100,
        ]);

        PackageFeature::create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'payroll',
            'feature_name' => 'Payroll Processing',
            'limit' => null,
        ]);

        $response = $this->request()->getJson("/v1/saas/packages/{$package->uuid}");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertCount(2, $response->json('data.features'));
        $this->assertEquals('pro', $response->json('data.code'));
        $this->assertEquals(100, $response->json('data.features.0.limit'));
        $this->assertNull($response->json('data.features.1.limit'));
    }

    public function test_package_index_includes_subscription_counts(): void
    {
        $package = Package::create([
            'code' => 'growth',
            'name' => 'Growth Plan',
            'monthly_price' => 149000,
            'yearly_price' => 1490000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $companyOne = Company::create([
            'code' => 'cmp_one',
            'name' => 'Company One',
            'legal_name' => 'Company One LLC',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $companyTwo = Company::create([
            'code' => 'cmp_two',
            'name' => 'Company Two',
            'legal_name' => 'Company Two LLC',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        Subscription::create([
            'company_id' => $companyOne->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 149000,
        ]);

        Subscription::create([
            'company_id' => $companyTwo->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'cancelled',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDays(1),
            'billing_cycle' => 'monthly',
            'amount' => 149000,
        ]);

        $response = $this->request()->getJson('/v1/saas/packages?status=active&per_page=50');

        $response->assertOk();
        $response->assertJsonPath('data.0.code', 'growth');
        $response->assertJsonPath('data.0.activeSubscriptionsCount', 1);
        $response->assertJsonPath('data.0.totalSubscriptionsCount', 2);
    }

    /**
     * Test: create package requires admin
     */
    public function test_create_package_requires_admin()
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $nonAdminLoginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
        ]);

        $nonAdminToken = $nonAdminLoginResponse->json('data.accessToken');

        $response = $this->withHeader('Authorization', 'Bearer '.$nonAdminToken)
            ->postJson('/v1/saas/packages', [
                'code' => 'basic',
                'name' => 'Basic Plan',
                'monthly_price' => 99000,
                'yearly_price' => 990000,
                'billing_unit' => 'flat',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    /**
     * Test: create package add-on requires admin
     */
    public function test_create_package_addon_requires_admin()
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Regular User',
            'email' => 'addon.user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $nonAdminLoginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'addon.user@example.com',
            'password' => 'StrongPass1',
        ]);

        $nonAdminToken = $nonAdminLoginResponse->json('data.accessToken');

        $response = $this->withHeader('Authorization', 'Bearer '.$nonAdminToken)
            ->postJson('/v1/saas/package-addons', [
                'code' => 'extra_users',
                'name' => 'Extra Users',
                'price_per_unit' => 25000,
                'unit_name' => 'user / month',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    /**
     * Test: create package as admin
     */
    public function test_create_package_as_admin()
    {
        $response = $this->request()->postJson('/v1/saas/packages', [
            'code' => 'basic',
            'name' => 'Basic Plan',
            'description' => 'Basic plan for startups',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'flat',
            'color' => '#007bff',
            'sort_order' => 1,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertEquals('basic', $response->json('data.code'));
        $this->assertEquals('Basic Plan', $response->json('data.name'));

        $this->assertDatabaseHas('packages', [
            'code' => 'basic',
            'name' => 'Basic Plan',
        ]);
    }

    /**
     * Test: create package add-on as admin
     */
    public function test_create_package_addon_as_admin()
    {
        $response = $this->request()->postJson('/v1/saas/package-addons', [
            'code' => 'extra_users',
            'name' => 'Extra Users',
            'description' => 'Extra user seats',
            'price_per_unit' => 25000,
            'unit_name' => 'user / month',
            'status' => 'active',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertEquals('extra_users', $response->json('data.code'));
        $this->assertEquals('Extra Users', $response->json('data.name'));

        $this->assertDatabaseHas('package_addons', [
            'code' => 'extra_users',
            'name' => 'Extra Users',
        ]);
    }

    /**
     * Test: update package as admin
     */
    public function test_update_package_as_admin()
    {
        $package = Package::create([
            'code' => 'basic',
            'name' => 'Basic Plan',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'flat',
        ]);

        $response = $this->request()->putJson("/v1/saas/packages/{$package->uuid}", [
            'name' => 'Basic Plan Updated',
            'monthly_price' => 109000,
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertEquals('Basic Plan Updated', $response->json('data.name'));
        $this->assertEquals(109000, $response->json('data.monthlyPrice'));
        $this->assertEquals('inactive', $response->json('data.status'));

        $this->assertDatabaseHas('packages', [
            'code' => 'basic',
            'name' => 'Basic Plan Updated',
            'status' => 'inactive',
        ]);
    }

    /**
     * Test: update package add-on as admin
     */
    public function test_update_package_addon_as_admin()
    {
        $addon = PackageAddon::create([
            'code' => 'extra_users',
            'name' => 'Extra Users',
            'description' => 'Extra user seats',
            'price_per_unit' => 25000,
            'unit_name' => 'user / month',
            'status' => 'active',
        ]);

        $response = $this->request()->putJson("/v1/saas/package-addons/{$addon->id}", [
            'name' => 'Extra Seats',
            'price_per_unit' => 30000,
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertEquals('Extra Seats', $response->json('data.name'));
        $this->assertEquals(30000, $response->json('data.pricePerUnit'));
        $this->assertEquals('inactive', $response->json('data.status'));

        $this->assertDatabaseHas('package_addons', [
            'id' => $addon->id,
            'name' => 'Extra Seats',
            'status' => 'inactive',
        ]);
    }

    /**
     * Test: delete package as admin
     */
    public function test_delete_package_as_admin()
    {
        $package = Package::create([
            'code' => 'basic',
            'name' => 'Basic Plan',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'flat',
        ]);

        $response = $this->request()->deleteJson("/v1/saas/packages/{$package->uuid}");

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('packages', ['uuid' => $package->uuid]);
    }

    public function test_delete_package_is_blocked_when_subscription_history_exists(): void
    {
        $package = Package::create([
            'code' => 'growth',
            'name' => 'Growth Plan',
            'monthly_price' => 149000,
            'yearly_price' => 1490000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        $company = Company::create([
            'code' => 'cmp_growth',
            'name' => 'Growth Company',
            'legal_name' => 'Growth Company LLC',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 149000,
        ]);

        $response = $this->request()->deleteJson("/v1/saas/packages/{$package->uuid}");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'PACKAGE_IN_USE');

        $this->assertDatabaseHas('packages', ['uuid' => $package->uuid]);
    }

    /**
     * Test: delete package add-on as admin
     */
    public function test_delete_package_addon_as_admin()
    {
        $addon = PackageAddon::create([
            'code' => 'extra_users',
            'name' => 'Extra Users',
            'description' => 'Extra user seats',
            'price_per_unit' => 25000,
            'unit_name' => 'user / month',
            'status' => 'active',
        ]);

        $response = $this->request()->deleteJson("/v1/saas/package-addons/{$addon->id}");

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('package_addons', ['id' => $addon->id]);
    }

    /**
     * Test: add feature to package as admin
     */
    public function test_add_feature_to_package()
    {
        $package = Package::create([
            'code' => 'pro',
            'name' => 'Pro Plan',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'flat',
        ]);

        $response = $this->request()->postJson(
            "/v1/saas/packages/{$package->uuid}/features",
            [
                'feature_code' => 'payroll',
                'feature_name' => 'Payroll Processing',
                'limit' => null,
            ]
        );

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertEquals('payroll', $response->json('data.code'));
        $this->assertTrue($response->json('data.isUnlimited'));

        $this->assertDatabaseHas('package_features', [
            'package_uuid' => $package->uuid,
            'feature_code' => 'payroll',
        ]);
    }

    /**
     * Test: update feature limit as admin
     */
    public function test_update_feature_limit()
    {
        $package = Package::create([
            'code' => 'pro',
            'name' => 'Pro Plan',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'flat',
        ]);

        $feature = PackageFeature::create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'employees',
            'feature_name' => 'Employee Count',
            'limit' => 50,
        ]);

        $response = $this->request()->putJson(
            "/v1/saas/packages/features/{$feature->id}",
            ['limit' => 100]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertEquals(100, $response->json('data.limit'));

        $this->assertDatabaseHas('package_features', [
            'id' => $feature->id,
            'limit' => 100,
        ]);
    }

    /**
     * Test: delete feature from package
     */
    public function test_delete_feature_from_package()
    {
        $package = Package::create([
            'code' => 'pro',
            'name' => 'Pro Plan',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'flat',
        ]);

        $feature = PackageFeature::create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'payroll',
            'feature_name' => 'Payroll',
            'limit' => null,
        ]);

        $response = $this->request()->deleteJson(
            "/v1/saas/packages/features/{$feature->id}"
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('package_features', ['id' => $feature->id]);
    }
}
