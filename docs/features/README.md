# Feature Documentation Map

Dokumen per fitur dipisah agar tim cepat memahami flow end-to-end (UI, API, aturan bisnis, edge case, dan test).

**Template heading standar README fitur:** `Ringkasan` → `Akses` → `UI Aktif` → `Flow Bisnis End-to-End` → `Lifecycle Dan Keputusan Bisnis` → `Integrasi` → `Kontrak API` → `Existing Vs Target`. Jika sebuah fitur butuh section tambahan seperti `Status`, `Catatan QA`, atau `Test & Evidence`, letakkan setelah section inti agar evaluasi lintas modul tetap seragam.

**Role & permission per URL (lintas fitur):** `../planning/active-hcm-templates-and-permissions.md` — indeks halaman menu HCM yang aktif, modul JS, area API, dan target akses (HCM Admin vs karyawan). Update dokumen ini bersamaan saat menambah route/menu atau mengubah siapa yang boleh memanggil API.

**Siklus payroll (pre / actual / post):** `../planning/payroll-lifecycle.md`.

**Peta integrasi lintas fitur:** [INTEGRATION-MAP.md](INTEGRATION-MAP.md) — daftar sumber data, modul konsumen, route aktif, dan README yang harus dibaca bersama.

---

## 📋 Index Fitur dengan Status & Links

> **Rule:** Setiap fitur WAJIB punya folder `docs/features/<feature-name>/` dengan `README.md` + `IMPLEMENTATION.md` + docs lainnya.  
> Lihat: `../.cursor/rules/documentation-feature-packaging.mdc`

| # | Feature | Status | README | Implementation | Additional |
|---|---------|--------|--------|-----------------|------------|
| 1 | Identity & Auth | ✅ Complete | [→](identity-auth/) | [ID Details](identity-auth/) | Login, token, RBAC |
| 2 | Organization & Employees | ✅ Complete | [→](employees-organization/) | [ID Details](employees-organization/) | [USE-CASES](employees-organization/USE-CASES.md) |
| 3 | Attendance & Shift | ✅ Complete | [→](attendance-shift-schedule/) | [📘 Implementation](attendance-shift-schedule/IMPLEMENTATION.md) | [📍 Attendance Core](attendance/README.md), [🤳 Attendance Selfie](attendance-selfie/README.md), [🧭 Tracker](attendance-shift-schedule/tracker.md) |
| 4 | Overtime | ✅ Complete | [→](overtime/) | [ID Details](overtime/) | PP 35/2021 calculator |
| 5 | Leave & Holidays | ✅ Complete | [→](leave-and-holidays/) | [ID Details](leave-and-holidays/) | Request workflow |
| 6 | Company Policies | ✅ Complete | [→](policies/) | [ID Details](policies/) | Policy CRUD |
| 7 | Ticketing | ✅ Complete | [→](tickets/) | [ID Details](tickets/) | SLA, assignment |
| 8 | Performance Review | ✅ Complete | [→](performance/) | [ID Details](performance/) | Cycles, indicators |
| 9 | Goal Tracking | ✅ Complete | [→](goal-tracking/) | [ID Details](goal-tracking/) | Goal types |
| 10 | Training | ✅ Complete | [→](training/) | [ID Details](training/) | Training types |
| 11 | Promotion | ✅ Complete | [→](promotion/) | [ID Details](promotion/) | Records (admin) |
| 12 | Resignation | ✅ Complete | [→](resignation/) | [ID Details](resignation/) | Mutasi process |
| 13 | Termination | ✅ Complete | [→](termination/) | [ID Details](termination/) | Termination records |
| 14 | Payroll Components | ✅ Complete | [→](payroll-salary-components/) | [ID Details](payroll-salary-components/) | Master components |
| 15 | Payroll Runs | ✅ Complete | [→](payroll-runs/) | [ID Details](payroll-runs/) | Slip lines, periods |
| 16 | Payroll Items | ✅ Complete | [→](payroll-items/) | [ID Details](payroll-items/) | Custom items |
| 17 | Employee Salary | ✅ Complete | [→](employee-salary/) | [📘 Implementation](employee-salary/IMPLEMENTATION.md) | [🧭 Tracker](employee-salary/tracker.md), [🧪 E2E Testing](employee-salary/E2E-TESTING.md) |
| 18 | Payslip | ✅ Complete | [→](payslip/) | [📘 Implementation](payslip/IMPLEMENTATION.md) | [🧭 Tracker](payslip/tracker.md), self-service slip bulanan + PDF |
| 19 | Payroll THR | ✅ Complete | [→](payroll-thr/) | [📘 Implementation](payroll-thr/IMPLEMENTATION.md) | [🧭 Tracker](payroll-thr/tracker.md), yearly setup + batch pay/post |
| 20 | Payroll PKWT Compensation | ✅ Complete | [→](payroll-pkwt-compensation/) | [📘 Implementation](payroll-pkwt-compensation/IMPLEMENTATION.md) | [🧭 Tracker](payroll-pkwt-compensation/tracker.md), preview + standalone payroll |
| 21 | **Purchase Transactions** | ✅ Complete | [→](purchase-transaction/) | [📘 Implementation](purchase-transaction/IMPLEMENTATION.md) | [🧪 E2E Testing](purchase-transaction/E2E-TESTING.md) |
| 22 | **Packages** | ✅ Complete | [→](packages/) | [📘 Implementation](packages/IMPLEMENTATION.md) | [🧪 E2E Testing](packages/E2E-TESTING.md) |
| 23 | **Domain Management** | ✅ Complete | [→](domain-management/) | [📘 Implementation](domain-management/IMPLEMENTATION.md) | [🧪 E2E Testing](domain-management/E2E-TESTING.md) |
| 24 | **Asset Management** | 🚧 In Progress | [→](asset-management/) | [📘 Implementation](asset-management/IMPLEMENTATION.md) | [🧪 E2E Testing](asset-management/E2E-TESTING.md) |
| 25 | **Subscriptions** | ✅ Complete | [→](subscriptions/) | [📘 Implementation](subscriptions/IMPLEMENTATION.md) | [🧪 E2E Testing](subscriptions/E2E-TESTING.md) |
| 26 | **Super Admin Dashboard** | 🚧 In Progress | [→](super-admin-dashboard/) | [📘 Implementation](super-admin-dashboard/IMPLEMENTATION.md) | [🧭 Tracker](super-admin-dashboard/tracker.md), global SaaS analytics |
| 27 | Tenant Management | 🚧 Planning | [→](user\ tenant\ management/) | - | Blueprint & roadmap |
| 28 | User Management | ✅ Implemented (Backend API) | [→](user-management/) | [📘 Implementation](user-management/IMPLEMENTATION.md) | [🧭 Use Cases](user-management/USE-CASES.md), [🧪 E2E Testing](user-management/E2E-TESTING.md) |
| 29 | Reporting System | 🚧 In Progress (Phase 3) | [→](reporting/) | [📘 Implementation](reporting/IMPLEMENTATION.md) | [🧪 E2E Testing](reporting/E2E-TESTING.md) |
| 30 | Locations / Wilayah Sync | ✅ Complete | [→](locations/) | [📘 Implementation](locations/IMPLEMENTATION.md) | [🧪 E2E Testing](locations/E2E-TESTING.md) |
| 31 | Recovery Vault | 🚧 Proposed | [→](recovery-vault/) | [📘 Implementation](recovery-vault/IMPLEMENTATION.md) | [🧪 E2E Testing](recovery-vault/E2E-TESTING.md) |
| 32 | Cronjob Scheduler | ✅ Complete | [→](cronjob/) | [📘 Implementation](cronjob/IMPLEMENTATION.md) | [🧪 E2E Testing](cronjob/E2E-TESTING.md) |
| 33 | Export Reconciliation | 🚧 Planning | [→](export-reconciliation/) | [📘 Implementation](export-reconciliation/IMPLEMENTATION.md) | [🧪 E2E Testing](export-reconciliation/E2E-TESTING.md) |
| 34 | Knowledge Base (help) | ✅ Complete | [→](knowledgebase/) | [📘 Implementation](knowledgebase/IMPLEMENTATION.md) | Config-driven `/knowledgebase` |
| 35 | Landing Pages (Marketing) | 🚧 Planning | [→](landing-pages/) | [📘 Implementation](landing-pages/IMPLEMENTATION.md) | [🧪 E2E Testing](landing-pages/E2E-TESTING.md) |
| 36 | Trial & Billing Dashboard | 🚧 Planning | [→](trial-billing-dashboard/) | [📘 Implementation](trial-billing-dashboard/IMPLEMENTATION.md) | [🧪 E2E Testing](trial-billing-dashboard/E2E-TESTING.md) |
| 37 | Mock Payment (Dev) | ✅ Dev Ready | [→](mock-payment/) | [📘 Implementation](mock-payment/IMPLEMENTATION.md) | [🧭 Tracker](mock-payment/tracker.md), [⚡ Quick Guide](../MOCK-PAYMENT-GATEWAY-GUIDE.md) |

---

## 📚 Daftar Fitur (Legacy - migrated ke table di atas)

- `identity-auth/README.md` - Login, register, token, auth guard, dan role hint.
- `employees-organization/README.md` - Employees, departments, designations, dan data organisasi.
- `employees-organization/USE-CASES.md` - Use case & matriks hak akses employee (admin vs self); acuan sebelum mengunci RBAC di API/UI.
- `attendance/README.md` - Attendance core: `/attendance-employee`, `/attendance-admin`, `/attendance-report`, GPS/location, correction, dan archive/live report behavior.
- `attendance/IMPLEMENTATION.md` - Controller, tenant scope, location storage, report integration, dan regression surface attendance.
- `attendance/tracker.md` - Snapshot status, evidence test, dan gap aktif attendance core.
- `attendance-selfie/README.md` - Attendance selfie: camera modal, upload, hash integrity, admin download, dan guard tenant.
- `attendance-selfie/IMPLEMENTATION.md` - Route, storage, runtime browser flow, security notes, dan coverage test selfie.
- `attendance-selfie/tracker.md` - Snapshot status, evidence, dan gap aktif untuk menu selfie attendance.
- `attendance-shift-schedule/README.md` - Shift master, schedule timing, timesheets admin, dan kaitannya ke attendance/reporting.
- `attendance-shift-schedule/IMPLEMENTATION.md` - Identifier contract, tenant guard, data model, dan validasi negative path shift/schedule.
- `attendance-shift-schedule/tracker.md` - Snapshot status, evidence runtime, dan gap aktif shift/schedule.
- `overtime/README.md` - Master overtime type, overtime request, kalkulator PP 35/2021, dan policy negatif.
- `leave-and-holidays/README.md` - Leave requests, leave settings, holidays.
- `policies/README.md` - Company policy CRUD dan relasi attachment/payload.
- `tickets/README.md` - Ticketing end-to-end: list/grid/detail, comments, attachment, SLA, assignment history, RBAC.
- `performance/README.md` - Performance Phase 1: cycles, indicator templates/items, review workflow employee→manager→admin.
- `goal-tracking/README.md` - Goal Tracking Phase 1: goal types (admin) + goals scope me/team/all, export CSV.
- `training/README.md` - Training Phase 1: training types, trainers, trainings, participants, dan tenant/RBAC guard.
- `promotion/README.md` - Promotion Phase 1: promotion records (admin-only), list + modal CRUD.
- `resignation/README.md` - Resignation Phase 1: resignation records (admin-only list/mutasi), self read + employee detail section.
- `termination/README.md` - Termination Phase 1: termination records (type + dates + reason), pola sama resignation.
- `asset-management/README.md` - Asset Management tenant-scoped: category, asset master, assignment history, attachment, issue reporting.
- `payroll-salary-components/README.md` - Master komponen gaji (Indonesia-oriented flags) + API `/salary-components`.
- `payroll-runs/README.md` - Periode payroll, run draft/final, baris slip (Phase 1) + API `/payroll-periods`, `/payroll-runs`, `/payroll/my-slip-lines`.
- `payroll-items/README.md` - Halaman `/payroll` (Payroll Items): CRUD `hcm_payroll_items` (kustom / taut master).
- `payslip/README.md` - Halaman `/payslip` (self-service employee): fallback ke periode final terbaru, agregasi monthly + THR + PKWT, dan unduhan PDF.
- `payroll-thr/README.md` - Halaman `/payroll-thr`: pengaturan tahunan THR, batch generate/disburse/post-payroll, slip THR PDF, dan gate reconciliation.
- `payroll-pkwt-compensation/README.md` - Halaman `/payroll-pkwt-compensation`: preview kompensasi kontrak berakhir, generate standalone payroll, dan flow pembayaran.
- `employee-salary/README.md` - Halaman gaji karyawan (baseSalary / fixedAllowance) + integrasi lembur & komponen.
- `employee-salary/IMPLEMENTATION.md` - Detail route, API contract, role guard, dan alur submit kompensasi.
- `employee-salary/tracker.md` - Snapshot readiness, evidence runtime, dan gap governance untuk surface kompensasi karyawan.
- `employee-salary/E2E-TESTING.md` - Skenario browser E2E admin vs non-admin (Playwright).
- `purchase-transaction/README.md` - **SaaS Billing:** Invoice management, payment tracking, payment reminders, financial reports (Revenue/Aging/Churn), admin + company dashboards.
- `packages/README.md` - **SaaS Packages:** package CRUD, add-on catalog, status/search/pagination, dan assignment fitur per package (admin mutation + non-admin guard).
- `domain-management/README.md` - **SaaS Domain Management:** domain CRUD admin, verification details, manual verify flow, dan status monitoring baseline.
- `subscriptions/README.md` - **SaaS Subscriptions:** lifecycle subscription company ke package, renewal, status/cycle filters, dan admin guard.
- `user-management/README.md` - **User Management:** backend API live untuk user-role-permission (filter/pagination/CRUD/export/assignment) + baseline schema & audit.
- `user-management/USE-CASES.md` - Use case akses dua layer: role company (tenant membership) vs role-permission aplikasi (RBAC), termasuk skenario employee menjadi admin via role assignment.
- `reporting/README.md` - **Reporting System:** snapshot-based reporting (API `/v1/hcm/reports/snapshots`) + mode Live/Archive pada report pages.
- `reporting/IMPLEMENTATION.md` - Arsitektur teknis reporting snapshot, export generator, dan kontrak storage.
- `reporting/E2E-TESTING.md` - Skenario manual UI/API untuk validasi live/archive + export.
- `locations/README.md` - **Locations / Wilayah Sync:** lokasi Indonesia dari `wilayah.id` disimpan lokal, dipakai halaman Provinces / Regencies / Districts.
- `locations/IMPLEMENTATION.md` - Arsitektur tabel `wilayah_*`, command `wilayah:sync`, scheduler bulanan, dan alur pruning/upsert.
- `locations/E2E-TESTING.md` - Skenario smoke UI untuk verifikasi halaman lokasi dan sync lokal.
- `cronjob/README.md` - **Cronjob Scheduler:** konfigurasi jadwal task via UI (`/cronjob`) dengan persist ke settings.
- `cronjob/IMPLEMENTATION.md` - Wiring controller + setting helper + konsumsi scheduler (`Kernel` dan `routes/console.php`).
- `cronjob/E2E-TESTING.md` - Skenario manual simpan konfigurasi + verifikasi role guard HCM Admin.
- `export-reconciliation/README.md` - **Export Reconciliation:** kontrol pre-action untuk aksi finansial berisiko (finalize/disburse/mark-paid/verify) agar selalu ada bukti rekonsiliasi.
- `export-reconciliation/IMPLEMENTATION.md` - Desain teknis gate export, data model evidence, dan rollout bertahap lintas payroll + billing.
- `export-reconciliation/API-CONTRACT.md` - Draft endpoint export/gate validation/list/download evidence untuk sinkronisasi BE-FE.
- `export-reconciliation/TRACKING.md` - Tracking milestone, WBS task, risiko, mitigasi, dan status progres implementasi.
- `export-reconciliation/E2E-TESTING.md` - Skenario manual validasi gate wajib export sebelum action finansial dijalankan.
- `knowledgebase/README.md` - **Knowledge Base:** bantuan dalam aplikasi dari `config/hcm_knowledgebase.php` (`/knowledgebase`, kategori, artikel).
- `knowledgebase/IMPLEMENTATION.md` - Route, controller, partial sidebar, redirect legacy, dan tes `KnowledgebaseWebTest`.
- `recovery-vault/README.md` - **Recovery Vault:** audit trail CRUD immutable + snapshot/restore pipeline untuk recovery bencana, hanya service internal dan super admin.
- `recovery-vault/IMPLEMENTATION.md` - Arsitektur service, database terpisah, model event, retention 90 hari, dan hardening keamanan.
- `recovery-vault/API-CONTRACT.md` - Draft kontrak endpoint internal/admin, payload, idempotency, dan error code.
- `recovery-vault/E2E-TESTING.md` - Skenario verifikasi API internal, permission super admin, dan simulasi restore bencana.

---

## 🎯 Cara Pakai

1. Mulai dari folder fitur yang dikerjakan.
2. Buka `README.md` → `IMPLEMENTATION.md` → docs lainnya sesuai kebutuhan.
3. Cocokkan flow UI di Blade + JS dengan kontrak API.
4. Verifikasi edge case dan test yang sudah ada.
5. **Sebelum selesai:** cek `../.cursor/rules/documentation-feature-packaging.mdc` checklist (folder structure + cleanup + main index update).
6. Jika ada perubahan perilaku, update file fitur terkait + `../planning/implementation-status.md`.
