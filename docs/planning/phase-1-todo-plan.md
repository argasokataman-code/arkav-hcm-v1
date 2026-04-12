# Phase 1 TODO Plan

TODO ini diturunkan dari `../architecture/feature-flowchart.md` untuk acuan pengerjaan feature secara berurutan.
Dokumen ini saat ini dieksekusi dengan pendekatan single Laravel API (monolith backend) + frontend existing Node/Vite.

## Status implementasi saat ini (real code audit)

Legend:
- `DONE` = sudah berjalan di kode saat ini
- `IN_PROGRESS` = sebagian jalan / perlu hardening atau selaras spec
- `NOT_STARTED` = belum ada atau hanya placeholder UI

Ringkasan (April 2026):
- **Backend:** identity + HCM luas (employees, departments, designations, policies, holidays, leave-requests, leave-settings, overtime, attendance, timesheets, schedule-timing, **shifts**, **overtime-types**), `/health`.
- **Frontend:** login + guard token; wiring data untuk employees, attendance, leave-settings, shift master, overtime master, dan modul HCM terkait (lihat `planning/implementation-status.md`).
- **Path API:** mayoritas fitur leave/attendance berada di **`/v1/hcm/*`**, bukan `/v1/leave/*` (dokumen lawas/OpenAPI mungkin belum sinkron).
- **API docs:** Swagger UI tersedia di `GET /api-docs` (spec file: `docs/api/openapi.yaml`).

Update readiness terakhir:
- Struktur project: `backend/` + `frontend/`.
- Satu Laravel app; domain logical tetap terpisah di dokumentasi.
- Frontend: template existing + bundle Vite dari `frontend/resources/`.

## Dependency ringkas

1. Auth API + Auth Guard
2. Department/Designation master data
3. Employee feature
4. Leave feature
5. Dashboard summary wiring
6. Final hardening

## Milestone 1 - Authentication Foundation (High)

- [x] Setup Identity Service endpoint (`register/login/logout/me`) - `DONE`
- [x] Implement token/session handling di frontend - `DONE` (`localStorage` + `api-client.js` / `AuthApi`)
- [x] Implement auth guard untuk protected routes - `DONE` (middleware `api.token` pada route HCM)
- [x] Build login/register/logout flow - `DONE` (Laravel + halaman template)
- [x] Define auth error contract - `DONE`:
  - [x] `AUTH_INVALID_CREDENTIALS`
  - [x] `AUTH_UNAUTHORIZED`
  - [x] `AUTH_FORBIDDEN`

## Milestone 2 - Master Data Foundation (Medium)

- [x] Setup Core HCM endpoint department (`GET/POST /departments`) - `DONE`
- [x] Setup Core HCM endpoint designation (`GET/POST /designations`) - `DONE`
- [ ] Setup update endpoint:
  - [x] `PUT /departments/{id}` - `DONE`
  - [x] `PUT /designations/{id}` - `DONE`
- [x] Build UI department/designation (list/add/edit) - `DONE` (template + `hcm-pages-data.js` / alur terkait)
- [ ] Define service ownership - `IN_PROGRESS`:
  - [x] mutasi department/designation hanya dari Core HCM Service
  - [ ] frontend tidak mengakses database langsung

## Milestone 3 - Employee Feature (High)

- [x] Setup employee endpoint (`GET list`, `GET detail`, `POST create`) - `DONE`
- [x] Build employees page (list/detail/create) - `DONE` (`employees-data.js` + template)
- [x] Wiring modal add/edit employee ke API create/update termasuk field kompensasi salary - `DONE`
- [x] Bulk upload full detail employee + template Excel untuk admin (create/update massal) - `DONE`
- [x] Validasi form dan error state - `DONE`
- [x] Tambahkan pagination + filter contract pada `GET /employees` - `DONE`
- [x] Tambah komponen kompensasi employee (`baseSalary`, `fixedAllowance`) untuk integrasi kalkulator lembur - `DONE`
- [x] Dokumentasi use case employee + matriks hak akses (`docs/features/employees-organization/USE-CASES.md`) - `DONE`
- [x] Selaraskan API/UI employee dengan USE-CASES (admin-only list/mutasi; self-read/update terbatas) - `DONE` (Apr 2026: `HcmEmployeeController`, redirect UI, `HcmEmployeeApiTest`)

## Milestone 4 - Leave Feature (High)

- [x] Setup endpoint leave (path aktual: `/v1/hcm/leave-requests`, `/v1/hcm/leave-settings`, dll.) - `DONE`
- [x] Attendance & timesheet & schedule-timing (di `/v1/hcm/*`) - `DONE`
- [x] Shift master + relasi jadwal (`hcm_shifts`, `hcm_shift_id` pada schedule timing) - `DONE`
- [x] Overtime master + relasi request (`hcm_overtime_types`, `hcm_overtime_type_id` opsional) - `DONE`
- [x] Build leave settings UI - `DONE` (`leave-settings-data.js`)
- [x] Holidays baseline sync Indonesia + admin override manual (`POST /holidays/sync-indonesia`, source `api|manual`) - `DONE`
- [x] Build leave **submission/approval** inti (list admin vs self, modal CRUD, approve/decline hanya **hcmAdmin** di API + redirect `/leaves` → `/leaves-employee` bila bukan admin) - `DONE` (Apr 2026); **sisa:** `/leave-report` agregat, kartu ringkasan template, `/leave-type` vs `/leave-settings`, OpenAPI `/v1/leave` lawas
- [x] Link leave data ke user/employee context - `DONE` (sesuai model migrasi `leave_requests` berbasis `user_id`)
- [x] Status leave pada data: `pending` dan seterusnya - `DONE` (sesuai implementasi controller)

## Milestone 4B - Tickets Module (High)

- [x] Setup endpoint ticket (`/v1/hcm/tickets*`) + RBAC employee own scope vs admin all scope - `DONE`
- [x] Build UI tickets list/grid/detail berbasis API (hapus dummy template) - `DONE`
- [x] Ticket advanced workflow: status lengkap, SLA due date, assignee/reassign history, comment timeline, attachment upload/download - `DONE`
- [x] Feature tests ticket (own CRUD, forbidden, admin manage, validation attachment) - `DONE` (`TicketApiTest`)
- [x] Dokumentasi tickets + matriks akses aktif HCM - `DONE`

## Milestone 4C - Performance Module (High)

- [x] Setup schema `performance_*` (cycles, templates/items, reviews, scores) - `DONE`
- [x] Setup endpoint Performance (`/v1/hcm/performance/*`) dengan RBAC employee/manager/admin - `DONE`
- [x] Build UI shell Performance (hapus dummy template) + wiring `performance-data.js` - `DONE`
- [x] Feature tests alur employee→manager→admin + forbidden admin-only - `DONE` (`PerformanceApiTest`)
- [x] Dokumentasi Performance (feature README + matriks akses aktif) - `DONE` (`docs/features/performance/README.md`, matriks HCM)

## Milestone 4D - Goal Tracking Module (Medium)

- [x] Setup schema goals: `performance_goal_types`, `performance_goals` - `DONE`
- [x] Setup endpoint Goal Tracking (`/v1/hcm/performance/goal-types`, `/v1/hcm/performance/goals`) + RBAC scope `me/team/all` - `DONE`
- [x] Build UI shell Goal Type + Goal Tracking (hapus dummy template) + wiring `goal-data.js` - `DONE`
- [x] Feature tests goals + goal types + forbidden cases - `DONE` (`GoalTrackingApiTest`)
- [x] Dokumentasi Goal Tracking (feature README + matriks akses aktif) - `DONE` (`docs/features/goal-tracking/README.md`, matriks HCM)

## Milestone 4D - Goal Tracking Module (High)

- [x] Setup schema goals: `performance_goal_types`, `performance_goals` - `DONE`
- [x] Setup endpoint Goal Tracking (`/v1/hcm/performance/goal-types`, `/v1/hcm/performance/goals`) + RBAC scope `me/team/all` - `DONE`
- [x] Build UI shell Goal Tracking (hapus dummy template) + wiring `goal-data.js` - `DONE`
- [x] Feature tests goal tracking (happy path + forbidden) - `DONE` (`GoalTrackingApiTest`)
- [x] Dokumentasi Goal Tracking + matriks akses aktif - `DONE` (`docs/features/goal-tracking/README.md`, matriks HCM)

## Milestone 4E - Training Module (Medium)

- [x] Setup schema training: `hcm_training_types`, `hcm_trainings`, `hcm_training_participants` - `DONE`
- [x] Setup endpoint Training (`/v1/hcm/training/types`, `/v1/hcm/training/trainings`) + RBAC admin-only untuk trainings - `DONE`
- [x] Build UI shell Training Type + Training (hapus dummy template) + wiring `training-data.js` - `DONE`
- [x] Feature tests training types + trainings + forbidden cases - `DONE` (`TrainingApiTest`)
- [x] Dokumentasi Training (feature README + matriks akses aktif) - `DONE` (`docs/features/training/README.md`, matriks HCM)

## Milestone 4F - Promotion Module (Medium)

- [x] Setup schema promotion: `hcm_promotions` - `DONE`
- [x] Setup endpoint Promotion (`/v1/hcm/promotions`) + RBAC admin-only - `DONE`
- [x] Build UI shell Promotion (hapus dummy template) + wiring `promotion-data.js` - `DONE`
- [x] Feature tests promotion (happy path + forbidden) - `DONE` (`PromotionApiTest`)
- [x] Dokumentasi Promotion (feature README + API spec + OpenAPI) - `DONE`

## Milestone 4G - Resignation Module (Medium)

- [x] Setup schema resignation: `hcm_resignations` - `DONE`
- [x] Setup endpoint Resignation (`/v1/hcm/resignations`) + RBAC (admin mutasi/list; karyawan self show + per-user list) - `DONE`
- [x] Build UI shell Resignation (hapus dummy template) + wiring `resignation-data.js` + section employee detail - `DONE`
- [x] Feature tests resignation (happy path + forbidden) - `DONE` (`ResignationApiTest`)
- [x] Dokumentasi Resignation (feature README + API spec + OpenAPI + matriks akses) - `DONE`

## Milestone 4H - Termination Module (Medium)

- [x] Setup schema termination: `hcm_terminations` - `DONE`
- [x] Setup endpoint Termination (`/v1/hcm/terminations`) + RBAC (admin mutasi/list; karyawan self show + per-user list) - `DONE`
- [x] Build UI shell Termination (hapus dummy template + modal theme) + wiring `termination-data.js` + section employee detail - `DONE`
- [x] Feature tests termination - `DONE` (`TerminationApiTest`)
- [x] Dokumentasi Termination + OpenAPI + matriks akses - `DONE`

## Milestone 5 - Dashboard Summary Wiring (High)

- [ ] Build dashboard summary cards (active employees, pending leave, departments) - `NOT_STARTED`
- [ ] Add welcome message dari `/auth/me` - `NOT_STARTED`
- [ ] Add quick navigation ke employee dan leave pages - `NOT_STARTED`
- [ ] Handle loading/empty/error states - `NOT_STARTED`
- [ ] Pastikan dashboard consume API, bukan query data langsung - `NOT_STARTED`

## Milestone 6 - Final Hardening and QA (Medium)

- [ ] Route cleanup + penyelarasan dokumentasi (`/v1/leave` vs `/v1/hcm`) - `IN_PROGRESS`
- [ ] API error contract konsisten (`code`, `message`, `traceId`) - `IN_PROGRESS`
- [x] Basic `/health` di backend - `DONE`
- [x] Sanitasi dependency URL eksternal pada halaman aktif + template demo berisiko (Blade + frontend asset wiring) - `DONE`
- [x] Migrasi auth frontend HCM dari token `localStorage` ke cookie HttpOnly untuk modul data utama (attendance, leave-settings, employees, overtime, shifts, departments/designations/policies) - `DONE`
- [ ] Correlation ID / `X-Trace-Id` propagation - `NOT_STARTED`
- [ ] Regression test menyeluruh (auth + HCM + attendance + shifts + overtime types) - `IN_PROGRESS` (ada subset feature tests)

## Dokumentasi role & template aktif (lintas fitur)

- [x] Indeks halaman HCM aktif + target role/API: `planning/active-hcm-templates-and-permissions.md` - `DONE`
- [ ] Setiap PR fitur HCM: jaga matriks §3 tetap akurat (otomatis lewat proses review + rule Cursor `role-permissions-with-features`) - `ONGOING`
- [x] Master komponen gaji (`hcm_salary_components`, `/v1/hcm/salary-components`, halaman `/salary-component-master`) — `DONE` (April 2026)

## Next eksekusi prioritas (langsung teknis)

1. Menyelaraskan `docs/api/openapi.yaml` dan bagian `/v1/leave/*` di spec markdown dengan route Laravel aktual.
2. Melengkapi dashboard summary cards + welcome dari `/auth/me` (Milestone 5).
3. Hardening: error envelope seragam, logging, `X-Trace-Id`.
4. Perluas automated tests untuk leave-requests flow dan attendance edge cases.
5. [x] Penautan overtime ↔ master komponen gaji (FK `hcm_salary_component_id`, field di list + `POST .../calculate`) — `DONE` (April 2026).
6. [x] Fondasi payroll periode: `hcm_payroll_periods` / `hcm_payroll_runs` / `hcm_payroll_lines` + `calculate-draft` + `finalize` + `GET /payroll/my-slip-lines` — `DONE` (April 2026). **Next:** agregasi lembur disetujui per periode ke baris slip; potongan % dari master komponen; wire UI `/payslip` + PDF.
