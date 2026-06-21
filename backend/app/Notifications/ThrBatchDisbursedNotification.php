<?php

namespace App\Notifications;

use App\Models\HcmThrBatch;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to admins/employees when THR payments are processed and disbursed.
 */
class ThrBatchDisbursedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly HcmThrBatch $batch,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('payroll.thr.disbursed', [
            'companyUuid' => (string) ($this->batch->company_uuid ?? ''),
            'entityType' => 'payroll_thr',
            'entityUuid' => (string) ($this->batch->uuid ?? ''),
            'title' => 'THR payment processed',
            'message' => 'Year-end bonus for '.$this->batch->calendar_year,
            'occurredAt' => now(),
        ], [
            'event' => 'payroll.thr.disbursed',
            'batchId' => (int) $this->batch->id,
            'batchUuid' => (string) ($this->batch->uuid ?? ''),
            'calendarYear' => (int) ($this->batch->calendar_year ?? 0),
            'status' => (string) ($this->batch->status ?? ''),
            'totalAmount' => (float) ($this->batch->total_amount ?? 0),
            'employeeCount' => (int) ($this->batch->employee_count ?? 0),
        ]);
    }
}
