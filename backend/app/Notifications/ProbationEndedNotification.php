<?php

namespace App\Notifications;

use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee when their probation period has ended.
 *
 * The message is fully dynamic — company name and contract type come from
 * the database; nothing is hardcoded.
 */
class ProbationEndedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $employeeName,
        public readonly string $companyName,
        public readonly string $companyUuid,
        public readonly string $contractType,
        public readonly string $employmentHistoryUuid,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $contractLabel = match ($this->contractType) {
            'permanent' => 'pegawai tetap',
            'contract' => 'pegawai kontrak',
            default => $this->contractType,
        };

        $message = "Selamat {$this->employeeName}! Masa probasi Anda di {$this->companyName} telah selesai. "
            ."Anda kini resmi menjadi {$contractLabel} di perusahaan tersebut.";

        return NotificationPayloadFactory::make('employee.probation.ended', [
            'companyUuid' => $this->companyUuid,
            'entityType' => 'employee_employment_history',
            'entityUuid' => $this->employmentHistoryUuid,
            'title' => 'Masa probasi selesai',
            'message' => $message,
            'occurredAt' => now(),
        ], [
            'event' => 'employee.probation.ended',
            'employeeName' => $this->employeeName,
            'companyName' => $this->companyName,
            'companyUuid' => $this->companyUuid,
            'contractType' => $this->contractType,
            'contractLabel' => $contractLabel,
            'employmentHistoryUuid' => $this->employmentHistoryUuid,
        ]);
    }
}
