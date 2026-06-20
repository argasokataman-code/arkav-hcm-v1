# Core HCM: Employee Profiles & Normalized History

Profil karyawan dan tabel riwayat ter-normalisasi.

## `employee_profiles`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope (wave tenantization `2026_04_21_100000_tenantize_employee_org_core_tables`)
- `user_id BIGINT UNSIGNED NOT NULL UNIQUE` (FK `users`, cascade on delete)
- `hire_date DATE NULL` — tanggal bergabung resmi (opsional; untuk tenure / THR; jika null UI/API memakai `users.created_at` sebagai `joinDate` efektif)
- `department_id BIGINT UNSIGNED NULL` (FK `departments`, null on delete)
- `designation_id BIGINT UNSIGNED NULL` (FK `designations`, null on delete)
- `manager_user_id BIGINT UNSIGNED NULL` (FK `users`, null on delete)
- `team VARCHAR(100) NULL` — label team denormalized (legacy; runtime sekarang pakai `employee_assignments.team_id` → `teams` table)
- `designation VARCHAR(150) NULL` — label designation denormalized (legacy fallback)
- `employment_status VARCHAR(50) NULL` — status aktif/inactive (legacy; runtime pakai `employee_employment_history`)
- Personal identity: `nik VARCHAR(100) NULL` (encrypted), `place_of_birth VARCHAR(150) NULL`, `date_of_birth DATE NULL`, `gender VARCHAR(20) NULL`, `marital_status VARCHAR(30) NULL`, `religion VARCHAR(50) NULL`, `nationality VARCHAR(50) NULL`
- Contact: `phone VARCHAR(50) NULL`, `address TEXT NULL`, `address_province_code VARCHAR(10) NULL`, `address_regency_code VARCHAR(10) NULL`, `address_district_code VARCHAR(10) NULL`, `address_postal_code VARCHAR(10) NULL`
- `bio TEXT NULL`
- `profile_photo_path VARCHAR(500) NULL`
- Bank (legacy; runtime pakai `employee_bank_accounts`): `bank_name VARCHAR(150) NULL`, `bank_account_no VARCHAR(100) NULL`, `bank_ifsc_code VARCHAR(100) NULL`, `bank_branch VARCHAR(150) NULL`
- Compensation (legacy; runtime pakai `employee_compensations`): `base_salary DECIMAL(15,2) NOT NULL DEFAULT 0`, `fixed_allowance DECIMAL(15,2) NOT NULL DEFAULT 0`
- Contract (legacy; runtime pakai `employee_contracts`): `contract_type VARCHAR(50) NULL`, `contract_start_date DATE NULL`, `contract_end_date DATE NULL`
- JSON legacy (pre-normalized): `emergency_contacts JSON NULL`, `education_items JSON NULL`, `experience_items JSON NULL`
- `created_at`, `updated_at`

**Note:** Runtime utama sekarang memakai **normalized history tables** (lihat di bawah). Kolom legacy di `employee_profiles` tetap dipertahankan untuk kompatibilitas migrasi bertahap.

---

## Normalized History Tables (April 2026)

Untuk menjaga payroll/HCM tetap konsisten, data master employee kini juga disimpan ke tabel riwayat efektif berikut.

### `employee_employment_history`

- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `employee_profiles`, cascade on delete)
- `employment_status VARCHAR(50) NOT NULL DEFAULT 'active'`
- `employee_type VARCHAR(50) NULL`
- `start_date DATE NOT NULL`
- `end_date DATE NULL`
- `probation_end_date DATE NULL`
- `notes TEXT NULL`
- `created_at`, `updated_at`

Index:
- `KEY employee_employment_history_employee_start_idx (employee_id, start_date)`

### `employee_assignments`

- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `employee_profiles`, cascade on delete)
- `department_id BIGINT UNSIGNED NULL` (FK `departments`, null on delete)
- `designation_id BIGINT UNSIGNED NULL` (FK `designations`, null on delete)
- `team_id BIGINT UNSIGNED NULL` (FK `teams`, null on delete)
- `manager_user_id BIGINT UNSIGNED NULL` (FK `users`, null on delete)
- `is_primary BOOLEAN NOT NULL DEFAULT 1`
- `start_date DATE NOT NULL`
- `end_date DATE NULL`
- `team_name VARCHAR(100) NULL` — fallback legacy
- `notes TEXT NULL`
- `created_at`, `updated_at`

Index:
- `KEY employee_assignments_employee_start_idx (employee_id, start_date)`
- `KEY employee_assignments_employee_team_idx (employee_id, team_id)`

### `employee_compensations`

- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `employee_profiles`, cascade on delete)
- `salary_type VARCHAR(50) NOT NULL DEFAULT 'monthly'`
- `base_salary DECIMAL(15,2) NOT NULL DEFAULT 0`
- `fixed_allowance DECIMAL(15,2) NOT NULL DEFAULT 0`
- `currency VARCHAR(10) NOT NULL DEFAULT 'IDR'`
- `effective_date DATE NOT NULL`
- `end_date DATE NULL`
- `notes TEXT NULL`
- `created_at`, `updated_at`

Index:
- `KEY employee_compensations_employee_effective_idx (employee_id, effective_date)`

### `employee_contracts`

- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `employee_profiles`, cascade on delete)
- `contract_type VARCHAR(50) NOT NULL DEFAULT 'permanent'` — `permanent` (PKWTT) | `contract` (PKWT)
- `start_date DATE NULL`
- `end_date DATE NULL`
- `status VARCHAR(50) NULL` — `active`, `ended`, `terminated`
- `notes TEXT NULL`
- `created_at`, `updated_at`

Index:
- `KEY employee_contracts_employee_start_idx (employee_id, start_date)`

### `employee_bank_accounts`

- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `employee_profiles`, cascade on delete)
- `bank_name VARCHAR(150) NULL`
- `account_number VARCHAR(100) NULL`
- `account_holder_name VARCHAR(150) NULL`
- `bank_ifsc_code VARCHAR(100) NULL`
- `bank_branch VARCHAR(150) NULL`
- `is_primary BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Index:
- `KEY employee_bank_accounts_employee_primary_idx (employee_id, is_primary)`

### `employee_tax_profiles`

- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `employee_profiles`, cascade on delete)
- `npwp VARCHAR(100) NULL`
- `tax_status VARCHAR(50) NULL`
- `ptkp_status VARCHAR(50) NULL`
- `effective_date DATE NOT NULL`
- `end_date DATE NULL`
- `created_at`, `updated_at`

Index:
- `KEY employee_tax_profiles_employee_effective_idx (employee_id, effective_date)`

**Note:** Runtime saat ini disederhanakan ke satu row efektif terbaru per employee untuk Indonesia.

### `employee_benefits`

- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `employee_profiles`, cascade on delete)
- `bpjs_kesehatan_no VARCHAR(100) NULL`
- `bpjs_ketenagakerjaan_no VARCHAR(100) NULL`
- `effective_date DATE NOT NULL`
- `end_date DATE NULL`
- `created_at`, `updated_at`

Index:
- `KEY employee_benefits_employee_effective_idx (employee_id, effective_date)`

### `employee_emergency_contacts`

- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `employee_profiles`, cascade on delete)
- `name VARCHAR(150) NOT NULL`
- `relationship VARCHAR(100) NULL`
- `phone VARCHAR(50) NULL`
- `email VARCHAR(150) NULL`
- `sort_order INT UNSIGNED NOT NULL DEFAULT 0`
- `created_at`, `updated_at`

### `employee_educations`

- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `employee_profiles`, cascade on delete)
- `institution VARCHAR(150) NULL`
- `degree VARCHAR(100) NULL`
- `field_of_study VARCHAR(150) NULL`
- `start_year SMALLINT UNSIGNED NULL`
- `end_year SMALLINT UNSIGNED NULL`
- `notes TEXT NULL`
- `sort_order INT UNSIGNED NOT NULL DEFAULT 0`
- `created_at`, `updated_at`

### `employee_experiences`

- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `employee_profiles`, cascade on delete)
- `company VARCHAR(150) NULL`
- `position VARCHAR(150) NULL`
- `start_date DATE NULL`
- `end_date DATE NULL`
- `description TEXT NULL`
- `sort_order INT UNSIGNED NOT NULL DEFAULT 0`
- `created_at`, `updated_at`

---

## Related Documentation

- **Organization:** `docs/database/core-hcm/organization.md`
- **Feature Docs:** `docs/features/employees-organization/`
- **API:** `docs/api/hcm-employees-api.md`
