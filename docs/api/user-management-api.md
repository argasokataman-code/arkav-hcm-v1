# User Management API

Status: Live (backend implemented)
Base path: `/v1/hcm/user-management`

**Last Updated:** 2026-04-18
**Changes:** Internal tenant-awareness refactoring for RBAC hardening (auth flow optimization, company context validation)

## 1) Users

### GET `/users`

Query:
- `page`, `perPage`
- `search` (name/email)
- `status` (`active|inactive|all`)
- `roleCode` (optional)

Success 200:
- `data[]`: user summary + active roles
- `meta.pagination`

### GET `/users/{id}`

Success 200:
- user profile
- role assignments
- permission summary (effective)

### POST `/users`

Body:
- `name` required
- `email` required unique
- `password` required
- `roleCodes[]` optional (company context diambil dari tenant middleware)

Errors:
- `422 VALIDATION_ERROR`
- `403 AUTH_FORBIDDEN`

### PUT `/users/{id}`

Body (partial):
- `name`, `status`, `email` (opsional)

## 2) Roles

### GET `/roles`

Query:
- `scope` (`global|company`)
- `status`

### POST `/roles`

Body:
- `code`, `name` required
- `description` optional
- `status` optional

### PUT `/roles/{id}`

Body partial:
- `name`, `description`, `status`

### DELETE `/roles/{id}`

Soft delete/archive role jika masih dipakai.

## 3) Permissions

### GET `/permissions`

Query:
- `module`
- `search`

### POST `/roles/{id}/permissions:sync`

Body:
- `permissionCodes[]` required

Behavior:
- replace seluruh mapping role-permission dengan daftar terbaru.

## 4) User Role Assignment

### GET `/users/{id}/roles`

Return role assignments history + active flag.

### POST `/users/{id}/roles`

Body:
- `roleCode` required
- `effectiveFrom` optional
- `effectiveUntil` optional

### DELETE `/users/{id}/roles/{assignmentId}`

Behavior:
- revoke assignment (soft revoke status inactive)

## Error Codes (Draft)

- `AUTH_UNAUTHORIZED` (401)
- `AUTH_FORBIDDEN` (403)
- `TENANT_FORBIDDEN` (403)
- `VALIDATION_ERROR` (422)
- `USER_NOT_FOUND` (404)
- `ROLE_NOT_FOUND` (404)
- `PERMISSION_NOT_FOUND` (404)
- `ROLE_IN_USE` (422)
