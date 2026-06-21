<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class PaymentWebhookController extends Controller
{
    /**
     * Handle Stripe webhook
     * POST /api/webhooks/stripe
     *
     * CRITICAL: Validates webhook signature to prevent replay attacks
     */
    public function handleStripe(Request $request): JsonResponse
    {
        $signature = $request->header('Stripe-Signature');
        if (! $signature) {
            Log::warning('Stripe webhook: Missing signature header');

            return response()->json(['success' => false, 'error' => 'Missing signature'], 400);
        }

        $body = $request->getContent();

        try {
            // Validate Stripe signature (prevents replay attacks)
            $event = Webhook::constructEvent(
                $body,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook: Invalid JSON payload', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => 'Invalid JSON'], 400);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: Invalid signature', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => 'Invalid signature'], 401);
        }

        // IMPORTANT: Idempotency - check if we already processed this event
        $eventType = $event['type'];
        $eventId = $event['id'];

        // Use a simple cache key or DB record to track processed webhooks
        $cacheKey = "stripe_webhook:$eventId";
        if (cache()->get($cacheKey)) {
            Log::info('Stripe webhook: Already processed', ['event_id' => $eventId]);

            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        Log::info('Stripe webhook received', ['type' => $eventType, 'event_id' => $eventId]);

        try {
            match ($eventType) {
                'charge.succeeded' => $this->handleChargeSucceeded($event['data']['object']),
                'charge.failed' => $this->handleChargeFailed($event['data']['object']),
                'charge.refunded' => $this->handleChargeRefunded($event['data']['object']),
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event['data']['object']),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event['data']['object']),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event['data']['object']),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event['data']['object']),
                default => Log::info('Stripe webhook: Unhandled event type', ['type' => $eventType]),
            };

            // Mark as processed (idempotency)
            cache()->put($cacheKey, true, now()->addHours(24));

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Processing error', [
                'event_id' => $eventId,
                'type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleChargeSucceeded(array $charge): void
    {
        $stripeChargeId = $charge['id'];
        $stripeCustomerId = $charge['customer'] ?? null;
        $amount = $charge['amount'] / 100; // Convert from cents
        $currency = strtoupper($charge['currency'] ?? 'USD');

        Log::info('Stripe charge succeeded', [
            'charge_id' => $stripeChargeId,
            'customer_id' => $stripeCustomerId,
            'amount' => $amount,
        ]);

        // Find payment by Stripe charge ID.
        $payment = Payment::query()
            ->where('gateway', 'stripe')
            ->where(function ($query) use ($stripeChargeId): void {
                $query->where('gateway_reference', $stripeChargeId)
                    ->orWhere('metadata->stripe_charge_id', $stripeChargeId)
                    ->orWhere('metadata->latest_charge_id', $stripeChargeId);
            })
            ->latest('id')
            ->first();

        if ($payment) {
            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'verified_at' => now(),
                'metadata' => array_merge($payment->metadata ?? [], [
                    'stripe_charge_id' => $stripeChargeId,
                ]),
            ]);

            $invoice = $payment->invoice;
            if ($invoice && $this->isRecurringRenewalInvoice($invoice)) {
                $this->markRecurringRenewalPaidFromWebhook($payment, 'WEBHOOK_CHARGE_SUCCEEDED', 'Recurring renewal charge succeeded via Stripe webhook.');
            }

            Log::info('Payment marked completed', ['payment_id' => $payment->id]);
        }
    }

    private function handleChargeFailed(array $charge): void
    {
        $stripeChargeId = $charge['id'];
        $failureMessage = $charge['failure_message'] ?? 'Unknown reason';

        Log::warning('Stripe charge failed', [
            'charge_id' => $stripeChargeId,
            'failure' => $failureMessage,
        ]);

        $payment = Payment::where('gateway_reference', $stripeChargeId)->first();
        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'metadata' => array_merge($payment->metadata ?? [], [
                    'failure_reason' => $failureMessage,
                    'failed_at' => now()->toIso8601String(),
                ]),
            ]);
            Log::info('Payment marked failed', ['payment_id' => $payment->id]);
        }
    }

    private function handleChargeRefunded(array $charge): void
    {
        $stripeChargeId = $charge['id'];
        $refundAmount = $charge['amount_refunded'] / 100;

        Log::info('Stripe charge refunded', [
            'charge_id' => $stripeChargeId,
            'refund_amount' => $refundAmount,
        ]);

        $payment = Payment::where('gateway_reference', $stripeChargeId)->first();
        if ($payment) {
            $payment->update([
                'status' => 'refunded',
                'metadata' => array_merge($payment->metadata ?? [], [
                    'refund_amount' => $refundAmount,
                    'refunded_at' => now()->toIso8601String(),
                ]),
            ]);
        }
    }

    private function handleInvoicePaymentSucceeded(array $invoice): void
    {
        $stripeInvoiceId = $invoice['id'];
        $stripeCustomerId = $invoice['customer'] ?? null;
        $stripePaymentIntentId = (string) ($invoice['payment_intent'] ?? '');

        Log::info('Stripe invoice payment succeeded', [
            'invoice_id' => $stripeInvoiceId,
            'customer_id' => $stripeCustomerId,
        ]);

        $payment = Payment::query()
            ->where('gateway', 'stripe')
            ->where(function ($query) use ($stripeInvoiceId, $stripePaymentIntentId): void {
                $query->where('gateway_reference', $stripeInvoiceId)
                    ->orWhere('metadata->stripe_invoice_id', $stripeInvoiceId);

                if ($stripePaymentIntentId !== '') {
                    $query->orWhere('gateway_reference', $stripePaymentIntentId)
                        ->orWhere('metadata->stripe_intent_id', $stripePaymentIntentId);
                }
            })
            ->latest('id')
            ->first();

        if (! $payment) {
            Log::warning('Stripe invoice payment succeeded: no payment matched', [
                'invoice_id' => $stripeInvoiceId,
                'payment_intent' => $stripePaymentIntentId,
            ]);

            return;
        }

        $this->markRecurringRenewalPaidFromWebhook($payment, 'WEBHOOK_INVOICE_PAID', 'Recurring renewal invoice settled via Stripe invoice webhook.');
    }

    private function handleInvoicePaymentFailed(array $invoice): void
    {
        $stripeInvoiceId = $invoice['id'];
        $stripeCustomerId = $invoice['customer'] ?? null;
        $stripePaymentIntentId = (string) ($invoice['payment_intent'] ?? '');
        $failureReason = $invoice['last_finalization_error']['message'] ?? 'Unknown reason';

        Log::warning('Stripe invoice payment failed', [
            'invoice_id' => $stripeInvoiceId,
            'customer_id' => $stripeCustomerId,
            'reason' => $failureReason,
        ]);

        $payment = Payment::query()
            ->where('gateway', 'stripe')
            ->where(function ($query) use ($stripeInvoiceId, $stripePaymentIntentId): void {
                $query->where('gateway_reference', $stripeInvoiceId)
                    ->orWhere('metadata->stripe_invoice_id', $stripeInvoiceId);

                if ($stripePaymentIntentId !== '') {
                    $query->orWhere('gateway_reference', $stripePaymentIntentId)
                        ->orWhere('metadata->stripe_intent_id', $stripePaymentIntentId);
                }
            })
            ->latest('id')
            ->first();

        if (! $payment) {
            Log::warning('Stripe invoice payment failed: no payment matched', [
                'invoice_id' => $stripeInvoiceId,
                'payment_intent' => $stripePaymentIntentId,
            ]);

            return;
        }

        $payment->update([
            'status' => 'failed',
            'metadata' => array_merge($payment->metadata ?? [], [
                'failure_reason' => $failureReason,
                'stripe_invoice_id' => $stripeInvoiceId,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
            ]),
        ]);

        $invoiceRecord = $payment->invoice;
        if ($invoiceRecord) {
            $invoiceRecord->update(['status' => 'payment_failed']);

            if ($this->isRecurringRenewalInvoice($invoiceRecord)) {
                $this->setRecurringRenewalReason(
                    $invoiceRecord,
                    'STRIPE_INVOICE_PAYMENT_FAILED',
                    (string) $failureReason
                );
            }
        }
    }

    private function handleSubscriptionUpdated(array $subscription): void
    {
        $stripeSubscriptionId = $subscription['id'];
        $status = $subscription['status']; // active, past_due, unpaid, canceled, ended, etc.

        Log::info('Stripe subscription updated', [
            'subscription_id' => $stripeSubscriptionId,
            'status' => $status,
        ]);

        // Find corresponding Subscription record via metadata mapping.
        $sub = Subscription::query()
            ->where('metadata->stripe_subscription_id', $stripeSubscriptionId)
            ->latest('id')
            ->first();

        if ($sub) {
            $sub->update([
                'status' => $status === 'active' ? 'active' : 'suspended',
                'metadata' => array_merge($sub->metadata ?? [], [
                    'stripe_status' => $status,
                    'updated_at' => now()->toIso8601String(),
                ]),
            ]);
            Log::info('Subscription status updated', ['subscription_id' => $sub->id, 'status' => $status]);
        }
    }

    private function handleSubscriptionDeleted(array $subscription): void
    {
        $stripeSubscriptionId = $subscription['id'];
        $canceledAt = $subscription['canceled_at'] ?? null;

        Log::info('Stripe subscription deleted', [
            'subscription_id' => $stripeSubscriptionId,
            'canceled_at' => $canceledAt,
        ]);

        $sub = Subscription::query()
            ->where('metadata->stripe_subscription_id', $stripeSubscriptionId)
            ->latest('id')
            ->first();

        if ($sub) {
            $sub->update([
                'status' => 'canceled',
                'terminated_at' => now(),
                'termination_reason' => 'stripe_subscription_deleted',
                'metadata' => array_merge($sub->metadata ?? [], [
                    'stripe_canceled_at' => $canceledAt,
                ]),
            ]);
            Log::info('Subscription terminated', ['subscription_id' => $sub->id]);
        }
    }

    private function markInvoicePaidForPayment(Payment $payment): bool
    {
        $invoice = $payment->invoice;
        if (! $invoice || $invoice->is_paid) {
            return false;
        }

        $invoice->markAsPaid();

        return true;
    }

    private function isRecurringRenewalInvoice(Invoice $invoice): bool
    {
        $notes = json_decode((string) ($invoice->notes ?? ''), true);

        return is_array($notes) && ($notes['source'] ?? null) === 'recurring_subscription_renewal';
    }

    private function extendSubscriptionForRecurringRenewal(?Subscription $subscription): void
    {
        if (! $subscription) {
            return;
        }

        if (in_array($subscription->status, ['expired', 'canceled', 'cancelled', 'terminated'], true)) {
            return;
        }

        $currentEnd = $subscription->ends_at ?? now();
        $baseDate = $currentEnd->isFuture() ? $currentEnd : now();
        $billingCycle = $subscription->billing_cycle ?? 'monthly';

        $newEnd = match ($billingCycle) {
            'yearly' => $baseDate->copy()->addYear(),
            'quarterly' => $baseDate->copy()->addMonths(3),
            default => $baseDate->copy()->addMonth(),
        };

        $subscription->update([
            'status' => 'active',
            'ends_at' => $newEnd,
            'grace_started_at' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }

    private function markRecurringRenewalPaidFromWebhook(Payment $payment, string $reasonCode, string $reasonMessage): void
    {
        DB::transaction(function () use ($payment, $reasonCode, $reasonMessage): void {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->with(['invoice', 'subscription'])->first();
            if (! $lockedPayment) {
                return;
            }

            $lockedInvoice = $lockedPayment->invoice
                ? Invoice::query()->whereKey($lockedPayment->invoice->getKey())->lockForUpdate()->first()
                : null;

            if (! $lockedInvoice) {
                return;
            }

            $alreadyPaid = (bool) $lockedInvoice->is_paid;
            if (! $alreadyPaid) {
                $lockedInvoice->markAsPaid();
            }

            if (! $this->isRecurringRenewalInvoice($lockedInvoice)) {
                return;
            }

            $this->setRecurringRenewalReason($lockedInvoice, $reasonCode, $reasonMessage);

            if ($alreadyPaid) {
                return;
            }

            $lockedSubscription = $lockedPayment->subscription
                ? Subscription::query()->whereKey($lockedPayment->subscription->getKey())->lockForUpdate()->first()
                : null;

            if (! $lockedSubscription) {
                return;
            }

            $this->extendSubscriptionForRecurringRenewal($lockedSubscription);
        });
    }

    private function setRecurringRenewalReason(Invoice $invoice, string $reasonCode, string $reasonMessage): void
    {
        $invoice->forceFill([
            'renewal_reason_code' => $reasonCode,
            'renewal_reason_message' => mb_substr($reasonMessage, 0, 255),
        ])->save();
    }

    /**
     * Handle Midtrans notification (POST /api/webhooks/midtrans)
     *
     * Authentication: SHA512 signature in payload body (no dedicated header).
     * Idempotency: based on order_id (merchant-generated unique ID).
     */
    public function handleMidtrans(Request $request): JsonResponse
    {
        $data = $request->all();
        $orderId = (string) ($data['order_id'] ?? '');

        if ($orderId === '') {
            Log::warning('Midtrans webhook: Missing order_id', ['payload' => $data]);

            return response()->json(['success' => true, 'message' => 'Skipped: no order_id']);
        }

        $midtrans = app(MidtransService::class);

        if (! $midtrans->verifySignature($data)) {
            Log::warning('Midtrans webhook: Invalid signature', ['order_id' => $orderId]);

            return response()->json(['success' => false, 'error' => 'Invalid signature'], 401);
        }

        // Idempotency: order_id based (Midtrans has no dedicated webhook-id header)
        $cacheKey = "midtrans_webhook:{$orderId}";
        if (cache()->get($cacheKey)) {
            Log::info('Midtrans webhook: Already processed', ['order_id' => $orderId]);

            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        $txStatus = strtolower((string) ($data['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($data['fraud_status'] ?? ''));

        Log::info('Midtrans webhook received', [
            'order_id' => $orderId,
            'transaction_status' => $txStatus,
            'fraud_status' => $fraudStatus,
        ]);

        try {
            $state = $midtrans->resolvePaymentState($txStatus, $fraudStatus);

            if ($state === 'paid') {
                $this->handleMidtransPaymentPaid($data, $orderId);
            } elseif ($state === 'failed') {
                $this->handleMidtransPaymentFailed($data, $orderId);
            }
            // 'pending' state — no action needed, wait for next notification

            cache()->put($cacheKey, true, now()->addHours(24));

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Midtrans webhook: Processing error', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleMidtransPaymentPaid(array $data, string $orderId): void
    {
        $payment = $this->findPaymentByMidtransIdentifiers($orderId);
        if (! $payment) {
            Log::warning('Midtrans webhook: No payment matched for paid notification', ['order_id' => $orderId]);

            return;
        }

        $payment->update([
            'status' => 'completed',
            'paid_at' => now(),
            'verified_at' => now(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'midtrans_transaction_id' => (string) ($data['transaction_id'] ?? ''),
                'midtrans_payment_type' => (string) ($data['payment_type'] ?? ''),
                'midtrans_fraud_status' => (string) ($data['fraud_status'] ?? ''),
            ]),
        ]);

        $this->markRecurringRenewalPaidFromWebhook($payment, 'WEBHOOK_MIDTRANS_PAID', 'Midtrans payment notification received and verified.');
    }

    private function handleMidtransPaymentFailed(array $data, string $orderId): void
    {
        $payment = $this->findPaymentByMidtransIdentifiers($orderId);
        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => 'failed',
            'metadata' => array_merge($payment->metadata ?? [], [
                'midtrans_transaction_status' => (string) ($data['transaction_status'] ?? ''),
                'midtrans_fraud_status' => (string) ($data['fraud_status'] ?? ''),
                'failed_at' => now()->toIso8601String(),
            ]),
        ]);

        $invoice = $payment->invoice;
        if ($invoice && $this->isRecurringRenewalInvoice($invoice)) {
            $this->setRecurringRenewalReason(
                $invoice,
                'MIDTRANS_PAYMENT_FAILED',
                'Midtrans notification: transaction_status='.($data['transaction_status'] ?? 'unknown')
            );
        }
    }

    private function findPaymentByMidtransIdentifiers(string $orderId): ?Payment
    {
        return Payment::query()
            ->where('gateway', 'midtrans')
            ->where(function ($query) use ($orderId): void {
                $query->where('gateway_reference', $orderId)
                    ->orWhere('metadata->midtrans_order_id', $orderId);
            })
            ->latest('id')
            ->first();
    }
}
