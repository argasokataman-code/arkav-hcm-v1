<?php

namespace App\Notifications;

use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee when their attendance correction request is approved by admin.
 *
 * Uses the `database` channel to land in the in-app notification inbox.
 */
class AttendanceCorrectionApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $companyUuid,
        public readonly string $workDate,
        public readonly int $attendanceRecordId,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Koreksi absensi Anda untuk tanggal {$this->workDate} telah disetujui oleh admin.";

        return NotificationPayloadFactory::make('attendance.correction.approved', [
            'companyUuid' => $this->companyUuid,
            'entityType'  => 'attendance_record',
            'entityUuid'  => (string) $this->attendanceRecordId,
            'title'       => 'Koreksi absensi disetujui',
            'message'     => $message,
            'occurredAt'  => now(),
        ], [
            'event'              => 'attendance.correction.approved',
            'companyUuid'        => $this->companyUuid,
            'workDate'           => $this->workDate,
            'attendanceRecordId' => $this->attendanceRecordId,
        ]);
    }
}
