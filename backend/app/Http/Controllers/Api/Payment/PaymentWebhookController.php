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

class PaymentWebhookController extends Controller
{
    // Stripe webhooks removed — only Midtrans supported

    /**
     * Handle Midtrans notification (POST /api/webhooks/midtrans)
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

    // ─── Shared helpers (used by Midtrans webhook) ───────────────────

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
