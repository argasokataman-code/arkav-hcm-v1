# API Specification - Phase 1 (Index + Global Conventions)

Dokumen ini adalah **index** + **aturan global** (regex, envelope error/success, mapping HTTP status) untuk Phase 1.

Detail kontrak endpoint **wajib** merujuk dokumen per fitur di `docs/api/` (agar audit tidak punya double source of truth).

## Feature specs (per menu/service)

- Identity: `docs/api/identity-api.md`
- Employees: `docs/api/hcm-employees-api.md`
- Training: `docs/api/hcm-training-api.md`
- Tickets: `docs/api/hcm-tickets-api.md`
- Master data (departments/designations/policies): `docs/api/hcm-masterdata-api.md`
- Shifts & schedule: `docs/api/hcm-shift-schedule-api.md`
- Holidays: `docs/api/hcm-holidays-api.md`
- Overtime: `docs/api/hcm-overtime-api.md`
- Leave: `docs/api/hcm-leave-api.md`
- Attendance (+ timesheets + schedule timing): `docs/api/hcm-attendance-api.md`
- Activity feed (`/activity` page): `docs/api/hcm-activity-api.md`
- Performance: `docs/api/hcm-performance-api.md`
- Promotion: `docs/api/hcm-promotion-api.md`
- Resignation: `docs/api/hcm-resignation-api.md`
- Termination: `docs/api/hcm-termination-api.md`
- Payroll (master komponen gaji): `docs/api/hcm-salary-components-api.md`
- Payroll (periode & run, draft/finalize, slip self, THR: kalkulator + pengaturan tahunan): `docs/api/hcm-payroll-api.md`
- Payroll items (halaman `/payroll`, CRUD katalog): `docs/api/hcm-payroll-items-api.md`
- User management (live API role-permission): `docs/api/user-management-api.md`

## 1) Global conventions

- Content-Type: `application/json` (kecuali upload `multipart/form-data`)
- Time format:
  - ISO-8601 untuk timestamp (contoh: `2026-04-07T10:15:00Z`)
  - `date` untuk tanggal (`YYYY-MM-DD`) jika endpoint memakai rule `date`
  - `H:i` untuk input jam kerja (schedule/shift)
- API versioning: prefix `/v1`
- Authentication: `Authorization: Bearer <token>` (atau cookie HttpOnly `arcav_access_token` untuk browser flow), enforced by middleware `api.token`
- Response envelope:
  - success: `{ success: true, data: ... }`
  - error: `{ success: false, error: ... }`

## 2) Regex patterns (shared)

Regex ini adalah sumber kebenaran untuk validasi parity FE/BE:

- `email`: `^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$`
- `password_strong`: `^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$`
- `name`: `^[A-Za-z][A-Za-z\s'.-]{1,149}$`
- `code_upper_snake`: `^[A-Z][A-Z0-9_]{1,49}$` (max 50 chars)
- `employee_no`: `^[A-Z0-9-]{3,50}$`
- `uuid_like_trace`: `^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$`
- `date_yyyy_mm_dd`: `^\d{4}-\d{2}-\d{2}$`
- `code_slug`: `^[a-z0-9_-]+$` (dipakai untuk field seperti `shift.code`, `overtimeTypes.code`)

## 3) Standard error handling

### Error response format

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": [
      { "field": "email", "rule": "validation", "message": "Email format is invalid" }
    ],
    "traceId": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

Catatan:
- `traceId` berasal dari middleware (request attribute) dan membantu audit/debug.

### HTTP status mapping

- `400` malformed request / bad query params
- `401` missing/invalid token
- `403` role tidak punya akses
- `404` resource not found
- `409` duplicate/conflict data
- `422` validation/domain rule error
- `429` too many requests
- `500` internal server error

## 4) Response success envelope

### Single resource

```json
{ "success": true, "data": {} }
```

### List resource with pagination (varies per feature)

```json
{
  "success": true,
  "data": [],
  "meta": { "page": 1, "perPage": 20, "total": 120 }
}
```

- **SaaS Modules**
  - Packages (subscription tiers, feature management): `docs/api/packages-api.md`
  - Subscriptions (company subscription management): `docs/api/subscriptions-api.md`
  - Purchase transactions (invoices, payments, revenue): `docs/api/purchase-transaction-api.md`
  - Domain management (custom domains, DNS, SSL): `docs/api/domain-management-api.md`
  - Super admin dashboard (analytics, KPIs): `docs/api/super-admin-dashboard-api.md`
- Companies: `docs/api/company-api.md`

## 1) Global conventions

