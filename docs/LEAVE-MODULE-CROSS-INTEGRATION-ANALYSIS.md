# HCM Module Cross-Integration Analysis & Recommendations

**Date:** April 2026  
**Status:** ANALYSIS COMPLETE

---

## 1. Leave ↔ Payroll Integration

### Current State
- ✅ Leave request data structure: `leave_requests` table with `user_id`, `status`, `date_from`, `date_to`, `days`
- ✅ Employee leave balance: `employee_leave_balances` tracks used/available days
- ✅ Leave ledger: `leave_ledger` records balance changes per status
- ❌ **MISSING**: Direct payroll impact calculation in payroll run generation

### Required Integrations

#### A. Unpaid Leave Impact on Salary
**Scenario:** When employee has approved "unpaid_leave" request during payroll period

**Current Implementation:**
- Leave requests are tracked in `leave_requests` table
- Balance is updated in `leave_ledger`
- Payroll calculation in `HcmPayrollRun` does NOT check leave status

**Recommended Changes:**

1. **Add Leave Deduction Item to Payroll:**
```php
// File: app/Http/Controllers/Api/HcmPayrollController.php
// When calculating payroll, check for approved leave requests

$approvedLeaveInPeriod = LeaveRequest::query()
    ->where('company_id', $companyId)
    ->where('user_id', $employeeId)
    ->where('status', 'approved')
    ->whereBetween('date_from', [$periodStart, $periodEnd])
    ->orWhereBetween('date_to', [$periodStart, $periodEnd])
    ->get();

foreach ($approvedLeaveInPeriod as $leave) {
    $leaveType = LeaveType::find($leave->leave_type_id);
    
    // Only unpaid leave affects salary
    if ($leaveType && !$leaveType->deduct_from_balance) {
        // Create salary deduction line item
        HcmPayrollLine::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employeeId,
            'item_type' => 'deduction',
            'description' => 'Unpaid Leave: ' . $leave->leave_type,
            'amount' => calculateUnpaidLeaveCost($employeeId, $leave->days),
        ]);
    }
}
```

2. **Database Schema Addition:**
```sql
-- Add column to track if payroll has processed leave
ALTER TABLE leave_requests ADD COLUMN payroll_processed_at TIMESTAMP NULL;

-- This ensures idempotency when recalculating payroll
```

3. **Leave Types Configuration:**
- **Paid Leave** (annual_leave, sick_leave): No salary deduction
- **Unpaid Leave** (unpaid_leave, lop): Full salary deduction
- Configuration in `hcm_leave_type_settings`: `is_paid` column

#### B. Balance Carryover to Next Payroll Period
**Scenario:** Unused leave carries forward to next month/year

**Current State:**
- `employee_leave_balances` has `carried_forward` column (NOT currently used)

**Recommended Action:**
```php
// File: app/Services/Hcm/LeaveBalanceCarryoverService.php
class LeaveBalanceCarryoverService {
    public function applyMonthlyCarryover(int $companyId, int $year, int $month): void
    {
        $balances = EmployeeLeaveBalance::where('company_id', $companyId)
            ->where('year', $year)
            ->get();
            
        foreach ($balances as $balance) {
            $unused = $balance->balance; // remaining days
            
            // Create next month's balance with carryover
            EmployeeLeaveBalance::create([
                'company_id' => $companyId,
                'employee_id' => $balance->employee_id,
                'leave_type_id' => $balance->leave_type_id,
                'year' => $balance->balance < 0 ? $year + 1 : $year,
                'balance' => max(0, $unused),
                'carried_forward' => max(0, $unused),
            ]);
        }
    }
}
```

---

## 2. Leave ↔ Attendance Integration

### Current State
- ❌ **NO INTEGRATION**: Attendance records treat leave days as "absent"
- ❌ Attendance status field doesn't distinguish "on leave" vs "absent"
- ❌ Leave requests don't mark dates in attendance system

### Recommended Integrations

#### A. Attendance Status Enhancement
**Current Attendance Status Values:** `present`, `absent`, `late`, `early-out`

**Recommended Addition:** Add `on_leave` status
```sql
-- Migration to update attendance records for approved leave dates
UPDATE attendance_records
SET status = 'on_leave'
WHERE (work_date BETWEEN ? AND ?)
  AND user_id IN (
      SELECT user_id FROM leave_requests
      WHERE status = 'approved'
        AND company_id = ?
        AND (
          work_date BETWEEN date_from AND date_to
          OR DATE(date_from) = DATE(work_date)
        )
  );
```

#### B. Leave Approval Trigger
**Recommendation:** When leave is APPROVED, automatically update attendance records
```php
// File: app/Http/Controllers/Api/HcmLeaveRequestController.php
// In update() method when status changes to 'approved'

public function markAttendanceOnLeave(LeaveRequest $leave): void
{
    // Get all working days in range
    $workingDays = $this->getWorkingDaysInRange(
        $leave->date_from,
        $leave->date_to
    );
    
    foreach ($workingDays as $date) {
        AttendanceRecord::updateOrCreate(
            [
                'user_id' => $leave->user_id,
                'work_date' => $date,
                'company_id' => $leave->company_id,
            ],
            ['status' => 'on_leave']
        );
    }
}
```

#### C. Reports Impact
**Recommendation:** Attendance reports should exclude "on_leave" days from:
- Absent count
- Late arrival calculations
- OT eligibility calculations
- Performance metrics

```php
// File: app/Repositories/AttendanceRepository.php
public function getAbsentDays(int $userId, $month, $year): int
{
    return AttendanceRecord::where('user_id', $userId)
        ->where('status', 'absent')  // Only absent, not on_leave
        ->whereMonth('work_date', $month)
        ->whereYear('work_date', $year)
        ->count();
}
```

---

## 3. Leave ↔ Overtime (OT) Integration

### Current State
- ❌ **NO INTEGRATION**: Employees on leave can't request OT (data error)
- ❌ No validation to prevent overlapping OT and leave

### Recommended Integration
```php
// File: app/Http/Controllers/Api/HcmOvertimeController.php (hypothetical)
public function validateOvertimeRequest(OvertimeRequest $request): bool
{
    // Check if employee is on approved leave for this date
    $leaveConflict = LeaveRequest::where('user_id', $request->user_id)
        ->where('status', 'approved')
        ->whereDate('date_from', '<=', $request->work_date)
        ->whereDate('date_to', '>=', $request->work_date)
        ->exists();
    
    if ($leaveConflict) {
        throw new ValidationException('Employee is on approved leave for this date');
    }
    
    return true;
}
```

---

## 4. Leave ↔ Performance/Incentives

### Current State
- ❌ **NO TRACKING**: Leave frequency not tracked for performance evaluation
- ❌ No integration with bonus/incentive calculations

### Recommended Integration
```php
// File: app/Services/Hcm/PerformanceMetricsService.php
public function calculateLeaveFrequency(int $userId, int $year): array
{
    $approved = LeaveRequest::where('user_id', $userId)
        ->where('status', 'approved')
        ->whereYear('date_from', $year)
        ->sum('days');
    
    $pending = LeaveRequest::where('user_id', $userId)
        ->where('status', 'pending')
        ->whereYear('date_from', $year)
        ->sum('days');
    
    return [
        'approved_days' => $approved,
        'pending_days' => $pending,
        'absenteeism_score' => ($approved / 365) * 100,  // % of year on approved leave
    ];
}
```

---

## 5. Database Relationship Diagram

### Current Schema
```
User
├── LeaveRequest (user_id)
│   ├── LeaveRequestBreakdown (leave_request_id)
│   ├── LeaveApproval (leave_request_id)
│   └── LeaveType → HcmLeaveTypeSetting
├── EmployeeLeaveBalance
│   ├── LeaveType (leave_type_id)
│   └── LeaveLedger
├── AttendanceRecord (user_id)
└── HcmPayrollLine (employee_id)
```

### Recommended Enhancements
```
LeaveRequest
├── payroll_impact_recorded: boolean  // Idempotency
├── attendance_marked: boolean        // Track if attendance updated
└── [NEW] related_payroll_run_id     // Link to which payroll period

AttendanceRecord
├── [UPDATE] status: enum('present', 'absent', 'late', 'early-out', 'on_leave')
└── [NEW] leave_request_id: nullable // Foreign key to leave request

HcmPayrollLine
├── leave_days: nullable              // For leave deduction line items
└── [NEW] leave_request_id: nullable  // Cross-reference
```

---

## 6. Data Flow Scenarios

### Scenario 1: Employee Requests Annual Leave
```
1. Employee submits LeaveRequest (status: pending)
   ↓
2. Manager approves: update LeaveRequest.status = 'approved'
   ↓
3. TRIGGER (RECOMMENDED):
   ├── Update EmployeeLeaveBalance.used (+days)
   ├── Create LeaveLedger entry
   └── Mark AttendanceRecord.status = 'on_leave' for date range
   ↓
4. Next Payroll Run:
   - Check approved leave dates in payroll period
   - Adjust salary (if unpaid)
   - Add payroll line items
```

### Scenario 2: Employee Declines Leave (Manager Rejects)
```
1. Manager declines: update LeaveRequest.status = 'declined'
   ↓
2. TRIGGER (ALREADY IMPLEMENTED):
   ├── Revert EmployeeLeaveBalance.used (-days)
   ├── Create LeaveLedger entry with reversal
   ↓
3. RECOMMENDED ADDITION:
   ├── Revert AttendanceRecord.status = 'absent' (or original)
   └── Mark as no longer on leave
```

---

## 7. Cross-Module Testing Checklist

### Leave → Payroll
- [ ] Test unpaid leave deducts salary in payroll run
- [ ] Test paid leave does NOT deduct salary
- [ ] Test multiple leave requests in same period
- [ ] Test leave spanning payroll period boundaries
- [ ] Test idempotency: recalculating payroll doesn't double-charge

### Leave → Attendance
- [ ] Test approved leave marks attendance as 'on_leave'
- [ ] Test declined leave doesn't update attendance
- [ ] Test attendance reports exclude 'on_leave' days
- [ ] Test late/early-out not counted for on-leave days
- [ ] Test multi-tenant isolation (Company A leave doesn't affect Company B attendance)

### Leave → Overtime
- [ ] Test employee can't request OT on approved leave day
- [ ] Test pending leave doesn't block OT submission
- [ ] Test OT request deleted if leave approved after

### Leave → Performance
- [ ] Test leave frequency calculated correctly
- [ ] Test bonus not applied for high absenteeism
- [ ] Test metrics updated in real-time

---

## 8. Implementation Priority

### Phase 1 (CRITICAL) - Before Production
- ✅ Fix multi-tenant isolation in leave module (DONE)
- ✅ Add overlap detection for leave requests (DONE)
- ❌ **Add attendance status update on leave approval** (TODO)
- ❌ **Prevent OT on leave dates** (TODO)

### Phase 2 (HIGH) - Next Sprint
- ❌ **Add unpaid leave salary deduction to payroll** (TODO)
- ❌ **Add attendance status to reports** (TODO)
- ❌ **Create leave carryover service** (TODO)

### Phase 3 (MEDIUM) - Following Sprint
- ❌ **Add leave frequency metrics to performance** (TODO)
- ❌ **Enhanced leave type configuration UI** (TODO)
- ❌ **Approval workflow integration** (TODO)

### Phase 4 (NICE-TO-HAVE)
- ❌ **Calendar view of team leave** (TODO)
- ❌ **Leave balance projection** (TODO)
- ❌ **Advance booking requirements enforcement** (TODO)

---

## 9. SQL Queries for Integration Testing

### Check Leave Impact on Attendance
```sql
-- Find days where employee is on approved leave
SELECT lr.user_id, lr.date_from, lr.date_to, lr.days, ar.status
FROM leave_requests lr
LEFT JOIN attendance_records ar ON (
    ar.user_id = lr.user_id 
    AND ar.work_date BETWEEN lr.date_from AND lr.date_to
)
WHERE lr.status = 'approved'
  AND lr.company_id = ?
ORDER BY lr.date_from;

-- Verify attendance records marked correctly
SELECT 
    COUNT(*) as total_leave_days,
    SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as marked_on_leave,
    SUM(CASE WHEN status != 'on_leave' THEN 1 ELSE 0 END) as not_marked
FROM attendance_records ar
WHERE ar.user_id IN (
    SELECT DISTINCT user_id FROM leave_requests
    WHERE status = 'approved' AND company_id = ?
)
AND ar.work_date IN (
    SELECT DISTINCT work_date FROM leave_requests
    WHERE status = 'approved' AND company_id = ?
);
```

### Check Payroll Impact
```sql
-- Verify unpaid leave deductions in payroll
SELECT 
    hp.id,
    lr.user_id,
    lr.leave_type,
    lr.days,
    hp.description,
    hp.amount,
    lr.status
FROM hcm_payroll_lines hp
JOIN leave_requests lr ON hp.leave_request_id = lr.id
WHERE hp.payroll_run_id = ?
  AND hp.item_type = 'deduction';

-- Calculate total unpaid leave cost
SELECT 
    lr.user_id,
    SUM(lr.days) as total_unpaid_days,
    SUM(lr.days * daily_rate) as total_deduction
FROM leave_requests lr
JOIN users u ON lr.user_id = u.id
WHERE lr.status = 'approved'
  AND lr.company_id = ?
  AND MONTH(lr.date_from) = ?
  AND YEAR(lr.date_from) = ?;
```

---

## 10. API Contract Updates (OpenAPI)

### Required Endpoint Extensions

#### GET /v1/hcm/leave-requests (Enhanced)
**Response should include:**
```json
{
    "data": [
        {
            "id": 1,
            "userId": 5,
            "leaveType": "annual_leave",
            "dateFrom": "2026-04-20",
            "dateTo": "2026-04-22",
            "days": 2.0,
            "status": "approved",
            "attendanceStatus": "marked",  // NEW: 'marked', 'pending', 'failed'
            "payrollProcessed": true,       // NEW: whether included in payroll
            "payrollImpact": {              // NEW
                "deduction": 0.0,           // salary deduction amount
                "category": "paid_leave"    // 'paid_leave', 'unpaid_leave'
            },
            "notes": "Family vacation"
        }
    ],
    "meta": {
        "integrations": {
            "attendance": { "status": "active", "version": "1.0" },
            "payroll": { "status": "active", "version": "1.0" }
        }
    }
}
```

---

## Summary

**Leave Module Current State:**
- ✅ Multi-tenant isolation verified
- ✅ Date overlap prevention implemented
- ✅ Balance tracking working
- ❌ Missing payroll integration
- ❌ Missing attendance integration
- ❌ Missing overtime conflict check

**Risk Level:** MEDIUM - Leave approval doesn't affect payroll/attendance calculations yet

**Recommendation:** Implement Phase 1 items before production use of leave module.
