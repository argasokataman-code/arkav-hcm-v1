# Identity Service Schema

Tabel untuk autentikasi, user management, dan RBAC legacy.

## `users`

- `id BIGINT UNSIGNED PK`
- `name VARCHAR(255) NOT NULL`
- `email VARCHAR(191) NOT NULL UNIQUE`
- `email_verified_at TIMESTAMP NULL`
- `password VARCHAR(255) NOT NULL` — Laravel bcrypt hash
- `remember_token VARCHAR(100) NULL` — Laravel "remember me" token
- `deleted_at TIMESTAMP NULL` — soft delete
- `created_at`, `updated_at`

Index:
- `UNIQUE KEY users_email_unique (email)`
- `FULLTEXT KEY users_name_email_fulltext (name, email)` — MySQL fulltext search untuk direktori karyawan (migrasi `2026_04_10_120000_add_scale_indexes_for_hcm_queries`)

**Note:** Runtime pakai Laravel default (tidak ada `status` enum eksplisit atau `last_login_at`; autentikasi via `auth_tokens` table).

---

## `roles` (Legacy)

- `id BIGINT UNSIGNED PK`
- `code VARCHAR(50) NOT NULL UNIQUE` (contoh: `admin`, `manager`, `employee`)
- `name VARCHAR(100) NOT NULL`
- `created_at`, `updated_at`

**Note:** Legacy role system. Runtime utama sekarang pakai `hcm_roles` (lihat `docs/database/user-management/rbac.md`).

---

## `permissions` (Legacy)

- `id BIGINT UNSIGNED PK`
- `code VARCHAR(100) NOT NULL UNIQUE`
- `name VARCHAR(150) NOT NULL`
- `created_at`, `updated_at`

**Note:** Legacy permission system. Runtime utama sekarang pakai `hcm_permissions`.

---

## `user_roles` (Legacy)

- `id BIGINT UNSIGNED PK`
- `user_id BIGINT UNSIGNED NOT NULL`
- `role_id BIGINT UNSIGNED NOT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (user_id) REFERENCES users(id)`
- `FOREIGN KEY (role_id) REFERENCES roles(id)`
- `UNIQUE KEY uq_user_roles (user_id, role_id)`

---

## `role_permissions` (Legacy)

- `id BIGINT UNSIGNED PK`
- `role_id BIGINT UNSIGNED NOT NULL`
- `permission_id BIGINT UNSIGNED NOT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (role_id) REFERENCES roles(id)`
- `FOREIGN KEY (permission_id) REFERENCES permissions(id)`
- `UNIQUE KEY uq_role_permissions (role_id, permission_id)`

---

## `auth_tokens`

- `id BIGINT UNSIGNED PK`
- `user_id BIGINT UNSIGNED NOT NULL`
- `token_hash CHAR(64) NOT NULL UNIQUE`
- `expires_at DATETIME NOT NULL`
- `revoked_at DATETIME NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (user_id) REFERENCES users(id)`

Index:
- `UNIQUE KEY auth_tokens_token_hash_unique (token_hash)`
- `KEY auth_tokens_user_id_idx (user_id)`
- `KEY auth_tokens_expires_at_idx (expires_at)`

---

## Related Documentation

- **Modern RBAC:** `docs/database/user-management/rbac.md` (hcm_roles, hcm_permissions)
- **Employee Profiles:** `docs/database/core-hcm/employee-profiles.md`
- **API Auth Flow:** `docs/api/identity-api.md`
