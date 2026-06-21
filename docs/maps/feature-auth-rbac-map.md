# Feature Map: Authentication & Authorization (RBAC)

## 1. Entry Points (API)
| Method | Path | Controller | Permission |
|--------|------|------------|------------|
| POST | `/v1/auth/register` | `AuthController` | (public) |
| POST | `/v1/auth/login` | `AuthController` | (public) |
| POST | `/v1/auth/logout` | `AuthController` | (authenticated) |
| GET | `/v1/auth/me` | `AuthController` | (authenticated) |
| GET/POST | `/v1/hcm/roles` | `HcmRoleManagementController` | `rbac.manage` |
| GET/POST | `/v1/hcm/permissions` | `HcmPermissionController` | `rbac.manage` |
| GET/POST | `/v1/hcm/users` | `HcmUserManagementController` | `user.manage` |

## 2. Controllers
- `backend/app/Http/Controllers/Api/Auth/AuthController.php` — Login/register/logout
- `backend/app/Http/Controllers/Api/UserManagement/HcmRoleManagementController.php` — Role CRUD
- `backend/app/Http/Controllers/Api/UserManagement/HcmPermissionController.php` — Permission CRUD
- `backend/app/Http/Controllers/Api/UserManagement/HcmUserManagementController.php` — User CRUD

## 3. Auth Concerns
- `HandlesAuthCompany` — Company context
- `HandlesAuthCore` — Core auth logic
- `HandlesAuthPermissions` — Permission checks
- `HandlesAuthProfile` — Profile management

## 4. Middleware (Security)
- `AuthenticateApiToken` — API token validation
- `EnsureHcmWebPagesAuthenticated` — Web page auth
- `EnsureHcmWebAdminPage` — Admin page guard
- `EnsureHcmPermission` — Permission middleware
- `EnsureSuperAdmin` — Super admin check
- `SecurityHeadersMiddleware` — Security headers
- `ResolveTenantContext` — Tenant resolution
- `ArcavAccessTokenResolver` — Token resolver (Support)

## 5. Models
- `App\Models\User` — Base user
- `App\Models\Company` — Tenant/company
- `App\Models\CompanyUser` — User-company membership
- `App\Models\HcmRole` — Custom roles
- `App\Models\HcmPermission` — Permissions
- `App\Models\HcmRolePermission` — Role-permission mapping
- `App\Models\HcmUserRole` — User-role assignment
- `App\Models\HcmUserRoleAudit` — Audit trail
- `App\Models\AuthToken` — API tokens

## 6. Services
- `backend/app/Services/HcmRbacService.php` — RBAC logic
- `backend/app/Support/ArcavAccessTokenResolver.php` — Token resolution
- `backend/app/Support/TenantContextResolver.php` — Tenant context

## 7. Key Relations
```
User (N) <-> (N) Company (via CompanyUser)
HcmRole (N) <-> (N) HcmPermission (via HcmRolePermission)
User (N) <-> (N) HcmRole (via HcmUserRole, scoped by company_id)
```

## 8. Multi-Tenant Rules
- Every query MUST have `company_id` in WHERE for tenant-scoped data
- Platform roles (super_admin): no company_id
- Permissions: global, related to roles via `hcm_role_permissions` + `company_id`

## 9. Seeders
- `HcmRolesSeeder` — Default roles
- `HcmPermissionsSeeder` — Default permissions
- `HcmUserManagementSeeder` — User management data
- `SuperAdminDeveloperSeeder` — Super admin
