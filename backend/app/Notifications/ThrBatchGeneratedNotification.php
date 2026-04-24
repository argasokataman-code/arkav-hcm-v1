<?php

namespace App\Notifications;

use App\Models\HcmThrBatch;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to admins when THR batch is generated and ready for review.
 */
class ThrBatchGeneratedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly HcmThrBatch $batch,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('payroll.thr.batch_generated', [
            'companyUuid' => (string) ($this->batch->company_uuid ?? ''),
            'entityType' => 'payroll_thr',
            'entityUuid' => (string) ($this->batch->uuid ?? ''),
            'title' => 'THR batch generated',
            'message' => 'Batch for year ' . $this->batch->calendar_year,
            'occurredAt' => $this->batch->created_at,
        ], [
            'event' => 'payroll.thr.batch_generated',
            'batchId' => (int) $this->batch->id,
            'batchUuid' => (string) ($this->batch->uuid ?? ''),
            'calendarYear' => (int) ($this->batch->calendar_year ?? 0),
            'status' => (string) ($this->batch->status ?? ''),
            'totalAmount' => (float) ($this->batch->total_amount ?? 0),
            'employeeCount' => (int) ($this->batch->employee_count ?? 0),
        ]);
    }
}
