# Employees & Organization

## Ringkasan

Fitur ini menjadi directory utama employee, organisasi, departemen, designation, dan data profil kerja yang dipakai modul HCM lain. Hampir semua flow operasional seperti attendance, leave, overtime, salary, promotion, termination, training, dan payroll menggantungkan identitas employee, status kerja, dan struktur organisasinya dari modul ini.

## Akses

- HCM Admin: boleh melihat full directory, membuat/mengubah employee, serta mengelola department, designation, dan policy.
- Employee: hanya boleh membaca dan mengubah data sendiri pada subset field yang diizinkan.
- Rujukan perilaku dan hak akses paling rinci tetap berada di `USE-CASES.md` dan `../../planning/active-hcm-templates-and-permissions.md`.

## UI Aktif

- Halaman employee list, employee details, employee report, department, designation, dan policy memakai JS manager employee/HCM pages.
- Halaman `/employee-salary` menggunakan data employee yang sama untuk kompensasi bulanan.

## Flow Bisnis End-to-End

1. HCM Admin membuat atau memperbarui employee beserta profil organisasinya.
2. Sistem menyimpan kombinasi data di `users`, `employee_profiles`, dan tabel normalized history/compatibility yang relevan.
3. Halaman employee list, detail, preview panel, salary, training history, dan modul lain membaca data employee yang sama dari API ini.
4. Employee dapat melihat detail dirinya sendiri dan mengubah subset field self-service yang diperbolehkan.

## Lifecycle Dan Keputusan Bisnis

- Status kerja seperti `active`, `inactive`, dan `probation` menjadi penentu filter dan eligibility di modul lain.
- Kompensasi legacy `base_salary` dan `fixed_allowance` tetap di-sync untuk backward compatibility.
- Normalized history dipakai agar perubahan assignment, kontrak, bank account, dan kompensasi bisa terlacak tanpa bergantung hanya pada satu tabel profil.

## Integrasi

- Employee Salary: `/employee-salary`, `GET /v1/hcm/employees`, dan `PUT /v1/hcm/employees/{id}` memakai data employee yang sama. Lihat `docs/features/employee-salary/README.md`.
- Attendance, Leave, Overtime, Training, Promotion, Termination, dan Payroll semua bergantung pada identity + employment status employee dari modul ini. Lihat `docs/features/INTEGRATION-MAP.md`.
- Training: employee detail memakai `GET /v1/hcm/training/users/{userId}/trainings`. Lihat `docs/features/training/README.md`.
- Promotion dan Termination: mutasi lifecycle employee selalu merujuk user/employee dari directory yang sama. Lihat `docs/features/promotion/README.md` dan `docs/features/termination/README.md`.

## Kontrak API

## Use case (sumber kebenaran perilaku & hak akses)

- **`USE-CASES.md`** — definisi aktor (HCM Admin vs karyawan), matriks hak, alur UC-01–UC-07, aturan bulk upload, privasi field, dan **gap implementasi vs target** (termasuk pembatasan akses API employee).
- **`../../planning/active-hcm-templates-and-permissions.md`** — posisi halaman employee dalam indeks **semua** template HCM aktif (URL, JS, API, target role).

## Existing Vs Target

- Existing: employee CRUD, organization masters, detail histories, salary modal, bulk upload, dan wilayah address flow sudah aktif.
- Existing: API sudah melayani kebutuhan banyak modul turunan dan expose kompensasi untuk integrasi overtime/payroll.
- Target: template bulk multi-sheet dengan referensi master dan UX import yang lebih kaya masih backlog.

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
- Employee list untuk **super admin code 1** kini menampilkan 2 tab scope directory:
  - `Global Semua Tenant` → list lintas tenant.
  - `Khusus Super Admin Code 1` → list dipaksa ke tenant aktif code 1.
  Untuk user selain super admin code 1, UX tetap existing (tanpa tab scope tambahan).
- Halaman **`/employee-salary`** (HCM Admin, `hcm.web.admin`): tabel kompensasi dari API yang sama + modal sunting; lihat `docs/features/employee-salary/README.md`.
- Modal `Add Employee` dan `Edit Employee` di halaman employee sekarang memuat input kompensasi (`baseSalary`, `fixedAllowance`) dan submit langsung ke API employee.
- Modal `Add Employee` dan `Edit Employee` sekarang juga menggunakan dropdown berjenjang wilayah (`provinceId` → `regencyId` → `districtId` → `villageId`) untuk input alamat, lalu field `address` disusun otomatis agar data tersimpan konsisten.
- Detail alamat manual (jalan/gedung/RT-RW/patokan) disimpan terpisah di field `addressDetail` melalui textarea, tanpa mengganti alamat utama hasil komposisi wilayah.
- Aksi edit dari tabel employee dibuka via ikon pensil, data employee diprefill ke modal edit, lalu disimpan via `PUT /employees/{id}`.
- Admin bisa melakukan bulk upload detail employee via modal `Bulk Upload Employee` di halaman employee.
- Template Excel bulk tersedia dari endpoint `GET /v1/hcm/employees/bulk-template`, dan upload via `POST /v1/hcm/employees/bulk-upload`.
- Validasi bulk sekarang **strict + transactional**: controlled fields (`employment_status`, `salary_type`, `contract_type`, `contract_status`, `gender`, `marital_status`, `religion`, `bank_name`, `tax_status`) diverifikasi penuh, dan jika ada satu baris invalid maka seluruh import di-rollback.
- Template saat ini masih **single-sheet**; peningkatan berikutnya yang disarankan adalah template multi-sheet dengan sheet referensi master dan dropdown.

## Catatan implementasi

- Banyak field employee diturunkan dari kombinasi `users` dan `employee_profiles`.
- Status kerja (`active|inactive|probation`) dipakai untuk filter list.
- API employee sekarang juga expose `baseSalary` dan `fixedAllowance` untuk integrasi kalkulator lembur/payroll.
