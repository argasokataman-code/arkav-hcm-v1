<?php

namespace App\Notifications;

use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee when their attendance correction request is dismissed (not approved) by admin.
 */
class AttendanceCorrectionDismissedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $companyUuid,
        public readonly string $workDate,
        public readonly int $attendanceRecordId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Koreksi absensi Anda untuk tanggal {$this->workDate} tidak dapat diproses oleh admin.";

        return NotificationPayloadFactory::make('attendance.correction.dismissed', [
            'companyUuid' => $this->companyUuid,
            'entityType' => 'attendance_record',
            'entityUuid' => (string) $this->attendanceRecordId,
            'title' => 'Koreksi absensi ditolak',
            'message' => $message,
            'occurredAt' => now(),
        ], [
            'event' => 'attendance.correction.dismissed',
            'companyUuid' => $this->companyUuid,
            'workDate' => $this->workDate,
            'attendanceRecordId' => $this->attendanceRecordId,
        ]);
    }
}
