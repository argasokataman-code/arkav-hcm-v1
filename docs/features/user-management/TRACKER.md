# User Management - Status Tracker

Last reviewed: 2026-04-21

## Purpose

Tracker ini dipakai untuk melacak status implementasi user-management, gap yang masih tersisa, dan evidence terbaru. Jika ada perubahan status di README/IMPLEMENTATION, file ini juga harus ikut diupdate.

## Current Snapshot

- Status: Implemented (Backend API v1 + Authorization Pattern v1 + Global Super Admin Flag v1)
- API wiring: verified through wiring tests
- Multi-tenant RBAC: verified through integration/regression tests
- Tenant isolation: verified, no known cross-tenant access issue in latest audit
- UI alignment: follows active template patterns for list/export/modal CRUD flows
- **Global Super Admin (Developer)**: persisted via `users.is_super_admin` (BOOLEAN, indexed). Primary source of truth for `User::isGlobalHcmAdmin()` + `HcmRbacService::isGlobalAdmin()`. One developer account (`qa.login@example.com`) backfilled on migration. Email config retained only as bootstrap fallback.
- **Tenant Super Admin**: unchanged contract — `company_users.role='owner'` membership + `hcm_user_roles` role assignment per company.

## Remaining Gaps

- `hcm_roles` has no platform-scoped rows (`company_id IS NULL`). The earlier documentation draft suggested platform roles `super_admin`/`internal_support`; that approach is superseded by the `users.is_super_admin` flag. If product later wants granular permission matrix for internal support staff, revisit by seeding platform-scoped roles.
- `config('hcm.super_admin_emails')` array is intentionally unused — removed as a runtime signal.

## Evidence Log

- 2026-04-21: Added `users.is_super_admin` column (migration `2026_04_30_070000_add_is_super_admin_to_users_table.php`). Data backfill promoted `qa.login@example.com` user to `is_super_admin=1`. `User::isGlobalHcmAdminSignal()` now reads flag first, email config as fallback only. `HcmRbacService::isGlobalAdmin()` delegates to `User::isGlobalHcmAdmin()`. `DevelopmentSuperUserSeeder` sets the flag for the primary super user. `UserGlobalAdminEmailTest` now locks the flag-first contract. PHPUnit: 551 passed, 0 failed, 1 skipped. Vitest wiring (tickets + checkout + profile-settings + company-invoices): 12 passed.
- 2026-04-19: wiring tests pass, RBAC tests pass, tenant isolation verified.
- 2026-04-19: FE auth client and tenant-context flow revalidated against backend auth contract.

## Update Rule

- Whenever `README.md` or `IMPLEMENTATION.md` changes its `Status` section, update this tracker in the same change set.
- If a regression or gap is found, add a short note here before closing the task.