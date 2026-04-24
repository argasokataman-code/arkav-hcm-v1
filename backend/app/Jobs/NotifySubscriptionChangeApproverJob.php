<?php

namespace App\Jobs;

use App\Models\HcmSubscriptionChangeRequest;
use App\Models\User;
use App\Notifications\SubscriptionChangeApprovalNeededNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Notify global super-admin(s) that a tenant submitted a plan change request.
 */
class NotifySubscriptionChangeApproverJob implements ShouldQueue
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

        $adminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));

        $admins = User::query()
            ->whereRaw('LOWER(email) = ?', [$adminEmail])
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new SubscriptionChangeApprovalNeededNotification($record));
        }
    }
}
