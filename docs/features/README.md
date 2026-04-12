# Feature Documentation Map

Dokumen per fitur dipisah agar tim cepat memahami flow end-to-end (UI, API, aturan bisnis, edge case, dan test).

**Role & permission per URL (lintas fitur):** `../planning/active-hcm-templates-and-permissions.md` — indeks halaman menu HCM yang aktif, modul JS, area API, dan target akses (HCM Admin vs karyawan). Update dokumen ini bersamaan saat menambah route/menu atau mengubah siapa yang boleh memanggil API.

**Siklus payroll (pre / actual / post):** `../planning/payroll-lifecycle.md`.

## Daftar fitur

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
- `user tenant management/new_feature.md` - Blueprint pengembangan tenant management dan roadmap migrasi menuju arsitektur multi-tenant SaaS.
- `payroll-salary-components/README.md` - Master komponen gaji (Indonesia-oriented flags) + API `/salary-components`.
- `payroll-runs/README.md` - Periode payroll, run draft/final, baris slip (Phase 1) + API `/payroll-periods`, `/payroll-runs`, `/payroll/my-slip-lines`.
- `payroll-items/README.md` - Halaman `/payroll` (Payroll Items): CRUD `hcm_payroll_items` (kustom / taut master).
- Halaman **`/payroll-thr`**: pengaturan tahunan THR (`hcm_thr_yearly_settings`) + estimasi pro rata + batch mass calculate/assign (`hcm_thr_batches` / lines); lihat `docs/api/hcm-payroll-api.md`.
- `employee-salary/README.md` - Halaman gaji karyawan (baseSalary / fixedAllowance) + integrasi lembur & komponen.

## Cara pakai

1. Mulai dari folder fitur yang dikerjakan.
2. Cocokkan flow UI di Blade + JS dengan kontrak API.
3. Verifikasi edge case dan test yang sudah ada.
4. Jika ada perubahan perilaku, update file fitur terkait + `planning/implementation-status.md`.
