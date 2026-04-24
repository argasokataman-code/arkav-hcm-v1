<?php

namespace App\Notifications;

use App\Models\HcmPayrollRun;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to employees when monthly payroll is disbursed/paid.
 */
class MonthlyPayrollDisbursedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly HcmPayrollRun $payrollRun,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('payroll.monthly.disbursed', [
            'companyUuid' => (string) ($this->payrollRun->company_uuid ?? ''),
            'entityType' => 'payroll_monthly',
            'entityUuid' => (string) ($this->payrollRun->uuid ?? ''),
            'title' => 'Monthly payroll disbursed',
            'message' => 'Salary for ' . $this->payrollRun->period_label,
            'occurredAt' => now(),
        ], [
            'event' => 'payroll.monthly.disbursed',
            'runId' => (int) $this->payrollRun->id,
            'runUuid' => (string) ($this->payrollRun->uuid ?? ''),
            'periodLabel' => (string) ($this->payrollRun->period_label ?? ''),
            'status' => (string) ($this->payrollRun->status ?? ''),
            'totalAmount' => (float) ($this->payrollRun->total_gross_amount ?? 0),
            'employeeCount' => (int) ($this->payrollRun->total_employee_count ?? 0),
        ]);
    }
}
