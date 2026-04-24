<?php

namespace App\Notifications;

use App\Models\HcmSubscriptionChangeRequest;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionChangeApprovalNeededNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly HcmSubscriptionChangeRequest $record,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $preview = (array) ($this->record->preview ?? []);

        return NotificationPayloadFactory::make('subscription.change_approval_needed', [
            'severity' => 'critical',
            'companyUuid' => (string) $this->record->company_uuid,
            'entityType' => 'subscription_change_request',
            'entityUuid' => (string) $this->record->id,
            'actorUserUuid' => (string) $this->record->user_uuid,
            'title' => 'Subscription change approval needed',
            'message' => (string) $this->record->action,
            'occurredAt' => $this->record->created_at,
        ], [
            'event' => 'subscription_change_approval_needed',
            'requestId' => $this->record->id,
            'companyUuid' => $this->record->company_uuid,
            'requestedByUserUuid' => $this->record->user_uuid,
            'action' => $this->record->action,
            'status' => $this->record->status,
            'fromPackageUuid' => $this->record->from_package_uuid,
            'toPackageUuid' => $this->record->to_package_uuid,
            'effectiveAt' => optional($this->record->effective_at)->toIso8601String(),
            'priceDelta' => $preview['price_delta'] ?? null,
            'requestedAt' => optional($this->record->created_at)->toIso8601String(),
        ]);
    }
}
