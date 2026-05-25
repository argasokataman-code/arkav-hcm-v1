<?php

namespace App\Notifications;

use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to HCM admins when an employee's probation period has ended.
 *
 * All fields are dynamic — resolved from the database; nothing is hardcoded.
 */
class ProbationCycleAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $employeeName,
        public readonly string $companyName,
        public readonly string $companyUuid,
        public readonly string $contractType,
        public readonly string $employmentHistoryUuid,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $contractLabel = match ($this->contractType) {
            'permanent' => 'pegawai tetap',
            'contract'  => 'pegawai kontrak',
            default     => $this->contractType,
        };

        $message = "Masa probasi karyawan {$this->employeeName} di {$this->companyName} telah berakhir. "
            . "Status yang direncanakan: {$contractLabel}. "
            . "Silakan perbarui status kepegawaian sesuai keputusan manajemen.";

        return NotificationPayloadFactory::make('employee.probation.ended.admin', [
            'companyUuid' => $this->companyUuid,
            'entityType' => 'employee_employment_history',
            'entityUuid' => $this->employmentHistoryUuid,
            'title' => 'Probasi karyawan selesai',
            'message' => $message,
            'occurredAt' => now(),
        ], [
            'event'                  => 'employee.probation.ended.admin',
            'employeeName'           => $this->employeeName,
            'companyName'            => $this->companyName,
            'companyUuid'            => $this->companyUuid,
            'contractType'           => $this->contractType,
            'contractLabel'          => $contractLabel,
            'employmentHistoryUuid'  => $this->employmentHistoryUuid,
        ]);
    }
}
