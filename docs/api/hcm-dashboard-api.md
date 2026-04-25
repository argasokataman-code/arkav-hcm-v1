# HCM Dashboard API

## Overview

Endpoint dashboard HCM untuk halaman:

- admin dashboard (`/index`)
- employee dashboard (`/employee-dashboard`)

Keduanya memakai base prefix runtime: `/v1/hcm`.

Source of truth runtime:

- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/HcmDashboardController.php`

## Access Rule

- Semua endpoint memerlukan bearer token (`api.token`).
- Scope tenant ditentukan oleh tenant context aktif (`X-Company-Code` atau resolver default membership).
- Ringkasan admin dashboard hanya untuk role admin HCM pada tenant aktif.

## Endpoints Aktif

### 1. Admin dashboard summary

`GET /v1/hcm/dashboard-summary`

Response envelope:

```json
{
  "success": true,
  "data": {
    "executiveOverview": {},
    "attendanceToday": {},
    "payrollAndLeaveInbox": {},
    "workforceAndAlerts": {},
    "legacyWidgets": {}
  }
}
```

#### Catatan kontrak aktif

- `legacyWidgets` adalah payload additive untuk hidrasi kartu/tabel legacy di Blade (`index-dashboard-data.js`).
- Field ini tidak menghapus field existing; kompatibel untuk FE lama.
- Sub-kunci `legacyWidgets` saat ini mencakup:
  - `attendanceOverview`
  - `topPerformer`
  - `departmentBreakdown[]`
  - `clockInOut[]`
  - `lateEmployees[]`
  - `employees[]`
  - `invoices[]`
  - `recentActivities[]`
  - `birthdays.today[]`
  - `birthdays.tomorrow[]`

### 2. Employee dashboard summary

`GET /v1/hcm/employee-dashboard-summary`

Response envelope:

```json
{
  "success": true,
  "data": {
    "scheduleToday": {},
    "attendanceThisMonth": {},
    "leaveBalance": {},
    "payrollSnapshot": {}
  }
}
```

## Negative Scenario

- Token tidak valid: `401`
- Tidak punya akses tenant/role: `403`

## Contract Notes (2026-04-25)

- Tidak ada path baru/diubah.
- Perubahan bersifat additive pada payload `GET /v1/hcm/dashboard-summary` melalui `legacyWidgets` untuk memastikan data kartu runtime bukan static HTML.
