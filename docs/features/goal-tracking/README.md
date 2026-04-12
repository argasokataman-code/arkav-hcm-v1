# Goal Tracking (Phase 1)

Modul ini melengkapi Performance Phase 1 dengan fitur **Goal Tracking**: master *goal type* (admin-only untuk mutasi) dan daftar goals yang bisa diakses dengan scope **me/team/all** sesuai role.

Rujukan role & akses per URL: `docs/planning/active-hcm-templates-and-permissions.md`.

## UI pages (template aktif)

- **`/goal-type`** (HCM Admin)
  - List + search goal types.
  - CRUD via modal `#arcav_goal_type_modal`.
- **`/goal-tracking`** (Authenticated)
  - List goals dengan filter scope, goal type, status, keyword.
  - CRUD via modal `#arcav_goal_modal`.
  - Export CSV dari list saat ini.

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

