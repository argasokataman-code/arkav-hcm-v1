<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the next approver in a sequence approval chain when the previous level approves.
 */
class LeaveNextApproverNotification extends Notification
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
        return NotificationPayloadFactory::make('leave.approval.next_level', [
            'companyUuid' => (string) ($this->leaveRequest->company_uuid ?? ''),
            'entityType' => 'leave',
            'entityUuid' => (string) ($this->leaveRequest->uuid ?? ''),
            'title' => 'Leave request awaiting your approval',
            'message' => (string) ($this->leaveRequest->leave_type ?? ''),
            'occurredAt' => now(),
        ], [
            'event' => 'leave.approval.next_level',
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
