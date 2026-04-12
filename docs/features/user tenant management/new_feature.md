# User Tenant Management Blueprint

Dokumen ini menjadi acuan pengembangan untuk membawa sistem HCM Arcav dari kondisi saat ini menuju arsitektur single-company yang tetap kompatibel dan multi-tenant SaaS yang aman.

Dokumen ini sengaja berangkat dari kondisi kode yang sudah ada di repo, bukan dari asumsi greenfield.

## 1. Tujuan

Target akhir pengembangan:

1. Sistem tetap bisa dipakai untuk skenario satu perusahaan.
2. Sistem bisa melayani banyak tenant dalam satu database bersama.
3. Semua modul HCM memakai isolasi data berbasis `company_id`.
4. Payroll, leave, dan attendance tetap stabil selama masa transisi.
5. Arsitektur payroll bergerak bertahap dari model legacy `hcm_salary_components` ke model `payroll item -> employee compensation -> payroll line`.

## 2. Snapshot Kondisi Saat Ini

Kondisi aktual repo per April 2026:

1. Sistem belum memiliki core SaaS table untuk company membership dan subscription.
2. Leave dan holiday sudah mulai tenant-aware secara parsial melalui `company_id`.
3. Employee core, employee normalization, payroll periods, payroll runs, dan payroll lines masih global.
4. Payroll item sudah mulai diperkenalkan, tetapi masih menjadi bridge ke `hcm_salary_components`.
5. Banyak query business-critical masih mengasumsikan satu populasi user global, terutama pada payroll draft generation.

Implikasinya:

1. Ini bukan proyek membangun multi-tenant dari nol.
2. Ini adalah proyek standardisasi tenant boundary yang saat ini baru hidup di sebagian domain.
3. Risiko terbesar ada pada modul yang sudah berjalan tetapi belum konsisten terhadap tenant context.

## 3. Prinsip Arsitektur

### 3.1 Tenant isolation

1. Semua data bisnis perusahaan harus punya `company_id`, kecuali tabel global yang memang lintas tenant.
2. Semua query read dan write wajib berjalan di bawah tenant context yang eksplisit.
3. Tenant context tidak boleh hanya dititipkan di frontend; backend harus menjadi source of truth.

### 3.2 Backward compatibility

1. Existing single-company deployment harus tetap bisa berjalan.
2. Existing data harus dibackfill ke default company sebelum constraint diperketat.
3. Perubahan harus bersifat phased, bukan big bang.

### 3.3 Payroll safety first

1. Jangan memutus logic payroll existing sekaligus.
2. `hcm_salary_components` diperlakukan sebagai legacy master yang didepresiasi bertahap.
3. New logic harus membaca `hcm_payroll_items` sebagai master bisnis utama.

### 3.4 UI consistency

1. Semua halaman baru wajib reuse template Bootstrap yang sudah ada.
2. Jangan memperkenalkan design system baru.
3. Menu dan akses harus tetap sinkron dengan matriks role dan permission HCM aktif.

## 4. System Impact Analysis

### 4.1 Tabel global yang tetap tanpa `company_id`

1. `users`
2. `plans`
3. `migrations`
4. `jobs`
5. `failed_jobs`
6. `cache`
7. `cache_locks`

Catatan:

1. `users` tetap global agar satu user bisa bergabung ke lebih dari satu company.
2. Relasi user ke tenant tidak disimpan di `users`, tetapi di tabel penghubung.

### 4.2 Core SaaS table yang wajib ditambahkan

1. `companies`
2. `company_users`
3. `subscriptions`
4. `payments`
5. `company_settings`

Tabel tambahan yang sangat disarankan:

1. `company_branches`
2. `invoices`
3. `roles`
4. `permissions`
5. `company_role_user` atau pola serupa untuk RBAC tenant-level

### 4.3 Tabel tenant yang wajib distandardisasi dengan `company_id`

#### Employee and organization

1. `employee_profiles`
2. `employee_assignments`
3. `employee_bank_accounts`
4. `employee_benefits`
5. `employee_compensations`
6. `employee_contracts`
7. `employee_educations`
8. `employee_emergency_contacts`
9. `employee_employment_history`
10. `employee_experiences`
11. `employee_leave_balances`
12. `employee_tax_profiles`
13. `departments`
14. `designations`
15. `teams`

#### Attendance and shift

1. `attendance_records`
2. `hcm_shifts`
3. `hcm_schedule_timings`
4. `holiday_calendars`
5. `holidays`

#### Leave

1. `leave_policies`
2. `leave_policy_assignments`
3. `leave_types`
4. `leave_requests`
5. `leave_request_attachments`
6. `leave_request_audits`
7. `leave_request_breakdowns`
8. `leave_approvals`
9. `leave_approval_workflows`
10. `leave_approval_workflow_steps`
11. `leave_blackout_dates`
12. `leave_ledger`

#### Payroll and compensation

1. `hcm_payroll_items`
2. `hcm_payroll_lines`
3. `hcm_payroll_periods`
4. `hcm_payroll_runs`
5. `hcm_thr_batches`
6. `hcm_thr_batch_lines`
7. `hcm_thr_disbursements`
8. `hcm_thr_yearly_settings`
9. `hcm_overtime_types`
10. `overtime_requests`

#### Performance, training, lifecycle, ticketing

1. `performance_cycles`
2. `performance_goal_types`
3. `performance_goals`
4. `performance_indicator_items`
5. `performance_indicator_templates`
6. `performance_review_scores`
7. `performance_reviews`
8. `hcm_trainings`
9. `hcm_training_types`
10. `hcm_trainers`
11. `hcm_training_participants`
12. `hcm_promotions`
13. `hcm_resignations`
14. `hcm_terminations`
15. `tickets`
16. `ticket_comments`
17. `ticket_attachments`
18. `ticket_categories`
19. `ticket_assignment_histories`

### 4.4 Kondisi per domain: current vs target

| Domain | Kondisi saat ini | Target |
| --- | --- | --- |
| Company and subscription | Belum ada | Full SaaS core |
| Employee master | Global | Tenant-scoped |
| Employee normalized history | Global | Tenant-scoped |
| Leave | Sudah parsial tenant-aware | Konsisten dan wajib tenant-scoped |
| Holiday and calendar | Sudah parsial tenant-aware | Konsisten dan aman untuk fallback global |
| Attendance | Mayoritas belum tenant-aware | Tenant-scoped |
| Payroll periods and runs | Global | Tenant-scoped |
| Payroll lines | Global | Tenant-scoped |
| THR and overtime | Belum konsisten tenant-scoped | Tenant-scoped |
| Performance, training, ticketing | Perlu standardisasi | Tenant-scoped |

### 4.5 Dependency map kritikal

#### Core tenant dependency

1. `users` -> `company_users` -> `companies`
2. `companies` -> `subscriptions` -> `payments`
3. `companies` -> `company_settings`

#### Employee dependency

1. `users` -> `employee_profiles`
2. `employee_profiles` -> assignment, compensation, contract, bank, tax, benefits, education, emergency contact, experience, employment history
3. `employee_profiles` -> leave balance, attendance, overtime, payroll, performance, training, ticketing, lifecycle

#### Payroll dependency

1. `hcm_payroll_items` = master definition
2. `employee_compensations` = assignment per employee
3. `attendance_records`, `overtime_requests`, `leave_ledger` = transaction source
4. `hcm_payroll_periods` -> `hcm_payroll_runs` -> `hcm_payroll_lines` = payroll transaction chain

#### Leave dependency

1. `leave_types` -> `leave_policies`
2. `leave_policies` -> `leave_policy_assignments`
3. `leave_requests` -> `leave_approvals`
4. `leave_requests` -> `leave_ledger`
5. `leave_ledger` -> `employee_leave_balances`

### 4.6 High-risk areas

#### Payroll

1. `PayrollDraftBuilder` saat ini membaca populasi user global dan berpotensi mencampur data antar company.
2. Payroll period uniqueness saat ini global per bulan-tahun, padahal nanti harus unik per company.
3. Payroll line masih terhubung kuat ke `hcm_salary_components`, sehingga refactor tenant dan refactor arsitektur payroll akan saling mempengaruhi.

#### Leave

1. Sebagian logic sudah mengenal `company_id`, tetapi belum menjadi enforced standard di semua flow.
2. Ada risiko data campuran antara leave type global dan leave type tenant-specific bila aturan tidak dipertegas dari awal.
3. Ledger, policy, dan approval harus memakai tenant context yang konsisten agar tidak terjadi salah saldo.

#### Attendance

1. Attendance, schedule, shift, dan holiday harus mengikuti company yang sama dengan employee active assignment.
2. Integrasi attendance ke payroll dan leave akan rusak bila attendance masih global.

## 5. Database Refactoring Plan

### 5.1 Core SaaS schema

#### `companies`

Kolom minimum:

1. `id`
2. `code`
3. `name`
4. `legal_name`
5. `status`
6. `owner_user_id`
7. `timezone`
8. `currency`
9. `country_code`
10. `created_at`
11. `updated_at`

#### `company_users`

Kolom minimum:

1. `id`
2. `company_id`
3. `user_id`
4. `role`
5. `status`
6. `joined_at`
7. `invited_by_user_id`
8. `created_at`
9. `updated_at`

Constraint minimum:

1. Unique `company_id + user_id`

#### `subscriptions`

Kolom minimum:

1. `id`
2. `company_id`
3. `plan_id`
4. `status`
5. `starts_at`
6. `ends_at`
7. `trial_ends_at`
8. `auto_renew`
9. `metadata`
10. `created_at`
11. `updated_at`

#### `payments`

Kolom minimum:

1. `id`
2. `company_id`
3. `subscription_id`
4. `amount`
5. `currency`
6. `status`
7. `gateway`
8. `gateway_reference`
9. `paid_at`
10. `metadata`
11. `created_at`
12. `updated_at`

#### `company_settings`

Kolom minimum:

1. `id`
2. `company_id`
3. `key`
4. `value`
5. `type`
6. `created_at`
7. `updated_at`

Constraint minimum:

1. Unique `company_id + key`

### 5.2 Standardisasi tenant column

Aturan schema untuk seluruh tabel tenant:

1. Tambahkan `company_id` nullable pada fase awal migrasi.
2. Backfill seluruh row existing ke default company.
3. Tambahkan index yang sesuai, minimal `company_id` dan kombinasi query utama.
4. Setelah seluruh read/write path siap, baru pertimbangkan `NOT NULL` dan foreign key.

### 5.3 Perubahan uniqueness

Unique global lama harus ditinjau ulang menjadi unique per tenant. Contoh prioritas:

1. `departments.name`
2. `designations.name`
3. `teams.name`
4. `leave_types.code`
5. `hcm_payroll_items.code`
6. `hcm_payroll_periods.period_year + period_month`

Target uniqueness baru:

1. `company_id + business_key`
2. Untuk data optional global, gunakan aturan eksplisit agar tidak clash dengan tenant row.

### 5.4 Payroll architecture correction

#### Target model

1. `hcm_payroll_items` menjadi master definisi payroll component.
2. `employee_compensations` menyimpan assignment komponen dan nilai efektif per employee.
3. `hcm_payroll_lines` menyimpan hasil transaksi per payroll run.

#### Kebijakan transisi

1. `hcm_salary_components` tetap dibaca untuk compatibility layer.
2. New feature tidak boleh menambah coupling baru ke `hcm_salary_components`.
3. Semua endpoint dan service baru harus mengutamakan `hcm_payroll_items`.

## 6. Detailed Migration Plan

### Phase 0. Analysis and design freeze

Deliverable:

1. Mapping tabel final dan ownership tenant.
2. Definisi tenant context resolution.
3. Daftar query berisiko tinggi yang wajib diubah lebih dulu.

Exit criteria:

1. Skema target disetujui.
2. Strategi backfill disetujui.
3. Strategi cutover payroll disetujui.

### Phase 1. SaaS core foundation

Scope:

1. Buat `companies`.
2. Buat `company_users`.
3. Buat `subscriptions`.
4. Buat `payments`.
5. Buat `company_settings`.
6. Siapkan seeded default company untuk deployment existing.

Output:

1. User existing otomatis terhubung ke default company sebagai owner/admin sesuai strategi backfill.
2. Tenant context sudah bisa diselesaikan dari request authenticated user.

### Phase 2. Tenantize employee and organization core

Scope:

1. Tambahkan `company_id` ke `employee_profiles` dan semua tabel turunannya.
2. Tambahkan `company_id` ke `departments`, `designations`, dan `teams`.
3. Backfill data berdasarkan hubungan employee dan default company.

Output:

1. Semua employee data hanya hidup di satu tenant.
2. Query employee list dan employee detail sudah tenant-safe.

### Phase 3. Tenantize attendance, leave, overtime, THR

Scope:

1. Standardisasi `company_id` pada attendance, shift, schedule, holiday, leave, overtime, THR.
2. Rapikan fallback logic untuk leave type dan holiday yang boleh global.
3. Tambahkan test cross-tenant forbidden access.

Output:

1. Attendance dan leave engine tidak lagi global.
2. Overtime dan THR mengikuti tenant yang sama dengan employee.

### Phase 4. Tenantize payroll transaction chain

Scope:

1. Tambahkan `company_id` ke `hcm_payroll_items`, `hcm_payroll_periods`, `hcm_payroll_runs`, `hcm_payroll_lines`.
2. Ubah period uniqueness menjadi per company.
3. Ubah payroll draft builder agar selalu bekerja di tenant context.
4. Tambahkan filter tenant ke seluruh payroll API dan reporting.

Output:

1. Satu company hanya melihat payroll miliknya.
2. Satu periode bulan yang sama bisa ada di banyak company tanpa bentrok.

### Phase 5. Payroll legacy deprecation

Scope:

1. Pisahkan pemakaian business master ke `hcm_payroll_items`.
2. Ubah service payroll, overtime, dan slip agar tidak lagi bergantung langsung pada `hcm_salary_components` untuk logic baru.
3. Sisakan compatibility adapter hanya untuk data lama dan seed lawas.

Output:

1. `hcm_salary_components` berubah fungsi menjadi legacy support atau reference data, bukan pusat arsitektur payroll.

### Phase 6. SaaS onboarding and billing flow

Scope:

1. User register.
2. Create company.
3. Create owner membership.
4. Select plan.
5. Create subscription.
6. Payment recording dan expiry handling.

Output:

1. End-to-end SaaS onboarding hidup.
2. Feature access bisa dibatasi berdasarkan status subscription.

## 7. Backend Refactoring Plan

### 7.1 Tenant context resolution

Tenant context harus bisa diperoleh dari kombinasi berikut:

1. Authenticated user.
2. Active company selection jika user tergabung ke banyak company.
3. Route model atau resource company-bound yang diverifikasi di backend.

Implementasi minimum yang dibutuhkan:

1. Tenant resolver service.
2. Middleware untuk menetapkan active company.
3. Helper atau service container binding agar controller dan service tidak menghitung tenant secara manual berulang-ulang.

### 7.2 Query safety

Semua repository, service, dan controller harus ditinjau dengan aturan berikut:

1. `select` wajib memfilter `company_id` untuk tabel tenant.
2. `insert` dan `update` wajib menulis `company_id` dari tenant context, bukan dari input mentah user.
3. `exists`, `unique`, dan validation rule wajib mempertimbangkan company boundary.

### 7.3 Model and scope conventions

Tambahan teknis yang disarankan:

1. Trait `BelongsToCompany` untuk model tenant.
2. Local scope `forCompany($companyId)`.
3. Observer atau service layer untuk auto-fill `company_id` bila aman.

### 7.4 RBAC refactor

Target jangka menengah:

1. Pisahkan role global aplikasi dari role per company.
2. `company_users.role` menjadi baseline membership role.
3. Jika perlu, roles and permissions tenant-level ditambahkan setelah core tenant stabil.

### 7.5 Payroll-specific backend work

1. Refactor payroll draft builder agar menerima `company_id` atau `Company` sebagai parameter utama.
2. Refactor payroll APIs untuk scoping per company di list, detail, finalize, slip, export, dan admin summary.
3. Refactor payroll item management agar unique code dan lookup terjadi per company.
4. Tambahkan adapter untuk membaca legacy `hcm_salary_components` selama masa transisi.

### 7.6 Leave-specific backend work

1. Pastikan seluruh request, approval, ledger, balance, policy, dan holiday query memakai tenant context yang sama.
2. Putuskan dari awal apakah `leave_types` boleh global, tenant-only, atau hybrid. Jangan biarkan ambigu.
3. Tambahkan guard agar employee tidak bisa memakai policy dari company lain walaupun ID valid.

### 7.7 Attendance-specific backend work

1. Schedule timing, shift, attendance record, dan holiday source harus company-bound.
2. Attendance summary dan employee dashboard wajib hanya membaca data tenant aktif.
3. Integrasi attendance ke payroll harus menggunakan employee tenant yang sama.

## 8. Frontend and UI Plan

### 8.1 Prinsip

1. Reuse template existing.
2. Jangan mengubah visual language.
3. Fokus pada flow dan keamanan akses, bukan redesign.

### 8.2 Halaman baru minimum

1. Company profile.
2. Company settings.
3. Users and roles.
4. Subscription and billing.

### 8.3 Perubahan halaman existing

1. Header atau topbar perlu active company selector bila satu user bisa punya banyak company.
2. Semua halaman admin HCM harus memuat data dari active company saja.
3. Employee-facing pages harus tetap bekerja tanpa melihat data tenant lain.

### 8.4 Menu target

1. Dashboard
2. Employee
3. Organization
4. Attendance
5. Leave
6. Payroll
7. Compensation
8. Overtime
9. Performance
10. Training
11. Helpdesk
12. Settings

### 8.5 Frontend technical tasks

1. Tambahkan bootstrap data untuk active company.
2. Tambahkan tenant-aware API client behavior bila endpoint membutuhkan company selection.
3. Pastikan permission rendering mengikuti backend `me` payload atau company membership payload.

## 9. Risk Analysis

### 9.1 Data leakage risk

Masalah:

1. Query lama yang lupa filter tenant akan membocorkan data antar company.

Mitigasi:

1. Audit query per domain.
2. Tambahkan test cross-tenant.
3. Tambahkan scope standar untuk model tenant.

### 9.2 Payroll regression risk

Masalah:

1. Payroll adalah area paling sensitif secara finansial dan legal.

Mitigasi:

1. Tenantize payroll setelah employee, leave, attendance, dan overtime context sudah rapi.
2. Sediakan regression test untuk draft, finalize, my slip, admin slip, THR, overtime pay, dan export.
3. Gunakan dual-read atau compatibility adapter selama masa transisi legacy.

### 9.3 Migration risk

Masalah:

1. Menambah `company_id` ke tabel besar bisa memicu kegagalan backfill atau constraint mismatch.

Mitigasi:

1. Tambah kolom nullable terlebih dahulu.
2. Backfill dalam migrasi portable atau command terpisah yang idempotent.
3. Baru perketat constraint setelah aplikasi siap.

### 9.4 Hybrid state risk

Masalah:

1. Selama fase transisi, sebagian domain sudah tenant-aware dan sebagian belum.

Mitigasi:

1. Tetapkan phase boundary yang jelas.
2. Jangan menganggap tenant-ready sebelum seluruh dependency domain itu selesai.
3. Cantumkan status per modul di dokumentasi planning dan implementation status.

### 9.5 Subscription gating risk

Masalah:

1. Billing restriction yang terlalu cepat bisa memblokir flow HCM utama.

Mitigasi:

1. Luncurkan billing visibility lebih dulu.
2. Aktifkan enforcement fitur setelah core company membership stabil.

## 10. Final Implementation Roadmap

### Wave 1. Foundation

1. Finalkan schema `companies`, `company_users`, `subscriptions`, `payments`, `company_settings`.
2. Tambahkan default company untuk existing deployment.
3. Bangun tenant resolver dan company membership model.

### Wave 2. Tenant-safe employee core

1. Tenantize employee profile dan tabel turunannya.
2. Tenantize department, designation, team.
3. Ubah employee API dan UI menjadi tenant-safe.

### Wave 3. Tenant-safe leave and attendance

1. Standardisasi leave, holiday, attendance, shift, schedule, overtime.
2. Tambahkan regression test cross-tenant untuk approval, balance, dan attendance summary.

### Wave 4. Tenant-safe payroll

1. Tenantize payroll period, run, line, item, THR.
2. Refactor payroll draft builder dan slip endpoints.
3. Verifikasi payroll history, finalize, reset payment, export, admin slip, and self slip.

### Wave 5. Payroll legacy transition

1. Kurangi coupling ke `hcm_salary_components`.
2. Pindahkan pusat master bisnis ke `hcm_payroll_items`.
3. Sisakan compatibility bridge yang terdokumentasi jelas.

### Wave 6. SaaS onboarding and billing

1. Register -> create company -> assign owner.
2. Plan selection -> subscription -> payment.
3. Feature restriction berdasarkan subscription status.

## 11. Prioritas Implementasi

Urutan kerja yang direkomendasikan:

1. Tenant resolver dan core SaaS tables.
2. Employee and organization tenantization.
3. Leave and attendance standardization.
4. Payroll and THR tenantization.
5. Payroll legacy decoupling.
6. SaaS onboarding and billing UI.

Urutan ini dipilih karena employee, leave, attendance, overtime, dan payroll saling bergantung. Memulai dari billing atau UI multi-company tanpa tenant boundary backend yang stabil akan memperbesar risiko regresi.

## 12. Definition of Done Per Phase

Satu phase dianggap selesai bila:

1. Schema, model, service, API, dan UI untuk scope phase tersebut sudah tenant-safe.
2. Ada test happy path dan forbidden cross-tenant path.
3. Dokumentasi fitur, planning, dan OpenAPI yang terdampak sudah di-update.
4. Tidak ada query utama yang masih global untuk domain phase tersebut.

## 13. Keputusan Produk yang Harus Dikunci Sebelum Coding Besar

1. Apakah `leave_types` akan hybrid global plus tenant, atau tenant-only.
2. Apakah satu user boleh aktif di banyak company dalam satu akun.
3. Apakah role tenant-level cukup sederhana di `company_users.role` pada fase awal.
4. Kapan billing enforcement mulai memblokir fitur, dan fitur mana saja yang terdampak.
5. Seberapa lama `hcm_salary_components` dipertahankan sebagai compatibility layer.

## 14. Rekomendasi Eksekusi Praktis

1. Jangan buka semua modul sekaligus dalam satu PR.
2. Selesaikan satu wave secara end-to-end agar scope tidak bocor.
3. Payroll harus diperlakukan sebagai subprogram tersendiri dengan regression matrix yang jelas.
4. Setelah blueprint ini disetujui, breakdown berikutnya sebaiknya berupa task list teknis per wave.
