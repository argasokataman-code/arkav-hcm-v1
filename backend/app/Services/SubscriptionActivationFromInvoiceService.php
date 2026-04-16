<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Activates a {@see Subscription} in {@code pending_payment} when its linked invoice is paid.
 */
class SubscriptionActivationFromInvoiceService
{
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

        $cycle = $subscription->billing_cycle === 'yearly' ? 'yearly' : 'monthly';
        $endsAt = $cycle === 'yearly'
            ? Carbon::now()->addYear()
            : Carbon::now()->addMonth();

        $subscription->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'trial_ends_at' => null,
        ]);

        Log::info('Subscription activated from paid invoice', [
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'ends_at' => $endsAt->toDateString(),
        ]);
    }
}
