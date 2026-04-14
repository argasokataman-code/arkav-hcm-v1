# User Management - Implementation

Status: Implemented (Backend API v1)
Updated: 2026-04-13

## 1. Objective

Menyediakan fondasi akses berbasis role-permission yang tenant-aware, tetap kompatibel dengan model existing (`company_users.role`) selama masa transisi.

## 2. Database Schema (Implemented)

### 2.1 `hcm_roles`

Purpose:
- Master role reusable per company/global.

Columns:
- `id` bigint PK
- `company_id` bigint nullable FK -> `companies.id` (null = global/system role)
- `code` varchar(80) unique per scope (`OWNER`, `HCM_ADMIN`, `MANAGER`, `EMPLOYEE`, dst)
- `name` varchar(150)
- `description` text nullable
- `is_system` boolean default false
- `status` enum/string (`active|inactive|archived`) default `active`
- `created_at`, `updated_at`

Indexes:
- unique (`company_id`, `code`)
- index (`status`)

### 2.2 `hcm_permissions`

Purpose:
- Katalog permission granular berbasis aksi.

Columns:
- `id` bigint PK
- `module` varchar(100) (`employees`, `payroll`, `attendance`, `settings`, ...)
- `resource` varchar(100) (`user`, `role`, `salary_component`, ...)
- `action` varchar(50) (`view`, `create`, `update`, `delete`, `approve`, ...)
- `code` varchar(191) unique (contoh: `user.view`, `user.create`, `role.assign`)
- `description` text nullable
- `name` varchar(150)
- `is_active` boolean default true
- `created_at`, `updated_at`

Indexes:
- unique (`code`)
- index (`module`, `resource`, `action`)

### 2.3 `hcm_role_permissions`

Purpose:
- Pivot role -> permission.

Columns:
- `id` bigint PK
- `role_id` bigint FK -> `hcm_roles.id` cascade delete
- `permission_id` bigint FK -> `hcm_permissions.id` cascade delete
- `created_at` timestamp nullable

Indexes:
- unique (`role_id`, `permission_id`)

### 2.4 `hcm_user_roles`

Purpose:
- Assignment role ke user, tenant-aware, time-bound.

Columns:
- `id` bigint PK
- `user_id` bigint FK -> `users.id` cascade delete
- `company_id` bigint FK -> `companies.id` cascade delete
- `role_id` bigint FK -> `hcm_roles.id` cascade delete
- `status` enum/string (`active|inactive|revoked`) default `active`
- `effective_from` date nullable
- `effective_until` date nullable
- `revoked_at` timestamp nullable
- `assigned_by_user_id` bigint nullable FK -> `users.id`
- `created_at`, `updated_at`

Indexes:
- index (`user_id`, `company_id`, `status`)
- index (`role_id`, `status`)

### 2.5 `hcm_user_role_audits`

Purpose:
- Audit trail perubahan akses.

Columns:
- `id` bigint PK
- `actor_user_id` bigint nullable FK -> `users.id`
- `target_user_id` bigint FK -> `users.id`
- `company_id` bigint nullable FK -> `companies.id`
- `role_id` bigint nullable FK -> `hcm_roles.id`
- `action` varchar(80) (`role_assigned`, `role_revoked`, ...)
- `notes` text nullable
- `metadata` json nullable
- `created_at`

Indexes:
- index (`target_user_id`, `created_at`)
- index (`action`)

## 3. Implemented API Endpoints

Base path: `/v1/hcm/user-management`

- Users: list/filter/pagination, detail, create, update, export csv
- Users: delete (remove membership from active company + revoke active role assignments)
- Roles: list, create, update, delete/archive-on-use
- Permissions: list and sync role-permissions
- Assignment: list user roles, assign role, revoke assignment

## 3.1 Web UI (Users Page)

Route: `/users`

Implemented:
- Dynamic table bound to `/v1/hcm/user-management/users`
- Filter: search, status, role
- CRUD wiring:
	- create user
	- update user
	- delete user from active company (backend delete endpoint)
- Role assignment panel per user:
	- assign role (`POST /users/{id}/roles`)
	- revoke role assignment (`DELETE /users/{id}/roles/{assignmentId}`)
- Export CSV (`GET /users/export?format=csv`)

## 3.2 Web UI (Roles & Permissions Page)

Route: `/roles-permissions`

Implemented:
- Dynamic roles list bound to `/v1/hcm/user-management/roles?scope=company&status=...`
- Filter: search and status
- CRUD wiring:
	- create role
	- update role
	- delete/archive role
- Permission sync modal per role:
	- load permission catalog (`GET /permissions`)
	- sync selected permission codes (`POST /roles/{id}/permissions:sync`)

Notes:
- Roles list payload now includes `permissionCodes` for each role so frontend can preselect active permissions in modal without extra endpoint.

## 4. Compatibility Strategy

Current state:
- Role tenant saat ini tersimpan di `company_users.role`.

Transition strategy:
1. Seed system roles + permission catalog.
2. Backfill `hcm_user_roles` dari `company_users`.
3. Resolver authorization baca prioritas:
- `hcm_user_roles` aktif
- fallback ke `company_users.role` (selama migration window)
4. Setelah stabil, `company_users.role` jadi compatibility field atau di-drop pada fase berikut.

## 5. Authorization Rules (Initial)

- `hcm_admin` / `owner`:
- manage users, roles, assignment, permission sync di company sendiri
- `manager`:
- view user list terbatas scope team (opsional fase 2)
- `employee`:
- read self profile only

Non-admin tidak boleh:
- create/update/delete role
- assign/revoke role user lain
- sync permission role

## 6. API Envelope

Gunakan konsisten:
- success: `{ success: true, data: ... }`
- error: `{ success: false, error: { code, message, details? } }`

## 7. Rollout Plan

Phase A (Schema + Seed) - Done:
- tabel user-management dibuat
- seed roles/permissions baseline ditambahkan

Phase B (API) - Done:
- user list/detail + role assignment
- role CRUD + permission sync
- export users (CSV)

Phase C (Hardening) - Next:
- audit report
- cache permission resolver
- remove fallback lama jika sudah aman
