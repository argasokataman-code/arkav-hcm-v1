# User Management: RBAC Schema

Tabel untuk role-based access control modern (menggantikan legacy roles/permissions).

## `hcm_roles`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (FK `companies`, null on delete)
- `code VARCHAR(80) NOT NULL`
- `name VARCHAR(150) NOT NULL`
- `description VARCHAR(2000) NULL`
- `status VARCHAR(30) NOT NULL DEFAULT 'active'`
- `is_system BOOLEAN NOT NULL DEFAULT 0`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL`
- `UNIQUE KEY hcm_roles_company_code_unique (company_id, code)`

Index:
- `KEY hcm_roles_company_status_idx (company_id, status)`
- `KEY hcm_roles_code_idx (code)`

---

## `hcm_permissions`

- `id BIGINT UNSIGNED PK`
- `code VARCHAR(120) NOT NULL UNIQUE`
- `module VARCHAR(80) NOT NULL` (index) — `employee`, `payroll`, `leave`, `system`, dll
- `resource VARCHAR(80) NOT NULL` (index) — `profile`, `salary`, `request`, dll
- `action VARCHAR(80) NOT NULL` (index) — `view`, `create`, `update`, `delete`, `manage`
- `name VARCHAR(150) NOT NULL`
- `description VARCHAR(2000) NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1` (index)
- `created_at`, `updated_at`

Index:
- `KEY hcm_permissions_mra_idx (module, resource, action)`
- `KEY hcm_permissions_code_idx (code)`

**Note:** Permission `module=system` hanya dikirim ke **global HCM admin**; tenant admin tidak melihat permission System di builder role.

---

## `hcm_role_permissions`

- `id BIGINT UNSIGNED PK`
- `role_id BIGINT UNSIGNED NOT NULL` (FK `hcm_roles`, cascade on delete)
- `permission_id BIGINT UNSIGNED NOT NULL` (FK `hcm_permissions`, cascade on delete)
- `created_at TIMESTAMP NULL`

Constraint:
- `FOREIGN KEY (role_id) REFERENCES hcm_roles(id) ON DELETE CASCADE`
- `FOREIGN KEY (permission_id) REFERENCES hcm_permissions(id) ON DELETE CASCADE`
- `UNIQUE KEY hcm_role_permissions_role_permission_unique (role_id, permission_id)`

---

## `hcm_user_roles`

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

Constraint:
- `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE`
- `FOREIGN KEY (role_id) REFERENCES hcm_roles(id) ON DELETE CASCADE`
- `FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL`

Index:
- `KEY hcm_user_roles_lookup_idx (user_id, company_id, status)`
- `KEY hcm_user_roles_role_status_idx (role_id, status)`

---

## `hcm_user_role_audits`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (FK `companies`, null on delete)
- `actor_user_id BIGINT UNSIGNED NULL` (FK `users`, null on delete)
- `target_user_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade on delete)
- `role_id BIGINT UNSIGNED NULL` (FK `hcm_roles`, null on delete)
- `action VARCHAR(80) NOT NULL` — `assigned`, `revoked`, `changed`
- `notes TEXT NULL`
- `metadata JSON NULL`
- `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`

Constraint:
- `FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL`
- `FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL`
- `FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE`
- `FOREIGN KEY (role_id) REFERENCES hcm_roles(id) ON DELETE SET NULL`

Index:
- `KEY hcm_user_role_audits_target_created_idx (target_user_id, created_at)`
- `KEY hcm_user_role_audits_action_idx (action)`

---

## Related Documentation

- **Legacy roles/permissions:** `docs/database/identity/users-auth.md`
- **Feature Docs:** `docs/features/user-management/`
- **API:** `docs/api/user-management-api.md`
- **RBAC Matrix:** `docs/planning/active-hcm-templates-and-permissions.md`
