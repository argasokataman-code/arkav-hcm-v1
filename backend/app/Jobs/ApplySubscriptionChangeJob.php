<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\HcmSubscriptionChangeRequest;
use App\Models\Package;
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
    ) {
    }

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

            if ($record->action === HcmSubscriptionChangeRequest::ACTION_UPGRADE) {
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
                    'termination_reason' => 'Tenant-initiated cancellation request ' . $record->id,
                ]);
            } elseif ($record->to_package_uuid) {
                $target = Package::query()->where('uuid', $record->to_package_uuid)->first();
                if ($target && (string) $target->status === 'active') {
                    $billingCycle = (string) ($subscription->billing_cycle ?? 'monthly');
                    $amount = (float) ($billingCycle === 'yearly' ? $target->yearly_price : $target->monthly_price);
                    $provisionEndsAt = $subscription->ends_at && $subscription->ends_at->isFuture()
                        ? $subscription->ends_at
                        : now()->copy()->addHours(24);

                    $subscription->update([
                        'package_uuid' => $target->uuid,
                        'plan_code' => $target->code,
                        'amount' => $amount,
                        'status' => 'pending_payment',
                        'starts_at' => null,
                        'ends_at' => $provisionEndsAt,
                    ]);

                    $invoice = $this->createOrGetDowngradeInvoice($subscription, $record, $amount);

                    $metadata = (array) ($subscription->metadata ?? []);
                    $metadata['pending_invoice_id'] = (int) $invoice->id;
                    $metadata['pending_invoice_uuid'] = (string) $invoice->uuid;
                    $metadata['pending_invoice_source'] = 'subscription_change_downgrade';
                    $subscription->update(['metadata' => $metadata]);

                    SendInvoiceEmailJob::dispatchAfterResponse((int) $invoice->id);
                }
            }

            $record->update([
                'status' => HcmSubscriptionChangeRequest::STATUS_APPLIED,
                'applied_at' => now(),
            ]);
        });
    }

    private function createOrGetDowngradeInvoice(
        Subscription $subscription,
        HcmSubscriptionChangeRequest $record,
        float $amount
    ): Invoice
    {
        $marker = '[downgrade_request:' . $record->id . ']';

        $existing = Invoice::query()
            ->where('company_id', $subscription->company_id)
            ->where('subscription_id', $subscription->id)
            ->where('is_paid', false)
            ->where('notes', 'like', '%' . $marker . '%')
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
            'notes' => $marker . ' Auto-generated invoice for approved downgrade to package ' . ($subscription->plan_code ?? '-'),
        ]);
    }
}
