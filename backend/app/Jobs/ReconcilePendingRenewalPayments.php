<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Services\MidtransService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcilePendingRenewalPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Payment::query()
            ->where('status', 'pending')
            ->where('metadata->source', 'recurring_subscription_renewal')
            ->where('gateway', 'midtrans')
            ->whereNotNull('invoice_id')
            ->with(['invoice.subscription'])
            ->orderBy('id')
            ->chunkById(100, function ($payments): void {
                foreach ($payments as $payment) {
                    $this->reconcilePayment($payment);
                }
            });
    }

    private function reconcilePayment(Payment $payment): void
    {
        $invoice = $payment->invoice;
        if (! $invoice) {
            return;
        }

        $this->reconcileMidtransPayment($payment, $invoice);
    }

    private function reconcileMidtransPayment(Payment $payment, Invoice $invoice): void
    {
        $orderId = (string) (($payment->metadata['midtrans_order_id'] ?? '') ?: $payment->gateway_reference);
        if ($orderId === '') {
            $this->updateInvoiceReason($invoice, 'STALE_INVOICE_DETECTED', 'Pending Midtrans renewal payment has no order_id for reconciliation.');
            $this->recordEvent($invoice->subscription, $invoice, $payment, 'renewal_anomaly', 'STALE_INVOICE_DETECTED', 'Pending Midtrans renewal payment has no order_id.');
            $this->emitRenewalMetric('renewal_reconciliation_anomaly', 'STALE_INVOICE_DETECTED', $invoice);
            $this->emitFailureSpikeAlert($invoice, 'STALE_INVOICE_DETECTED');
            return;
        }

        try {
            $midtrans = app(MidtransService::class);
            $tx       = $midtrans->getTransaction($orderId);
        } catch (\Throwable $exception) {
            $this->updateInvoiceReason($invoice, 'MIDTRANS_DOWN', 'Midtrans reconciliation unavailable: ' . $exception->getMessage());
            $this->recordEvent($invoice->subscription, $invoice, $payment, 'renewal_anomaly', 'MIDTRANS_DOWN', 'Midtrans reconciliation unavailable.');
            $this->emitRenewalMetric('renewal_reconciliation_anomaly', 'MIDTRANS_DOWN', $invoice);
            $this->emitRenewalAlert('gateway_down', 'MIDTRANS_DOWN', 'Midtrans reconciliation unavailable.', $invoice, ['error' => $exception->getMessage()]);
            $this->emitFailureSpikeAlert($invoice, 'MIDTRANS_DOWN');
            return;
        }

        if (! is_array($tx)) {
            $this->updateInvoiceReason($invoice, 'MIDTRANS_DOWN', 'Midtrans reconciliation returned no transaction payload.');
            $this->recordEvent($invoice->subscription, $invoice, $payment, 'renewal_anomaly', 'MIDTRANS_DOWN', 'Midtrans reconciliation returned no transaction payload.');
            $this->emitRenewalMetric('renewal_reconciliation_anomaly', 'MIDTRANS_DOWN', $invoice);
            $this->emitRenewalAlert('gateway_down', 'MIDTRANS_DOWN', 'Midtrans reconciliation returned no transaction payload.', $invoice);
            $this->emitFailureSpikeAlert($invoice, 'MIDTRANS_DOWN');
            return;
        }

        $txStatus    = strtolower((string) ($tx['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($tx['fraud_status'] ?? ''));
        $state       = $midtrans->resolvePaymentState($txStatus, $fraudStatus);

        if ($state === 'paid') {
            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'midtrans_transaction_id' => (string) ($tx['transaction_id'] ?? ''),
                    'midtrans_payment_type'   => (string) ($tx['payment_type'] ?? ''),
                    'midtrans_fraud_status'   => $fraudStatus,
                ]),
            ]);
            $this->markPaymentAsPaid($payment, $invoice);
            return;
        }

        if ($state === 'failed') {
            $payment->update([
                'status' => 'failed',
                'metadata' => array_merge($payment->metadata ?? [], [
                    'reconciled_at'     => now()->toIso8601String(),
                    'reconciled_status' => $txStatus,
                ]),
            ]);

            $this->updateInvoiceReason($invoice, 'MIDTRANS_INVOICE_EXPIRED', 'Reconciliation: Midtrans transaction is cancelled/expired/denied.');
            $this->recordEvent($invoice->subscription, $invoice, $payment, 'renewal_failed', 'MIDTRANS_INVOICE_EXPIRED', 'Reconciliation: Midtrans transaction is cancelled/expired/denied.');
            $this->emitRenewalMetric('renewal_reconciliation_failed', 'MIDTRANS_INVOICE_EXPIRED', $invoice);
            $this->emitFailureSpikeAlert($invoice, 'MIDTRANS_INVOICE_EXPIRED');
        }
        // state === 'pending': no action, customer may still pay
    }

    private function markPaymentAsPaid(Payment $payment, Invoice $invoice): void
    {
        DB::transaction(function () use ($payment, $invoice): void {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->first();
            $lockedInvoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->first();

            if (! $lockedPayment || ! $lockedInvoice) {
                return;
            }

            if ($lockedInvoice->is_paid) {
                return;
            }

            $lockedPayment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'verified_at' => now(),
                'metadata' => array_merge($lockedPayment->metadata ?? [], [
                    'reconciled_at' => now()->toIso8601String(),
                    'reconciled_status' => 'PAID',
                ]),
            ]);

            $lockedInvoice->markAsPaid();
            $this->updateInvoiceReason($lockedInvoice, 'RECONCILIATION_PAID', 'Renewal payment confirmed by reconciliation polling.');
            $this->recordEvent($lockedInvoice->subscription, $lockedInvoice, $lockedPayment, 'renewal_paid', 'RECONCILIATION_PAID', 'Renewal payment confirmed by reconciliation polling.');
        });
    }

    private function updateInvoiceReason(Invoice $invoice, string $reasonCode, string $reasonMessage): void
    {
        $invoice->forceFill([
            'renewal_reason_code' => $reasonCode,
            'renewal_reason_message' => mb_substr($reasonMessage, 0, 255),
        ])->save();
    }

    private function recordEvent(
        ?Subscription $subscription,
        ?Invoice $invoice,
        ?Payment $payment,
        string $eventType,
        ?string $reasonCode,
        ?string $reasonMessage
    ): void {
        if (! $subscription) {
            return;
        }

        SubscriptionEvent::query()->create([
            'company_id' => $subscription->company_id,
            'company_uuid' => $subscription->getAttribute('company_uuid'),
            'subscription_id' => $subscription->id,
            'subscription_uuid' => $subscription->uuid,
            'invoice_id' => $invoice?->id,
            'invoice_uuid' => $invoice?->uuid,
            'payment_id' => $payment?->id,
            'payment_uuid' => $payment?->uuid,
            'renewal_period_key' => $invoice?->renewal_period_key,
            'event_type' => $eventType,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage !== null ? mb_substr($reasonMessage, 0, 255) : null,
            'payload' => null,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function emitRenewalMetric(string $metricKey, string $reasonCode, ?Invoice $invoice = null, array $context = []): void
    {
        Log::info('renewal_monitoring.metric', array_merge([
            'metric_key' => $metricKey,
            'reason_code' => $reasonCode,
            'invoice_id' => $invoice?->id,
            'company_id' => $invoice?->company_id,
            'renewal_period_key' => $invoice?->renewal_period_key,
            'recorded_at' => now()->toIso8601String(),
        ], $context));
    }

    private function emitRenewalAlert(string $alertType, string $reasonCode, string $message, ?Invoice $invoice = null, array $context = []): void
    {
        Log::warning('renewal_monitoring.alert', array_merge([
            'alert_type' => $alertType,
            'reason_code' => $reasonCode,
            'message' => $message,
            'invoice_id' => $invoice?->id,
            'company_id' => $invoice?->company_id,
            'renewal_period_key' => $invoice?->renewal_period_key,
            'recorded_at' => now()->toIso8601String(),
        ], $context));
    }

    private function emitFailureSpikeAlert(?Invoice $invoice, string $reasonCode): void
    {
        $failureCodes = [
            'MIDTRANS_DOWN',
            'STALE_INVOICE_DETECTED',
            'MIDTRANS_INVOICE_EXPIRED',
        ];

        if (! in_array($reasonCode, $failureCodes, true)) {
            return;
        }

        $count = Invoice::query()
            ->whereNotNull('renewal_period_key')
            ->whereIn('renewal_reason_code', $failureCodes)
            ->whereDate('issue_date', '>=', now()->subDay()->toDateString())
            ->count();

        $this->emitRenewalMetric('renewal_failure_count_24h', $reasonCode, $invoice, [
            'window' => '24h',
            'count' => $count,
        ]);

        if ($count < 3) {
            return;
        }

        $this->emitRenewalAlert('failure_spike', 'RENEWAL_FAILURE_SPIKE', 'Renewal failure spike threshold reached in the last 24 hours.', $invoice, [
            'window' => '24h',
            'count' => $count,
            'threshold' => 3,
            'trigger_reason_code' => $reasonCode,
        ]);
    }
}
