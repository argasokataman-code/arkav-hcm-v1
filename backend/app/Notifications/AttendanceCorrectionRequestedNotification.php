<?php

namespace App\Notifications;

use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to HCM admins when an employee submits an attendance correction request.
 *
 * Uses the `database` channel to land in the in-app notification inbox.
 */
class AttendanceCorrectionRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $employeeName,
        public readonly string $companyUuid,
        public readonly string $workDate,
        public readonly string $reason,
        public readonly int $attendanceRecordId,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "{$this->employeeName} mengajukan koreksi absensi untuk tanggal {$this->workDate}. Alasan: {$this->reason}";

        return NotificationPayloadFactory::make('attendance.correction.requested', [
            'companyUuid' => $this->companyUuid,
            'entityType'  => 'attendance_record',
            'entityUuid'  => (string) $this->attendanceRecordId,
            'title'       => 'Koreksi absensi diajukan',
            'message'     => $message,
            'occurredAt'  => now(),
        ], [
            'event'               => 'attendance.correction.requested',
            'employeeName'        => $this->employeeName,
            'companyUuid'         => $this->companyUuid,
            'workDate'            => $this->workDate,
            'reason'              => $this->reason,
            'attendanceRecordId'  => $this->attendanceRecordId,
        ]);
    }
}
