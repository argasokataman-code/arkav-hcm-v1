<?php

namespace App\Notifications;

use App\Models\OvertimeRequest;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a configured approver when an overtime request is submitted
 * and the approval flow config has been set up for the company.
 */
class OvertimeApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly OvertimeRequest $overtimeRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('overtime.approval.requested', [
            'companyUuid' => (string) ($this->overtimeRequest->company_uuid ?? ''),
            'entityType' => 'overtime',
            'entityUuid' => (string) ($this->overtimeRequest->uuid ?? ''),
            'title' => 'Overtime request awaiting approval',
            'message' => (string) ($this->overtimeRequest->work_date ?? ''),
            'occurredAt' => now(),
        ], [
            'event' => 'overtime.approval.requested',
            'overtimeRequestId' => (int) $this->overtimeRequest->id,
            'overtimeRequestUuid' => (string) ($this->overtimeRequest->uuid ?? ''),
            'requesterId' => (int) ($this->overtimeRequest->user_id ?? 0),
            'workDate' => (string) ($this->overtimeRequest->work_date ?? ''),
            'minutes' => (int) ($this->overtimeRequest->minutes ?? 0),
        ]);
    }
}
