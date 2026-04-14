# User Management - E2E Testing Plan

## Objective

Memastikan fitur user-role-permission berjalan aman dan sesuai role boundary.

## Test Accounts

- Admin: `qa.login@example.com`
- Non-admin: company member role `employee`

## Scenario 1 - Admin Lists Users

1. Login admin
2. Buka menu User Management > Users
3. Expected:
- list tampil
- search/filter berfungsi
- detail role user terlihat
- API check:
	- `GET /v1/hcm/user-management/users?search=<keyword>&status=active&page=1&perPage=20` → `200`

## Scenario 2 - Admin Creates Role

1. Buka tab Roles & Permissions
2. Buat role baru (`team_lead`)
3. Pilih permission minimal `user.view`
4. Expected:
- role tersimpan
- role muncul di list
- API check:
	- `POST /v1/hcm/user-management/roles` → `201`

## Scenario 3 - Assign Role to User

1. Dari Users, buka detail user target
2. Assign role `team_lead`
3. Expected:
- assignment aktif muncul
- audit event tercatat
- API check:
	- `POST /v1/hcm/user-management/users/{id}/roles` → `201`

## Scenario 4 - Revoke Role

1. Revoke role user target
2. Expected:
- assignment status inactive / revoked
- audit event tercatat
- API check:
	- `DELETE /v1/hcm/user-management/users/{id}/roles/{assignmentId}` → `200`

## Scenario 7 - Export User Data

1. Login admin
2. Trigger export users
3. Expected:
- file CSV terunduh
- header minimal: `User ID, Name, Email, Status, Company Role, Active Role Codes`
- API check:
	- `GET /v1/hcm/user-management/users/export?format=csv` → `200`

## Scenario 5 - Permission Boundary Non-admin

1. Login non-admin
2. Akses endpoint mutasi role/permission
3. Expected:
- response `403`
- UI tidak menampilkan tombol mutasi

## Scenario 6 - Tenant Isolation

1. Admin company A mencoba assign role user company B
2. Expected:
- `403 TENANT_FORBIDDEN`

## Exit Criteria

- Semua skenario pass
- Tidak ada privilege escalation
- Audit minimal untuk assign/revoke tercatat
