<?php

namespace App\Notifications;

use App\Models\HcmPayrollRun;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to admins when monthly payroll is generated.
 */
class MonthlyPayrollGeneratedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly HcmPayrollRun $payrollRun,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('payroll.monthly.generated', [
            'companyUuid' => (string) ($this->payrollRun->company_uuid ?? ''),
            'entityType' => 'payroll_monthly',
            'entityUuid' => (string) ($this->payrollRun->uuid ?? ''),
            'title' => 'Monthly payroll generated',
            'message' => 'Payroll for '.$this->payrollRun->period_label,
            'occurredAt' => $this->payrollRun->created_at,
        ], [
            'event' => 'payroll.monthly.generated',
            'runId' => (int) $this->payrollRun->id,
            'runUuid' => (string) ($this->payrollRun->uuid ?? ''),
            'periodLabel' => (string) ($this->payrollRun->period_label ?? ''),
            'status' => (string) ($this->payrollRun->status ?? ''),
            'totalAmount' => (float) ($this->payrollRun->total_gross_amount ?? 0),
            'employeeCount' => (int) ($this->payrollRun->total_employee_count ?? 0),
        ]);
    }
}
