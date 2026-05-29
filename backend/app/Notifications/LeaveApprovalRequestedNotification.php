<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a configured approver when a leave request is submitted and
 * the approval flow config has been set up for the company.
 */
class LeaveApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly LeaveRequest $leaveRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('leave.approval.requested', [
            'companyUuid' => (string) ($this->leaveRequest->company_uuid ?? ''),
            'entityType' => 'leave',
            'entityUuid' => (string) ($this->leaveRequest->uuid ?? ''),
            'title' => 'Leave request awaiting approval',
            'message' => (string) ($this->leaveRequest->leave_type ?? ''),
            'occurredAt' => now(),
        ], [
            'event' => 'leave.approval.requested',
            'leaveRequestId' => (int) $this->leaveRequest->id,
            'leaveRequestUuid' => (string) ($this->leaveRequest->uuid ?? ''),
            'leaveType' => (string) ($this->leaveRequest->leave_type ?? ''),
            'requesterId' => (int) ($this->leaveRequest->user_id ?? 0),
            'dateFrom' => optional($this->leaveRequest->date_from)->toDateString(),
            'dateTo' => optional($this->leaveRequest->date_to)->toDateString(),
            'days' => (float) ($this->leaveRequest->days ?? 0),
        ]);
    }
}
