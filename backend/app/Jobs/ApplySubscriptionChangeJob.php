<?php

namespace App\Jobs;

use App\Models\HcmSubscriptionChangeRequest;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Services\BillingTaxCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Apply approved subscription change request to the active subscription row.
 *
 * This job is intentionally idempotent: if a request is already applied,
 * cancelled, or rejected, handle() exits safely.
 *
 * For downgrade flow, we always switch the subscription into pending_payment
 * and issue a fresh invoice for the new package amount. This ensures tenant
 * pays the new (downgraded) package before reactivation, not legacy overdue
 * invoices from the previous package.
 */
class ApplySubscriptionChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $changeRequestId,
    ) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $record = HcmSubscriptionChangeRequest::query()
                ->lockForUpdate()
                ->where('id', $this->changeRequestId)
                ->first();

            if (! $record) {
                return;
            }

            if ($record->status === HcmSubscriptionChangeRequest::STATUS_APPLIED) {
                return;
            }

            if ($record->status !== HcmSubscriptionChangeRequest::STATUS_APPROVED) {
                return;
            }

            $subscription = $record->current_subscription_uuid
                ? Subscription::query()->where('uuid', $record->current_subscription_uuid)->first()
                : null;

            if (! $subscription) {
                $record->update([
                    'status' => HcmSubscriptionChangeRequest::STATUS_APPLIED,
                    'applied_at' => now(),
                ]);

                return;
            }

            if ($record->action === HcmSubscriptionChangeRequest::ACTION_CANCEL) {
                $subscription->update([
                    'status' => 'cancelled',
                    'auto_renew' => false,
                    'terminated_at' => now(),
                    'termination_reason' => 'Tenant-initiated cancellation request '.$record->id,
                ]);
            } elseif ($record->action === HcmSubscriptionChangeRequest::ACTION_UPGRADE) {
                $this->applyUpgradeWithAddons($subscription, $record);
            } elseif ($record->to_package_uuid) {
                $this->applyDowngradeWithAddons($subscription, $record);
            }

            $record->update([
                'status' => HcmSubscriptionChangeRequest::STATUS_APPLIED,
                'applied_at' => now(),
            ]);
        });
    }

    private function calculateAddonRecurringForTarget(Subscription $subscription, Package $target): float
    {
        $recurringTotal = 0.0;
        $paidAddons = PurchaseTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('transaction_type', 'addon')
            ->where('status', 'paid')
            ->get();

        foreach ($paidAddons as $tx) {
            $addon = PackageAddon::find($tx->package_addon_id);
            if (! $addon || $addon->status !== 'active') {
                continue;
            }

            $isBuiltIn = DB::table('package_addon_assignments')
                ->where('package_uuid', $target->uuid)
                ->where('package_addon_id', $addon->id)
                ->exists();

            if ($isBuiltIn) {
                continue;
            }

            $recurringTotal += (float) ($tx->total_amount ?? 0);
        }

        return $recurringTotal;
    }

    private function applyUpgradeWithAddons(Subscription $subscription, HcmSubscriptionChangeRequest $record): void
    {
        $target = Package::query()->where('uuid', $record->to_package_uuid)->first();
        if (! $target || (string) $target->status !== 'active') {
            return;
        }

        $billingCycle = (string) ($subscription->billing_cycle ?? 'monthly');
        $packagePrice = (float) ($billingCycle === 'yearly' ? $target->yearly_price : $target->monthly_price);
        $addonRecurring = $this->calculateAddonRecurringForTarget($subscription, $target);
        $newAmount = round($packagePrice + $addonRecurring, 2);

        // Mark built-in addon transactions as consolidated
        $paidAddons = PurchaseTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('transaction_type', 'addon')
            ->where('status', 'paid')
            ->get();

        foreach ($paidAddons as $tx) {
            $isBuiltIn = DB::table('package_addon_assignments')
                ->where('package_uuid', $target->uuid)
                ->where('package_addon_id', $tx->package_addon_id)
                ->exists();

            if ($isBuiltIn) {
                $tx->update(['status' => 'consolidated']);
            }
        }

        $subscription->update([
            'package_uuid' => $target->uuid,
            'plan_code' => $target->code,
            'amount' => $newAmount,
        ]);

        $record->update([
            'status' => HcmSubscriptionChangeRequest::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
    }

    private function applyDowngradeWithAddons(Subscription $subscription, HcmSubscriptionChangeRequest $record): void
    {
        $target = Package::query()->where('uuid', $record->to_package_uuid)->first();
        if (! $target || (string) $target->status !== 'active') {
            return;
        }

        $billingCycle = (string) ($subscription->billing_cycle ?? 'monthly');
        $packagePrice = (float) ($billingCycle === 'yearly' ? $target->yearly_price : $target->monthly_price);
        $addonRecurring = $this->calculateAddonRecurringForTarget($subscription, $target);
        $newAmount = round($packagePrice + $addonRecurring, 2);

        $provisionEndsAt = $subscription->ends_at && $subscription->ends_at->isFuture()
            ? $subscription->ends_at
            : now()->copy()->addHours(24);

        $subscription->update([
            'package_uuid' => $target->uuid,
            'plan_code' => $target->code,
            'amount' => $newAmount,
            'status' => 'pending_payment',
            'starts_at' => null,
            'ends_at' => $provisionEndsAt,
        ]);

        $invoice = $this->createOrGetDowngradeInvoice($subscription, $record, $newAmount);

        $metadata = (array) ($subscription->metadata ?? []);
        $metadata['pending_invoice_id'] = (int) $invoice->id;
        $metadata['pending_invoice_uuid'] = (string) $invoice->uuid;
        $metadata['pending_invoice_source'] = 'subscription_change_downgrade';
        $subscription->update(['metadata' => $metadata]);

        SendInvoiceEmailJob::dispatchAfterResponse((int) $invoice->id);
    }

    private function createOrGetDowngradeInvoice(
        Subscription $subscription,
        HcmSubscriptionChangeRequest $record,
        float $amount
    ): Invoice {
        $marker = '[downgrade_request:'.$record->id.']';

        $existing = Invoice::query()
            ->where('company_id', $subscription->company_id)
            ->where('subscription_id', $subscription->id)
            ->where('is_paid', false)
            ->where('notes', 'like', '%'.$marker.'%')
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $taxRateSnapshot = app(BillingTaxCalculationService::class)
            ->resolvePolicyRateSnapshot((int) $subscription->company_id, now()->format('Y-m'));

        return Invoice::query()->create([
            'company_id' => $subscription->company_id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->copy()->addDay()->toDateString(),
            'amount_due' => $amount,
            'billing_tax_rate_snapshot' => $taxRateSnapshot > 0 ? $taxRateSnapshot : null,
            'status' => 'draft',
            'notes' => $marker.' Auto-generated invoice for approved downgrade to package '.($subscription->plan_code ?? '-'),
        ]);
    }
}
