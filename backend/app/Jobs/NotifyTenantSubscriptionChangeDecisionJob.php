<?php

namespace App\Jobs;

use App\Models\HcmSubscriptionChangeRequest;
use App\Models\User;
use App\Notifications\SubscriptionChangeDecisionNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Notify the tenant user (who submitted the change request) about the
 * approve/reject decision made by the super-admin.
 */
class NotifyTenantSubscriptionChangeDecisionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $changeRequestId,
    ) {
    }

    public function handle(): void
    {
        $record = HcmSubscriptionChangeRequest::query()
            ->with(['company', 'user'])
            ->where('id', $this->changeRequestId)
            ->first();

        if (! $record) {
            return;
        }

        // Only notify for final decisions
        $finalStatuses = [
            HcmSubscriptionChangeRequest::STATUS_APPROVED,
            HcmSubscriptionChangeRequest::STATUS_REJECTED,
            HcmSubscriptionChangeRequest::STATUS_APPLIED,
        ];

        if (! in_array($record->status, $finalStatuses, true)) {
            return;
        }

        // Notify the tenant user who submitted the request
        $tenant = $record->user_uuid
            ? User::query()->where('uuid', $record->user_uuid)->first()
            : null;

        if ($tenant) {
            $tenant->notify(new SubscriptionChangeDecisionNotification($record));
        }
    }
}
