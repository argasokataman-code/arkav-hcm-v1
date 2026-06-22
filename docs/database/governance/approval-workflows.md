# Governance: Approval Workflows

## `hcm_approval_configs`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `company_id BIGINT UNSIGNED NOT NULL`
- `company_uuid CHAR(36) NULL` (index)
- `module VARCHAR(50) NOT NULL` — `leave`, `expense`, `offer`, `overtime`
- `approval_mode ENUM('sequence','simultaneous') NOT NULL DEFAULT 'simultaneous'`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Constraint:
- `UNIQUE KEY hcm_approval_configs_company_module_unique (company_id, module)`
- `FOREIGN KEY (company_uuid) REFERENCES companies(uuid) ON DELETE SET NULL`

Index:
- `KEY hcm_approval_configs_company_id_idx (company_id)`
- `KEY hcm_approval_configs_company_uuid_idx (company_uuid)`

---

## `hcm_approval_config_approvers`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `hcm_approval_config_id BIGINT UNSIGNED NOT NULL` (FK `hcm_approval_configs`, cascade on delete)
- `company_id BIGINT UNSIGNED NOT NULL`
- `company_uuid CHAR(36) NULL` (index)
- `approver_user_id BIGINT UNSIGNED NOT NULL`
- `approver_user_uuid CHAR(36) NULL` (index)
- `sequence_order TINYINT UNSIGNED NOT NULL DEFAULT 1` — urutan untuk mode `sequence`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (hcm_approval_config_id) REFERENCES hcm_approval_configs(id) ON DELETE CASCADE`
- `FOREIGN KEY (company_uuid) REFERENCES companies(uuid) ON DELETE SET NULL`
- `FOREIGN KEY (approver_user_uuid) REFERENCES users(uuid) ON DELETE SET NULL`

Index:
- `KEY hcm_acapprovers_config_order_idx (hcm_approval_config_id, sequence_order)`
- `KEY hcm_acapprovers_company_id_idx (company_id)`
- `KEY hcm_acapprovers_company_uuid_idx (company_uuid)`
- `KEY hcm_acapprovers_approver_user_id_idx (approver_user_id)`
- `KEY hcm_acapprovers_approver_user_uuid_idx (approver_user_uuid)`

---

## Related Documentation

- **Leave Management:** `docs/database/leave-attendance/leave-management.md`
- **Feature Docs:** `docs/features/approval-settings/`
- **API:** `docs/features/approval-settings/API.md`
