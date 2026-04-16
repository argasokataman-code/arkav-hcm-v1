<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasCompanyBillingOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Admin User',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $adminLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ]);
        $this->adminToken = (string) $adminLogin->json('data.accessToken');

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $userLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
        ]);
        $this->userToken = (string) $userLogin->json('data.accessToken');
    }

    private function adminRequest()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->adminToken);
    }

    private function userRequest()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->userToken);
    }

    public function test_non_admin_cannot_access_billing_overview(): void
    {
        $this->userRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=trial')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');
    }

    public function test_admin_can_list_trial_and_subscribed_tabs_with_invoice_email_status(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $trialCompany = Company::query()->create([
            'code' => 'TRIAL01',
            'name' => 'Trial Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        Subscription::query()->create([
            'company_id' => $trialCompany->id,
            'package_id' => $package->id,
            'plan_code' => $package->code,
            'status' => 'trial',
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => now()->addDays(29),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        $paidCompany = Company::query()->create([
            'code' => 'PAID01',
            'name' => 'Paid Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $paidSub = Subscription::query()->create([
            'company_id' => $paidCompany->id,
            'package_id' => $package->id,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $paidCompany->id,
            'subscription_id' => $paidSub->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 100000,
            'notes' => null,
        ]);

        InvoiceEmailLog::query()->create([
            'invoice_id' => $invoice->id,
            'to_email' => 'billing@paid.co',
            'status' => 'sent',
            'provider_message_id' => 'msg-1',
            'error_message' => null,
        ]);

        $trial = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=trial')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $trial->json('data'));
        $this->assertSame('TRIAL01', $trial->json('data.0.company.code'));
        $this->assertSame('trial', $trial->json('data.0.subscription.status'));

        $subscribed = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=subscribed')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $subscribed->json('data'));
        $this->assertSame('PAID01', $subscribed->json('data.0.company.code'));
        $this->assertSame('active', $subscribed->json('data.0.subscription.status'));
        $this->assertSame('sent', $subscribed->json('data.0.email.status'));
        $this->assertNotNull($subscribed->json('data.0.latestInvoice.id'));
    }
}

