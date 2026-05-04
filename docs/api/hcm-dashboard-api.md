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
    "profile": {
      "name": "Budi Santoso",
      "email": "bs3@d.id",
      "designation": "Staff",
      "team": "General",
      "phone": "08123456789",
      "joinDate": "2026-01-10",
      "reportOffice": "-",
      "profilePhotoUrl": "/storage/avatars/35/example.jpg",
      "greeting": "Selamat pagi"
    },
    "attendanceToday": {
      "nowLabel": "08:35, 04 May 2026",
      "progressPercent": 65,
      "productionHours": 5.75,
      "punchInAt": "08:01",
      "punchOutAt": "-",
      "punchState": "in",
      "canPunch": true,
      "needsReview": false,
      "summaryTotalWorking": "5h 45m",
      "summaryProductive": "5h 15m",
      "summaryBreak": "30m",
      "summaryOvertime": "-",
      "checkInLatitude": -6.2,
      "checkInLongitude": 106.8,
      "checkOutLatitude": null,
      "checkOutLongitude": null
    },
    "attendanceStats": {
      "todayHours": 5.75,
      "todayTarget": 8,
      "weekHours": 32,
      "weekTarget": 40,
      "monthHours": 84,
      "monthTarget": 98,
      "monthOvertimeHours": 6,
      "monthOvertimeTarget": 28
    },
    "leave": {
      "total": 4,
      "pending": 1,
      "approved": 2,
      "declined": 1
    },
    "overtime": {
      "pending": 0,
      "approvedThisMonth": 2,
      "approvedHoursThisMonth": 6
    },
    "payroll": {
      "latestPeriod": "04/2026",
      "latestRunStatus": "finalized",
      "paymentStatus": "paid",
      "latestNetPay": 4500000
    },
    "ui": {
      "referenceDate": "2026-05-04",
      "referenceYear": 2026,
      "isCurrentDay": true
    }
  }
}
```

Catatan kontrak runtime:

- `profile.profilePhotoUrl` bersifat nullable dan dipakai dashboard employee untuk sinkron avatar dengan foto profil terbaru.
- Tombol GPS di card attendance menggunakan koordinat browser untuk preview lokasi, tetapi source of truth attendance tetap berasal dari `attendanceToday.checkIn*` / `checkOut*`.

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

## Contract Notes (2026-05-04)

- Payload `GET /v1/hcm/employee-dashboard-summary` kini mendokumentasikan `profile.profilePhotoUrl` (nullable) untuk sinkron avatar card employee dashboard.
- Tidak ada perubahan path atau authorization flow.
