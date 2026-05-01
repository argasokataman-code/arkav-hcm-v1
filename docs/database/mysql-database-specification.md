# MySQL Database Specification - Phase 1

Dokumen ini adalah spesifikasi database MySQL untuk implementasi Phase 1 microservice.

## 1) General standards

- DBMS: MySQL 8.0+
- Storage engine: InnoDB
- Charset: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`
- Timezone: UTC
- Primary key type: `BIGINT UNSIGNED AUTO_INCREMENT`
- Audit columns standar:
  - `created_at DATETIME`
  - `updated_at DATETIME`
  - optional `deleted_at DATETIME` untuk soft delete

## 2) Database separation

Rencana multi-database (tiap service satu DB):

- `arcav_identity_db`
- `arcav_hcm_core_db`
- `arcav_leave_attendance_db`

**Implementasi saat ini (Phase 1, single Laravel):** satu koneksi MySQL per environment; semua tabel migrasi berada dalam skema yang sama. Batas “service” tetap di level kode/route, bukan DB fisik terpisah.

Skema HCM aktif **tidak** memakai multi-tenant bawaan: tabel legacy `tenants`, `subscription_plans`, `subscription_plan_features`, `tenant_subscriptions` dan kolom `users.tenant_id` dihapus lewat migrasi `2026_04_11_100000_drop_legacy_tenant_subscription_tables` (bekas dump gabungan).

Update tenant foundation (April 2026):
- Core SaaS tabel baru ditambahkan: `companies`, `company_users`, `subscriptions`, `payments`, `company_settings`.
- Wave tenantization employee/org menambahkan `company_id` (nullable + indexed + backfill default company) ke:
  - `employee_profiles`
  - `departments`
  - `designations`
  - `teams`
- Wave tenantization attendance/shift menambahkan `company_id` (nullable + indexed + backfill default company) ke:
  - `attendance_records`
  - `hcm_shifts`
  - `hcm_schedule_timings`
- Wave tenantization payroll menambahkan `company_id` (nullable + indexed + backfill default company) ke:
  - `hcm_payroll_periods` (termasuk update unique key menjadi `(company_id, period_year, period_month)`)
  - `hcm_payroll_runs`
  - `hcm_payroll_lines`
  - `hcm_salary_components`
  - `hcm_overtime_types`
- Wave tenantization payroll-adjacent menambahkan `company_id` (nullable + indexed + backfill default company) ke:
  - `hcm_payroll_items`
  - `hcm_thr_yearly_settings` (unique key diperlebar menjadi `(company_id, calendar_year)`)
  - `hcm_thr_batches`

Tambahan schema compliance (April 2026):
- Tabel baru `export_reconciliation_evidences` untuk menyimpan bukti export sebelum action berisiko (finalize/disburse/mark-paid/verify).
- Scope evidence bersifat tenant-aware via `company_id` + pasangan `feature_key/action_key/scope_ref`.

### `export_reconciliation_evidences`
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (tenant scope)
- `feature_key VARCHAR(80) NOT NULL` (contoh: `payroll_run`, `invoice`, `payment`)
- `action_key VARCHAR(80) NOT NULL` (contoh: `finalize`, `disburse`, `mark_paid`, `verify`)
- `scope_ref VARCHAR(120) NOT NULL` (contoh: `run:24`, `invoice:81`)
- `exported_by_user_id BIGINT UNSIGNED NULL` (user pemicu export)
- `exported_at DATETIME NULL`
- `file_format VARCHAR(10) NOT NULL` (`csv|xlsx`)
- `file_path VARCHAR(500) NOT NULL`
- `row_count INT UNSIGNED NOT NULL DEFAULT 0`
- `filter_payload JSON NULL`
- `dataset_checksum CHAR(64) NULL` (sha256)
- `expires_at DATETIME NULL`
- `created_at`, `updated_at`

Index:
- `KEY exp_recon_scope_exported_idx (company_id, feature_key, action_key, scope_ref, exported_at)`
- `KEY exp_recon_user_exported_idx (exported_by_user_id, exported_at)`
- `KEY exp_recon_company_expires_idx (company_id, expires_at)`

Catatan: jika nanti dipecah deploy, migrasi dan FK perlu direview ulang.

## 3) Identity service schema (`arcav_identity_db`)

### `users`
- `id BIGINT UNSIGNED PK`
- `name VARCHAR(150) NOT NULL`
- `email VARCHAR(191) NOT NULL UNIQUE`
- `password_hash VARCHAR(255) NOT NULL`
- `status ENUM('active','inactive') NOT NULL DEFAULT 'active'`
- `last_login_at DATETIME NULL`
- `created_at`, `updated_at`

Index:
- `UNIQUE KEY uq_users_email (email)`
- `KEY idx_users_status (status)`

### `roles`
- `id BIGINT UNSIGNED PK`
- `code VARCHAR(50) NOT NULL UNIQUE` (contoh: `admin`, `manager`, `employee`)
- `name VARCHAR(100) NOT NULL`
- `created_at`, `updated_at`

### `permissions`
- `id BIGINT UNSIGNED PK`
- `code VARCHAR(100) NOT NULL UNIQUE`
- `name VARCHAR(150) NOT NULL`
- `created_at`, `updated_at`

### `user_roles`
- `id BIGINT UNSIGNED PK`
- `user_id BIGINT UNSIGNED NOT NULL`
- `role_id BIGINT UNSIGNED NOT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (user_id) REFERENCES users(id)`
- `FOREIGN KEY (role_id) REFERENCES roles(id)`
- `UNIQUE KEY uq_user_roles (user_id, role_id)`

### `role_permissions`
- `id BIGINT UNSIGNED PK`
- `role_id BIGINT UNSIGNED NOT NULL`
- `permission_id BIGINT UNSIGNED NOT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (role_id) REFERENCES roles(id)`
- `FOREIGN KEY (permission_id) REFERENCES permissions(id)`
- `UNIQUE KEY uq_role_permissions (role_id, permission_id)`

### `auth_tokens`
- `id BIGINT UNSIGNED PK`
- `user_id BIGINT UNSIGNED NOT NULL`
- `token_hash CHAR(64) NOT NULL UNIQUE`
- `expires_at DATETIME NOT NULL`
- `revoked_at DATETIME NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (user_id) REFERENCES users(id)`

## 4) Core HCM service schema (`arcav_hcm_core_db`)

### `departments`
- `id BIGINT UNSIGNED PK`
- `code VARCHAR(50) NOT NULL UNIQUE`
- `name VARCHAR(150) NOT NULL`
- `description TEXT NULL`
- `is_active TINYINT(1) NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

### `designations`
- `id BIGINT UNSIGNED PK`
- `code VARCHAR(50) NOT NULL UNIQUE`
- `name VARCHAR(150) NOT NULL`
- `description TEXT NULL`
- `is_active TINYINT(1) NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

### `employees`
- `id BIGINT UNSIGNED PK`
- `employee_no VARCHAR(50) NOT NULL UNIQUE`
- `user_id BIGINT UNSIGNED NULL` (reference ke identity service)
- `full_name VARCHAR(150) NOT NULL`
- `email VARCHAR(191) NOT NULL`
- `department_id BIGINT UNSIGNED NOT NULL`
- `designation_id BIGINT UNSIGNED NOT NULL`
- `employment_status ENUM('active','inactive','probation') NOT NULL DEFAULT 'active'`
- `join_date DATE NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (department_id) REFERENCES departments(id)`
- `FOREIGN KEY (designation_id) REFERENCES designations(id)`

Index:
- `UNIQUE KEY uq_employees_employee_no (employee_no)`
- `KEY idx_employees_department (department_id)`
- `KEY idx_employees_designation (designation_id)`
- `KEY idx_employees_user_id (user_id)`

### `policies`
- `id BIGINT UNSIGNED PK`
- `department_id BIGINT UNSIGNED NULL` (FK `departments`, null on delete)
- `name VARCHAR(150) NOT NULL`
- `description TEXT NOT NULL`
- `effective_date DATE NULL`
- `created_at`, `updated_at`

### `employee_profiles`
- `id BIGINT UNSIGNED PK`
- `user_id BIGINT UNSIGNED NOT NULL UNIQUE` (FK `users`, cascade on delete)
- `hire_date DATE NULL` — tanggal bergabung resmi (opsional; untuk tenure / THR; jika null UI/API memakai `users.created_at` sebagai `joinDate` efektif)
- `department_id BIGINT UNSIGNED NULL` (FK `departments`, null on delete)
- `designation_id BIGINT UNSIGNED NULL` (FK `designations`, null on delete)
- Field tambahan profil: `team`, `designation` (label denormalized / bebas jika tanpa master), `nik`, `place_of_birth`, `date_of_birth`, `gender`, `marital_status`, `religion`, `nationality`, `phone`, `address`, `bio`, data bank, JSON `emergency_contacts`, `education_items`, `experience_items`
- Komponen kompensasi: `base_salary DECIMAL(15,2) NOT NULL DEFAULT 0`, `fixed_allowance DECIMAL(15,2) NOT NULL DEFAULT 0`
- `created_at`, `updated_at`

### `teams`
- `id BIGINT UNSIGNED PK`
- `department_id BIGINT UNSIGNED NULL` (FK `departments`, null on delete)
- `name VARCHAR(150) NOT NULL`
- `is_active` boolean default true
- `created_at`, `updated_at`

### Riwayat employee ter-normalisasi (April 2026)

Untuk menjaga payroll/HCM tetap konsisten, data master employee kini juga disimpan ke tabel riwayat efektif berikut:

- **`employee_employment_history`**: `employee_id`, `employment_status`, `employee_type`, `start_date`, `end_date`, **`probation_end_date`**, `notes`, timestamps.
- **`employee_assignments`**: `employee_id`, `department_id`, `designation_id`, **`team_id`** (FK `teams`), fallback `team_name`, `manager_user_id`, `start_date`, `end_date`, `is_primary`, `notes`, timestamps.
- **`employee_compensations`**: `employee_id`, `salary_type` (`monthly|daily|hourly`), `base_salary`, `fixed_allowance`, `currency`, `effective_date`, `end_date`, `notes`, timestamps.
- **`employee_contracts`**: `employee_id`, `contract_type` (`pkwt|pkwtt`), `start_date`, `end_date`, `status` (`active|ended|terminated`), `notes`, timestamps.
- **`employee_bank_accounts`**: `employee_id`, `bank_name`, `account_number`, `account_holder_name`, `bank_ifsc_code`, `bank_branch`, `is_primary`, timestamps.
- **`employee_tax_profiles`**: `employee_id`, `npwp`, `tax_status`, `ptkp_status`, `effective_date`, `end_date`, timestamps. Runtime saat ini disederhanakan ke satu row efektif terbaru per employee untuk Indonesia.
- **`employee_benefits`**: `employee_id`, `bpjs_kesehatan_no`, `bpjs_ketenagakerjaan_no`, `effective_date`, `end_date`, timestamps.

### `hcm_shifts`
- `id BIGINT UNSIGNED PK`
- `code VARCHAR(64) NOT NULL UNIQUE`
- `name VARCHAR(255) NOT NULL`
- `start_time`, `end_time` (TIME)
- `description VARCHAR(500) NULL`
- `is_active` boolean default true
- `sort_order` small unsigned default 0
- `created_at`, `updated_at`

### `hcm_schedule_timings`
- `id BIGINT UNSIGNED PK`
- `user_id` FK unique (satu baris per user)
- `hcm_shift_id BIGINT UNSIGNED NULL` (FK `hcm_shifts`, null on delete) — jika terisi, jam efektif mengikuti shift
- `start_time`, `end_time` (TIME)
- `source`, `updated_by_user_id`
- `created_at`, `updated_at`

### `hcm_overtime_types`
- `id BIGINT UNSIGNED PK`
- `code VARCHAR(64) NOT NULL UNIQUE`
- `name VARCHAR(255) NOT NULL`
- `description VARCHAR(500) NULL`
- `payment_multiplier DECIMAL(8,2) NOT NULL DEFAULT 1.00`
- `is_active` boolean default true
- `sort_order` small unsigned default 0
- `created_at`, `updated_at`

### `hcm_roles` (User Management)
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (FK `companies`, null on delete)
- `code VARCHAR(80) NOT NULL`
- `name VARCHAR(150) NOT NULL`
- `description VARCHAR(2000) NULL`
- `status VARCHAR(30) NOT NULL DEFAULT 'active'`
- `is_system` boolean default false
- `created_at`, `updated_at`
- Constraint: unique (`company_id`, `code`)

### `hcm_permissions` (User Management)
- `id BIGINT UNSIGNED PK`
- `code VARCHAR(120) NOT NULL UNIQUE`
- `module`, `resource`, `action` (indexed)
- `name VARCHAR(150) NOT NULL`
- `description VARCHAR(2000) NULL`
- `is_active` boolean default true
- `created_at`, `updated_at`

### `hcm_role_permissions` (User Management)
- `id BIGINT UNSIGNED PK`
- `role_id BIGINT UNSIGNED NOT NULL` (FK `hcm_roles`, cascade on delete)
- `permission_id BIGINT UNSIGNED NOT NULL` (FK `hcm_permissions`, cascade on delete)
- `created_at TIMESTAMP NULL`
- Constraint: unique (`role_id`, `permission_id`)

### `hcm_user_roles` (User Management)
- `id BIGINT UNSIGNED PK`
- `user_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade on delete)
- `company_id BIGINT UNSIGNED NOT NULL` (FK `companies`, cascade on delete)
- `role_id BIGINT UNSIGNED NOT NULL` (FK `hcm_roles`, cascade on delete)
- `assigned_by_user_id BIGINT UNSIGNED NULL` (FK `users`, null on delete)
- `status VARCHAR(30) NOT NULL DEFAULT 'active'`
- `effective_from DATE NULL`
- `effective_until DATE NULL`
- `revoked_at TIMESTAMP NULL`
- `created_at`, `updated_at`

### `hcm_user_role_audits` (User Management)
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (FK `companies`, null on delete)
- `actor_user_id BIGINT UNSIGNED NULL` (FK `users`, null on delete)
- `target_user_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade on delete)
- `role_id BIGINT UNSIGNED NULL` (FK `hcm_roles`, null on delete)
- `action VARCHAR(80) NOT NULL`
- `notes TEXT NULL`
- `metadata JSON NULL`
- `created_at TIMESTAMP NOT NULL`

### `hcm_salary_components`
- `id BIGINT UNSIGNED PK`
- `code VARCHAR(64) NOT NULL UNIQUE`, `name VARCHAR(200) NOT NULL`, `description` TEXT NULL
- `kind` VARCHAR(32) NOT NULL — `addition` | `deduction`
- `category` VARCHAR(64) NOT NULL — subset per `kind` (lihat model `HcmSalaryComponent`)
- `category_uuid` CHAR(36) NULL (index) — FK opsional ke `hcm_salary_component_categories.uuid` (null on delete) untuk canonical category mapping berbasis UUID, sementara kolom `category` tetap dipertahankan untuk kompatibilitas legacy
- `legal_basis` VARCHAR(500) NULL, `legal_notes` TEXT NULL
- `default_percent` DECIMAL(8,4) NULL — persen default (mis. 1.0000 = 1%); NULL = nominal per siklus gaji
- `percent_basis` VARCHAR(64) NULL — dasar perhitungan jika `default_percent` terisi (`basic_wage`, `wage_bpjs_health`, `wage_bpjs_tk`, `gross_monthly_ter`, `thr_calculation_base`); keduanya harus null bersamaan untuk komponen nominal
- **UNIQUE KEY** `hcm_salary_components_kind_category_name_unique (kind, category, name)` — mencegah duplikasi nama komponen dalam kombinasi kind+category yang sama
- Flag boolean: `include_bpjs_health_wage_base`, `include_bpjs_tk_wage_base`, `include_thr_calculation_base`, `include_pph21_ter_gross`, `include_pph21_annual_reconciliation`, `subject_overtime_regulation`, `affects_net_pay`, `employer_cost_line`
- `is_system_locked` boolean default false — baris seed; tidak boleh dihapus
- Seed sistem aktif mencakup komponen integrasi payroll penting seperti `upah_pokok`, `tunjangan_tetap`, `upah_lembur`, `thr`, dan `kompensasi_pkwt`
- `sort_order` small unsigned, `is_active` boolean
- `created_at`, `updated_at`

### `hcm_payroll_periods` / `hcm_payroll_runs` / `hcm_payroll_lines` (April 2026)

Siklus **actual payroll** per kalender bulan:

- **`hcm_payroll_periods`**: `period_year` (small unsigned), `period_month` (1–12), `status` (default `open`; menjadi **`posted`** setelah ada run yang **finalized**), UNIQUE(`period_year`, `period_month`).
- **`hcm_payroll_runs`**: FK `hcm_payroll_period_id` → `hcm_payroll_periods` (cascade delete), `purpose` VARCHAR(32) default `monthly` (`monthly` = gaji rutin, `thr` = THR massal, `pkwt_compensation` = kompensasi PKWT standalone/off-cycle), `status` (`draft` | `finalized`), `calculated_at`, `finalized_at`, `finalized_by_user_id` nullable → `users` (null on delete). Index (`hcm_payroll_period_id`, `status`); index komposit (`hcm_payroll_period_id`, `purpose`, `status`) — migrasi `2026_04_15_105000_add_purpose_to_hcm_payroll_runs_table`, diperlebar oleh `2026_04_13_020000_widen_hcm_payroll_runs_purpose_length`.
- **`hcm_payroll_lines`**: FK `hcm_payroll_run_id` → `hcm_payroll_runs` (cascade), FK `user_id` → `users` (cascade), FK opsional `hcm_salary_component_id` → `hcm_salary_components` (null on delete), snapshot `component_code` / `component_name`, `kind`, `category`, `amount` DECIMAL(15,2), `sort_order`, `meta` JSON nullable. Index (`hcm_payroll_run_id`, `user_id`).

Detail API & backlog engine: `docs/api/hcm-payroll-api.md`, `docs/planning/payroll-lifecycle.md`.

### `hcm_thr_yearly_settings` (THR — pengaturan per tahun)

- `id`, `company_id` BIGINT UNSIGNED NULL (index), `calendar_year` SMALLINT UNSIGNED (tahun kalender perencanaan, mis. 2026)
- UNIQUE komposit: (`company_id`, `calendar_year`)
- `eid_date` DATE NOT NULL — tanggal Lebaran referensi
- `payment_date` DATE NULL — rencana transfer **THR** (biasanya terpisah dari jadwal gaji bulanan; sering 7–10 hari sebelum H)
- `calculation_cutoff_date` DATE NULL — cut-off perhitungan pro rata (mis. H-1)
- `notes` TEXT NULL
- `created_at`, `updated_at`

### `hcm_thr_batches` / `hcm_thr_batch_lines` (THR mass calculate & assign)

- **`hcm_thr_batches`**: `company_id` BIGINT UNSIGNED NULL (index), `calendar_year`, FK opsional `hcm_thr_yearly_setting_id` → `hcm_thr_yearly_settings` (null on delete), `cutoff_date` DATE, agregat `grand_total_eligible`, `eligible_line_count`, `total_line_count`, `status` (`draft` | `assigned`), `assigned_at`, FK `assigned_by_user_id` / `generated_by_user_id` → `users` (null on delete), FK opsional `hcm_payroll_period_id` / `hcm_payroll_run_id` setelah assign. Index tenant utama: (`company_id`, `calendar_year`, `status`). Migrasi: `2026_04_16_105000_create_hcm_thr_batch_tables` + `2026_04_22_090000_tenantize_thr_and_payroll_item_tables`.
- **`hcm_thr_batch_lines`**: FK `hcm_thr_batch_id` (cascade), FK `user_id` (cascade), **`thr_slip_public_no` VARCHAR(48) NOT NULL UNIQUE** — nomor slip resmi tercetak (format baru `THR-{tahun}-{ULID}`; data lama dari migrasi: `THR-{tahun}-{id}`), snapshot nama/no pegawai, `join_date_used`, `base_salary`, `fixed_allowance`, `reference_wage`, `months_of_service`, `multiplier`, `thr_gross`, `row_status` (`full` \| `pro_rata` \| `nihil` \| `invalid`), `eligible` boolean. UNIQUE(`hcm_thr_batch_id`, `user_id`). Migrasi tambahan: `2026_04_17_130000_add_thr_slip_public_no_to_hcm_thr_batch_lines`.

### `hcm_payroll_items` (katalog halaman Payroll Items)

Baris katalog slip / payroll (boleh **mirror** master atau **kustom**):

- FK opsional **`hcm_salary_component_id`** UNIQUE → `hcm_salary_components` (null on delete); jika null = item tanpa taut master.
- `company_id` BIGINT UNSIGNED NULL (index), `code`, `name`, `kind`, `category`, `notes` (TEXT nullable), `sort_order`, `is_active`, timestamps.

Seed migrasi mengisi taut ke `upah_pokok`, `tunjangan_tetap_transport`, `upah_lembur` serta satu contoh kustom. API baca: `GET /v1/hcm/payroll-items` — `docs/api/hcm-payroll-items-api.md`.

## 5) Leave attendance service schema (`arcav_leave_attendance_db`)

### `leave_types` (implementasi migrasi saat ini)

Sudah ada di monolith (migrasi `2026_04_18_000001_create_leave_management_foundation_tables`).

- `id BIGINT UNSIGNED PK`
- `company_id` BIGINT UNSIGNED NULL (index)
- `code` VARCHAR(64) UNIQUE
- `name` VARCHAR(150)
- `is_paid`, `requires_approval`, `requires_attachment`, `deduct_from_balance`, `is_active` (boolean)
- `created_at`, `updated_at`

### `leave_policies` (implementasi migrasi saat ini)

- `id`
- `company_id` NULL (index)
- `leave_type_id` FK -> `leave_types`
- `name`
- `days_per_year` DECIMAL(8,2)
- `min_service_months` SMALLINT UNSIGNED
- `is_prorated`, `carry_forward`, `is_earned_leave`, `allow_negative_balance`
- `max_carry_days`, `expire_after_days` SMALLINT UNSIGNED NULL
- `effective_from`, `effective_to`
- `created_at`, `updated_at`

### `leave_policy_assignments` (implementasi migrasi saat ini)

- `id`
- `company_id` NULL (index)
- `policy_id` FK -> `leave_policies`
- `employee_id` FK -> `users`
- `effective_date`, `end_date`
- `created_at`, `updated_at`

Menggantikan pola array assignment (`assignee_user_ids`) untuk assignment policy per karyawan.

### `employee_leave_balances` (implementasi migrasi saat ini)

- `id`
- `company_id` NULL (index)
- `employee_id` FK -> `users`
- `leave_type_id` FK -> `leave_types`
- `year` SMALLINT UNSIGNED
- `balance`, `used`, `expired`, `carried_forward` DECIMAL(10,2)
- `created_at`, `updated_at`
- UNIQUE(`employee_id`, `leave_type_id`, `year`)

### `leave_ledger` (implementasi migrasi saat ini)

Source of truth transaksi saldo cuti.

- `id`
- `company_id` NULL (index)
- `employee_id` FK -> `users`
- `leave_type_id` FK -> `leave_types`
- `policy_id` FK NULL -> `leave_policies`
- `transaction_type` VARCHAR(40)
- `amount`, `balance_after` DECIMAL(10,2)
- `reference_type`, `reference_id` (untuk idempotency/audit)
- `occurred_on` DATE
- `notes` TEXT NULL
- `created_by` BIGINT UNSIGNED NULL
- `created_at`, `updated_at`

### `leave_approvals` (implementasi migrasi saat ini)

- `id`
- `company_id` NULL (index)
- `leave_request_id` FK -> `leave_requests`
- `approver_id` FK -> `users`
- `level`, `status`, `acted_at`, `notes`
- `created_at`, `updated_at`

### `leave_requests` (implementasi migrasi saat ini)

Versi dokumen asli memakai `employee_id` + `leave_type_id`. **Tabel aktual di repo** (lihat migrasi):

- `company_id` BIGINT UNSIGNED NULL INDEX — tenant scope (wave tenantization leave 2026-04-13); backfill ke default_company
- `user_id` FK → `users`
- `leave_type` string
- `date_from`, `date_to` DATE
- `days` DECIMAL(5,1)
- `status` string (mis. pending)
- `notes` TEXT NULL
- `created_at`, `updated_at`

### `hcm_leave_type_settings` / `hcm_leave_custom_policies`

Konfigurasi tipe cuti dan kebijakan kustom (admin), dipakai API leave-settings.
Status saat ini: masih dipakai oleh UI/settings legacy, berdampingan dengan foundation table baru.

### `holidays` / `overtime_requests`

Master hari libur dan pengajuan lembur (user-scoped); tabel `overtime_requests` mendukung FK opsional `hcm_overtime_type_id` (nullOnDelete) ke master tipe lembur, dan FK opsional **`hcm_salary_component_id`** (nullOnDelete) ke **`hcm_salary_components`** — penautan ke baris slip “upah lembur” untuk integrasi payroll.

### `holiday_calendars` (implementasi migrasi saat ini)

- `id`
- `company_id` NULL (index)
- `date`
- `name`
- `is_national`, `is_joint_leave`, `deduct_from_leave` (boolean)
- `source` VARCHAR(20)
- `last_synced_at` TIMESTAMP NULL
- `created_at`, `updated_at`
- UNIQUE(`company_id`, `date`, `name`)

### Tambahan tabel future-ready leave (migrasi `2026_04_19_010000_create_leave_future_development_tables`)

#### `leave_approval_workflows`

- Menentukan workflow approval per leave type + rentang hari + masa efektif.
- Kolom utama: `company_id`, `leave_type_id`, `name`, `min_days`, `max_days`, `is_active`, `effective_from`, `effective_to`.

#### `leave_approval_workflow_steps`

- Step detail workflow multi-level approval.
- Kolom utama: `workflow_id`, `level`, `approver_scope`, `approver_user_id`, `designation_id`, `requires_all_approvers`, `sla_hours`.
- UNIQUE(`workflow_id`, `level`).

#### `leave_blackout_dates`

- Periode pembatasan cuti.
- Kolom utama: `company_id`, `leave_type_id`, `name`, `rule_type`, `start_date`, `end_date`, `max_people_per_day`, `reason`, `is_active`.

#### `leave_request_breakdowns`

- Breakdown per tanggal/jam untuk satu request (full-day, half-day, hourly).
- Kolom utama: `leave_request_id`, `leave_date`, `unit_type`, `session`, `minutes`, `is_working_day`, `is_holiday`, `holiday_name`, `deducted_days`, `meta`.
- UNIQUE(`leave_request_id`, `leave_date`, `session`).

#### `leave_request_attachments`

- Metadata dokumen pendukung pengajuan cuti dan verifikasi HR.
- Kolom utama: `leave_request_id`, `uploaded_by`, `document_type`, `file_name`, `file_path`, `mime_type`, `file_size_bytes`, `is_required`, `notes`, `verified_by`, `verified_at`.

#### `leave_request_audits`

- Audit perubahan request cuti (activity log).
- Kolom utama: `leave_request_id`, `actor_user_id`, `action`, `from_status`, `to_status`, `changes`.

### `attendance_records` (implementasi aktual berbeda dari skema referensi di bawah)

**Migrasi Laravel saat ini** memakai antara lain `user_id`, `work_date`, `check_in_at`, `check_out_at`, koordinat GPS punch (`check_in_latitude`, `check_in_longitude`, `check_out_latitude`, `check_out_longitude`, `decimal(10,7)` nullable), status koreksi, break fields — lihat `backend/database/migrations/*attendance*`. Indeks tambahan performa: `KEY attendance_records_work_date_index (work_date)` (migrasi `2026_04_10_120000_add_scale_indexes_for_hcm_queries`). Pada **MySQL**, tabel `users` memiliki `FULLTEXT` `users_name_email_fulltext (name, email)` untuk pencarian direktori karyawan (lihat migrasi yang sama).

Skema referensi asli (multi-service):

- `employee_id`, `attendance_date`, `check_in_time`, `check_out_time`, `status`, `notes`
- `UNIQUE (employee_id, attendance_date)`

## 6) Data integrity rules

- `end_date >= start_date` pada `leave_requests`
- `check_out_time >= check_in_time` jika keduanya terisi
- Email format validation dilakukan di application layer
- Cross-service ID validation dilakukan via API call antar service

## 7) Migration order (recommended)

1. Identity: `users`, `auth_tokens`, session/cache Laravel bawaan
2. Core HCM: `departments`, `designations`, `employees` / profil terkait, `policies`, `employee_profiles`
3. `hcm_shifts` sebelum atau bersamaan dengan `hcm_schedule_timings` (FK `hcm_shift_id` ditambahkan setelah `hcm_shifts` ada)
4. Attendance & leave: `attendance_records`, `hcm_schedule_timings`, `hcm_overtime_types`, `holidays`, `leave_requests`, `overtime_requests` (+ FK `hcm_overtime_type_id`, `hcm_salary_component_id` → `hcm_salary_components`), tabel leave-settings

Urutan pasti mengikuti timestamp file di `backend/database/migrations/`.

## 8) Seed minimum data

- Roles: `admin`, `manager`, `employee`
- Permissions dasar per domain
- Department sample: `HR`, `Engineering`, `Finance`
- Designation sample: `Staff`, `Senior Staff`, `Lead`
- Leave types sample: `annual`, `sick`, `unpaid`
