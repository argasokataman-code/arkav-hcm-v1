<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XenditWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.xendit.callback_token' => 'xendit_test_token']);
    }

    public function test_invoice_paid_webhook_marks_payment_and_activates_pending_subscription(): void
    {
        [$company, $subscription] = $this->createSubscriptionFixture('pending_payment', 'monthly');

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(1)->toDateString(),
            'amount_due' => 250000,
            'status' => 'draft',
            'notes' => null,
        ]);

        $payment = Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'invoice_id' => $invoice->id,
            'amount' => 250000,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'gateway' => 'xendit',
            'gateway_reference' => 'xnd-inv-001',
            'metadata' => [
                'xendit_invoice_id' => 'xnd-inv-001',
                'xendit_external_id' => 'renewal-inv-'.$invoice->id,
            ],
        ]);

        $this->withHeaders([
            'X-Callback-Token' => 'xendit_test_token',
            'xendit-webhook-id' => 'wh_001',
        ])->postJson('/webhooks/xendit', [
            'event' => 'invoice.paid',
            'id' => 'xnd-inv-001',
            'external_id' => 'renewal-inv-'.$invoice->id,
            'amount' => 250000,
        ])->assertOk()->assertJsonPath('success', true);

        $payment->refresh();
        $invoice->refresh();
        $subscription->refresh();

        $this->assertSame('completed', $payment->status);
        $this->assertNotNull($payment->verified_at);
        $this->assertTrue($invoice->is_paid);
        $this->assertSame('active', $subscription->status);
    }

    public function test_recurring_invoice_is_extended_once_and_duplicate_webhook_is_ignored(): void
    {
        [$company, $subscription] = $this->createSubscriptionFixture('active', 'monthly');
        $originalEndsAt = $subscription->ends_at->copy();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(1)->toDateString(),
            'amount_due' => 150000,
            'status' => 'draft',
            'notes' => json_encode([
                'source' => 'recurring_subscription_renewal',
            ], JSON_UNESCAPED_SLASHES),
        ]);

        Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'invoice_id' => $invoice->id,
            'amount' => 150000,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'gateway' => 'xendit',
            'gateway_reference' => 'xnd-inv-002',
            'metadata' => [
                'xendit_invoice_id' => 'xnd-inv-002',
                'xendit_external_id' => 'renewal-inv-'.$invoice->id,
            ],
        ]);

        $payload = [
            'event' => 'invoice.paid',
            'id' => 'xnd-inv-002',
            'external_id' => 'renewal-inv-'.$invoice->id,
            'amount' => 150000,
        ];

        $this->withHeaders([
            'X-Callback-Token' => 'xendit_test_token',
            'xendit-webhook-id' => 'wh_002',
        ])->postJson('/webhooks/xendit', $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $subscription->refresh();
        $firstEndsAt = $subscription->ends_at->copy();

        $this->withHeaders([
            'X-Callback-Token' => 'xendit_test_token',
            'xendit-webhook-id' => 'wh_002',
        ])->postJson('/webhooks/xendit', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Already processed');

        $subscription->refresh();
        $this->assertTrue($firstEndsAt->greaterThan($originalEndsAt));
        $this->assertSame($firstEndsAt->toDateTimeString(), $subscription->ends_at->toDateTimeString());
    }

    public function test_missing_xendit_webhook_id_uses_fallback_and_is_idempotent(): void
    {
        $payload = [
            'event' => 'invoice.paid',
            'id' => 'xnd-inv-003',
        ];

        $this->withHeaders([
            'X-Callback-Token' => 'xendit_test_token',
        ])->postJson('/webhooks/xendit', $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders([
            'X-Callback-Token' => 'xendit_test_token',
        ])->postJson('/webhooks/xendit', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Already processed');
    }

    public function test_missing_callback_token_is_rejected(): void
    {
        $this->withHeaders([
            'xendit-webhook-id' => 'wh_missing_token',
        ])->postJson('/webhooks/xendit', [
            'event' => 'invoice.paid',
            'id' => 'xnd-inv-004',
        ])->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Invalid token');
    }

    public function test_invalid_callback_token_is_rejected(): void
    {
        $this->withHeaders([
            'X-Callback-Token' => 'invalid_xendit_token',
            'xendit-webhook-id' => 'wh_invalid_token',
        ])->postJson('/webhooks/xendit', [
            'event' => 'invoice.paid',
            'id' => 'xnd-inv-005',
        ])->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Invalid token');
    }

    /**
     * @return array{0: Company, 1: Subscription}
     */
    private function createSubscriptionFixture(string $status, string $billingCycle): array
    {
        $package = Package::query()->create([
            'code' => 'pro',
            'name' => 'Pro',
            'monthly_price' => 150000,
            'yearly_price' => 1500000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => strtoupper((string) str()->random(6)),
            'name' => 'Webhook Co '.str()->random(4),
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
            'status' => $status,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'trial_ends_at' => null,
            'auto_renew' => true,
            'billing_cycle' => $billingCycle,
            'amount' => 150000,
        ]);

        return [$company, $subscription];
    }
}
