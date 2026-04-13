<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageFeature;
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
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals('basic', $response->json('data.0.code'));
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
            'package_id' => $package->id,
            'feature_code' => 'employee_management',
            'feature_name' => 'Employee Management',
            'limit' => 100,
        ]);

        PackageFeature::create([
            'package_id' => $package->id,
            'feature_code' => 'payroll',
            'feature_name' => 'Payroll Processing',
            'limit' => null,
        ]);

        $response = $this->request()->getJson("/v1/saas/packages/{$package->id}");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertCount(2, $response->json('data.features'));
        $this->assertEquals('pro', $response->json('data.code'));
        $this->assertEquals(100, $response->json('data.features.0.limit'));
        $this->assertNull($response->json('data.features.1.limit'));
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

        $response = $this->request()->putJson("/v1/saas/packages/{$package->id}", [
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

        $response = $this->request()->deleteJson("/v1/saas/packages/{$package->id}");

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
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
            "/v1/saas/packages/{$package->id}/features",
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
            'package_id' => $package->id,
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
            'package_id' => $package->id,
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
            'package_id' => $package->id,
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
