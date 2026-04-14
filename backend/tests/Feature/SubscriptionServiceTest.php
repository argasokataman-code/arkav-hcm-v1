<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected string $nonAdminToken;
    protected Package $basicPackage;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Register and login admin
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Admin',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ]);

        $this->token = $loginResponse->json('data.accessToken');

        // Register and login non-admin user
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Company User',
            'email' => 'company.user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $nonAdminLoginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'company.user@example.com',
            'password' => 'StrongPass1',
        ]);

        $this->nonAdminToken = $nonAdminLoginResponse->json('data.accessToken');

        // Create test package and company
        $this->basicPackage = Package::create([
            'code' => 'basic',
            'name' => 'Basic Plan',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $this->company = Company::create([
            'code' => 'test',
            'name' => 'Test Company',
            'legal_name' => 'Test Company Inc',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);
    }

    private function request()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function nonAdminRequest()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->nonAdminToken);
    }

    public function test_create_subscription_as_admin()
    {
        $response = $this->request()->postJson('/v1/saas/subscriptions', [
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonths(1)->toDateString(),
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertEquals('basic', $response->json('data.planCode'));
        $this->assertEquals('monthly', $response->json('data.billingCycle'));

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'status' => 'active',
        ]);
    }

    public function test_list_subscriptions_with_filters()
    {
        Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'plan_code' => 'basic',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths(1),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $response = $this->request()->getJson('/v1/saas/subscriptions?status=active');

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_non_admin_can_list_and_view_subscription_read_only()
    {
        $subscription = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'plan_code' => 'basic',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths(1),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $listResponse = $this->nonAdminRequest()->getJson('/v1/saas/subscriptions?status=active');
        $listResponse->assertOk();
        $listResponse->assertJson(['success' => true]);
        $this->assertGreaterThan(0, count($listResponse->json('data') ?? []));

        $showResponse = $this->nonAdminRequest()->getJson("/v1/saas/subscriptions/{$subscription->id}");
        $showResponse->assertOk();
        $showResponse->assertJson(['success' => true]);
        $showResponse->assertJsonPath('data.id', $subscription->id);
    }

    public function test_update_subscription()
    {
        $subscription = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'plan_code' => 'basic',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths(1),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $response = $this->request()->putJson("/v1/saas/subscriptions/{$subscription->id}", [
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertEquals('inactive', $response->json('data.status'));

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'inactive',
        ]);
    }

    public function test_delete_subscription()
    {
        $subscription = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'plan_code' => 'basic',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths(1),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $response = $this->request()->deleteJson("/v1/saas/subscriptions/{$subscription->id}");

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }

    public function test_renew_subscription()
    {
        $subscription = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'plan_code' => 'basic',
            'status' => 'expired',
            'starts_at' => now()->subMonths(1),
            'ends_at' => now()->subDays(1),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $futureDate = now()->addMonths(1);

        $response = $this->request()->postJson("/v1/saas/subscriptions/{$subscription->id}/renew", [
            'ends_at' => $futureDate->toDateString(),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertEquals('active', $response->json('data.status'));

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
        ]);
    }

    public function test_non_admin_cannot_create_subscription()
    {
        $response = $this->nonAdminRequest()->postJson('/v1/saas/subscriptions', [
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonths(1)->toDateString(),
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'ADMIN_REQUIRED');
    }

    public function test_non_admin_cannot_update_delete_or_renew_subscription()
    {
        $subscription = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'plan_code' => 'basic',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths(1),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $updateResponse = $this->nonAdminRequest()->putJson("/v1/saas/subscriptions/{$subscription->id}", [
            'status' => 'inactive',
        ]);
        $updateResponse->assertStatus(403);
        $updateResponse->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $deleteResponse = $this->nonAdminRequest()->deleteJson("/v1/saas/subscriptions/{$subscription->id}");
        $deleteResponse->assertStatus(403);
        $deleteResponse->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $renewResponse = $this->nonAdminRequest()->postJson("/v1/saas/subscriptions/{$subscription->id}/renew", [
            'ends_at' => now()->addMonths(2)->toDateString(),
        ]);
        $renewResponse->assertStatus(403);
        $renewResponse->assertJsonPath('error.code', 'ADMIN_REQUIRED');
    }
}
