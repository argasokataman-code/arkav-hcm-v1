<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee when their approved leave request is cancelled.
 *
 * Uses the `database` channel to land in the in-app notification inbox.
 */
class LeaveCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly LeaveRequest $leaveRequest,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('leave.cancelled', [
            'companyUuid' => (string) ($this->leaveRequest->company_uuid ?? ''),
            'entityType' => 'leave',
            'entityUuid' => (string) ($this->leaveRequest->uuid ?? ''),
            'title' => 'Leave request cancelled',
            'message' => (string) ($this->leaveRequest->leave_type ?? ''),
            'occurredAt' => now(),
        ], [
            'event' => 'leave.cancelled',
            'leaveRequestId' => (int) $this->leaveRequest->id,
            'leaveRequestUuid' => (string) ($this->leaveRequest->uuid ?? ''),
            'leaveType' => (string) ($this->leaveRequest->leave_type ?? ''),
            'requesterId' => (int) ($this->leaveRequest->user_id ?? 0),
            'dateFrom' => optional($this->leaveRequest->date_from)->toDateString(),
            'dateTo' => optional($this->leaveRequest->date_to)->toDateString(),
            'days' => (float) ($this->leaveRequest->days ?? 0),
            'status' => (string) ($this->leaveRequest->status ?? ''),
            'cancellationReason' => (string) ($this->leaveRequest->notes ?? ''),
        ]);
    }
}
