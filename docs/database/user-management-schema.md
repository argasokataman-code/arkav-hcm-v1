# User Management Database Schema (Draft)

Status: Draft desain untuk implementasi fase berikut.

## New Tables

1. `hcm_roles`
- master role tenant-aware

2. `hcm_permissions`
- katalog permission granular (`module.resource.action`)

3. `hcm_role_permissions`
- pivot role-permission

4. `hcm_user_roles`
- assignment role ke user per company dengan effective period

5. `hcm_user_role_audits`
- audit event perubahan akses

## Existing Table Impact

- `users`: tetap sebagai source utama identitas user.
- `company_users`: tetap dipakai untuk membership tenant.
- `company_users.role`: dipertahankan sementara sebagai compatibility selama migration window.

## Compatibility Rules

Authorization resolver (urutan):
1. baca role aktif dari `hcm_user_roles`
2. jika kosong, fallback ke `company_users.role`

## Suggested Indexes

- `hcm_roles`: unique (`company_id`, `code`)
- `hcm_permissions`: unique (`code`), index (`module`, `resource`, `action`)
- `hcm_role_permissions`: unique (`role_id`, `permission_id`)
- `hcm_user_roles`: index (`user_id`, `company_id`, `status`)
- `hcm_user_role_audits`: index (`target_user_id`, `created_at`)

## Seed Baseline (Suggested)

Roles:
- `owner`
- `hcm_admin`
- `manager`
- `employee`

Permissions (minimum):
- `user.view`
- `user.create`
- `user.update`
- `user.assign_role`
- `role.view`
- `role.create`
- `role.update`
- `role.delete`
- `role.sync_permission`
