# User Management

## Scope

Fitur ini memusatkan pengelolaan user, role, dan permission untuk aplikasi HCM/SaaS.

Target fase awal:
- User list + search/filter + detail
- Role management (create/update/archive)
- Permission catalog per modul
- Assignment role ke user per company (tenant-aware)
- Audit trail perubahan role/permission

## Status

Status: Implemented (Backend API v1)
Version: 1.0
Last updated: 2026-04-13

## Documentation Structure

1. USE-CASES.md
- Definisi use case per aktor, model akses dua layer (company role vs app RBAC), dan aturan employee dapat menjadi admin melalui role assignment.

2. IMPLEMENTATION.md
- Desain teknis skema DB, aturan domain, strategi migrasi kompatibel.

3. E2E-TESTING.md
- Skenario validasi manual per role (admin vs non-admin).

4. ../../api/user-management-api.md
- Kontrak endpoint API live untuk implementasi backend/frontend.

## Implemented API Surface

Base path: `/v1/hcm/user-management`

- Users:
	- `GET /users` (filter + pagination)
	- `GET /users/export` (CSV export)
	- `GET /users/{id}`
	- `POST /users`
	- `PUT /users/{id}`
- Roles:
	- `GET /roles`
	- `POST /roles`
	- `PUT /roles/{id}`
	- `DELETE /roles/{id}`
- Permissions:
	- `GET /permissions`
	- `POST /roles/{id}/permissions:sync`
- User Role Assignment:
	- `GET /users/{id}/roles`
	- `POST /users/{id}/roles`
	- `DELETE /users/{id}/roles/{assignmentId}`

## Why This First

Menu ini diprioritaskan karena dipakai lintas modul:
- onboarding user baru
- kontrol akses menu/aksi
- hardening keamanan operasional harian

Tanpa fondasi user-role-permission yang rapi, modul lain cenderung pakai rule ad-hoc dan sulit dipelihara.
