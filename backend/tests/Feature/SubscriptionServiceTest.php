<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
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

    public function test_list_subscriptions_respects_per_page(): void
    {
        for ($i = 0; $i < 3; $i++) {
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
        }

        $response = $this->request()->getJson('/v1/saas/subscriptions?per_page=2&status=active');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(2, $response->json('pagination.per_page'));
    }

    public function test_list_subscriptions_filter_suspended(): void
    {
        Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'plan_code' => 'basic',
            'status' => 'suspended',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $response = $this->request()->getJson('/v1/saas/subscriptions?status=suspended');

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertGreaterThanOrEqual(1, count($response->json('data') ?? []));
        $this->assertContains('suspended', array_column($response->json('data'), 'status'));
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

    public function test_update_subscription_to_suspended(): void
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
            'status' => 'suspended',
        ]);

        $response->assertOk();
        $this->assertEquals('suspended', $response->json('data.status'));
    }

    public function test_create_trial_subscription_success(): void
    {
        $starts = now()->toDateString();
        $ends = now()->addMonths(1)->toDateString();
        $trialEnds = now()->addDays(10)->toDateString();

        $response = $this->request()->postJson('/v1/saas/subscriptions', [
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'status' => 'trial',
            'starts_at' => $starts,
            'ends_at' => $ends,
            'trial_ends_at' => $trialEnds,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'trial');
        $this->assertNotNull($response->json('data.trialEndsAt'));
    }

    public function test_create_trial_missing_trial_ends_at_returns_422(): void
    {
        $response = $this->request()->postJson('/v1/saas/subscriptions', [
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'status' => 'trial',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonths(1)->toDateString(),
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_trial_trial_end_after_subscription_end_returns_422(): void
    {
        $starts = now()->toDateString();
        $ends = now()->addMonths(1)->toDateString();
        $trialEnds = now()->addMonths(2)->toDateString();

        $response = $this->request()->postJson('/v1/saas/subscriptions', [
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'status' => 'trial',
            'starts_at' => $starts,
            'ends_at' => $ends,
            'trial_ends_at' => $trialEnds,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_update_subscription_to_trial_requires_trial_ends_at(): void
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
            'status' => 'trial',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_update_subscription_to_trial_with_dates_succeeds(): void
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

        $trialEnds = now()->addDays(7)->toDateString();

        $response = $this->request()->putJson("/v1/saas/subscriptions/{$subscription->id}", [
            'status' => 'trial',
            'trial_ends_at' => $trialEnds,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'trial');
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

    public function test_renew_inactive_subscription_after_get_by_id(): void
    {
        $subscription = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'plan_code' => 'basic',
            'status' => 'inactive',
            'starts_at' => now()->subMonths(1),
            'ends_at' => now()->subDays(1),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $this->request()->getJson("/v1/saas/subscriptions/{$subscription->id}")->assertOk()->assertJsonPath('data.status', 'inactive');

        $futureDate = now()->addMonths(1)->toDateString();
        $renew = $this->request()->postJson("/v1/saas/subscriptions/{$subscription->id}/renew", [
            'ends_at' => $futureDate,
        ]);

        $renew->assertOk();
        $renew->assertJsonPath('data.status', 'active');
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

    public function test_update_subscription_changes_package_recalculates_amount(): void
    {
        $pkg2 = Package::create([
            'code' => 'premium',
            'name' => 'Premium Plan',
            'monthly_price' => 500000,
            'yearly_price' => 5000000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

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
            'package_id' => $pkg2->id,
        ]);

        $response->assertOk();
        $this->assertEquals(500000, (int) $response->json('data.amount'));
        $this->assertSame('premium', $response->json('data.planCode'));
    }

    public function test_create_subscription_rejects_non_active_package(): void
    {
        $inactive = Package::create([
            'code' => 'legacy_pkg',
            'name' => 'Legacy',
            'monthly_price' => 1,
            'yearly_price' => 10,
            'billing_unit' => 'company',
            'status' => 'archived',
        ]);

        $response = $this->request()->postJson('/v1/saas/subscriptions', [
            'company_id' => $this->company->id,
            'package_id' => $inactive->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'PACKAGE_NOT_ACTIVE');
    }

    public function test_invoice_mark_paid_activates_pending_payment_subscription(): void
    {
        $sub = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->basicPackage->id,
            'plan_code' => 'basic',
            'status' => 'pending_payment',
            'starts_at' => now(),
            'ends_at' => now()->addWeek(),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'subscription_id' => $sub->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'amount_due' => 99000,
        ]);

        $invoice->markAsPaid();

        $sub->refresh();
        $this->assertSame('active', $sub->status);
        $this->assertTrue($sub->ends_at->isFuture());
    }
}
