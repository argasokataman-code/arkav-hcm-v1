# User Management - Implementation

Status: Implemented (Backend API v1 + Authorization Pattern v1 + Global Super Admin Flag v1)
Updated: 2026-04-21

Tracker: [TRACKER.md](TRACKER.md)

## 1. Objective

Menyediakan fondasi akses berbasis role-permission yang tenant-aware, tetap kompatibel dengan model existing (`company_users.role`) selama masa transisi, dengan satu akun Global Super Admin yang dipersistensi di DB untuk maintenance platform.

## 2. Database Schema (Implemented)

### 2.0 `users.is_super_admin` (2026-04-21)

Purpose:
- Penanda Global Super Admin (developer / platform maintainer). Satu akun khusus yang menguasai seluruh aplikasi tanpa batas tenant / feature gate.

Column:
- `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0, after `password`.

Index:
- `users_is_super_admin_idx` on (`is_super_admin`).

Runtime contract:
- `User::isGlobalHcmAdminSignal()` reads this flag first. `hcm.admin_email` config is kept only as bootstrap fallback for fresh installs / legacy fixtures before the backfill runs.
- `HcmRbacService::isGlobalAdmin()` delegates to `User::isGlobalHcmAdmin()` — single source of truth.

Migration: `database/migrations/2026_04_30_070000_add_is_super_admin_to_users_table.php` (includes data backfill for the user whose email matches `config('hcm.admin_email')`).

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

## 3. Backend Authorization Pattern (Implemented 18 April 2026)

### 3.1 Core Components

**Trait: `ChecksPermissions`** (located at `app/Http/Controllers/Api/Concerns/ChecksPermissions.php`)

Provides centralized permission checking methods used across all API controllers:

```php
// Core methods:
public function hasPermission(Request $request, string $code): bool
public function ensurePermission(Request $request, string $code): ?JsonResponse
public function hasAnyPermission(Request $request, array $codes): bool
public function ensureAnyPermission(Request $request, array $codes): ?JsonResponse
public function activeCompanyId(Request $request): int
```

**Usage Pattern:**

1. **Trait adoption** - Controllers include `use ChecksPermissions;`
2. **Permission-based helpers** - Controllers define private helpers encapsulating permission logic:
   ```php
   private function canManageEmployee(Request $request): bool {
       return $this->hasAnyPermission($request, ['employee.manage', 'employee.admin']);
   }
   ```
3. **Endpoint gating** - Methods use helpers at entry point:
   ```php
   public function store(Request $request): JsonResponse {
       if (!$this->canManageEmployee($request)) {
           return $this->forbidden();
       }
       // ... proceed
   }
   ```

### 3.2 Permission Code Taxonomy

Permission codes follow `module.action` format:

| Module | Codes | Usage |
|--------|-------|-------|
| `employee.*` | view, manage, admin | Employee lifecycle (hire/update/data view) |
| `attendance.*` | view, manage, admin, update | Clock-in/out, roster, corrections |
| `payroll.*` | view, manage, finalize, disburse | Payroll runs, components, verification |
| `leave.*` | view, manage, approve | Leave requests and approvals |
| `ticket.*` | view, manage, admin | Help desk tickets |
| `training.*` | view, manage | Training programs, registrations |
| `promotion.*` | view, manage | Promotion records |
| `termination.*` | view, manage | Separation records |
| `schedule.*` | view, manage | Shifts, rosters, schedules |
| `performance.*` | view, manage | Performance reviews, goals |
| `user.*` | view, manage | User management (admin only) |
| `role.*` | view, manage | Role-permission admin (global admin) |
| `settings.*` | view, manage | Company settings (admin) |

**Global (SaaS Admin) Operations:**

Operations that span across tenants use explicit global-admin signal via `isGlobalHcmAdmin()` method:
- `PackageController` - SaaS package management
- `SubscriptionController` - Billing/subscription operations
- `InvoiceController`, `PaymentController`, `DomainController` - Tenant SaaS operations

### 3.3 Migration Status (7 Stages Complete)

**22 Controllers successfully migrated from hardcoded `isHcmAdmin()` checks to permission-based authorization:**

| Stage | Controllers | Pattern | Status |
|-------|-------------|---------|--------|
| 1 | HcmPerformanceController, HcmUserManagementController, HcmHolidayController, PackageController, BulkPaymentImportController | Syntax fixes + permission alignment | ✓ Complete |
| 2 | DomainController, InvoiceController, PaymentController, HcmLeaveRequestController, HcmTicketController | SaaS tenant-scoped permissions | ✓ Complete |
| 3 | AttendanceController, HcmOvertimeRequestController, HcmActivityController | Complex branching → permission helpers | ✓ Complete |
| 4 | HcmShiftController, HcmOvertimeTypeController, HcmEmailSettingsController, SaasCompanyBillingOverviewController, PurchaseTransactionController, CustomDomainController | Global SaaS admin signal | ✓ Complete |
| 5 | SubscriptionController, TransactionController, CompanyController | Unified global-admin pattern | ✓ Complete |
| 6 | HcmEmployeeController, HcmTrainingController, HcmPromotionController, HcmTerminationController | Tenant + cross-user permission helpers | ✓ Complete |
| 7 | HcmPerformanceController (revisited) | Permission helper consolidation | ✓ Complete (18 April) |

**Validation:**
- PHP lint: All 22 controllers → **No syntax errors** ✓
- IDE diagnostics: All 22 controllers → **No errors found** ✓  
- Pattern scan: 89 → 62 residual `isHcmAdmin()` calls in remaining controllers
- Full audit: See `/FRONTEND-ROLE-PERMISSIONS-AUDIT.md` for stage-by-stage evidence

### 3.4 Response Envelope (Standardized)

**Permission Denied (403):**
```json
{
  "success": false,
  "error": {
    "code": "PERMISSION_DENIED",
    "message": "You do not have permission to perform this action."
  }
}
```

**Admin Required (403 for global-admin-only operations):**
```json
{
  "success": false,
  "error": {
    "code": "ADMIN_REQUIRED",
    "message": "Admin access required."
  }
}
```

## 3.5 Implemented API Endpoints

Base path: `/v1/hcm/user-management`

- Users: list/filter/pagination, detail, create, update, export csv
- Users: delete (remove membership from active company + revoke active role assignments)
- Roles: list, create, update, delete/archive-on-use
- Permissions: list and sync role-permissions
- Assignment: list user roles, assign role, revoke assignment

## 3.6 Web UI (Users Page)

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

## 3.7 Web UI (Roles & Permissions Page)

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

## 5. Authorization Rules (Permission-Based)

**Admin-level** (Role with `user.manage`, `role.manage`):
- Create/update/delete users
- Create/update/delete roles
- Assign/revoke role to users
- Sync permissions per role
- Export user data
- View all users in company

**Manager-level** (Role with `user.view`, limited to team):
- View user list (scoped to direct reports, opsional fase 2)
- View self profile

**Employee-level** (No specific permissions):
- View self profile only
- Cannot create/update/delete role
- Cannot assign/revoke role
- Cannot sync permission role
- Cannot export user data

**Non-admin permission denial:**
- All mutating operations return **403 PERMISSION_DENIED**
- Enforce at backend API level (via `ChecksPermissions` trait)

## 6. API Envelope

Gunakan konsisten:
- success: `{ success: true, data: ... }`
- error: `{ success: false, error: { code, message, details? } }`

Error codes:
- `PERMISSION_DENIED` - User lacks required permission
- `ADMIN_REQUIRED` - Global admin privilege required
- `TENANT_FORBIDDEN` - Cross-tenant access attempt
- `VALIDATION_ERROR` - Invalid input

## 7. Frontend Integration

**Permission Exposure:**
- `/v1/identity/auth/me` now returns:
  ```json
  {
    "permissions": ["user.view", "role.manage", ...],
    "permissionCodes": {"user": ["view", "manage"], ...},
    "activeCompanyId": 1
  }
  ```
- Frontend uses this to conditionally show/hide buttons and UI elements
- Backend remains source of truth (403 responses enforced even if UI hides buttons)

## 8. Rollout Plan

Phase A (Schema + Seed) - Done:
- tabel user-management dibuat
- seed roles/permissions baseline ditambahkan

Phase B (API) - Done:
- user list/detail + role assignment
- role CRUD + permission sync
- export users (CSV)

Phase C (Authorization Pattern) - Done (18 April 2026):
- ChecksPermissions trait implemented
- 22 controllers migrated to permission-based helpers
- Backend authorization enforced consistently
- 0 syntax errors, 0 diagnostic errors

Phase D (Hardening) - Next:
- Full E2E test execution (Scenario 1-9)
- audit report generation
- cache permission resolver optimization
- remove fallback dari `company_users.role` jika sudah aman
- performance testing dengan load (bulk user/role operations)
