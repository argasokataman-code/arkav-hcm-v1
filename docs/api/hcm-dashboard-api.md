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

### 3. Global HRMS search catalog

`GET /v1/hcm/search?q=<keyword>&limit=<1..50>`

Tujuan:

- Menyediakan katalog navigasi HRMS yang bisa dicari dari input global di header/sidebar.
- Hasil sudah difilter server-side sesuai konteks user:
  - route khusus global admin tidak muncul untuk user non-global
  - route employee self-service tetap tersedia untuk user employee
  - route lain mengikuti permission context tenant aktif

Response envelope:

```json
{
  "success": true,
  "data": {
    "query": "ticket",
    "total": 3,
    "limit": 8,
    "items": [
      {
        "routeName": "tickets-admin",
        "section": "Tiket & dukungan",
        "label": "Tiket (admin)",
        "description": "Antrian & SLA.",
        "path": "/tickets-admin",
        "href": "https://<host>/tickets-admin"
      }
    ]
  }
}
```

Validasi query:

- `q`: required, string, min 1, max 120
- `limit`: optional, integer, min 1, max 50 (default 8)

## Negative Scenario

- Token tidak valid: `401`
- Tidak punya akses tenant/role: `403`

## Contract Notes (2026-04-25)

- Tidak ada path baru/diubah.
- Perubahan bersifat additive pada payload `GET /v1/hcm/dashboard-summary` melalui `legacyWidgets` untuk memastikan data kartu runtime bukan static HTML.

## Contract Notes (2026-05-01)

- Path baru additive: `GET /v1/hcm/search`.
- Tidak mengubah path atau struktur endpoint dashboard existing.
