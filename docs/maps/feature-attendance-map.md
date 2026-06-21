# Feature Map: Attendance

## 1. Entry Points (API)
| Method | Path | Controller | Permission |
|--------|------|------------|------------|
| GET/POST | `/v1/hcm/attendance` | `AttendanceEmployeeController` | `attendance.self` |
| GET | `/v1/hcm/attendance/admin` | `AttendanceAdminController` | `attendance.view` |
| POST | `/v1/hcm/attendance/correction` | `AttendanceCorrectionController` | `attendance.correction` |
| GET/POST | `/v1/hcm/attendance/schedule` | `AttendanceScheduleController` | `attendance.schedule` |
| GET/POST | `/v1/hcm/attendance/selfie` | `AttendanceSelfieController` | `attendance.selfie` |
| GET | `/v1/hcm/attendance/timesheet` | `AttendanceTimesheetController` | `attendance.timesheet` |
| GET/POST | `/v1/hcm/shifts` | `HcmShiftController` | `shift.manage` |
| GET/POST | `/v1/hcm/smart-attendance` | `HcmSmartAttendanceController` | `attendance.smart` |
| GET/POST | `/v1/hcm/attendance/settings` | `HcmAttendanceSettingsController` | `attendance.settings` |

## 2. Controllers
- `backend/app/Http/Controllers/Api/Attendance/AttendanceEmployeeController.php` — Clock in/out
- `backend/app/Http/Controllers/Api/Attendance/AttendanceAdminController.php` — Admin view & reports
- `backend/app/Http/Controllers/Api/Attendance/AttendanceCorrectionController.php` — Correction requests
- `backend/app/Http/Controllers/Api/Attendance/AttendanceScheduleController.php` — Schedule management
- `backend/app/Http/Controllers/Api/Attendance/AttendanceSelfieController.php` — Selfie verification
- `backend/app/Http/Controllers/Api/Attendance/AttendanceTimesheetController.php` — Timesheet
- `backend/app/Http/Controllers/Api/Attendance/HcmShiftController.php` — Shift management
- `backend/app/Http/Controllers/Api/Attendance/HcmSmartAttendanceController.php` — Smart shifting
- `backend/app/Http/Controllers/Api/Attendance/HcmAttendanceSettingsController.php` — Settings
- `backend/app/Http/Controllers/Api/Attendance/BaseAttendanceController.php` — Base class

## 3. Models
- `App\Models\AttendanceRecord` — Clock in/out data, GPS, break times
- `App\Models\HcmShift` — Shift definitions
- `App\Models\HcmScheduleTiming` — Schedule timing rules
- `App\Models\HcmScheduleRoster` — Roster/rotation
- `App\Models\HcmSmartPlannerSetting` — Smart planner config

## 4. Services
- `backend/app/Services/Hcm/SmartAttendanceShiftingService.php` — Auto-shift logic

## 5. Key Relations
```
AttendanceRecord -> User (N:1)
AttendanceRecord -> HcmShift (N:1)
HcmScheduleTiming -> HcmShift (N:1)
HcmScheduleRoster -> EmployeeProfile
```

## 6. Tests
- E2E: `backend/e2e/scenarios/smart-shifting-cs-24h.spec.js`
- E2E: `backend/e2e/scenarios/smart-shifting-negative-scenarios.spec.js`
