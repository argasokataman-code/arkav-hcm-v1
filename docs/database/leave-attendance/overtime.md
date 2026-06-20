# Overtime: Schema

## `hcm_overtime_types`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `code VARCHAR(64) NOT NULL UNIQUE`
- `name VARCHAR(255) NOT NULL`
- `description VARCHAR(500) NULL`
- `payment_multiplier DECIMAL(8,2) NOT NULL DEFAULT 1.00`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0`
- `created_at`, `updated_at`

Index:
- `KEY hcm_overtime_types_company_id_idx (company_id)`
- `KEY hcm_overtime_types_is_active_idx (is_active)`

---

## `overtime_requests`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `user_id BIGINT UNSIGNED NOT NULL` (FK `users`)
- `hcm_overtime_type_id BIGINT UNSIGNED NULL` (FK `hcm_overtime_types`, null on delete)
- `hcm_salary_component_id BIGINT UNSIGNED NULL` (FK `hcm_salary_components`, null on delete) — penautan ke baris slip "upah lembur" untuk integrasi payroll
- `work_date DATE NOT NULL`
- `start_time TIME NOT NULL`
- `end_time TIME NOT NULL`
- `total_hours DECIMAL(5,2) NOT NULL`
- `total_minutes INT UNSIGNED NOT NULL DEFAULT 0`
- `description TEXT NULL`
- `status VARCHAR(30) NOT NULL DEFAULT 'pending'` — `pending`, `approved`, `rejected`
- `approved_by_user_id BIGINT UNSIGNED NULL`
- `approved_at TIMESTAMP NULL`
- `notes TEXT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (user_id) REFERENCES users(id)`
- `FOREIGN KEY (hcm_overtime_type_id) REFERENCES hcm_overtime_types(id) ON DELETE SET NULL`
- `FOREIGN KEY (hcm_salary_component_id) REFERENCES hcm_salary_components(id) ON DELETE SET NULL`

Index:
- `KEY overtime_requests_company_id_idx (company_id)`
- `KEY overtime_requests_user_id_idx (user_id)`
- `KEY overtime_requests_work_date_idx (work_date)`
- `KEY overtime_requests_status_idx (status)`

---

## Related Documentation

- **Attendance:** `docs/database/leave-attendance/attendance.md`
- **Feature Docs:** `docs/features/overtime/`
- **API:** `docs/api/hcm-overtime-api.md`
