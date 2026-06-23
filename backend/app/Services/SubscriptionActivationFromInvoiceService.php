<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Services\AddonRecurringSubscriptionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Activates a {@see Subscription} in {@code pending_payment} when its linked invoice is paid.
 */
class SubscriptionActivationFromInvoiceService
{
    public function __construct(private readonly CompanyStatusSynchronizer $companyStatusSynchronizer) {}

    /**
     * After an invoice is marked paid: if {@code subscription_id} points to a
     * {@code pending_payment} subscription for the same company, flip to {@code active}
     * and start the paid period from now.
     */
    public function activateIfEligible(Invoice $invoice): void
    {
        if ($invoice->subscription_id === null) {
            return;
        }

        $subscription = Subscription::query()
            ->whereKey($invoice->subscription_id)
            ->where('company_id', $invoice->company_id)
            ->first();

        if (! $subscription || $subscription->status !== 'pending_payment') {
            return;
        }

        $metadata = (array) ($subscription->metadata ?? []);
        $expectedPendingInvoiceId = isset($metadata['pending_invoice_id'])
            ? (int) $metadata['pending_invoice_id']
            : null;

        // If subscription is locked to a specific pending invoice, only that
        // invoice can activate it.
        if ($expectedPendingInvoiceId !== null && $expectedPendingInvoiceId > 0 && $invoice->id !== $expectedPendingInvoiceId) {
            return;
        }

        $cycle = $subscription->billing_cycle === 'yearly' ? 'yearly' : 'monthly';
        $endsAt = $cycle === 'yearly'
            ? Carbon::now()->addYear()
            : Carbon::now()->addMonth();

        $subscription->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'trial_ends_at' => null,
            'metadata' => array_merge($metadata, [
                'pending_invoice_id' => null,
                'pending_invoice_uuid' => null,
                'pending_invoice_source' => null,
            ]),
        ]);

        // Restore addon amounts from paid transactions
        $paidAddons = PurchaseTransaction::query()
            ->where('company_id', $subscription->company_id)
            ->where('transaction_type', 'addon')
            ->where('status', 'paid')
            ->exists();

        if ($paidAddons) {
            app(AddonRecurringSubscriptionService::class)
                ->restoreForSubscription($subscription);
            $subscription = $subscription->fresh();
        }

        $this->companyStatusSynchronizer->syncFromSubscription($subscription->fresh('company'));

        Log::info('Subscription activated from paid invoice', [
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'ends_at' => $endsAt->toDateString(),
        ]);
    }
}
