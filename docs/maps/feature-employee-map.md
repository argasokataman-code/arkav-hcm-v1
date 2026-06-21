# Feature Map: Employee Management

## 1. Entry Points (API)
| Method | Path | Controller | Permission |
|--------|------|------------|------------|
| GET/POST | `/v1/hcm/employees` | `HcmEmployeeController` | `employee.view` / `employee.create` |
| GET/PUT | `/v1/hcm/employees/{id}` | `HcmEmployeeController` | `employee.view` / `employee.update` |
| DELETE | `/v1/hcm/employees/{id}` | `HcmEmployeeController` | `employee.delete` |
| GET/POST | `/v1/hcm/teams` | `HcmTeamController` | `employee.view` / `team.create` |
| GET/POST | `/v1/hcm/employee-documents` | `HcmEmployeeDocumentController` | `employee.view` |

## 2. Controllers
- `backend/app/Http/Controllers/Api/Employee/HcmEmployeeController.php` — Core CRUD
- `backend/app/Http/Controllers/Api/Employee/HcmTeamController.php` — Team management
- `backend/app/Http/Controllers/Api/Employee/HcmEmployeeDocumentController.php` — Document center

## 3. Controller Concerns (Traits)
- `HandlesEmployeeCoreEndpoints` — CRUD utama
- `HandlesEmployeeCoreExport` — Export CSV/Excel
- `HandlesEmployeeCoreBulk` — Bulk operations
- `HandlesEmployeeBulkOperations` — Shared bulk logic
- `HandlesEmployeeOrganizationEndpoints` — Team/dept assign
- `HandlesEmployeeProfilePhotoEndpoints` — Upload foto
- `HandlesEmployeeSharedUtilities` — Helpers

## 4. Models
- `App\Models\EmployeeProfile` — Primary model, uses `EncryptedOrPlaintext` cast for NIK/bank (UU PDP)
- `App\Models\User` — Base auth model
- `App\Models\Department` — Department
- `App\Models\Designation` — Job title/position
- `App\Models\Team` — Team grouping
- `App\Models\EmployeeContract` — Contract history
- `App\Models\EmployeeCompensation` — Salary history
- `App\Models\EmployeeBankAccount` — Bank accounts
- `App\Models\EmployeeTaxProfile` — Tax profile (PTKP)
- `App\Models\EmployeeBenefit` — Benefits
- `App\Models\EmployeeEducation` — Education history
- `App\Models\EmployeeExperience` — Work experience

## 5. Services
- `backend/app/Services/Hcm/EmployeeSnapshotService.php` — Snapshot current data
- `backend/app/Services/Media/AvatarStorageService.php` — Profile photo storage
- `backend/app/Services/Media/ImageProcessor.php` — Image compression

## 6. Key Relations
```
EmployeeProfile -> User (1:1)
EmployeeProfile -> Department (N:1)
EmployeeProfile -> Designation (N:1)
EmployeeProfile -> Team (N:1)
EmployeeProfile -> WilayahProvince/Regency/District/Village
EmployeeProfile -> EmployeeContract (1:N, snapshot pattern)
EmployeeProfile -> EmployeeCompensation (1:N, snapshot pattern)
EmployeeProfile -> EmployeeBankAccount (1:N)
```

## 7. Important Patterns
- **Snapshot Pattern:** `currentEmploymentSnapshot()`, `currentAssignmentSnapshot()`, `currentCompensationSnapshot()` — Get latest valid record
- **UUID-first:** All models use UUID alongside legacy integer IDs
- **Encryption:** NIK, bank_account_no, bank_ifsc_code, bank_branch are encrypted (UU PDP compliance)

## 8. Events & Notifications
- `App\Events\EmployeeProfileUpdated` — Profile change event
- `App\Notifications\ProbationCycleAdminNotification`
- `App\Notifications\ProbationEndedNotification`

## 9. Tests
- E2E: `backend/e2e/features/employee/employee.crud.super-admin.spec.js`
- Backend: `backend/tests/` — cari `*Employee*`
