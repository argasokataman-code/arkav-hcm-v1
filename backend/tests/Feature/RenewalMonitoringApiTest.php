<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewalMonitoringApiTest extends TestCase
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

    public function test_non_admin_cannot_access_renewal_monitoring(): void
    {
        $this->userRequest()
            ->getJson('/v1/saas/renewal-monitoring/summary')
            ->assertStatus(403);
    }

    public function test_admin_can_fetch_summary_records_detail_and_anomalies(): void
    {
        [$company, $subscription] = $this->createFixture();

        $paidInvoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->subDay()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'amount_due' => 120000,
            'status' => 'paid',
            'is_paid' => true,
            'renewal_reason_code' => 'WEBHOOK_INVOICE_PAID',
            'renewal_reason_message' => 'Renewal paid from webhook.',
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        $anomalyInvoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s_fail', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->subDay()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'amount_due' => 120000,
            'status' => 'sent',
            'is_paid' => false,
            'renewal_reason_code' => 'MIDTRANS_DOWN',
            'renewal_reason_message' => 'midtrans down during renewal polling',
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        SubscriptionEvent::query()->create([
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'subscription_id' => $subscription->id,
            'subscription_uuid' => $subscription->uuid,
            'invoice_id' => $paidInvoice->id,
            'invoice_uuid' => $paidInvoice->uuid,
            'payment_id' => null,
            'payment_uuid' => null,
            'renewal_period_key' => $paidInvoice->renewal_period_key,
            'event_type' => 'renewal_paid',
            'reason_code' => 'WEBHOOK_INVOICE_PAID',
            'reason_message' => 'Renewal paid from webhook.',
            'payload' => null,
            'occurred_at' => now(),
        ]);

        $summary = $this->adminRequest()
            ->getJson('/v1/saas/renewal-monitoring/summary?days=30')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(2, $summary->json('data.summary.totalRecords'));
        $this->assertSame(1, $summary->json('data.summary.paid'));
        $this->assertSame(1, $summary->json('data.summary.anomalies'));

        $records = $this->adminRequest()
            ->getJson('/v1/saas/renewal-monitoring/records?days=30')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(2, $records->json('data'));

        $detail = $this->adminRequest()
            ->getJson('/v1/saas/renewal-monitoring/records/'.$paidInvoice->renewal_period_key)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($paidInvoice->renewal_period_key, $detail->json('data.renewalPeriodKey'));
        $this->assertSame('WEBHOOK_INVOICE_PAID', $detail->json('data.reason.code'));
        $this->assertCount(1, $detail->json('data.timeline'));

        $anomalies = $this->adminRequest()
            ->getJson('/v1/saas/renewal-monitoring/anomalies?days=30')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $anomalies->json('data'));
        $this->assertSame($anomalyInvoice->renewal_period_key, $anomalies->json('data.0.renewalPeriodKey'));
        $this->assertSame('MIDTRANS_DOWN', $anomalies->json('data.0.reasonCode'));
    }

    /**
     * @return array{0: Company, 1: Subscription}
     */
    private function createFixture(): array
    {
        $package = Package::query()->create([
            'code' => 'starter-monitoring',
            'name' => 'Starter Monitoring',
            'monthly_price' => 120000,
            'yearly_price' => 1200000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'RENMON'.strtoupper((string) str()->random(2)),
            'name' => 'Renewal Monitoring Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'auto_renew' => true,
            'billing_cycle' => 'monthly',
            'amount' => 120000,
            'metadata' => [
                'gateway' => 'midtrans',
            ],
        ]);

        return [$company, $subscription];
    }
}
