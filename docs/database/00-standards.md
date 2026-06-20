# MySQL Database Standards & Conventions

Standar umum untuk semua tabel database ARCAV HCM.

## General Standards

- **DBMS:** MySQL 8.0+
- **Storage engine:** InnoDB
- **Charset:** `utf8mb4`
- **Collation:** `utf8mb4_unicode_ci`
- **Timezone:** UTC
- **Primary key type:** `BIGINT UNSIGNED AUTO_INCREMENT`
- **Audit columns standar:**
  - `created_at DATETIME`
  - `updated_at DATETIME`
  - optional `deleted_at DATETIME` untuk soft delete

## Database Separation Strategy

Rencana multi-database (tiap service satu DB):

- `arcav_identity_db`
- `arcav_hcm_core_db`
- `arcav_leave_attendance_db`

**Implementasi saat ini (Phase 1, single Laravel):** satu koneksi MySQL per environment; semua tabel migrasi berada dalam skema yang sama. Batas "service" tetap di level kode/route, bukan DB fisik terpisah.

## Tenant Foundation (April 2026)

Skema HCM aktif **tidak** memakai multi-tenant bawaan: tabel legacy `tenants`, `subscription_plans`, `subscription_plan_features`, `tenant_subscriptions` dan kolom `users.tenant_id` dihapus lewat migrasi `2026_04_11_100000_drop_legacy_tenant_subscription_tables` (bekas dump gabungan).

### Core SaaS Tables Added
- `companies`, `company_users`, `subscriptions`, `payments`, `company_settings`

### Tenantization Waves

**Wave 1: Employee/Org** — menambahkan `company_id` (nullable + indexed + backfill default company) ke:
- `employee_profiles`
- `departments`
- `designations`
- `teams`

**Wave 2: Attendance/Shift** — menambahkan `company_id` ke:
- `attendance_records`
- `hcm_shifts`
- `hcm_schedule_timings`

**Wave 3: Payroll** — menambahkan `company_id` ke:
- `hcm_payroll_periods` (termasuk update unique key menjadi `(company_id, period_year, period_month)`)
- `hcm_payroll_runs`
- `hcm_payroll_lines`
- `hcm_salary_components`
- `hcm_overtime_types`

**Wave 4: Payroll-Adjacent** — menambahkan `company_id` ke:
- `hcm_payroll_items`
- `hcm_thr_yearly_settings` (unique key diperlebar menjadi `(company_id, calendar_year)`)
- `hcm_thr_batches`

## Data Integrity Rules

- `end_date >= start_date` pada `leave_requests`
- `check_out_time >= check_in_time` jika keduanya terisi
- Email format validation dilakukan di application layer
- Cross-service ID validation dilakukan via API call antar service

## Migration Order (Recommended)

1. Identity: `users`, `auth_tokens`, session/cache Laravel bawaan
2. Core HCM: `departments`, `designations`, `employees` / profil terkait, `policies`, `employee_profiles`
3. `hcm_shifts` sebelum atau bersamaan dengan `hcm_schedule_timings` (FK `hcm_shift_id` ditambahkan setelah `hcm_shifts` ada)
4. Attendance & leave: `attendance_records`, `hcm_schedule_timings`, `hcm_overtime_types`, `holidays`, `leave_requests`, `overtime_requests` (+ FK `hcm_overtime_type_id`, `hcm_salary_component_id` → `hcm_salary_components`), tabel leave-settings

Urutan pasti mengikuti timestamp file di `backend/database/migrations/`.

## Seed Minimum Data

- Roles: `admin`, `manager`, `employee`
- Permissions dasar per domain
- Department sample: `HR`, `Engineering`, `Finance`
- Designation sample: `Staff`, `Senior Staff`, `Lead`
- Leave types sample: `annual`, `sick`, `unpaid`

---

**Note:** Jika nanti dipecah deploy, migrasi dan FK perlu direview ulang.
