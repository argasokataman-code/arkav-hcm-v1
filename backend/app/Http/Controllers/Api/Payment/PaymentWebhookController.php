<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        if (!$signature) {
            Log::warning('Stripe webhook: Missing signature header');
            return response()->json(['success' => false, 'error' => 'Missing signature'], 400);
        }

        $body = $request->getContent();
        
        try {
            // Validate Stripe signature (prevents replay attacks)
            $event = \Stripe\Webhook::constructEvent(
                $body,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook: Invalid JSON payload', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Invalid JSON'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
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

    /**
     * Handle Xendit webhook (for invoice.paid, invoice.expired, payment.successful, etc.)
     * POST /api/webhooks/xendit
     * 
     * CRITICAL: Validate Xendit signature via callback token (stored in config)
     */
    public function handleXendit(Request $request): JsonResponse
    {
        // Xendit uses X-Callback-Token header for authentication
        $callbackToken = $request->header('X-Callback-Token');
        $expectedToken = config('services.xendit.callback_token');

        if (!$callbackToken || $callbackToken !== $expectedToken) {
            Log::warning('Xendit webhook: Invalid callback token');
            return response()->json(['success' => false, 'error' => 'Invalid token'], 401);
        }

        $eventType = $request->get('event') ?? $request->get('type');
        $data = $request->all();

        // Xendit webhook id is preferred for idempotency and replay protection.
        // Some dashboard test deliveries may not include xendit-webhook-id,
        // so we derive a deterministic fallback id from event payload.
        $webhookId = trim((string) $request->header('xendit-webhook-id', ''));
        if ($webhookId === '') {
            $webhookId = $this->buildXenditFallbackWebhookId($eventType, $data);
            Log::warning('Xendit webhook: Missing webhook id header, using fallback id', [
                'fallback_webhook_id' => $webhookId,
                'type' => $eventType,
            ]);
        }

        $cacheKey = "xendit_webhook:$webhookId";
        if (cache()->get($cacheKey)) {
            Log::info('Xendit webhook: Already processed', ['webhook_id' => $webhookId]);
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        Log::info('Xendit webhook received', ['type' => $eventType, 'webhook_id' => $webhookId]);

        try {
            match ($eventType) {
                'invoice.paid' => $this->handleXenditInvoicePaid($data),
                'invoice.expired' => $this->handleXenditInvoiceExpired($data),
                'payment.successful' => $this->handleXenditPaymentSuccessful($data),
                'payment.failed' => $this->handleXenditPaymentFailed($data),
                default => Log::info('Xendit webhook: Unhandled event type', ['type' => $eventType]),
            };

            cache()->put($cacheKey, true, now()->addHours(24));

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Xendit webhook: Processing error', [
                'type' => $eventType,
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function buildXenditFallbackWebhookId(?string $eventType, array $data): string
    {
        $event = (string) ($eventType ?? 'unknown');
        $payloadId = (string) (
            $data['id']
            ?? ($data['data']['id'] ?? '')
            ?? ($data['external_id'] ?? '')
            ?? ($data['data']['external_id'] ?? '')
            ?? ($data['data']['reference_id'] ?? '')
        );

        if ($payloadId === '') {
            $payloadHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'xendit-webhook');
            $payloadId = substr($payloadHash, 0, 24);
        }

        return 'fallback:'.$event.':'.$payloadId;
    }

    private function handleXenditInvoicePaid(array $data): void
    {
        $xenditInvoiceId = isset($data['id']) ? (string) $data['id'] : null;
        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : null;
        $amount = $data['amount'] ?? 0;

        Log::info('Xendit invoice paid', [
            'invoice_id' => $xenditInvoiceId,
            'external_id' => $externalId,
            'amount' => $amount,
        ]);

        $payment = $this->findPaymentByXenditIdentifiers($xenditInvoiceId, $externalId);
        if (! $payment) {
            Log::warning('Xendit invoice paid: No payment matched', [
                'invoice_id' => $xenditInvoiceId,
                'external_id' => $externalId,
            ]);
            return;
        }

        $payment->update([
            'status' => 'completed',
            'paid_at' => now(),
            'verified_at' => now(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'xendit_invoice_id' => $xenditInvoiceId,
                'xendit_external_id' => $externalId,
            ]),
        ]);

        $this->markRecurringRenewalPaidFromWebhook($payment, 'WEBHOOK_INVOICE_PAID', 'Recurring renewal invoice settled via webhook callback.');
    }

    private function handleXenditInvoiceExpired(array $data): void
    {
        $xenditInvoiceId = isset($data['id']) ? (string) $data['id'] : null;
        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : null;

        Log::info('Xendit invoice expired', [
            'invoice_id' => $xenditInvoiceId,
            'external_id' => $externalId,
        ]);

        $payment = $this->findPaymentByXenditIdentifiers($xenditInvoiceId, $externalId);
        if ($payment) {
            $payment->update(['status' => 'expired']);

            $invoice = $payment->invoice;
            if ($invoice && $this->isRecurringRenewalInvoice($invoice)) {
                $this->setRecurringRenewalReason(
                    $invoice,
                    'XENDIT_INVOICE_EXPIRED',
                    'Gateway marked recurring renewal invoice as expired.'
                );
            }
        }
    }

    private function handleXenditPaymentSuccessful(array $data): void
    {
        $xenditPaymentId = isset($data['id']) ? (string) $data['id'] : null;
        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : null;

        Log::info('Xendit payment successful', [
            'payment_id' => $xenditPaymentId,
            'external_id' => $externalId,
        ]);

        $payment = $this->findPaymentByXenditIdentifiers($xenditPaymentId, $externalId);
        if ($payment) {
            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'verified_at' => now(),
                'metadata' => array_merge($payment->metadata ?? [], [
                    'xendit_payment_id' => $xenditPaymentId,
                    'xendit_external_id' => $externalId,
                ]),
            ]);
            $this->markRecurringRenewalPaidFromWebhook($payment, 'WEBHOOK_CHARGE_SUCCEEDED', 'Recurring renewal payment settled via Xendit payment.successful webhook.');
        }
    }

    private function handleXenditPaymentFailed(array $data): void
    {
        $xenditPaymentId = isset($data['id']) ? (string) $data['id'] : null;
        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : null;
        $failureReason = $data['failure_reason'] ?? 'Unknown reason';

        Log::warning('Xendit payment failed', [
            'payment_id' => $xenditPaymentId,
            'external_id' => $externalId,
            'reason' => $failureReason,
        ]);

        $payment = $this->findPaymentByXenditIdentifiers($xenditPaymentId, $externalId);
        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'metadata' => array_merge($payment->metadata ?? [], [
                    'failure_reason' => $failureReason,
                ]),
            ]);

            $invoice = $payment->invoice;
            if ($invoice && $this->isRecurringRenewalInvoice($invoice)) {
                $this->setRecurringRenewalReason(
                    $invoice,
                    'XENDIT_PAYMENT_FAILED',
                    (string) $failureReason
                );
            }
        }
    }

    private function findPaymentByXenditIdentifiers(?string $xenditId, ?string $externalId): ?Payment
    {
        if ($xenditId) {
            $payment = Payment::query()
                ->where('gateway', 'xendit')
                ->where(function ($query) use ($xenditId): void {
                    $query->where('gateway_reference', $xenditId)
                        ->orWhere('metadata->xendit_invoice_id', $xenditId)
                        ->orWhere('metadata->xendit_external_id', $xenditId);
                })
                ->latest('id')
                ->first();
            if ($payment) {
                return $payment;
            }
        }

        if ($externalId) {
            return Payment::query()
                ->where('gateway', 'xendit')
                ->where(function ($query) use ($externalId): void {
                    $query->where('gateway_reference', $externalId)
                        ->orWhere('metadata->xendit_external_id', $externalId)
                        ->orWhere('metadata->xendit_invoice_id', $externalId);
                })
                ->latest('id')
                ->first();
        }

        return null;
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
}
