# Leave Management: Schema

Tabel untuk leave types, policies, requests, balances, dan approval.

## `leave_types`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `uuid CHAR(36) NULL UNIQUE`
- `code VARCHAR(64) NOT NULL UNIQUE`
- `name VARCHAR(150) NOT NULL`
- `is_paid BOOLEAN NOT NULL DEFAULT 0`
- `requires_approval BOOLEAN NOT NULL DEFAULT 0`
- `requires_attachment BOOLEAN NOT NULL DEFAULT 0`
- `deduct_from_balance BOOLEAN NOT NULL DEFAULT 0`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Index:
- `KEY leave_types_company_id_idx (company_id)`
- `KEY leave_types_is_active_idx (is_active)`

---

## `leave_policies`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `leave_type_id BIGINT UNSIGNED NOT NULL` (FK `leave_types`)
- `name VARCHAR(150) NOT NULL`
- `days_per_year DECIMAL(8,2) NOT NULL`
- `min_service_months SMALLINT UNSIGNED NOT NULL`
- `is_prorated BOOLEAN NOT NULL DEFAULT 0`
- `carry_forward BOOLEAN NOT NULL DEFAULT 0`
- `is_earned_leave BOOLEAN NOT NULL DEFAULT 0`
- `allow_negative_balance BOOLEAN NOT NULL DEFAULT 0`
- `max_carry_days SMALLINT UNSIGNED NULL`
- `expire_after_days SMALLINT UNSIGNED NULL`
- `effective_from DATE NULL`
- `effective_to DATE NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)`

Index:
- `KEY leave_policies_company_id_idx (company_id)`
- `KEY leave_policies_leave_type_id_idx (leave_type_id)`
- `KEY leave_policies_effective_idx (effective_from, effective_to)`

---

## `leave_policy_assignments`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `policy_id BIGINT UNSIGNED NOT NULL` (FK `leave_policies`)
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `users`)
- `effective_date DATE NULL`
- `end_date DATE NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (policy_id) REFERENCES leave_policies(id)`
- `FOREIGN KEY (employee_id) REFERENCES users(id)`

Index:
- `KEY leave_policy_assignments_company_id_idx (company_id)`
- `KEY leave_policy_assignments_policy_id_idx (policy_id)`
- `KEY leave_policy_assignments_employee_id_idx (employee_id)`

---

## `employee_leave_balances`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `users`)
- `leave_type_id BIGINT UNSIGNED NOT NULL` (FK `leave_types`)
- `year SMALLINT UNSIGNED NOT NULL`
- `balance DECIMAL(10,2) NOT NULL DEFAULT 0`
- `used DECIMAL(10,2) NOT NULL DEFAULT 0`
- `expired DECIMAL(10,2) NOT NULL DEFAULT 0`
- `carried_forward DECIMAL(10,2) NOT NULL DEFAULT 0`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (employee_id) REFERENCES users(id)`
- `FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)`
- `UNIQUE KEY employee_leave_balances_employee_type_year_unique (employee_id, leave_type_id, year)`

Index:
- `KEY employee_leave_balances_company_id_idx (company_id)`
- `KEY employee_leave_balances_year_idx (year)`

---

## `leave_ledger`

Source of truth transaksi saldo cuti.

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `users`)
- `leave_type_id BIGINT UNSIGNED NOT NULL` (FK `leave_types`)
- `policy_id BIGINT UNSIGNED NULL` (FK `leave_policies`)
- `transaction_type VARCHAR(40) NOT NULL`
- `amount DECIMAL(10,2) NOT NULL`
- `balance_after DECIMAL(10,2) NOT NULL`
- `reference_type VARCHAR(50) NULL` — untuk idempotency/audit
- `reference_id BIGINT UNSIGNED NULL` — untuk idempotency/audit
- `occurred_on DATE NOT NULL`
- `notes TEXT NULL`
- `created_by BIGINT UNSIGNED NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (employee_id) REFERENCES users(id)`
- `FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)`
- `FOREIGN KEY (policy_id) REFERENCES leave_policies(id)`

Index:
- `KEY leave_ledger_company_id_idx (company_id)`
- `KEY leave_ledger_employee_type_idx (employee_id, leave_type_id)`
- `KEY leave_ledger_occurred_on_idx (occurred_on)`
- `KEY leave_ledger_reference_idx (reference_type, reference_id)`

---

## `leave_requests`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope (wave tenantization leave 2026-04-13)
- `user_id BIGINT UNSIGNED NOT NULL` (FK `users`)
- `leave_type VARCHAR(64) NOT NULL`
- `date_from DATE NOT NULL`
- `date_to DATE NOT NULL`
- `days DECIMAL(5,1) NOT NULL`
- `status VARCHAR(30) NOT NULL DEFAULT 'pending'`
- `notes TEXT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (user_id) REFERENCES users(id)`

Index:
- `KEY leave_requests_company_id_idx (company_id)`
- `KEY leave_requests_user_id_idx (user_id)`
- `KEY leave_requests_status_idx (status)`
- `KEY leave_requests_date_range_idx (date_from, date_to)`

---

## `leave_approvals`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `leave_request_id BIGINT UNSIGNED NOT NULL` (FK `leave_requests`)
- `approver_id BIGINT UNSIGNED NOT NULL` (FK `users`)
- `level INT UNSIGNED NOT NULL DEFAULT 1`
- `status VARCHAR(30) NOT NULL`
- `acted_at TIMESTAMP NULL`
- `notes TEXT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id)`
- `FOREIGN KEY (approver_id) REFERENCES users(id)`

Index:
- `KEY leave_approvals_company_id_idx (company_id)`
- `KEY leave_approvals_request_approver_idx (leave_request_id, approver_id)`
- `KEY leave_approvals_level_idx (level)`

---

## `holiday_calendars`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `date DATE NOT NULL`
- `name VARCHAR(255) NOT NULL`
- `is_national BOOLEAN NOT NULL DEFAULT 0`
- `is_joint_leave BOOLEAN NOT NULL DEFAULT 0`
- `deduct_from_leave BOOLEAN NOT NULL DEFAULT 0`
- `source VARCHAR(20) NOT NULL DEFAULT 'manual'` — `manual`, `sync_national`
- `last_synced_at TIMESTAMP NULL`
- `created_at`, `updated_at`

Constraint:
- `UNIQUE KEY holiday_calendars_company_date_name_unique (company_id, date, name)`

Index:
- `KEY holiday_calendars_company_id_idx (company_id)`
- `KEY holiday_calendars_date_idx (date)`

---

## `hcm_leave_type_settings` / `hcm_leave_custom_policies`

Konfigurasi tipe cuti dan kebijakan kustom (admin), dipakai API leave-settings. Status saat ini: masih dipakai oleh UI/settings legacy, berdampingan dengan foundation table baru.

---

## Future-Ready Tables (migrasi `2026_04_19_010000_create_leave_future_development_tables`)

### `leave_approval_workflows`

Menentukan workflow approval per leave type + rentang hari + masa efektif.
- Kolom utama: `company_id`, `leave_type_id`, `name`, `min_days`, `max_days`, `is_active`, `effective_from`, `effective_to`

### `leave_approval_workflow_steps`

Step detail workflow multi-level approval.
- Kolom utama: `workflow_id`, `level`, `approver_scope`, `approver_user_id`, `designation_id`, `requires_all_approvers`, `sla_hours`
- `UNIQUE(workflow_id, level)`

### `leave_blackout_dates`

Periode pembatasan cuti.
- Kolom utama: `company_id`, `leave_type_id`, `name`, `rule_type`, `start_date`, `end_date`, `max_people_per_day`, `reason`, `is_active`

### `leave_request_breakdowns`

Breakdown per tanggal/jam untuk satu request (full-day, half-day, hourly).
- Kolom utama: `leave_request_id`, `leave_date`, `unit_type`, `session`, `minutes`, `is_working_day`, `is_holiday`, `holiday_name`, `deducted_days`, `meta`
- `UNIQUE(leave_request_id, leave_date, session)`

### `leave_request_attachments`

Metadata dokumen pendukung pengajuan cuti dan verifikasi HR.
- Kolom utama: `leave_request_id`, `uploaded_by`, `document_type`, `file_name`, `file_path`, `mime_type`, `file_size_bytes`, `is_required`, `notes`, `verified_by`, `verified_at`

### `leave_request_audits`

Audit perubahan request cuti (activity log).
- Kolom utama: `leave_request_id`, `actor_user_id`, `action`, `from_status`, `to_status`, `changes`

---

## Related Documentation

- **Attendance:** `docs/database/leave-attendance/attendance.md`
- **Overtime:** `docs/database/leave-attendance/overtime.md`
- **Feature Docs:** `docs/features/leave-and-holidays/`
- **API:** `docs/api/hcm-leave-api.md`
