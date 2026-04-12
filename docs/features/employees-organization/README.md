# Employees & Organization

## Use case (sumber kebenaran perilaku & hak akses)

- **`USE-CASES.md`** — definisi aktor (HCM Admin vs karyawan), matriks hak, alur UC-01–UC-07, aturan bulk upload, privasi field, dan **gap implementasi vs target** (termasuk pembatasan akses API employee).
- **`../../planning/active-hcm-templates-and-permissions.md`** — posisi halaman employee dalam indeks **semua** template HCM aktif (URL, JS, API, target role).

## Scope

- Employee list/detail/create/update
- Department dan designation master
- Employee profile data pendukung

## API utama (`/v1/hcm`)

- `GET/POST /employees` — hanya **hcmAdmin** (full directory).
- `GET/PUT /employees/{id}` — **hcmAdmin** semua ID; karyawan hanya **id = user login**; `PUT` self terbatas field (phone, address, bio, emergency/education/experience) per `USE-CASES.md` §5.2.
- `GET/POST/PUT/DELETE /departments`, `/designations`, **`/policies`** — hanya **hcmAdmin**.

## Data model ringkas

- `users` + `employee_profiles` (FK opsional **`department_id` → `departments`**, **`designation_id` → `designations`**, plus kolom `designation` string untuk label/denormalisasi)
- tabel riwayat/normalized compatibility layer:
  - `employee_employment_history`
  - `employee_assignments` (termasuk `team_id` + fallback `team_name`)
  - `employee_compensations`
  - `employee_contracts`
  - `employee_bank_accounts`
  - `employee_tax_profiles`
  - `employee_benefits`
- master organisasi: `departments`, `designations`, `teams`
- komponen kompensasi legacy di `employee_profiles`: `base_salary`, `fixed_allowance` (tetap di-sync untuk backward compatibility)

## Frontend flow

- `frontend/resources/js/employees-data.js` — list/grid/report: cek `me.hcmAdmin` lalu load API; non-admin diarahkan ke `/employee-dashboard`. Laporan karyawan mengagregasi halaman `employees?perPage=100` (maks 50 halaman). Fungsi `renderReportTable` / `updateReportSummary` mengisi `/employee-report`.
- `frontend/resources/js/hcm-pages-data.js` — `/departments`, `/designations`, `/policy`: guard `hcmAdmin` + redirect; detail employee tetap memakai `GET /employees/{id}` (403 jika bukan self dan bukan admin).
- Halaman detail employee menampilkan ringkasan kompensasi (`baseSalary`, `fixedAllowance`) dalam format Rupiah.
- Halaman detail employee juga menampilkan **Shift & Schedule** (`source`, `display`, `shiftName`) dari payload `data.schedule` API `GET /employees/{id}` (dengan fallback `Auto` jika belum ada override jadwal).
- Detail page kini juga merender koleksi riwayat normalized: `employmentHistory`, `assignmentHistory`, `compensationHistory`, `contractHistory`, `bankAccounts`, plus `emergencyContacts`, `educationItems`, dan `experienceItems`.
- Employee list mendukung quick preview via side panel (offcanvas) saat klik baris, plus tombol lanjut ke detail lengkap.
- Navigasi dari list ke detail menyertakan `returnTo` + restore posisi scroll/list state saat kembali.
- Halaman **`/employee-salary`** (HCM Admin, `hcm.web.admin`): tabel kompensasi dari API yang sama + modal sunting; lihat `docs/features/employee-salary/README.md`.
- Modal `Add Employee` dan `Edit Employee` di halaman employee sekarang memuat input kompensasi (`baseSalary`, `fixedAllowance`) dan submit langsung ke API employee.
- Aksi edit dari tabel employee dibuka via ikon pensil, data employee diprefill ke modal edit, lalu disimpan via `PUT /employees/{id}`.
- Admin bisa melakukan bulk upload detail employee via modal `Bulk Upload Employee` di halaman employee.
- Template Excel bulk tersedia dari endpoint `GET /v1/hcm/employees/bulk-template`, dan upload via `POST /v1/hcm/employees/bulk-upload`.
- Validasi bulk sekarang **strict + transactional**: controlled fields (`employment_status`, `salary_type`, `contract_type`, `contract_status`, `gender`, `marital_status`, `religion`, `bank_name`, `tax_status`) diverifikasi penuh, dan jika ada satu baris invalid maka seluruh import di-rollback.
- Template saat ini masih **single-sheet**; peningkatan berikutnya yang disarankan adalah template multi-sheet dengan sheet referensi master dan dropdown.

## Catatan implementasi

- Banyak field employee diturunkan dari kombinasi `users` dan `employee_profiles`.
- Status kerja (`active|inactive|probation`) dipakai untuk filter list.
- API employee sekarang juga expose `baseSalary` dan `fixedAllowance` untuk integrasi kalkulator lembur/payroll.
