# Feature Integration Map

Dokumen ini menjadi peta cepat integrasi antar fitur agar setiap `docs/features/<feature>/README.md` bisa menunjuk ke sumber yang sama saat menjelaskan hubungan UI, API, dan alur bisnis lintas modul.

## Cara Baca

- **Sumber**: fitur yang menghasilkan data, status, atau guard.
- **Tujuan**: fitur yang mengonsumsi data atau bergantung pada status itu.
- **Rute/API aktif**: halaman atau endpoint yang menjadi titik sentuh runtime.
- **Dokumentasi terkait**: README fitur yang perlu dibaca bersama.

## Integrasi Inti HCM

| Sumber | Tujuan | Rute/API aktif | Dokumentasi terkait |
|---|---|---|---|
| Identity & Auth | Semua halaman protected | `/login`, `/register`, `/trial`, `POST /v1/identity/auth/login`, `GET /v1/identity/auth/me` | `identity-auth/`, `landing-pages/`, `subscriptions/` |
| Employees & Organization | Attendance, Overtime, Leave, Promotion, Termination, Employee Salary, Payroll | `/employees`, `GET /v1/hcm/employees`, `PUT /v1/hcm/employees/{id}` | `employees-organization/`, `attendance-shift-schedule/`, `overtime/`, `leave-and-holidays/`, `promotion/`, `termination/`, `employee-salary/`, `payroll-runs/` |
| Attendance & Shift | Leave, Reporting, Performance | `/attendance`, `/attendance-employee`, `GET /v1/hcm/attendance/me/*`, `GET /v1/hcm/timesheets` | `attendance-shift-schedule/`, `leave-and-holidays/`, `reporting/`, `performance/` |
| Leave & Holidays | Attendance, Performance | `/leaves`, `/leaves-employee`, `POST /v1/hcm/leave-requests`, `PUT /v1/hcm/leave-requests/{id}` | `leave-and-holidays/`, `attendance-shift-schedule/`, `performance/` |
| Overtime | Employee Salary, Payroll Components, Payroll Runs | `/overtime`, `/overtime-employee`, `POST /v1/hcm/overtime-requests/calculate`, `GET /v1/hcm/overtime-requests` | `overtime/`, `employee-salary/`, `payroll-salary-components/`, `payroll-runs/` |
| Payroll Salary Components | Payroll Items, Overtime | `/salary-component-master`, `GET /v1/hcm/salary-components` | `payroll-salary-components/`, `payroll-items/`, `overtime/` |
| Payroll Items | Employee Salary, Payroll Runs | `/payroll`, `GET /v1/hcm/payroll-items`, `GET /v1/hcm/payroll-item-assignments` | `payroll-items/`, `employee-salary/`, `payroll-runs/` |
| Employee Salary | Overtime, Payroll Runs | `/employee-salary`, `GET /v1/hcm/employees`, `PUT /v1/hcm/employees/{id}` | `employee-salary/`, `overtime/`, `payroll-runs/` |
| Payroll Runs | Export Reconciliation, Reporting, Employee self-service slip | `/payroll-run`, `/payroll-run-history`, `POST /v1/hcm/payroll-periods/{id}/calculate-draft`, `POST /v1/hcm/payroll-runs/{id}/disburse`, `POST /v1/hcm/payroll/send-slips` | `payroll-runs/`, `export-reconciliation/`, `reporting/` |
| Promotion | Employees & Organization, Employee Salary, Reporting | `/promotion`, `GET/POST /v1/hcm/promotions` | `promotion/`, `employees-organization/`, `employee-salary/`, `reporting/` |
| Resignation | Employees & Organization, Reporting | `/resignation`, `GET/POST /v1/hcm/resignations` | `resignation/`, `employees-organization/`, `reporting/` |
| Termination | Asset Management, Payroll Runs, Reporting | `/termination`, `GET /v1/hcm/terminations/settlement-preview`, `POST /v1/hcm/terminations/{id}/clearance-items/{assignmentId}/return` | `termination/`, `asset-management/`, `payroll-runs/`, `reporting/` |
| Asset Management | Termination, Tickets | `/assets`, `/asset-categories`, `POST /v1/hcm/assets/{asset}/issue-report` | `asset-management/`, `termination/`, `tickets/` |
| Tickets | Asset Management | `/tickets`, `POST /v1/hcm/tickets`, `POST /v1/hcm/assets/{asset}/issue-report` | `tickets/`, `asset-management/` |
| User Management | Semua modul HCM admin | `/users`, `/roles-permission`, `/user-management`, `/v1/hcm/user-management/*` | `user-management/`, `identity-auth/`, `super-admin-dashboard/` |

## Integrasi SaaS / Billing

| Sumber | Tujuan | Rute/API aktif | Dokumentasi terkait |
|---|---|---|---|
| Landing Pages | Trial onboarding, Packages, Identity gate | `/landing`, `/trial`, `/register` | `landing-pages/`, `packages/`, `identity-auth/` |
| Packages | Subscriptions, Trial onboarding | `/packages`, `/saas/packages`, `GET /v1/saas/packages`, `POST /v1/public/onboarding` | `packages/`, `subscriptions/`, `landing-pages/` |
| Subscriptions | Purchase Transactions, Invoices, Trial & Billing Dashboard | `/subscription`, `/saas/subscriptions`, `POST /v1/saas/subscriptions/{subscription}/renew` | `subscriptions/`, `purchase-transaction/`, `trial-billing-dashboard/` |
| Purchase Transactions | Reporting, Super Admin Dashboard | `/purchase-transaction`, `/saas/transactions`, `GET /v1/saas/transactions` | `purchase-transaction/`, `reporting/`, `super-admin-dashboard/` |
| Export Reconciliation | Payroll Runs, Purchase Transactions, Trial & Billing Dashboard | `GET/POST /v1/reconciliation/exports`, gated action endpoints finansial | `export-reconciliation/`, `payroll-runs/`, `purchase-transaction/`, `trial-billing-dashboard/` |
| Domain Management | Subscriptions, Trial & Billing Dashboard | `/domain`, `/saas/domains`, `GET /v1/saas/domains` | `domain-management/`, `subscriptions/`, `trial-billing-dashboard/` |
| Reporting | Super Admin Dashboard, Purchase Transactions, Payroll | `/saas/reports`, `/reports`, `/v1/saas/reports/*`, `/v1/hcm/reports/snapshots/*` | `reporting/`, `super-admin-dashboard/`, `purchase-transaction/`, `payroll-runs/` |
| Trial & Billing Dashboard | Subscriptions, Invoices, Payments, Reporting | `/saas/billing-overview`, `/company/invoices`, related SaaS billing APIs | `trial-billing-dashboard/`, `subscriptions/`, `purchase-transaction/`, `reporting/` |
| Super Admin Dashboard | Reporting, Packages, Subscriptions, Transactions | `/dashboard`, `/saas-dashboard`, `/v1/saas/dashboard/*` | `super-admin-dashboard/`, `reporting/`, `packages/`, `subscriptions/`, `purchase-transaction/` |

## Aturan Sinkronisasi Dokumentasi

- Jika README fitur menyebut modul lain sebagai sumber data atau guard, tambahkan cross-link ke README fitur terkait.
- Jika route atau endpoint menjadi titik integrasi utama, tulis rute aktifnya secara eksplisit di section `Integrasi` README masing-masing.
- Jika kontrak API berubah, sinkronkan juga ke `docs/api/openapi.yaml` dan dokumen API fitur terkait.