# User Management

## Ringkasan

Fitur ini memusatkan pengelolaan user, role, dan permission untuk aplikasi HCM/SaaS. User Management menjadi fondasi kontrol akses lintas modul karena assignment role, catalog permission, dan audit perubahan akses semuanya berawal dari surface ini.

## Akses

- **Global Super Admin (Developer / Platform Maintainer)** — satu akun khusus dengan penanda `users.is_super_admin = 1` di database. Akun ini menguasai seluruh aplikasi tanpa batas: lintas tenant, lintas modul, lintas feature gate. Tidak tunduk pada RBAC tenant maupun package feature gating.
- **Tenant Super Admin (Owner Company)** — admin internal company/tenant. Akses penuh hanya di scope company miliknya, tunduk pada package feature gating dan tenant isolation. Kontrak via `company_users.role = 'owner'` plus role `OWNER`/`ADMIN` di `hcm_user_roles`.
- **HCM Admin / admin dengan permission user management**: mengelola user, role, permission, dan assignment role **di scope tenant aktifnya**.
- **Non-admin atau user tanpa permission yang sesuai**: tidak boleh memutasi data user-management; backend tetap menjadi sumber kebenaran otorisasi.

## UI Aktif

- Surface utama memakai halaman user-management HCM dengan export, detail, dan modal CRUD role/assignment.
- Export/list role-assignment sudah memakai auth contract dan tenant-context yang sama dengan HCM UI lain.

## Flow Bisnis End-to-End

1. Admin membuka halaman user management.
2. Admin mencari user target, melihat detail, dan bila perlu mengekspor daftar user.
3. Admin membuat atau memperbarui role dan permission catalog sesuai kebutuhan operasional.
4. Admin melakukan assignment role ke user dalam scope company aktif.
5. Audit trail perubahan akses tersimpan agar perubahan role/permission bisa ditelusuri.

Khusus untuk global platform governance:
- Global Super Admin dapat melihat permission katalog lengkap termasuk modul `system`, untuk mengontrol halaman/platform setting yang tidak boleh didelegasikan ke tenant.
- Tenant HCM Admin tetap mengelola role tenant, tetapi builder permission mereka otomatis menyembunyikan modul `system` agar tidak muncul ekspektasi palsu terhadap akses yang memang global-only.

## Lifecycle Dan Keputusan Bisnis

- Ada dua layer super-admin yang **tidak boleh tertukar**:
  1. Global Super Admin: 1 akun developer/platform, disimpan via kolom `users.is_super_admin` (sumber kebenaran tunggal). Tidak tunduk feature gate, RBAC tenant, maupun tenant isolation.
  2. Tenant Super Admin (Owner / HCM Admin): per company, via RBAC (`hcm_user_roles` + `hcm_roles`) dan membership (`company_users.role`). Tunduk feature gate + tenant isolation.
- Permission UI hanya bersifat petunjuk; otorisasi final harus tetap diputuskan backend.
- Assignment role wajib tenant-aware untuk mencegah kebocoran akses lintas company.
- Fondasi RBAC ini diprioritaskan lebih dulu karena semua modul admin lain bergantung padanya.

## Integrasi

- Identity Auth: session auth, auth client, dan tenant context yang dipakai halaman user management harus selaras dengan flow login/admin. Lihat `docs/features/identity-auth/README.md`.
- Employees Organization: relasi user-employee-company menjadi dasar pencarian, assignment, dan validasi tenant-aware. Lihat `docs/features/employees-organization/README.md`.
- Super Admin Dashboard: statistik user global dan monitoring akses lintas tenant bergantung pada data user/sub-role platform. Lihat `docs/features/super-admin-dashboard/README.md`.
- Semua feature HCM admin: permission catalog dan role assignment dari modul ini menjadi gate menu/aksi modul seperti attendance, leave, reporting, payroll, tickets, dan training.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Scope

Fitur ini memusatkan pengelolaan user, role, dan permission untuk aplikasi HCM/SaaS.

Target fase awal:
- User list + search/filter + detail
- Role management (create/update/archive)
- Permission catalog per modul
- Assignment role ke user per company (tenant-aware)
- Audit trail perubahan role/permission

## Status

Status: Implemented (Backend API v1 + Authorization Pattern v1)
Version: 1.0
Last updated: 2026-04-21

**Latest:** 2026-04-21 - Global-only governance hardening completed for role setup and platform hub visibility. Permission catalog `GET /permissions` now hides `module=system` from tenant admins, while global super admin keeps full visibility for system-level permissions and cross-tenant platform oversight. Web guard + header/sidebar visibility for platform billing/revenue pages are now aligned to `isGlobalHcmAdmin`, and tenant billing self-service (`/subscription`, `/company/invoices`) remains available to tenant admins.

**Validation note:** The FE auth client and tenant-context flow used by user-management were also revalidated through `backend/tests/ui/auth-api.wiring.test.js`, so export/list pages now share the same verified auth contract as the rest of the HCM UI.

**Tracker rule:** because this document has a `Status` section, keep [TRACKER.md](TRACKER.md) updated with the current snapshot, remaining gaps, and latest evidence.

## Documentation Structure

1. **MULTI-TENANT-RBAC.md** ⭐ **NEW**
   - Complete multi-tenant RBAC implementation with strict tenant isolation
   - Schema updates, security implementation, API usage examples
   - Production-ready enterprise-grade RBAC system

2. USE-CASES.md
- Definisi use case per aktor, model akses dua layer (company role vs app RBAC), dan aturan employee dapat menjadi admin melalui role assignment.

3. IMPLEMENTATION.md
- Desain teknis skema DB, aturan domain, strategi migrasi kompatibel.

4. E2E-TESTING.md
- Skenario validasi manual per role (admin vs non-admin).

5. TRACKER.md
- Snapshot status operasional, gap, dan evidence terbaru.

6. ../../api/user-management-api.md
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

UI wiring note:
- `users-management.js` sends auth + tenant headers for the manual export CSV path too, and the export flow is covered by `backend/tests/ui/users-management.wiring.test.js`.
- Create/edit visibility still follows `users.manage` on UI, but backend stays as source of truth through server-side authorization.
- The UI keeps company-scoped requests aligned with BE by always pulling tenant context from `AuthApi` before list/export/role-assignment calls.

## Why This First

Menu ini diprioritaskan karena dipakai lintas modul:
- onboarding user baru
- kontrol akses menu/aksi
- hardening keamanan operasional harian

Tanpa fondasi user-role-permission yang rapi, modul lain cenderung pakai rule ad-hoc dan sulit dipelihara.

## Existing Vs Target

### Existing runtime yang sudah aktif

- Global Super Admin sudah punya penanda persisten di DB (`users.is_super_admin`, boolean, indexed). Satu akun developer cukup di-set flag=1 untuk akses tak terbatas; tidak perlu env email di runtime (email di `hcm.admin_email` hanya dipakai seeder awal dan sebagai bootstrap fallback saat flag belum ter-backfill).
- backend API v1 user-management sudah aktif dengan list, export, detail, CRUD role, sync permission, dan assignment role;
- export/list pages sudah memakai auth + tenant headers yang tervalidasi;
- multi-tenant RBAC tests dan wiring tests sudah menutup tenant isolation utama;
- permission catalog role setup sekarang menyembunyikan modul `system` untuk non-global admin, sehingga tenant admin hanya melihat permission yang benar-benar bisa mereka kelola;
- UI mengikuti pola template HCM aktif dengan breadcrumb, export, dan modal-based CRUD.

### Gap yang masih terbuka

- `hcm_roles` saat ini belum memiliki row platform-scoped (`company_id IS NULL`) seperti yang sempat dijanjikan di versi awal dokumen; untuk saat ini kebutuhan "global super admin" sudah ditutup oleh flag `users.is_super_admin`, bukan lewat RBAC platform role.
- dokumentasi hubungan permission catalog dengan modul-modul turunan masih perlu dirujuk konsisten lintas README modul lain.

### Keputusan kompromi sementara

- dokumentasi mengikuti runtime authorization yang aktif sekarang tanpa mengklaim semua modul sudah memakai permission matrix yang seragam penuh;
- backend tetap dinyatakan sebagai source of truth walau UI menyembunyikan aksi berdasarkan permission.
