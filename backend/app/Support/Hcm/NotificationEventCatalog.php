<?php

namespace App\Support\Hcm;

class NotificationEventCatalog
{
    /**
     * @return array<string, array{severity:string,title:string,description:string}>
     */
    public static function all(): array
    {
        return [
            'asset.assigned' => [
                'severity' => 'important',
                'title' => 'Asset assigned',
                'description' => 'Employee receives a custody handover notification.',
            ],
            'asset.returned' => [
                'severity' => 'important',
                'title' => 'Asset returned',
                'description' => 'Employee receives a custody return confirmation.',
            ],
            'subscription.change_approval_needed' => [
                'severity' => 'critical',
                'title' => 'Subscription change approval needed',
                'description' => 'Primary super admin must review tenant subscription change request.',
            ],
            'auth.password_reset_link_requested' => [
                'severity' => 'critical',
                'title' => 'Password reset requested',
                'description' => 'User requested a password reset link.',
            ],
            'billing.invoice.email_sent' => [
                'severity' => 'important',
                'title' => 'Invoice email sent',
                'description' => 'Invoice email was sent successfully.',
            ],
            'billing.invoice.email_failed' => [
                'severity' => 'important',
                'title' => 'Invoice email failed',
                'description' => 'Invoice email delivery failed.',
            ],
            'billing.invoice.reminder_sent' => [
                'severity' => 'important',
                'title' => 'Payment reminder sent',
                'description' => 'Payment reminder email was sent successfully.',
            ],
            'billing.invoice.reminder_failed' => [
                'severity' => 'important',
                'title' => 'Payment reminder failed',
                'description' => 'Payment reminder email failed to send.',
            ],
            'billing.payment_received' => [
                'severity' => 'important',
                'title' => 'Payment received',
                'description' => 'Payment has been received for an invoice.',
            ],
            'billing.invoice.overdue' => [
                'severity' => 'important',
                'title' => 'Invoice overdue',
                'description' => 'Invoice is overdue and needs follow-up.',
            ],
            'billing.subscription.cancelled' => [
                'severity' => 'important',
                'title' => 'Subscription cancelled',
                'description' => 'Subscription has been cancelled.',
            ],
            'billing.invoice.issued' => [
                'severity' => 'important',
                'title' => 'Invoice issued',
                'description' => 'New invoice has been issued to company billing contact.',
            ],
            'billing.subscription.expiring_in_7_days' => [
                'severity' => 'important',
                'title' => 'Subscription expiring in 7 days',
                'description' => 'Subscription renewal reminder before expiration.',
            ],
            'billing.payment_failed' => [
                'severity' => 'important',
                'title' => 'Payment failed',
                'description' => 'Payment failed for an invoice.',
            ],
            'billing.bulk_admin_notification' => [
                'severity' => 'informational',
                'title' => 'Bulk admin notification',
                'description' => 'Bulk operational notification sent to admin recipients.',
            ],
            'leave.requested' => [
                'severity' => 'important',
                'title' => 'Leave request submitted',
                'description' => 'New leave request submitted by employee awaiting approval.',
            ],
            'leave.approved' => [
                'severity' => 'important',
                'title' => 'Leave request approved',
                'description' => 'Leave request has been approved by manager or admin.',
            ],
            'leave.rejected' => [
                'severity' => 'important',
                'title' => 'Leave request rejected',
                'description' => 'Leave request has been declined or rejected.',
            ],
            'leave.cancelled' => [
                'severity' => 'informational',
                'title' => 'Leave request cancelled',
                'description' => 'Approved leave request has been cancelled.',
            ],
            'payroll.thr.batch_generated' => [
                'severity' => 'important',
                'title' => 'THR batch generated',
                'description' => 'Year-end bonus (THR) batch has been generated and is ready for review.',
            ],
            'payroll.thr.batch_assigned' => [
                'severity' => 'important',
                'title' => 'THR batch assigned',
                'description' => 'THR batch has been assigned to employees.',
            ],
            'payroll.thr.disbursed' => [
                'severity' => 'important',
                'title' => 'THR payment processed',
                'description' => 'Year-end bonus (THR) payment has been processed and disbursed to employees.',
            ],
            'payroll.thr.posted' => [
                'severity' => 'informational',
                'title' => 'THR posted to payroll',
                'description' => 'THR batch has been posted to payroll period.',
            ],
            'payroll.monthly.generated' => [
                'severity' => 'important',
                'title' => 'Monthly payroll generated',
                'description' => 'Monthly payroll has been calculated and generated.',
            ],
            'payroll.monthly.finalized' => [
                'severity' => 'important',
                'title' => 'Monthly payroll finalized',
                'description' => 'Monthly payroll has been finalized and is ready for disbursement.',
            ],
            'payroll.monthly.disbursed' => [
                'severity' => 'important',
                'title' => 'Monthly payroll disbursed',
                'description' => 'Monthly payroll payment has been processed and disbursed.',
            ],
            'ticket.created' => [
                'severity' => 'important',
                'title' => 'Ticket created',
                'description' => 'New support ticket has been created.',
            ],
            'ticket.assigned' => [
                'severity' => 'important',
                'title' => 'Ticket assigned',
                'description' => 'Ticket has been assigned or reassigned to a support staff.',
            ],
            'ticket.comment_added' => [
                'severity' => 'informational',
                'title' => 'Ticket comment added',
                'description' => 'New comment has been added to a ticket.',
            ],
            'ticket.resolved' => [
                'severity' => 'important',
                'title' => 'Ticket resolved',
                'description' => 'Ticket has been resolved and is awaiting closure confirmation.',
            ],
            'ticket.closed' => [
                'severity' => 'informational',
                'title' => 'Ticket closed',
                'description' => 'Support ticket has been closed.',
            ],
            'performance.review.created' => [
                'severity' => 'important',
                'title' => 'Performance review created',
                'description' => 'New performance review has been created and assigned to employee for self-assessment.',
            ],
            'performance.review.submitted' => [
                'severity' => 'important',
                'title' => 'Performance review submitted',
                'description' => 'Employee has submitted their self-assessment for performance review.',
            ],
            'performance.review.manager_reviewed' => [
                'severity' => 'important',
                'title' => 'Manager review completed',
                'description' => 'Manager has completed their review assessment of employee performance.',
            ],
            'performance.review.finalized' => [
                'severity' => 'important',
                'title' => 'Performance review finalized',
                'description' => 'Performance review has been finalized by admin with final assessment and scores.',
            ],
            'employee.probation.ended' => [
                'severity' => 'important',
                'title' => 'Masa probasi selesai',
                'description' => 'Notifikasi kepada karyawan bahwa masa probasi telah berakhir.',
            ],
            'employee.probation.ended.admin' => [
                'severity' => 'important',
                'title' => 'Probasi karyawan selesai',
                'description' => 'Notifikasi ke admin bahwa masa probasi karyawan telah berakhir.',
            ],
            'attendance.correction.requested' => [
                'severity' => 'important',
                'title' => 'Koreksi absensi diajukan',
                'description' => 'Notifikasi ke admin HCM bahwa karyawan mengajukan koreksi absensi.',
            ],
            'attendance.correction.approved' => [
                'severity' => 'important',
                'title' => 'Koreksi absensi disetujui',
                'description' => 'Notifikasi ke karyawan bahwa koreksi absensi mereka telah disetujui admin.',
            ],
            'attendance.correction.dismissed' => [
                'severity' => 'important',
                'title' => 'Koreksi absensi ditolak',
                'description' => 'Notifikasi ke karyawan bahwa koreksi absensi mereka tidak dapat diproses admin.',
            ],
        ];
    }

    /**
     * @return array{severity:string,title:string,description:string}
     */
    public static function definition(string $eventKey): array
    {
        return self::all()[$eventKey] ?? [
            'severity' => 'informational',
            'title' => $eventKey,
            'description' => '',
        ];
    }
}
