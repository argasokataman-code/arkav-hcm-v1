# Leave and Holidays — Implementation

Status: Implemented (Leave Request + Holiday + Leave Settings + Leave Balance)
Updated: 2026-05-08

## Overview

Surface leave & holidays runtime aktif mencakup request cuti employee, persetujuan admin, master tipe cuti, konfigurasi leave settings per tenant, dan kalender hari libur. Leave approval secara otomatis mengubah record attendance pada hari yang sama ke status `on_leave`.

## Controllers

- `backend/app/Http/Controllers/Api/HcmLeaveRequestController.php`
- `backend/app/Http/Controllers/Api/HcmLeaveTypeController.php`
- `backend/app/Http/Controllers/Api/HcmLeaveSettingController.php`
- `backend/app/Http/Controllers/Api/HcmHolidayController.php`

## Web Surfaces

- `backend/resources/views/leaves.blade.php` — admin view
- `backend/resources/views/leaves-employee.blade.php` — employee self-service
- `backend/resources/views/leave-type.blade.php` — master tipe cuti (admin)
- `backend/resources/views/leave-settings.blade.php` — konfigurasi cuti (admin)
- `backend/resources/views/leave-report.blade.php` — laporan cuti (admin)
- `backend/resources/views/holidays.blade.php` — master hari libur (admin)

## Route File

`backend/routes/api/leave.php`

Feature gate middleware: `hcm.api.feature:leave_management` (leave request/type/settings) dan `hcm.api.feature:holiday_calendar` (holiday).

## Main API Endpoints

### Leave Requests (feature: `leave_management`)
- `GET /v1/hcm/leave-requests` — list semua (admin) atau milik sendiri (employee via scope)
- `GET /v1/hcm/leave-requests/export` — export CSV/Excel (admin only)
- `GET /v1/hcm/employee-leave-balance` — saldo cuti per employee
- `POST /v1/hcm/leave-requests` — buat request (employee self atau admin atas nama)
- `PUT /v1/hcm/leave-requests/{id}` — update status/edit (admin approve/decline, employee edit jika pending)
- `DELETE /v1/hcm/leave-requests/{id}` — hapus request pending
- `GET /v1/hcm/leave-type-options` — opsi tipe cuti enabled untuk form

### Leave Types & Settings (feature: `leave_management`)
- `GET /v1/hcm/leave-types` — daftar tipe cuti tenant
- `POST /v1/hcm/leave-types` — buat tipe cuti baru
- `PUT /v1/hcm/leave-types/{id}` — update tipe cuti
- `DELETE /v1/hcm/leave-types/{id}` — hapus tipe cuti
- `GET /v1/hcm/leave-settings` — konfigurasi leave settings tenant
- `PUT /v1/hcm/leave-settings/types/{code}` — update setting per tipe cuti
- `POST /v1/hcm/leave-settings/custom-policies` — tambah custom policy
- `PUT /v1/hcm/leave-settings/custom-policies/{id}` — update custom policy
- `DELETE /v1/hcm/leave-settings/custom-policies/{id}` — hapus custom policy

### Holidays (feature: `holiday_calendar`)
- `GET /v1/hcm/holidays` — daftar hari libur tenant
- `POST /v1/hcm/holidays` — tambah hari libur (hcmAdmin only)
- `POST /v1/hcm/holidays/sync-indonesia` — sync hari libur Indonesia
- `PUT /v1/hcm/holidays/{id}` — update hari libur
- `DELETE /v1/hcm/holidays/{id}` — hapus hari libur

## Data Models

- `LeaveRequest` — request cuti dengan status `pending|approved|declined`
- `LeaveType` — master tipe cuti per tenant
- `EmployeeLeaveBalance` — saldo cuti per employee per tipe
- `LeaveLedger` — deduction/credit log saldo cuti
- `LeavePolicy` / `LeavePolicyAssignment` — custom policy assignments
- `HcmLeaveTypeSetting` / `HcmLeaveCustomPolicy` — konfigurasi tenant
- `LeaveApproval` — audit approval
- `LeaveRequestBreakdown` — rincian hari kerja efektif per request
- `Holiday` / `HolidayCalendar` — master hari libur

## Tenant Scope

- Semua query dikunci ke `company_id` dari tenant context aktif.
- Admin hanya boleh approve/decline request karyawan di tenant yang sama.
- Leave balance dan deduction hanya menyentuh data company aktif.

## Integrasi

- **Attendance**: approved leave mengubah record attendance pada hari kerja terkait menjadi `on_leave`. Reversal mengembalikan status attendance asal.
- **Overtime**: request overtime diblok jika ada approved leave pada tanggal yang sama (tenant + user sama).
- **Performance**: review cycle membaca data leave approved untuk kalkulasi leave frequency dan absenteeism.

## Frontend JS

- `frontend/resources/js/hcm-extras-data.js` — consumer utama leave API
- `frontend/resources/js/leave-settings-data.js` — leave settings page
