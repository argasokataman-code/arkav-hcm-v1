<?php

namespace Tests\Feature;

use App\Jobs\ReconcilePendingRenewalPayments;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ReconcilePendingRenewalPaymentsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_marks_payment_as_paid_when_gateway_reports_settled(): void
    {
        [$company, $subscription] = $this->createFixture();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'amount_due' => 130000,
            'status' => 'sent',
            'is_paid' => false,
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        $payment = Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'invoice_id' => $invoice->id,
            'amount' => 130000,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'gateway' => 'xendit',
            'gateway_reference' => 'xnd-inv-reconcile-1',
            'metadata' => [
                'source' => 'recurring_subscription_renewal',
                'xendit_invoice_id' => 'xnd-inv-reconcile-1',
            ],
        ]);

        $this->mock(XenditService::class, function ($mock): void {
            $mock->shouldReceive('getInvoice')
                ->once()
                ->with('xnd-inv-reconcile-1')
                ->andReturn(['status' => 'SETTLED']);
        });

        dispatch_sync(new ReconcilePendingRenewalPayments());

        $payment->refresh();
        $invoice->refresh();

        $this->assertSame('completed', $payment->status);
        $this->assertNotNull($payment->verified_at);
        $this->assertTrue((bool) $invoice->is_paid);
        $this->assertSame('RECONCILIATION_PAID', $invoice->renewal_reason_code);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'renewal_paid',
            'reason_code' => 'RECONCILIATION_PAID',
        ]);
    }

    public function test_reconcile_flags_stale_invoice_when_gateway_reference_missing(): void
    {
        [$company, $subscription] = $this->createFixture();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s_stale', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'amount_due' => 130000,
            'status' => 'sent',
            'is_paid' => false,
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'invoice_id' => $invoice->id,
            'amount' => 130000,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'gateway' => 'xendit',
            'gateway_reference' => null,
            'metadata' => [
                'source' => 'recurring_subscription_renewal',
            ],
        ]);

        dispatch_sync(new ReconcilePendingRenewalPayments());

        $invoice->refresh();
        $this->assertSame('STALE_INVOICE_DETECTED', $invoice->renewal_reason_code);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'renewal_anomaly',
            'reason_code' => 'STALE_INVOICE_DETECTED',
        ]);
    }

    public function test_reconcile_emits_gateway_down_alert_when_xendit_is_unavailable(): void
    {
        Log::spy();

        [$company, $subscription] = $this->createFixture();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s_alert', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'amount_due' => 130000,
            'status' => 'sent',
            'is_paid' => false,
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'invoice_id' => $invoice->id,
            'amount' => 130000,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'gateway' => 'xendit',
            'gateway_reference' => 'xnd-inv-alert-1',
            'metadata' => [
                'source' => 'recurring_subscription_renewal',
                'xendit_invoice_id' => 'xnd-inv-alert-1',
            ],
        ]);

        $this->mock(XenditService::class, function ($mock): void {
            $mock->shouldReceive('getInvoice')
                ->once()
                ->with('xnd-inv-alert-1')
                ->andThrow(new \RuntimeException('gateway timeout'));
        });

        dispatch_sync(new ReconcilePendingRenewalPayments());

        $invoice->refresh();
        $this->assertSame('XENDIT_DOWN', $invoice->renewal_reason_code);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'renewal_monitoring.alert'
                && ($context['alert_type'] ?? null) === 'gateway_down'
                && ($context['reason_code'] ?? null) === 'XENDIT_DOWN';
        })->once();
    }

    /**
     * @return array{0: Company, 1: Subscription}
     */
    private function createFixture(): array
    {
        $package = Package::query()->create([
            'code' => 'starter-reconcile',
            'name' => 'Starter Reconcile',
            'monthly_price' => 130000,
            'yearly_price' => 1300000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'RECON'.strtoupper((string) str()->random(2)),
            'name' => 'Reconcile Co',
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
            'amount' => 130000,
            'metadata' => [
                'gateway' => 'xendit',
            ],
        ]);

        return [$company, $subscription];
    }
}
