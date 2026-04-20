# Goal Tracking (Phase 1)

## Ringkasan

Modul ini melengkapi Performance Phase 1 dengan master goal type dan daftar goals yang bisa diakses pada scope `me`, `team`, atau `all` sesuai role. Goal tracking memberi lapisan sasaran kerja yang lebih operasional daripada appraisal, tetapi tetap memakai relasi employee-manager dan tenant scope yang sama.

## Akses

- Authenticated user: dapat membuat dan melihat goal dirinya sendiri.
- Manager: dapat melihat dan memperbarui goal bawahan dengan `manager_user_id = user.id`.
- HCM Admin: mutasi goal type dan akses scope `all`.

## UI Aktif

## UI pages (template aktif)

- **`/goal-type`** (HCM Admin)
  - List + search goal types.
  - CRUD via modal `#arcav_goal_type_modal`.
- **`/goal-tracking`** (Authenticated)
  - List goals dengan filter scope, goal type, status, keyword.
  - CRUD via modal `#arcav_goal_modal`.
  - Export CSV dari list saat ini.

## Flow Bisnis End-to-End

1. HCM Admin menyiapkan goal type yang bisa dipakai tenant.
2. Employee membuat goal pribadi dari `/goal-tracking`.
3. Manager memantau dan mengupdate goal bawahan bila diperlukan.
4. Admin dapat melihat keseluruhan goal tenant untuk kebutuhan monitoring dan evaluasi.

## Lifecycle Dan Keputusan Bisnis

- Scope `me`, `team`, dan `all` memisahkan boundary akses tanpa memecah endpoint.
- Goal owner tetap user pemilik; manager hanya mendapat hak update pada goal timnya.
- Goal type mutation tetap admin-only agar kamus sasaran tidak liar.

## Integrasi

- Performance: goal tracking menjadi pelengkap appraisal performance dan berbagi konteks role/manager yang sama. Lihat `docs/features/performance/README.md`.
- Employees & Organization: `manager_user_id` dan user owner mengandalkan data employee profile tenant. Lihat `docs/features/employees-organization/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## API endpoints (Laravel)

Base path: `/v1/hcm/performance`

### Goal Types

- `GET /goal-types` (Authenticated)
  - Untuk dropdown/filter di Goal Tracking.
- `POST /goal-types` (HCM Admin)
- `PUT /goal-types/{id}` (HCM Admin)
- `DELETE /goal-types/{id}` (HCM Admin)

### Goals

- `GET /goals?scope=me|team|all&status=&goalTypeId=&q=&perPage=`
  - **scope=me**: goals milik user login
  - **scope=team**: goals dengan `manager_user_id = user.id`
  - **scope=all**: admin-only
- `POST /goals` (Authenticated)
  - Create goal untuk diri sendiri; `manager_user_id` mengikuti `EmployeeProfile.manager_user_id` bila ada.
- `PUT /goals/{id}` (Owner / Manager / Admin)
  - Owner: goal milik sendiri
  - Manager: goal dengan `manager_user_id = user.id`
  - Admin: semua
- `DELETE /goals/{id}` (Owner / Admin)

## Existing Vs Target

- Existing: goal types, goal CRUD scope-aware, export CSV, dan manager/team filtering sudah aktif.
- Target: integrasi analitik yang lebih dalam ke performance review dan dashboard performa masih bisa diperluas.

## Data model (ringkas)

- `performance_goal_types`
  - `name` unique, `description`, `is_active`
- `performance_goals`
  - FK: `goal_type_id`, `user_id`, `manager_user_id`
  - Fields utama: `subject`, `target_achievement`, `start_date`, `end_date`, `description`, `status`, `progress_percent`

## Frontend wiring

- JS: `frontend/resources/js/goal-data.js` (disalin ke `backend/public/build/js/goal-data.js`)
- Route loading: `backend/resources/views/layout/partials/footer-scripts.blade.php` memuat `goal-data.js` untuk route `/goal-tracking` dan `/goal-type`
- UI template: Blade shells
  - `backend/resources/views/goal-type.blade.php`
  - `backend/resources/views/goal-tracking.blade.php`
  - Modals: `backend/resources/views/hcm/partials/goal-modals.blade.php`

## Tests

- `backend/tests/Feature/GoalTrackingApiTest.php`
  - Admin CRUD goal types + employee forbidden mutasi
  - Employee CRUD goal sendiri
  - Manager list scope=team + update goal bawahan
  - Admin list scope=all

## Seeder demo (dev)

- `backend/database/seeders/PerformanceSeeder.php`
  - Sekarang juga mengisi `performance_goal_types` dan `performance_goals` untuk demo halaman `/goal-type` dan `/goal-tracking`.
  - Idempotent: seeder menghapus data tabel goal/performance lalu mengisi ulang agar konsisten.

