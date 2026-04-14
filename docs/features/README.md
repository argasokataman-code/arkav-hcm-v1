# Feature Documentation Map

Dokumen per fitur dipisah agar tim cepat memahami flow end-to-end (UI, API, aturan bisnis, edge case, dan test).

**Role & permission per URL (lintas fitur):** `../planning/active-hcm-templates-and-permissions.md` — indeks halaman menu HCM yang aktif, modul JS, area API, dan target akses (HCM Admin vs karyawan). Update dokumen ini bersamaan saat menambah route/menu atau mengubah siapa yang boleh memanggil API.

**Siklus payroll (pre / actual / post):** `../planning/payroll-lifecycle.md`.

---

## 📋 Index Fitur dengan Status & Links

> **Rule:** Setiap fitur WAJIB punya folder `docs/features/<feature-name>/` dengan `README.md` + `IMPLEMENTATION.md` + docs lainnya.  
> Lihat: `../.cursor/rules/documentation-feature-packaging.mdc`

| # | Feature | Status | README | Implementation | Additional |
|---|---------|--------|--------|-----------------|------------|
| 1 | Identity & Auth | ✅ Complete | [→](identity-auth/) | [ID Details](identity-auth/) | Login, token, RBAC |
| 2 | Organization & Employees | ✅ Complete | [→](employees-organization/) | [ID Details](employees-organization/) | [USE-CASES](employees-organization/USE-CASES.md) |
| 3 | Attendance & Shift | ✅ Complete | [→](attendance-shift-schedule/) | [ID Details](attendance-shift-schedule/) | Schedule, timesheets |
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
| 17 | Employee Salary | ✅ Complete | [→](employee-salary/) | [📘 Implementation](employee-salary/IMPLEMENTATION.md) | [🧪 E2E Testing](employee-salary/E2E-TESTING.md) |
| 18 | **Purchase Transactions** | ✅ Complete | [→](purchase-transaction/) | [📘 Implementation](purchase-transaction/IMPLEMENTATION.md) | [🧪 E2E Testing](purchase-transaction/E2E-TESTING.md) |
| 19 | **Packages** | ✅ Complete | [→](packages/) | [📘 Implementation](packages/IMPLEMENTATION.md) | [🧪 E2E Testing](packages/E2E-TESTING.md) |
| 20 | **Domain Management** | ✅ Complete | [→](domain-management/) | [📘 Implementation](domain-management/IMPLEMENTATION.md) | [🧪 E2E Testing](domain-management/E2E-TESTING.md) |
| 21 | **Asset Management** | 🚧 In Progress | [→](asset-management/) | [📘 Implementation](asset-management/IMPLEMENTATION.md) | [🧪 E2E Testing](asset-management/E2E-TESTING.md) |
| 22 | **Subscriptions** | ✅ Complete | [→](subscriptions/) | [📘 Implementation](subscriptions/IMPLEMENTATION.md) | [🧪 E2E Testing](subscriptions/E2E-TESTING.md) |
| 23 | **Super Admin Dashboard** | ✅ Complete | [→](super-admin-dashboard/) | [📘 Implementation](super-admin-dashboard/) | SaaS overview & KPIs |
| 24 | Tenant Management | 🚧 Planning | [→](user\ tenant\ management/) | - | Blueprint & roadmap |
| 25 | User Management | ✅ Implemented (Backend API) | [→](user-management/) | [📘 Implementation](user-management/IMPLEMENTATION.md) | [🧪 E2E Testing](user-management/E2E-TESTING.md) |
| 26 | Reporting System | 🚧 In Progress (Phase 3) | [→](reporting/) | [📘 Implementation](reporting/IMPLEMENTATION.md) | [🧪 E2E Testing](reporting/E2E-TESTING.md) |
| 27 | Locations / Wilayah Sync | ✅ Complete | [→](locations/) | [📘 Implementation](locations/IMPLEMENTATION.md) | [🧪 E2E Testing](locations/E2E-TESTING.md) |

---

## 📚 Daftar Fitur (Legacy - migrated ke table di atas)

- `identity-auth/README.md` - Login, register, token, auth guard, dan role hint.
- `employees-organization/README.md` - Employees, departments, designations, dan data organisasi.
- `employees-organization/USE-CASES.md` - Use case & matriks hak akses employee (admin vs self); acuan sebelum mengunci RBAC di API/UI.
- `attendance-shift-schedule/README.md` - Attendance admin/employee, shift master, schedule timing, timesheets.
- `overtime/README.md` - Master overtime type, overtime request, kalkulator PP 35/2021, dan policy negatif.
- `leave-and-holidays/README.md` - Leave requests, leave settings, holidays.
- `policies/README.md` - Company policy CRUD dan relasi attachment/payload.
- `tickets/README.md` - Ticketing end-to-end: list/grid/detail, comments, attachment, SLA, assignment history, RBAC.
- `performance/README.md` - Performance Phase 1: cycles, indicator templates/items, review workflow employee→manager→admin.
- `goal-tracking/README.md` - Goal Tracking Phase 1: goal types (admin) + goals scope me/team/all, export CSV.
- `training/README.md` - Training Phase 1: training types (admin) + trainings (admin-only) + participants.
- `promotion/README.md` - Promotion Phase 1: promotion records (admin-only), list + modal CRUD.
- `resignation/README.md` - Resignation Phase 1: resignation records (admin-only list/mutasi), self read + employee detail section.
- `termination/README.md` - Termination Phase 1: termination records (type + dates + reason), pola sama resignation.
- `asset-management/README.md` - Asset Management tenant-scoped: category, asset master, assignment history, attachment, issue reporting.
- `payroll-salary-components/README.md` - Master komponen gaji (Indonesia-oriented flags) + API `/salary-components`.
- `payroll-runs/README.md` - Periode payroll, run draft/final, baris slip (Phase 1) + API `/payroll-periods`, `/payroll-runs`, `/payroll/my-slip-lines`.
- `payroll-items/README.md` - Halaman `/payroll` (Payroll Items): CRUD `hcm_payroll_items` (kustom / taut master).
- Halaman **`/payroll-thr`**: pengaturan tahunan THR (`hcm_thr_yearly_settings`) + estimasi pro rata + batch mass calculate/assign (`hcm_thr_batches` / lines); lihat `docs/api/hcm-payroll-api.md`.
- `employee-salary/README.md` - Halaman gaji karyawan (baseSalary / fixedAllowance) + integrasi lembur & komponen.
- `employee-salary/IMPLEMENTATION.md` - Detail route, API contract, role guard, dan alur submit kompensasi.
- `employee-salary/E2E-TESTING.md` - Skenario browser E2E admin vs non-admin (Playwright).
- `purchase-transaction/README.md` - **SaaS Billing:** Invoice management, payment tracking, payment reminders, financial reports (Revenue/Aging/Churn), admin + company dashboards.
- `packages/README.md` - **SaaS Packages:** package CRUD, add-on catalog, status/search/pagination, dan assignment fitur per package (admin mutation + non-admin guard).
- `domain-management/README.md` - **SaaS Domain Management:** domain CRUD admin, verification details, manual verify flow, dan status monitoring baseline.
- `subscriptions/README.md` - **SaaS Subscriptions:** lifecycle subscription company ke package, renewal, status/cycle filters, dan admin guard.
- `user-management/README.md` - **User Management:** backend API live untuk user-role-permission (filter/pagination/CRUD/export/assignment) + baseline schema & audit.
- `reporting/README.md` - **Reporting System:** snapshot-based reporting (API `/v1/hcm/reports/snapshots`) + mode Live/Archive pada report pages.
- `reporting/IMPLEMENTATION.md` - Arsitektur teknis reporting snapshot, export generator, dan kontrak storage.
- `reporting/E2E-TESTING.md` - Skenario manual UI/API untuk validasi live/archive + export.
- `locations/README.md` - **Locations / Wilayah Sync:** lokasi Indonesia dari `wilayah.id` disimpan lokal, dipakai halaman Provinces / Regencies / Districts.
- `locations/IMPLEMENTATION.md` - Arsitektur tabel `wilayah_*`, command `wilayah:sync`, scheduler bulanan, dan alur pruning/upsert.
- `locations/E2E-TESTING.md` - Skenario smoke UI untuk verifikasi halaman lokasi dan sync lokal.

---

## 🎯 Cara Pakai

1. Mulai dari folder fitur yang dikerjakan.
2. Buka `README.md` → `IMPLEMENTATION.md` → docs lainnya sesuai kebutuhan.
3. Cocokkan flow UI di Blade + JS dengan kontrak API.
4. Verifikasi edge case dan test yang sudah ada.
5. **Sebelum selesai:** cek `../.cursor/rules/documentation-feature-packaging.mdc` checklist (folder structure + cleanup + main index update).
6. Jika ada perubahan perilaku, update file fitur terkait + `../planning/implementation-status.md`.
