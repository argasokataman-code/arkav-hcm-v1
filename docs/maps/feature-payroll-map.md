# Feature Map: Payroll

## 1. Entry Points (API)
| Method | Path | Controller | Permission |
|--------|------|------------|------------|
| GET | `/v1/hcm/payroll/runs` | `HcmPayrollRunController@history` | `payroll.view` |
| GET | `/v1/hcm/payroll/runs/{id}` | `HcmPayrollRunController@show` | `payroll.view` |
| POST | `/v1/hcm/payroll/runs/{id}/finalize` | `HcmPayrollRunController@finalize` | `payroll.finalize` |
| POST | `/v1/hcm/payroll/runs/{id}/disburse` | `HcmPayrollRunController@disburse` | `payroll.disburse` |
| POST | `/v1/hcm/payroll/runs/{id}/void` | `HcmPayrollRunController@void` | `payroll.finalize` |
| GET | `/v1/hcm/payroll/my-slip-pdf` | `MonthlyPayslipService@renderPdf` | (employee self) |

## 2. Controllers
- `backend/app/Http/Controllers/Api/Payroll/HcmPayrollRunController.php` — Core payroll run logic
- `backend/app/Http/Controllers/Api/Payroll/HcmPayrollPeriodController.php` — Period management
- `backend/app/Http/Controllers/Api/Payroll/HcmSalaryComponentController.php` — Salary components
- `backend/app/Http/Controllers/Api/Payroll/HcmPayrollThrController.php` — THR (holiday bonus)
- `backend/app/Http/Controllers/Api/Payroll/HcmPayrollSettingsController.php` — Settings

## 3. Services (Business Logic)
- `backend/app/Services/Hcm/MonthlyPayslipService.php` — Slip gaji & PDF
- `backend/app/Services/Hcm/PayrollLateArrivalMigrationService.php` — Migrasi late arrival
- `backend/app/Services/Hcm/PayrollWorkRuleResolver.php` — Rule kerja
- `backend/app/Services/Hcm/PkwtCompensationService.php` — Kompensasi PKWT
- `backend/app/Services/Hcm/RefreshOpenPayrollDraftsService.php` — Refresh draft
- `backend/app/Services/Hcm/ThrBatchService.php` — Batch THR
- `backend/app/Services/Hcm/ThrProRataCalculator.php` — Kalkulasi THR pro-rata
- `backend/app/Services/Hcm/BpjsContributionCalculator.php` — Hitung BPJS

## 4. Models
- `App\Models\HcmPayrollRun` — Status: `draft`, `finalized`, `void`. Purpose: `monthly`, `thr`, `pkwt_compensation`
- `App\Models\HcmPayrollLine` — Per-employee breakdown, meta contains `paymentStatus`, `paidAt`
- `App\Models\HcmPayrollPeriod` — Period status: `open`, `posted`
- `App\Models\HcmSalaryComponent` — Kode komponen (gaji pokok, lembur, BPJS, dll)
- `App\Models\HcmSalaryComponentCategory` — Kategori komponen

## 5. Relations
```
HcmPayrollPeriod (1) -> (N) HcmPayrollRun
HcmPayrollRun (1) -> (N) HcmPayrollLine
HcmPayrollLine -> User (per employee)
HcmSalaryComponent -> HcmSalaryComponentCategory
```

## 6. Concerns (Traits di Controller)
- `BuildsMonthlyPayrollReports` — Bangun report bulanan
- `BuildsPayrollRunPayloads` — Serialize run data
- `HandlesPayrollRunReadEndpoints` — Read logic
- `HandlesPayrollRunRuntimeUtilities` — Helpers runtime
- `HandlesSalaryComponentCrud` — CRUD komponen gaji

## 7. Events & Notifications
- `App\Events\PayrollFinalized` — Triggered after finalize
- `App\Notifications\MonthlyPayrollGeneratedNotification`
- `App\Notifications\MonthlyPayrollDisbursedNotification`

## 8. Tests
- Backend: `backend/tests/` — cari `*Payroll*`
- E2E: `backend/e2e/scenarios/payroll-run.spec.js`
