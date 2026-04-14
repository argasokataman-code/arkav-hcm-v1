# Status implementasi (snapshot)

Dokumen ini merangkum **kode yang sudah ada** di repo pada April 2026. Gunakan sebagai acuan cepat; detail kontrak tetap pada `api/api-spec-phase-1.md`, `api/openapi.yaml`, dan `routes` Laravel.

Detail flow per modul sekarang dipisah di `docs/features/*` (mulai dari `docs/features/README.md`).

**Employee — use case & RBAC:** `docs/features/employees-organization/USE-CASES.md` — **`HcmEmployeeController`**: `GET/POST /employees` dan master **`departments` / `designations` / `policies`** (termasuk `GET` list) hanya **hcmAdmin**; `GET/PUT /employees/{id}` untuk non-admin hanya **profil sendiri**, subset field pada `PUT` self (§5.2). Tes: `HcmEmployeeApiTest`. UI: `employees-data.js` + `hcm-pages-data.js` redirect non-admin dari halaman direktori/master ke `/employee-dashboard`.

**Template HCM aktif + role/permission (semua menu ter-wire):** `docs/planning/active-hcm-templates-and-permissions.md` — tabel path web, modul JS, area API, target akses halaman & API; dipakai bersama rule Cursor `role-permissions-with-features` agar setiap development punya pandangan konsisten.

**Siklus payroll (referensi produk):** `docs/planning/payroll-lifecycle.md` — pre / actual / post payroll dipetakan ke modul Arcav + backlog terarah.

**Guard halaman web HCM:** middleware `EnsureHcmWebPagesAuthenticated` (group `web`) + `config/arcav_hcm_web_guard.php`. **Satu mode:** setiap GET/HEAD web wajib cookie API valid atau sesi web, **kecuali** whitelist `public_paths` / `public_prefixes` (/, login, register, signout, up, api-docs, …). Tidak ada lagi daftar `protected_paths` atau env yang mematikan guard global. Tamu pada path lain → **404** + **`error-404-guest`**. **Security headers** global: `SecurityHeadersMiddleware`. Indeks: `docs/security/README.md`. Tes: `WebHcmRouteGuardTest`.

**Locations / Wilayah Sync:** menu `countries` / `states` / `cities` sekarang membaca DB lokal dari `wilayah_*` tables dan disinkronkan dengan `wilayah.id` via `wilayah:sync` yang dijadwalkan bulanan pada tanggal 1 pukul 01:00 WIB. Data desa/kelurahan juga disimpan di `wilayah_villages` walau belum ditampilkan di menu. Dokumentasi fitur: `docs/features/locations/`.

## Runtime

- **Backend:** satu aplikasi Laravel di `backend/`; domain dipisah lewat prefix route, bukan deploy microservice terpisah.
- **Skala data (April 2026):** migrasi indeks `attendance_records.work_date` + (MySQL) `FULLTEXT` pada `users.name`/`users.email`; beberapa endpoint list memakai paginasi DB + ringkasan agregat (employees, attendance admin, timesheets, schedule timing, leave/overtime requests). Lihat `docs/api/hcm-*-api.md`.
- **Frontend:** aset template di `frontend/resources/`, di-build lewat Vite Laravel; Node `frontend/server.js` mem-proxy ke API.
- **Database:** satu skema MySQL per environment Laravel (bukan tiga database fisik seperti pada spesifikasi multi-DB lama).
- **API docs (Swagger UI):** tersedia halaman `GET /api-docs` yang memuat Swagger UI dan membaca spec dari `GET /api-docs/openapi.yaml` (file sumber: `docs/api/openapi.yaml`).

## API yang terpasang (ringkas)

**Identity** — `POST/GET` di `/v1/identity/auth/*` (register, login, logout, me). Respons **`GET /auth/me`** menyertakan boolean **`hcmAdmin`** (sama logika dengan `EnsuresHcmAdmin` / `User::isHcmAdmin()`) agar UI bisa membedakan admin HCM vs karyawan biasa.

**HCM (semua di bawah `/v1/hcm`, dengan middleware token):**

- Master: employees, departments, designations, **policies**
- **Master komponen gaji (API):** `GET/POST /salary-components`, `GET /salary-components/{id}`, `PUT/DELETE /salary-components/{id}` (hanya **hcmAdmin**). Tetap dipakai mesin/seed; **UI admin** utama lewat `/payroll` (payroll items). URL `/salary-component-master` → redirect `/payroll`. Spesifikasi: `docs/api/hcm-salary-components-api.md`. Tes: `HcmSalaryComponentApiTest`.
- **Payroll periode & run (Phase 1):** `GET/POST /payroll-periods`, `GET /payroll-periods/{id}`, `POST /payroll-periods/{id}/calculate-draft`, `GET /payroll-runs/{id}`, `POST /payroll-runs/{id}/finalize` (**hcmAdmin**); `GET /payroll/my-slip-lines` (self, baris final). Draft mengisi baris dari `base_salary` + `fixed_allowance` + master komponen. Spesifikasi: `docs/api/hcm-payroll-api.md`. Fitur: `docs/features/payroll-runs/README.md`. Tes: `HcmPayrollApiTest`.
- **THR:** halaman **`/payroll-thr`** — pengaturan tahunan (`GET/PUT /payroll/thr-settings/{year}`) + kalkulator `POST /payroll/thr-calculate` (`payroll-thr-data.js`) + **mass batch** (`thr-payroll-batch.js` / Vite; kolom tabel: karyawan, **rekening** (`bankName`/`bankAccountNo` dari profil), eligible, gaji pokok, tunjangan tetap, proporsi %, dibayar, ref gateway): `thr-batch` GET/generate, **`disburse`** (gateway stub), **`post-payroll`** (semua payable harus `paid`), **`send-slip`**, **`lines/{id}/slip`** (PDF; admin + pemilik baris), `GET /payroll/my-thr-slip` (JSON slip karyawan; tidak ada route web `/payslip-thr`). Tabel `hcm_thr_yearly_settings`, `hcm_thr_batches`, `hcm_thr_batch_lines`, `hcm_thr_disbursements`; run payroll `purpose` **`thr`**. **Data demo masa kerja:** `php artisan db:seed --class=ThrDemoEmployeesSeeder` (juga dipanggil dari `DatabaseSeeder`) — user `thr.demo.*@example.com`, sandi `StrongPass1`; set `calculationCutoffDate` **2026-04-09** di pengaturan THR **2026** agar baris batch cocok dengan skenario full / pro rata / nihil di seeder. Tes: `HcmPayrollThrApiTest`, `WebHcmRouteGuardTest` (path admin). **Reset QA:** `php artisan hcm:reset-thr-test-data` (default: jejak bayar/posting/slip; `thr_slip_public_no` tetap; `--fresh-slip-numbers` = nomor slip baru per baris; `--full` hapus batch + baris; `--keep-settings` dengan `--full`). SQL manual: `docs/features/payroll-runs/THR_RESET_MANUAL.sql`. **Skema:** migrasi `2026_04_11_100000_drop_legacy_tenant_subscription_tables` membersihkan tabel tenant/subscription legacy di skema HCM tunggal.
- **Payroll items:** `GET/POST/PUT/DELETE /payroll-items` (**hcmAdmin**); `GET` mendukung `?kind=addition|deduction` + `meta.linkedSalaryComponentIds`. Web: `/payroll` (penghasilan) dan `/payroll-deduction` (potongan) memakai `payroll-items-data.js` + modal partial. Spesifikasi: `docs/api/hcm-payroll-items-api.md`. Tes: `HcmPayrollItemApiTest`.
- **Payroll lembur (menu `/payroll-overtime`):** daftar `GET /overtime-requests` dengan filter `workDate` / `status` + ringkasan admin; link ke `/attendance-admin?date=` (tanggal sama). JS: `payroll-overtime-data.js`. Spesifikasi: `docs/api/hcm-overtime-api.md`. Tes: `OvertimeRequestApiTest`.
- **Halaman gaji karyawan:** `/employee-salary` (middleware **`hcm.web.admin`**) + `employee-salary-data.js` — daftar kompensasi dari `GET /v1/hcm/employees`, sunting **`baseSalary` / `fixedAllowance`** lewat `PUT /v1/hcm/employees/{id}`; slip gaji masih placeholder. Dokumentasi: `docs/features/employee-salary/README.md`.
- **Shifts:** `GET/POST /shifts`, `PUT/DELETE /shifts/{id}` (admin HCM; lihat `EnsuresHcmAdmin`)
- **Master Overtime Types:** `GET/POST /overtime-types`, `PUT/DELETE /overtime-types/{id}` (admin mutasi; non-admin hanya list tipe aktif)
- **Attendance:** admin timesheets, schedule timing, punch/me/history/stats, koreksi, dll. **Punch karyawan wajib GPS:** `POST /attendance/me/punch` memerlukan `latitude`/`longitude`; disimpan di `attendance_records`; UI `/attendance-employee` memakai **Leaflet + OpenStreetMap** (unpkg) + Geolocation API. Jika browser memblokir geolocation (`denied`), user bisa memilih titik manual di peta sebagai fallback operasional.
- **Schedule timing per user:** `GET /schedule-timing`, `PUT /schedule-timing/{userId}`, `DELETE ...` — payload mendukung **`shiftId`** opsional (referensi `hcm_shifts`); jika diisi, jam efektif mengikuti master shift.
- **Holidays** dan **leave-settings** — seluruh verb API (`GET` termasuk) hanya **hcmAdmin** (`EnsuresHcmAdmin` di `HcmHolidayController` / `HcmLeaveSettingController`); tes `HcmExtrasApiTest` + `LeaveSettingsApiTest`. Halaman `/holidays` dan `/leave-settings` memeriksa `me.hcmAdmin` dan mengarahkan non-admin ke `/employee-dashboard`.
- Holidays punya sinkronisasi baseline nasional: `POST /v1/hcm/holidays/sync-indonesia` (libur.deno.dev, per tahun) dengan strategi `source=api` vs override admin `source=manual`.
- **Leave-requests**, **overtime-requests** — non-admin hanya melihat/mengubah data sendiri; persetujuan status (`approved` / `declined`) untuk **leave** milik orang lain hanya **hcmAdmin** (`HcmLeaveRequestController::update`; `LeaveRequestsApiTest`). Halaman `/leaves` mengarahkan non-admin ke `/leaves-employee`. **`GET /leave-type-options`** menyediakan tipe cuti aktif untuk dropdown modal (user terautentikasi). **Overtime-requests** (`overtimeTypeId` opsional, `requestType`, `policyNote`) plus **FK `hcm_salary_component_id`** → `hcm_salary_components` (slip upah lembur; otomatis saat create, refresh saat owner mengubah pending). List & kalkulator menyertakan `salaryComponent*` / `salaryComponent`. **Web:** `/overtime` untuk admin HCM; non-admin dialihkan ke **`/overtime-employee`** (satu view, flag Blade; menu *Overtime (Admin)* vs *Overtime (Employee)*).
- **Kalkulator lembur:** `POST /overtime-requests/calculate` (acuan PP 35/2021, upah sejam=(gaji pokok+tunjangan tetap)/173) + `salaryComponent` selaras master komponen gaji.
- **Reporting snapshots:** `POST/GET /reports/snapshots`, `GET /reports/snapshots/{id}`, `POST /reports/snapshots/{id}/export` (admin-only, tenant-scoped). Export sekarang menghasilkan file nyata `csv|excel|pdf` di storage publik; halaman `/attendance-report`, `/payslip-report`, `/employee-report`, `/leave-report` mendukung mode **Live** vs **Archive**.
- **Health:** `GET /health` (tanpa auth)

**Catatan namespace:** banyak fitur “leave & attendance” secara **logis** tetap domain terpisah, tetapi **path HTTP saat ini** berada di `/v1/hcm/*`, bukan `/v1/leave/*`. Rencana atau OpenAPI yang masih menyebut `/v1/leave/...` diperlakukan sebagai referensi domain hingga diselaraskan.

## Skema database (tabel tambahan penting)

Selain `users`, `departments`, `designations`, dan data employee inti, migrasi yang relevan antara lain:

- `attendance_records`, `hcm_schedule_timings` (+ FK **`hcm_shift_id`** → `hcm_shifts`)
- **`hcm_shifts`** (code, name, start/end time, sort order, is_active)
- **`wilayah_provinces`**, **`wilayah_regencies`**, **`wilayah_districts`**, **`wilayah_villages`** — cache lokal data wilayah Indonesia dari `wilayah.id`; sync via `wilayah:sync`.
- `holidays`, `leave_requests`, `overtime_requests` (+ FK `hcm_overtime_type_id`, FK `hcm_salary_component_id` → `hcm_salary_components`)
- `holidays` kini menyimpan metadata sinkronisasi: `source` (`manual|api`) dan `last_synced_at`
- `hcm_leave_type_settings`, `hcm_leave_custom_policies`
- `hcm_overtime_types` (code, name, payment_multiplier, active, sort_order)
- **`hcm_salary_components`** — master komponen slip gaji + flag BPJS/THR/PPh/lembur/beban PK; seed regulasi Indonesia; `is_system_locked` untuk baris bawaan
- **`hcm_thr_yearly_settings`** — tanggal H, pembayaran, cut-off pro rata per tahun kalender
- `policies`, `employee_profiles` (termasuk `hire_date`, `base_salary`, `fixed_allowance`)

Lihat file di `backend/database/migrations/` untuk definisi kolom pasti.

## Frontend (wiring ke API)

- **Auth:** `api-client.js`, `auth-login.js`, `auth-guard.js` (cookie HttpOnly auth + redirect `/login` untuk unauthorized).
- **HCM umum:** `hcm-pages-data.js`, `hcm-extras-data.js`, `employees-data.js`.
- UX employee list: quick preview side panel pada `/employees`, tetap dipasangkan dengan halaman `/employee-details` untuk detail lengkap.
- **`/employees-grid`:** modal Add/Edit memakai **blok Blade yang sama** dengan `/employees` (`data-employee-add-form` / `data-employee-edit-form` + partial org). `employees-data.js` pada submit edit hanya mengirim `departmentId` / `designationId` jika berbeda dari snapshot saat data employee dimuat (mencegah FK organisasi ter-clear saat user hanya mengubah nama/gaji).
- UX employee list: aksi edit (ikon pensil) sekarang membuka modal edit yang terhubung ke API, termasuk update kompensasi (`baseSalary`, `fixedAllowance`).
- Halaman `/employee-details` menampilkan blok **Shift & Schedule** (jadwal aktif, source auto/manual, assigned shift) dari `GET /v1/hcm/employees/{id}`.
- Admin employee: tersedia bulk upload detail employee + download template Excel (`/v1/hcm/employees/bulk-template`, `/v1/hcm/employees/bulk-upload`) untuk update/create massal data karyawan (salary, team, designation, status, kontak, bank, dll).
- **Attendance / timesheet / jadwal:** `attendance-data.js` (termasuk integrasi shift pada jadwal jika disediakan API). Halaman **Attendance Admin**: ringkasan atas menampilkan **Present** dan **Absent** (dari `meta.summary` yang sama dengan kartu di bawahnya), tanpa prefix `+` yang membingungkan pada angka absent. Halaman **`/attendance-employee`**: struktur kartu (punch kiri, statistik + ringkasan) dirapikan (grid `g-3`, kartu stat kompak, ringkasan hari ini bertajuk); label tombol/teks produksi di-**lokalkan** ringan di JS.
- **Shift master (template):** `shift-master-data.js` memanggil endpoint shifts.
- **Leave settings:** `leave-settings-data.js`.
- **Master overtime (menu `/overtime-master`):** `overtime-master-data.js` untuk CRUD tipe lembur + multiplier.
- **Overtime:** `hcm-extras-data.js` — **`/overtime`** (admin HCM): semua request, kolom karyawan + **Pay component** (tautan ke master komponen slip), modal policy/status, kalkulator (output menyebut komponen slip) dengan dropdown karyawan untuk auto-fill kompensasi. **`/overtime-employee`:** hanya `scope=me`, tanpa dropdown karyawan di kalkulator; non-admin yang membuka `/overtime` dialihkan ke `/overtime-employee` (sama pola cuti). Menu sidebar/header (Attendance): item **Overtime** bertingkat seperti **Leaves** — berisi *Master Overtime*, *Overtime (Admin)*, *Overtime (Employee)*.
- **Tickets:** `tickets-data.js` (sinkron ke `backend/public/build/js/tickets-data.js`) untuk menu terpisah `/ticket-master`, `/tickets-admin`, `/tickets-employee`, `/tickets-grid`, `/ticket-details/{id}`. Dummy template diganti API real: list/grid/detail, create, comment timeline, attachment upload/download, assignment history, export CSV, plus master kategori (`/v1/hcm/tickets/categories`).
- **Performance (Phase 1):** `performance-data.js` (sinkron ke `backend/public/build/js/performance-data.js`) untuk halaman `/performance-indicator`, `/performance-appraisal`, `/performance-review`. Backend: schema `performance_*` (cycles, indicator templates/items, reviews, scores) + API `/v1/hcm/performance/*` untuk master template, cycles, dan workflow review employee→manager→admin. Tes: `PerformanceApiTest`. Dokumen: `docs/features/performance/README.md`.
- **Goal Tracking (Phase 1):** `goal-data.js` (sinkron ke `backend/public/build/js/goal-data.js`) untuk halaman `/goal-type` dan `/goal-tracking`. Backend: schema `performance_goal_types`, `performance_goals` + API `/v1/hcm/performance/goal-types` dan `/v1/hcm/performance/goals` (scope `me/team/all` + RBAC). Tes: `GoalTrackingApiTest`. Dokumen: `docs/features/goal-tracking/README.md`.
- **Training (Phase 1):** `training-data.js` (sinkron ke `backend/public/build/js/training-data.js`) untuk halaman `/training-type` dan `/training`. Backend: schema `hcm_training_types`, `hcm_trainings`, `hcm_training_participants` + API `/v1/hcm/training/types` (list semua auth; mutasi admin-only) dan `/v1/hcm/training/trainings` (admin-only Phase 1). Tes: `TrainingApiTest`. Dokumen: `docs/features/training/README.md`.
- **Trainers (Phase 1):** `training-data.js` untuk halaman `/trainers`. Backend: schema `hcm_trainers` + API `/v1/hcm/training/trainers` (admin-only). Tes: `TrainersApiTest`. Dokumen: `docs/features/training/README.md`.
- **Promotion (Phase 1):** `promotion-data.js` (sinkron ke `backend/public/build/js/promotion-data.js`) untuk `/promotion` dan **modal detail** di `/employee-details`. Backend: `hcm_promotions` + `GET/POST/PUT/DELETE /promotions`, `GET /promotions/{id}` + `GET /promotions/users/{userId}/promotions` (karyawan: self). Employee detail: `hcm-pages-data.js` memuat section Promotion. Tes: `PromotionApiTest`. Dokumen: `docs/features/promotion/README.md`, `docs/api/hcm-promotion-api.md`.
- **Resignation (Phase 1):** `resignation-data.js` (sinkron ke `backend/public/build/js/resignation-data.js`) untuk `/resignation` dan **modal detail** di `/employee-details`. Backend: `hcm_resignations` + `GET/POST/PUT/DELETE /resignations`, `GET /resignations/{id}` + `GET /resignations/users/{userId}/resignations` (karyawan: self). Employee detail: `hcm-pages-data.js` memuat section Resignation. Halaman list `/resignation` (dan `/promotion`): cek `GET /v1/identity/auth/me` → `hcmAdmin`, non-admin di-redirect ke `/employee-dashboard`. Tes: `ResignationApiTest`. Dokumen: `docs/features/resignation/README.md`, `docs/api/hcm-resignation-api.md`.
- **Termination (Phase 1):** `termination-data.js` (sinkron ke `backend/public/build/js/termination-data.js`) untuk `/termination` + modal detail di `/employee-details`. Backend: `hcm_terminations` + `GET/POST/PUT/DELETE /terminations`, per-user list + self read (sama pola resignation). Section Termination di employee detail. Non-admin di list page di-redirect ke `/employee-dashboard`. Tes: `TerminationApiTest`. Dokumen: `docs/features/termination/README.md`, `docs/api/hcm-termination-api.md`.
- Employee detail integration: `/employee-details` menampilkan section **Training** via `GET /v1/hcm/training/users/{userId}/trainings` (admin: semua; karyawan: self).
- Sinkronisasi auth frontend lanjutan: modul HCM yang semula masih `localStorage` + header Bearer (`attendance-data.js`, `hcm-pages-data.js`, `employees-data.js`, `hcm-extras-data.js`, `shift-master-data.js`, `overtime-master-data.js`, `leave-settings-data.js`) sudah dipindah ke cookie auth (`withCredentials` / `credentials: "same-origin"`), lalu disalin ke `backend/public/build/js/`.
- Util global validasi FE: `build/js/arcav-validation.js` (sumber: `frontend/resources/js/arcav-validation.js`) dimuat global via `footer-scripts` untuk membantu parity constraint/regex dengan API spec (tetap server-side adalah source of truth).

Semua pemanggilan mengasumsikan base path API yang di-proxy (mis. `/v1/...`) seperti pada server Node/Vite.

## Pengujian otomatis

- Feature tests di `backend/tests/Feature/` mencakup antara lain auth, attendance, **`HcmEmployeeApiTest`** (RBAC employee + self-update), **`LeaveRequestsApiTest`**, **`LeaveSettingsApiTest`**, **`HcmExtrasApiTest`**, **`ShiftMasterApiTest`**, **`OvertimeRequestApiTest`**, **`OvertimeTypeApiTest`**, **`TicketApiTest`**, **`PerformanceApiTest`**, **`GoalTrackingApiTest`**, **`PromotionApiTest`**, **`ResignationApiTest`**, **`TerminationApiTest`**.

## Catatan login (agar tidak kelewatan)

- Detail flow login/logout/guard dipelihara di `docs/features/identity-auth/README.md`.
- Hardening sudah aktif: login throttle (5 gagal/email+IP/60 detik), cookie HttpOnly auth, dan remember-me TTL lebih panjang.
- Sanitasi external URL di Blade sudah dilakukan: tidak ada `http(s)://` hardcoded di `backend/resources/views` (termasuk halaman demo), untuk menghindari dependency pihak ketiga tidak terkontrol.
- Lanjutan hardening aset frontend juga sudah dilakukan: import Google Fonts pada SCSS dinonaktifkan, demo map/video eksternal pada aset `frontend/resources/js` dinetralisir, dan file sinkron di `backend/public/build/js` ikut diperbarui.
- **Web admin-only (server-side):** middleware `hcm.web.admin` (`EnsureHcmWebAdminPage`) pada `GET /promotion`, `/resignation`, `/termination` — non-admin (cookie API atau sesi web) di-redirect ke `/employee-dashboard`; admin mengikuti `User::isHcmAdmin()`. Lihat `docs/security/hcm-web-route-guard.md`.
- Gap saat ini: forgot-password dan session management lanjutan (multi-device control) belum final.

## Dokumentasi yang mungkin tertinggal

- `docs/api/openapi.yaml` disinkronkan bertahap dengan `/v1/hcm` (termasuk promotion/resignation); bila bertentangan, utamakan **`backend/routes/api.php`** dan spesifikasi markdown per fitur.

## Pembaruan berikutnya yang disarankan

1. Menyelaraskan `openapi.yaml` dan bagian “Leave Service” di `api-spec-phase-1.md` dengan path `/v1/hcm` atau menambahkan gateway `/v1/leave` proxy.
2. Memperbarui matriks role di spec jika RBAC penuh sudah diverifikasi di kode.
