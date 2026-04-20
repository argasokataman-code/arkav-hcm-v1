# User Management API

Status: Implemented (UUID transition compatible)
Base path: `/v1/hcm/user-management`

Last updated: 2026-04-19

## Contract Notes

- Semua endpoint tenant-scoped: wajib ada active company context (header/tenant resolver).
- Format response standar: `{ success, data? , error? }`.
- Identifier transition:
  - `users/{id}` pada endpoint detail/update/delete saat ini memakai numeric user id.
  - `roles/{id}`, `users/{id}/roles`, dan `users/{id}/roles/{assignmentId}` sudah menerima UUID identifier, dengan fallback numeric legacy.

## Authorization Rules

- View endpoints: butuh salah satu permission `user.view`, `role.view`, atau `user_management.view`.
- Manage endpoints: butuh salah satu permission `user.create|user.update|user.assign_role|role.create|role.update|role.delete|role.sync_permission|user_management.manage`.
- Setup role/permission (`create role`, `update role`, `delete role`, `sync role permissions`) juga butuh application super user (`SUPER_USER_REQUIRED`) selain permission manage.

## Endpoints

### Users

- `GET /users`
  - Query: `page`, `perPage`, `search`, `status(active|inactive|all)`, `roleCode`

- `GET /users/export`
  - Query: `search`, `status`, `roleCode`, `format=csv`
  - Response: CSV stream (`text/csv`)

- `GET /users/{id}`
  - `{id}`: numeric user id

- `POST /users`
  - Body:
```json
{
  "name": "Assigned User",
  "email": "assigned.user@example.com",
  "password": "StrongPass1",
  "status": "active",
  "roleCodes": ["STAFF"]
}
```

- `PUT /users/{id}`
  - `{id}`: numeric user id
  - Body (partial): `name`, `email`, `status`

- `DELETE /users/{id}`
  - `{id}`: numeric user id
  - Behavior: remove membership pada company aktif + revoke assignment role aktif.

### Roles

- `GET /roles`
  - Query: `scope(global|company)`, `status(active|inactive|archived|all)`

- `POST /roles`
  - Body: `code`, `name`, `description?`, `status?`
  - Requires super user.

- `PUT /roles/{id}`
  - `{id}`: UUID role atau numeric legacy id
  - Body (partial): `name`, `description`, `status(active|inactive|archived)`
  - Requires super user.

- `DELETE /roles/{id}`
  - `{id}`: UUID role atau numeric legacy id
  - Requires super user.
  - Rule:
    - role system -> `ROLE_LOCKED` (422)
    - role in-use -> archive (`status=archived`)

### Permissions

- `GET /permissions`
  - Query: `module`, `search`

- `POST /roles/{id}/permissions:sync`
  - `{id}`: UUID role atau numeric legacy id
  - Body:
```json
{
  "permissionCodes": ["user.view", "user.update"]
}
```
  - Requires super user.

### User Role Assignment

- `GET /users/{id}/roles`
  - `{id}`: UUID user atau numeric legacy id

- `POST /users/{id}/roles`
  - `{id}`: UUID user atau numeric legacy id
  - Body:
```json
{
  "roleCode": "STAFF",
  "effectiveFrom": "2026-04-19",
  "effectiveUntil": "2026-12-31",
  "notes": "optional"
}
```

- `DELETE /users/{id}/roles/{assignmentId}`
  - `{id}`: UUID user atau numeric legacy id
  - `{assignmentId}`: UUID assignment atau numeric legacy id

## Common Errors

- `TENANT_CONTEXT_REQUIRED` (422)
- `AUTH_FORBIDDEN` (403)
- `SUPER_USER_REQUIRED` (403)
- `USER_NOT_FOUND` (404)
- `ROLE_NOT_FOUND` (404)
- `PERMISSION_NOT_FOUND` (404)
- `ROLE_ASSIGNMENT_NOT_FOUND` (404)
- `ROLE_ASSIGNMENT_NOT_ACTIVE` (422)
- `ROLE_LOCKED` (422)
- `SELF_DELETE_FORBIDDEN` (422)
