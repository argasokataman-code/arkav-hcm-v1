<?php

namespace App\Notifications;

use App\Models\HcmSubscriptionChangeRequest;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notify the tenant user that their subscription change request has been
 * approved or rejected by the super-admin.
 */
class SubscriptionChangeDecisionNotification extends Notification
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
        $record     = $this->record;
        $approvedStatuses = [
            HcmSubscriptionChangeRequest::STATUS_APPROVED,
            HcmSubscriptionChangeRequest::STATUS_APPLIED,
        ];
        $isApproved = in_array((string) $record->status, $approvedStatuses, true);

        $eventKey   = $isApproved ? 'subscription.change_approved' : 'subscription.change_rejected';
        $title      = $isApproved
            ? 'Subscription change approved'
            : 'Subscription change rejected';
        $severity   = $isApproved ? 'info' : 'warning';

        $preview = (array) ($record->preview ?? []);

        return NotificationPayloadFactory::make($eventKey, [
            'severity'      => $severity,
            'companyUuid'   => (string) $record->company_uuid,
            'entityType'    => 'subscription_change_request',
            'entityUuid'    => (string) $record->id,
            'actorUserUuid' => (string) $record->decided_by_user_uuid,
            'title'         => $title,
            'message'       => (string) $record->action,
            'occurredAt'    => $record->decided_at,
        ], [
            'event'           => $eventKey,
            'requestId'       => $record->id,
            'companyUuid'     => $record->company_uuid,
            'action'          => $record->action,
            'status'          => $record->status,
            'fromPackageUuid' => $record->from_package_uuid,
            'toPackageUuid'   => $record->to_package_uuid,
            'effectiveAt'     => optional($record->effective_at)->toIso8601String(),
            'priceDelta'      => $preview['price_delta'] ?? null,
            'decidedAt'       => optional($record->decided_at)->toIso8601String(),
            'notes'           => $record->notes,
        ]);
    }
}
