# Feature Map: Leave Management

## 1. Entry Points (API)
| Method | Path | Controller | Permission |
|--------|------|------------|------------|
| GET/POST | `/v1/hcm/leave-requests` | `HcmLeaveRequestController` | `leave.self` / `leave.view` |
| POST | `/v1/hcm/leave-requests/{id}/approve` | `HcmLeaveRequestController` | `leave.approve` |
| POST | `/v1/hcm/leave-requests/{id}/reject` | `HcmLeaveRequestController` | `leave.approve` |
| GET/POST | `/v1/hcm/leave-types` | `HcmLeaveTypeController` | `leave.settings` |
| GET/POST | `/v1/hcm/leave-settings` | `HcmLeaveSettingController` | `leave.settings` |
| GET/POST | `/v1/hcm/holidays` | `HcmHolidayController` | `holiday.manage` |

## 2. Controllers
- `backend/app/Http/Controllers/Api/Leave/HcmLeaveRequestController.php` — Main controller
- `backend/app/Http/Controllers/Api/Leave/HcmLeaveTypeController.php` — Leave type CRUD
- `backend/app/Http/Controllers/Api/Leave/HcmLeaveSettingController.php` — Settings
- `backend/app/Http/Controllers/Api/Leave/HcmHolidayController.php` — Holiday calendar

## 3. Controller Concerns
- `HandlesLeaveRequestCrud` — Create/read/update requests
- `HandlesLeaveRequestApproval` — Approve/reject workflow
- `HandlesLeaveRequestSelf` — Employee self-service

## 4. Models
- `App\Models\LeaveRequest` — Leave request data
- `App\Models\LeaveRequestBreakdown` — Day-by-day breakdown
- `App\Models\LeaveType` — Types (annual, sick, maternity, etc.)
- `App\Models\LeavePolicy` — Policy rules
- `App\Models\LeavePolicyAssignment` — Policy per employee
- `App\Models\LeaveApproval` — Approval chain
- `App\Models\LeaveLedger` — Balance tracking
- `App\Models\EmployeeLeaveBalance` — Current balance
- `App\Models\Holiday` — Holiday dates
- `App\Models\HolidayCalendar` — Calendar grouping
- `App\Models\HcmLeaveTypeSetting` — Type-specific settings
- `App\Models\HcmLeaveCustomPolicy` — Custom policies

## 5. Services
- `backend/app/Services/Hcm/LeaveLedgerService.php` — Ledger management
- `backend/app/Services/Hcm/LeaveWorkingDayCalculator.php` — Working day calc (skip weekends/holidays)

## 6. Key Relations
```
LeaveRequest -> User (N:1)
LeaveRequest -> LeaveType (N:1)
LeaveRequest -> LeaveRequestBreakdown (1:N)
LeaveRequest -> LeaveApproval (1:N)
LeavePolicy -> LeavePolicyAssignment (1:N)
LeaveLedger -> EmployeeLeaveBalance
Holiday -> HolidayCalendar (N:1)
```

## 7. Notifications
- `LeaveRequestedNotification` — When employee submits
- `LeaveApprovalRequestedNotification` — To approver
- `LeaveApprovedNotification` — When approved
- `LeaveRejectedNotification` — When rejected
- `LeaveCancelledNotification` — When cancelled
- `LeaveNextApproverNotification` — Multi-level approval
