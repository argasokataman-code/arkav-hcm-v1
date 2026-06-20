# Governance: Export & Reconciliation

## `export_reconciliation_evidences`

Bukti export sebelum action berisiko (finalize/disburse/mark-paid/verify).

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `feature_key VARCHAR(80) NOT NULL` — contoh: `payroll_run`, `invoice`, `payment`
- `action_key VARCHAR(80) NOT NULL` — contoh: `finalize`, `disburse`, `mark_paid`, `verify`
- `scope_ref VARCHAR(120) NOT NULL` — contoh: `run:24`, `invoice:81`
- `exported_by_user_id BIGINT UNSIGNED NULL` (FK `users`, null on delete)
- `exported_at DATETIME NULL`
- `file_format VARCHAR(10) NOT NULL` — `csv`, `xlsx`
- `file_path VARCHAR(500) NOT NULL`
- `row_count INT UNSIGNED NOT NULL DEFAULT 0`
- `filter_payload JSON NULL`
- `dataset_checksum CHAR(64) NULL` — SHA256 hash
- `expires_at DATETIME NULL`
- `created_at`, `updated_at`

Index:
- `KEY exp_recon_scope_exported_idx (company_id, feature_key, action_key, scope_ref, exported_at)`
- `KEY exp_recon_user_exported_idx (exported_by_user_id, exported_at)`
- `KEY exp_recon_company_expires_idx (company_id, expires_at)`

---

## `export_audit_logs`

Audit trail untuk semua aksi export data.

- `id BIGINT UNSIGNED PK`
- `user_uuid CHAR(36) NOT NULL` (index)
- `company_id BIGINT UNSIGNED NOT NULL` (index)
- `action VARCHAR(100) NOT NULL` — `export_employees`, `export_payroll_run`, `export_attendance_report`
- `format VARCHAR(20) NOT NULL DEFAULT 'csv'` — `csv`, `xlsx`, `pdf`
- `record_count INT NULL`
- `ip_address VARCHAR(45) NULL`
- `user_agent VARCHAR(500) NULL`
- `filters_applied JSON NULL`
- `exported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`
- `created_at`, `updated_at`

Index:
- `KEY export_audit_logs_user_uuid_idx (user_uuid)`
- `KEY export_audit_logs_company_id_idx (company_id)`
- `KEY export_audit_logs_action_idx (action)`
- `KEY export_audit_logs_exported_at_idx (exported_at)`

---

## Related Documentation

- **Data Privacy:** `docs/database/governance/data-privacy.md`
- **Feature Docs:** `docs/features/export-governance/`
- **API:** `docs/api/` export-related docs
