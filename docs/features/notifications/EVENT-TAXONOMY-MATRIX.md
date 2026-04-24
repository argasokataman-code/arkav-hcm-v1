# Event-Key Taxonomy Matrix

**Purpose**: Canonical reference of all notification event types across business domains with metadata, severity levels, and producer/consumer mappings.

**Last Updated**: 2026-04-24  
**Status**: Stable (v1.0 - All Phase 3 domains onboarded)

## Event Matrix by Domain

### Asset Management Domain

| Event Key | Severity | Title | Description | Producer | Recipients | Status |
|-----------|----------|-------|-------------|----------|------------|--------|
| `asset.assigned` | important | Asset assigned | Employee receives a custody handover notification. | AssetAllocationController::assignAsset() | Employee (asset owner) | ✅ Active |
| `asset.returned` | important | Asset returned | Employee receives a custody return confirmation. | AssetAllocationController::returnAsset() | Employee (asset owner) | ✅ Active |

### Authentication & Security Domain

| Event Key | Severity | Title | Description | Producer | Recipients | Status |
|-----------|----------|-------|-------------|----------|------------|--------|
| `auth.password_reset_link_requested` | critical | Password reset requested | User requested a password reset link. | PasswordController::sendResetLink() | User (requestor) | ✅ Active |

### Billing & Subscription Domain

| Event Key | Severity | Title | Description | Producer | Recipients | Status |
|-----------|----------|-------|-------------|----------|------------|--------|
| `billing.invoice.email_sent` | important | Invoice email sent | Invoice email was sent successfully. | InvoiceEmailJob | Billing contact (admin) | ✅ Active |
| `billing.invoice.email_failed` | important | Invoice email failed | Invoice email delivery failed. | InvoiceEmailErrorHandler | Billing contact (admin) | ✅ Active |
| `billing.invoice.reminder_sent` | important | Payment reminder sent | Payment reminder email was sent successfully. | SendPaymentReminderJob | Billing contact (admin) | ✅ Active |
| `billing.invoice.reminder_failed` | important | Payment reminder failed | Payment reminder email failed to send. | SendPaymentReminderErrorHandler | Billing contact (admin) | ✅ Active |
| `billing.payment_received` | important | Payment received | Payment has been received for an invoice. | PaymentReceivedListener | Billing contact (admin) | ✅ Active |
| `billing.invoice.overdue` | important | Invoice overdue | Invoice is overdue and needs follow-up. | OverdueInvoiceDetector | Billing contact (admin) | ✅ Active |
| `billing.subscription.cancelled` | important | Subscription cancelled | Subscription has been cancelled. | SubscriptionCancellationController | Subscription owner (admin) | ✅ Active |
| `billing.invoice.issued` | important | Invoice issued | New invoice has been issued to company billing contact. | InvoiceGeneratedListener | Billing contact (admin) | ✅ Active |
| `billing.subscription.expiring_in_7_days` | important | Subscription expiring in 7 days | Subscription renewal reminder before expiration. | SubscriptionExpirationReminder | Subscription owner (admin) | ✅ Active |
| `billing.payment_failed` | important | Payment failed | Payment failed for an invoice. | PaymentFailedListener | Billing contact (admin) | ✅ Active |
| `billing.bulk_admin_notification` | informational | Bulk admin notification | Bulk operational notification sent to admin recipients. | AdminNotificationBroadcaster | Admin users | ✅ Active |
| `subscription.change_approval_needed` | critical | Subscription change approval needed | Primary super admin must review tenant subscription change request. | SubscriptionChangeController::requestChange() | Primary super admin | ✅ Active |

### Leave Management Domain

| Event Key | Severity | Title | Description | Producer | Recipients | Status |
|-----------|----------|-------|-------------|----------|------------|--------|
| `leave.requested` | important | Leave request submitted | New leave request submitted by employee awaiting approval. | HcmLeaveRequestController::store() | Admin (HR), Manager | ✅ Active |
| `leave.approved` | important | Leave request approved | Leave request has been approved by manager or admin. | HcmLeaveRequestController::update() [pending→approved] | Employee (requestor) | ✅ Active |
| `leave.rejected` | important | Leave request rejected | Leave request has been declined or rejected. | HcmLeaveRequestController::update() [→declined] | Employee (requestor) | ✅ Active |
| `leave.cancelled` | informational | Leave request cancelled | Approved leave request has been cancelled. | HcmLeaveRequestController::update() [approved→pending] | Employee (requestor) | ✅ Active |

### Payroll Domain

| Event Key | Severity | Title | Description | Producer | Recipients | Status |
|-----------|----------|-------|-------------|----------|------------|--------|
| `payroll.thr.batch_generated` | important | THR batch generated | Year-end bonus (THR) batch has been generated and is ready for review. | ThrBatchGenerationJob | Admin (Finance) | ✅ Active |
| `payroll.thr.batch_assigned` | important | THR batch assigned | THR batch has been assigned to employees. | ThrBatchAssignmentController | Admin (Finance) | ✅ Active |
| `payroll.thr.disbursed` | important | THR payment processed | Year-end bonus (THR) payment has been processed and disbursed to employees. | ThrDisbursementController | Admin (Finance), Employees | ✅ Active |
| `payroll.thr.posted` | informational | THR posted to payroll | THR batch has been posted to payroll period. | ThrPostingController | Admin (Finance) | ✅ Active |
| `payroll.monthly.generated` | important | Monthly payroll generated | Monthly payroll has been calculated and generated. | MonthlyPayrollGenerationJob | Admin (Finance) | ✅ Active |
| `payroll.monthly.finalized` | important | Monthly payroll finalized | Monthly payroll has been finalized and is ready for disbursement. | MonthlyPayrollFinalizationController | Admin (Finance) | ✅ Active |
| `payroll.monthly.disbursed` | important | Monthly payroll disbursed | Monthly payroll payment has been processed and disbursed. | MonthlyPayrollDisbursementController | Admin (Finance), Employees | ✅ Active |

### Ticketing Domain

| Event Key | Severity | Title | Description | Producer | Recipients | Status |
|-----------|----------|-------|-------------|----------|------------|--------|
| `ticket.created` | important | Ticket created | New support ticket has been created. | HcmTicketController::store() | Admin (Support Team), Assignee | ✅ Active |
| `ticket.assigned` | important | Ticket assigned | Ticket has been assigned or reassigned to a support staff. | HcmTicketController::updateAssignee() | Assignee (support staff) | ✅ Active |
| `ticket.comment_added` | informational | Ticket comment added | New comment has been added to a ticket. | HcmTicketCommentController::store() | Ticket owner, Assignee | ✅ Active |
| `ticket.resolved` | important | Ticket resolved | Ticket has been resolved and is awaiting closure confirmation. | HcmTicketController::markResolved() | Ticket owner, Assignee | ✅ Active |
| `ticket.closed` | informational | Ticket closed | Support ticket has been closed. | HcmTicketController::markClosed() | Ticket owner, Assignee | ✅ Active |

### Performance Review Domain

| Event Key | Severity | Title | Description | Producer | Recipients | Status |
|-----------|----------|-------|-------------|----------|------------|--------|
| `performance.review.created` | important | Performance review created | New performance review has been created and assigned to employee for self-assessment. | HcmPerformanceController::createReview() | Admin (HR) | ✅ Active |
| `performance.review.submitted` | important | Performance review submitted | Employee has submitted their self-assessment for performance review. | HcmPerformanceController::submitReview() | Admin (HR) | ✅ Active |
| `performance.review.manager_reviewed` | important | Manager review completed | Manager has completed their review assessment of employee performance. | HcmPerformanceController::managerComplete() | Admin (HR) | ✅ Active |
| `performance.review.finalized` | important | Performance review finalized | Performance review has been finalized by admin with final assessment and scores. | HcmPerformanceController::finalize() | Employee, Manager | ✅ Active |

## Event Severity Classification

- **critical**: Requires immediate attention; security, authentication, or approval-gated operations
- **important**: Business workflow event; impacts compliance, payroll, or operational decisions
- **informational**: Supportive notification; FYI updates with lower urgency

## Notification Channel Mappings

| Event Category | Channel | Persistence | Queue | Rate Limit |
|---|---|---|---|---|
| Critical (auth, subscriptions) | Database + Email | Yes | notifications | 30 req/min (delivery retry) |
| Important (payroll, leave, tickets) | Database + Email | Yes | notifications | 100 req/min (delivery summary) |
| Informational (comments, status) | Database only | Yes | notifications | 100 req/min (delivery details) |

## Producer-to-Consumer Contract

### Pattern: Domain-Driven Producer

Each domain controller implements producer wiring:
1. **Create Event** → Instantiate Notification class with fresh model data
2. **Lookup Recipients** → Query User table or leverage relationships (employee, manager, assignee)
3. **Dispatch Notification** → Call `$user->notify(new DomainNotification($model))`
4. **Log Delivery** → NotificationDeliveryRecorder auto-captures event_key, channel, status
5. **Retry Policy** → Observability system manages retries (3 attempts, 60s/300s/900s backoff)

### Example: Leave Notification Wiring

```php
// HcmLeaveRequestController::store()
$leaveRequest = LeaveRequest::query()->create([...]);

// Notify admin users
$adminEmail = config('app.primary_hcm_admin_email');
if ($adminEmail) {
    $admin = User::query()->where('email', $adminEmail)->first();
    if ($admin) {
        $admin->notify(new LeaveRequestedNotification($leaveRequest));
    }
}
```

## Observable Dashboard Integration

**Feature**: Notification Observability Dashboard  
**Location**: `/admin/notifications/observability`  
**Reference Point**: Event-key taxonomy accessible as:
- **Summary View**: Displays total events per domain over time
- **Details View**: Filters by event-key to show delivery status
- **Export View**: Includes event_key in CSV/JSON export for audit trail

## Cross-Domain Event Statistics

| Domain | Total Events | Status | Last Updated |
|--------|--------------|--------|--------------|
| Asset Management | 2 | Stable | 2026-05-01 |
| Authentication | 1 | Stable | 2026-05-01 |
| Billing & Subscription | 11 | Stable | 2026-05-01 |
| Leave Management | 4 | Stable | 2026-04-24 |
| Payroll | 7 | Stable | 2026-04-24 |
| Ticketing | 5 | Stable | 2026-04-24 |
| Performance Review | 4 | Stable | 2026-04-24 |
| **TOTAL** | **34** | **Stable** | **2026-04-24** |

## Versioning & Future Roadmap

**v1.0 (Current)**: 34 events across 7 domains ✅ Onboarded

**v1.1 (Future Candidates)**:
- Attendance tracking domain (6-8 events: check-in, check-out, late alert, override)
- Project management domain (4-6 events: task assigned, milestone reached, deadline warning)
- Training & Development domain (3-5 events: course enrolled, certification completed, training due)

---

**Next Step**: Observability drilldown modal will reference this matrix to help admins understand event details per domain.
