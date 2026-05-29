<?php

namespace App\Notifications;

use App\Models\HcmTermination;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a configured approver when a termination is submitted
 * and the approval flow config has been set up for the company.
 */
class TerminationApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly HcmTermination $termination,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('termination.approval.requested', [
            'companyUuid' => (string) ($this->termination->company_uuid ?? ''),
            'entityType' => 'termination',
            'entityUuid' => (string) ($this->termination->uuid ?? ''),
            'title' => 'Termination case awaiting approval',
            'message' => (string) ($this->termination->termination_type ?? ''),
            'occurredAt' => now(),
        ], [
            'event' => 'termination.approval.requested',
            'terminationId' => (int) $this->termination->id,
            'terminationUuid' => (string) ($this->termination->uuid ?? ''),
            'subjectUserId' => (int) ($this->termination->user_id ?? 0),
            'terminationType' => (string) ($this->termination->termination_type ?? ''),
            'terminationDate' => (string) ($this->termination->termination_date ?? ''),
            'workflowStage' => (string) ($this->termination->workflow_stage ?? ''),
        ]);
    }
}
