<?php

namespace App\Services;

use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class AddonRecurringSubscriptionService
{
    /**
     * Restore paid addon transactions for a company onto a given subscription.
     * Dedup by package_addon_id — only the latest paid transaction per addon is restored.
     * Skips transactions already applied to this subscription.
     */
    public function restoreForSubscription(Subscription $subscription): void
    {
        $paidAddons = PurchaseTransaction::query()
            ->selectRaw('MAX(id) as id')
            ->where('company_id', $subscription->company_id)
            ->where('transaction_type', 'addon')
            ->where('status', 'paid')
            ->whereNotNull('package_addon_id')
            ->groupBy('package_addon_id')
            ->pluck('id');

        if ($paidAddons->isEmpty()) {
            return;
        }

        $transactions = PurchaseTransaction::query()
            ->whereIn('id', $paidAddons)
            ->get();

        foreach ($transactions as $tx) {
            if ((int) $tx->subscription_id === (int) $subscription->id) {
                continue;
            }

            DB::transaction(function () use ($tx, $subscription): void {
                $tx->update(['subscription_id' => $subscription->id]);
                $this->applyFromTransaction($tx->fresh());
            });
        }
    }

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
