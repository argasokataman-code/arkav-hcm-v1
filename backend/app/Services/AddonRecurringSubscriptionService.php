<?php

namespace App\Services;

use App\Models\PurchaseTransaction;
use App\Models\Subscription;

class AddonRecurringSubscriptionService
{
    /**
     * Apply paid addon transaction amount into recurring subscription amount once.
     */
    public function applyFromTransaction(PurchaseTransaction $transaction): void
    {
        if ((string) ($transaction->transaction_type ?? '') !== 'addon') {
            return;
        }

        if ((string) ($transaction->status ?? '') !== 'paid') {
            return;
        }

        if (! $transaction->subscription_id) {
            return;
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()->find($transaction->subscription_id);
        if (! $subscription) {
            return;
        }

        $metadata = (array) ($subscription->metadata ?? []);
        $appliedIds = array_values(array_filter((array) ($metadata['addon_applied_transaction_ids'] ?? []), fn ($value) => is_numeric($value)));
        $transactionId = (int) $transaction->id;

        if (in_array($transactionId, array_map('intval', $appliedIds), true)) {
            return;
        }

        $currentAmount = (float) ($subscription->amount ?? 0);
        $addonAmount = (float) ($transaction->total_amount ?? 0);
        if ($addonAmount <= 0) {
            return;
        }

        $metadata['addon_applied_transaction_ids'] = array_values(array_unique(array_merge($appliedIds, [$transactionId])));
        $metadata['addon_recurring_total'] = round((float) ($metadata['addon_recurring_total'] ?? 0) + $addonAmount, 2);

        $subscription->update([
            'amount' => round($currentAmount + $addonAmount, 2),
            'metadata' => $metadata,
        ]);
    }
}
