<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialUpgradeToPaidFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.mock_payments_enabled' => true]);
    }

    /**
     * @return array{token: string, company: Company, trialPackage: Package, paidPackage: Package, trialSubscription: Subscription}
     */
    private function bootstrapTrialTenant(): array
    {
        $company = $this->createIsolatedTestCompany([
            'name' => 'Trial Upgrade Co',
            'legal_name' => 'Trial Upgrade Co Ltd',
        ]);

        $trialPackage = Package::query()->create([
            'code' => 'trial-upgrade-trial',
            'name' => 'Trial Plan',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        $paidPackage = Package::query()->create([
            'code' => 'trial-upgrade-pro',
            'name' => 'Pro Plan',
            'monthly_price' => 299000,
            'yearly_price' => 2990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        $admin = $this->createHcmAdminWithCompany([
            'email' => 'trial-upgrade-owner@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $trialSubscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $trialPackage->uuid,
            'plan_code' => $trialPackage->code,
            'status' => 'trial',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDays(11),
            'trial_ends_at' => now()->addDays(11),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        return [
            'token' => $admin['token'],
            'company' => $company,
            'trialPackage' => $trialPackage,
            'paidPackage' => $paidPackage,
            'trialSubscription' => $trialSubscription,
        ];
    }

    public function test_trial_company_can_upgrade_checkout_then_pay_invoice_until_subscription_is_active(): void
    {
        $ctx = $this->bootstrapTrialTenant();

        $headers = [
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['company']->id,
        ];

        $this->assertDatabaseHas('subscriptions', [
            'id' => $ctx['trialSubscription']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'trial',
            'package_uuid' => $ctx['trialPackage']->uuid,
        ]);
        $this->assertSame(0, Invoice::query()->where('company_id', $ctx['company']->id)->count());

        $checkout = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/checkout', [
                'package_uuid' => $ctx['paidPackage']->uuid,
                'billing_cycle' => 'monthly',
                'billingEmail' => 'billing.trial-upgrade@example.com',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reused', false)
            ->assertJsonPath('data.subscription.status', 'pending_payment')
            ->assertJsonPath('data.subscription.packageCode', $ctx['paidPackage']->code)
            ->assertJsonPath('data.invoice.status', 'draft')
            ->assertJsonPath('data.invoice.isPaid', false);

        $invoiceId = (int) $checkout->json('data.invoice.id');
        $subscriptionId = (int) $checkout->json('data.subscription.id');

        // Checkout must reuse the existing active trial row by converting it to pending_payment.
        $this->assertSame($ctx['trialSubscription']->id, $subscriptionId);

        $hosted = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$invoiceId.'/mock-hosted-checkout')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('flow.mode', 'hosted')
            ->assertJsonPath('payment.status', 'pending');

        $paymentUuid = (string) $hosted->json('payment.uuid');
        $callbackToken = (string) $hosted->json('flow.callbackToken');

        $this->assertNotSame('', $paymentUuid);
        $this->assertNotSame('', $callbackToken);

        $this->withHeaders($headers)
            ->postJson('/v1/mock/webhook/charge-succeeded', [
                'payment_id' => $paymentUuid,
                'callback_token' => $callbackToken,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'company_id' => $ctx['company']->id,
            'status' => 'paid',
            'is_paid' => 1,
        ]);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoiceId,
            'status' => 'completed',
        ]);

        $upgraded = Subscription::query()->findOrFail($subscriptionId);
        $this->assertSame('active', $upgraded->status);
        $this->assertSame($ctx['paidPackage']->uuid, $upgraded->package_uuid);
        $this->assertNull($upgraded->trial_ends_at);

        $payment = Payment::query()->where('uuid', $paymentUuid)->firstOrFail();
        $this->assertSame('completed', $payment->status);
    }
}
