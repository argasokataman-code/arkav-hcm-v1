# Core HCM: Organization Structure

Tabel master untuk struktur organisasi perusahaan.

## `departments`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope (wave tenantization `2026_04_21_100000_tenantize_employee_org_core_tables`)
- `code VARCHAR(50) NOT NULL UNIQUE`
- `name VARCHAR(150) NOT NULL`
- `description TEXT NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Index:
- `UNIQUE KEY departments_code_unique (code)`
- `KEY departments_company_id_idx (company_id)`
- `KEY departments_is_active_idx (is_active)`

---

## `designations`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `code VARCHAR(50) NOT NULL UNIQUE`
- `name VARCHAR(150) NOT NULL`
- `description TEXT NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Index:
- `UNIQUE KEY designations_code_unique (code)`
- `KEY designations_company_id_idx (company_id)`
- `KEY designations_is_active_idx (is_active)`

---

## `teams`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `department_id BIGINT UNSIGNED NULL` (FK `departments`, null on delete)
- `name VARCHAR(100) NOT NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL`
- `UNIQUE KEY teams_department_name_unique (department_id, name)`

Index:
- `KEY teams_company_id_idx (company_id)`
- `KEY teams_department_id_idx (department_id)`

**Note:** Teams dibangun dari backfill `employee_assignments.team_name` via migrasi `2026_04_11_150000_harden_hcm_indonesia_consistency`.

---

## `policies`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `department_id BIGINT UNSIGNED NULL` (FK `departments`, null on delete)
- `name VARCHAR(150) NOT NULL`
- `description TEXT NOT NULL`
- `effective_date DATE NULL`
- `attachment_path VARCHAR(500) NULL` — file path untuk policy attachment
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL`

Index:
- `KEY policies_company_id_idx (company_id)`
- `KEY policies_department_id_idx (department_id)`
- `KEY policies_effective_date_idx (effective_date)`

---

## Related Documentation

- **Employee Profiles:** `docs/database/core-hcm/employee-profiles.md`
- **Employee Assignments:** `docs/database/core-hcm/employee-profiles.md#employee_assignments`
- **Feature Docs:** `docs/features/employees-organization/`
