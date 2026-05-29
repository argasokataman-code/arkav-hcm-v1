<?php

namespace App\Notifications;

use App\Models\HcmResignation;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a configured approver when a resignation is submitted
 * and the approval flow config has been set up for the company.
 */
class ResignationApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly HcmResignation $resignation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('resignation.approval.requested', [
            'companyUuid' => (string) ($this->resignation->company_uuid ?? ''),
            'entityType' => 'resignation',
            'entityUuid' => (string) ($this->resignation->uuid ?? ''),
            'title' => 'Resignation request awaiting approval',
            'message' => (string) ($this->resignation->resignation_date ?? ''),
            'occurredAt' => now(),
        ], [
            'event' => 'resignation.approval.requested',
            'resignationId' => (int) $this->resignation->id,
            'resignationUuid' => (string) ($this->resignation->uuid ?? ''),
            'requesterId' => (int) ($this->resignation->user_id ?? 0),
            'noticeDate' => (string) ($this->resignation->notice_date ?? ''),
            'resignationDate' => (string) ($this->resignation->resignation_date ?? ''),
        ]);
    }
}
