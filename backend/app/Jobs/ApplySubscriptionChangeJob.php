<?php

namespace App\Jobs;

use App\Models\HcmSubscriptionChangeRequest;
use App\Models\Package;
use App\Models\Subscription;
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

            if (! in_array($record->status, [
                HcmSubscriptionChangeRequest::STATUS_APPROVED,
                HcmSubscriptionChangeRequest::STATUS_PENDING,
            ], true)) {
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
                if ($target) {
                    $billingCycle = (string) ($subscription->billing_cycle ?? 'monthly');
                    $amount = (float) ($billingCycle === 'yearly' ? $target->yearly_price : $target->monthly_price);

                    $subscription->update([
                        'package_uuid' => $target->uuid,
                        'plan_code' => $target->code,
                        'amount' => $amount,
                    ]);
                }
            }

            $record->update([
                'status' => HcmSubscriptionChangeRequest::STATUS_APPLIED,
                'applied_at' => now(),
            ]);
        });
    }
}
