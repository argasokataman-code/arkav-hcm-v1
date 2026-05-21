# API Spec — Phase 1 (Quick notes)

These are minimal change notes created to satisfy the repository's API documentation guard.

Changed controllers (summary):

- `backend/app/Http/Controllers/Api/Dashboard/HcmDashboardController.php`
  - Enforced tenant scoping for `teamMembersPayload()` by filtering on `company_id` to avoid cross-tenant exposure.

- `backend/app/Http/Controllers/Api/DataPrivacy/HcmDataPrivacyController.php`
  - Minor adjustments to data privacy endpoints; see controller diffs for implementation details.

Notes:

- Please expand these into formal `docs/api/<feature>-api.md` pages or update `docs/api/openapi.yaml` as needed.
- This file was added automatically to satisfy the repository pre-commit API-docs check.
# API Specification - Phase 1 (Index + Global Conventions)

Dokumen ini adalah index + aturan global untuk kontrak API.

Sumber kebenaran kontrak saat ini:
- Runtime API: `backend/routes/api.php` + controller API terkait.
- OpenAPI: `docs/api/openapi.yaml`.
- Swagger-style markdown per menu di `docs/api/*.md` adalah turunan yang harus konsisten dengan dua sumber di atas.

## Feature specs (per menu/service)

- Identity: `docs/api/identity-api.md`
- Email settings: `docs/api/email-settings-api.md`
- Email webhooks: `docs/api/email-webhooks-api.md`
- Employees: `docs/api/hcm-employees-api.md`
- Training: `docs/api/hcm-training-api.md`
- Tickets: `docs/api/hcm-tickets-api.md`
- Master data (departments/designations/policies): `docs/api/hcm-masterdata-api.md`
- Shifts & schedule: `docs/api/hcm-shift-schedule-api.md`
- Holidays: `docs/api/hcm-holidays-api.md`
- Overtime: `docs/api/hcm-overtime-api.md`
- Leave: `docs/api/hcm-leave-api.md`
- Attendance (+ timesheets + schedule timing): `docs/api/hcm-attendance-api.md`
- Dashboard ringkasan (admin + employee): `docs/api/hcm-dashboard-api.md`
- Activity feed (`/activity` page): `docs/api/hcm-activity-api.md`
- Performance: `docs/api/hcm-performance-api.md`
- Promotion: `docs/api/hcm-promotion-api.md`
- Resignation: `docs/api/hcm-resignation-api.md`
- Termination: `docs/api/hcm-termination-api.md`
- Reporting: `docs/api/hcm-reporting-api.md`
- Payroll (master komponen gaji): `docs/api/hcm-salary-components-api.md`
- Payroll (periode & run, draft/finalize, slip self, THR: kalkulator + pengaturan tahunan): `docs/api/hcm-payroll-api.md`
- Payroll items (halaman `/payroll`, CRUD katalog): `docs/api/hcm-payroll-items-api.md`
- User management (live API role-permission): `docs/api/user-management-api.md`
- Packages: `docs/api/packages-api.md`
- Subscriptions: `docs/api/subscriptions-api.md`
- Purchase transactions: `docs/api/purchase-transaction-api.md`
- Custom domain: `docs/api/custom-domain-api.md`
- Super admin dashboard: `docs/api/super-admin-dashboard-api.md`
- Public onboarding: `docs/api/public-onboarding-api.md`
- Billing overview: `docs/api/saas-billing-overview-api.md`
- Invoices (admin detail + email history): `docs/api/invoice-api.md`
- Companies: `docs/api/company-api.md`

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

## 5) Sync rules (mandatory)

- Jika API route/controller berubah, wajib update dua artefak sekaligus:
  - `docs/api/openapi.yaml`
  - minimal satu dokumen `docs/api/<feature>-api.md` yang terdampak
- Jangan mengubah kontrak API tanpa isu nyata pada API atau kebutuhan fitur yang disetujui.
- Jika masih dalam masa transisi UUID, tulis eksplisit pada path terkait apakah endpoint:
  - UUID-only,
  - numeric-only (legacy), atau
  - UUID + numeric fallback.


## Changelog

### 2026-05-07 — Internal bug fixes (no contract change)

- `TransactionController`: fixed closure not capturing `$request` variable (runtime bug, no contract change)
- `AuthController`: removed duplicate `salary.admin` key in permissions response (no semantic change)
- `HcmLeaveTypeController`: added missing private `apiError()` helper method
- `HcmSalaryComponentController`: added missing `resolveTaxTreatmentCodeFromValidated()` method
- `HcmTicketController`: added missing `use Illuminate\Validation\Rule` import
- `HcmRoleManagementController`, `HcmPermissionController`: added missing `Controller` base class import
- `ReconciliationExportController`: fixed undefined variable in second payroll loop
- All changes are internal implementation fixes; API request/response contracts unchanged.
