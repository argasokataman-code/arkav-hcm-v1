# Training (Phase 1)

## Ringkasan

Menu **Training** menyediakan master **Training Types** dan daftar **Trainings** yang bisa di-assign ke beberapa peserta (karyawan).

Phase 1 fokus pada:
- CRUD **training types** (admin-only mutasi; list types bisa dipakai semua user terautentikasi untuk dropdown/filter).
- CRUD **trainings** (admin-only).
- Participants disimpan sebagai relasi many-to-many (training ↔ user).
- Pemilihan participants via **employee picker modal** (multi-select checkbox + search) yang memanggil `GET /v1/hcm/employees`.

## Halaman UI

- **`/training-type`**: master training types (Add/Edit/Delete via modal).
- **`/training`**: daftar trainings + filter type/status + search; Add/Edit/Delete via modal.

JS yang mem-wire UI:
- `frontend/resources/js/training-data.js` (sinkron ke `backend/public/build/js/training-data.js`)

## API (Laravel)

Prefix: `/v1/hcm/training/*` (middleware `api.token`)

### Trainers (Phase 1: admin-only)

- `GET /v1/hcm/training/trainers` (HCM Admin)
  - Query: `status` (`active|inactive`), `q`, `perPage`
- `POST /v1/hcm/training/trainers` (HCM Admin)
- `PUT /v1/hcm/training/trainers/{id}` (HCM Admin)
- `DELETE /v1/hcm/training/trainers/{id}` (HCM Admin)

### Training Types

- `GET /v1/hcm/training/types`
  - Authenticated: boleh
  - Non-admin: hanya `isActive=true`
- `POST /v1/hcm/training/types` (HCM Admin)
- `PUT /v1/hcm/training/types/{id}` (HCM Admin)
- `DELETE /v1/hcm/training/types/{id}` (HCM Admin)

### Trainings (Phase 1: admin-only)

- `GET /v1/hcm/training/trainings` (HCM Admin)
  - Query: `status`, `trainingTypeId`, `q`, `perPage`
- `POST /v1/hcm/training/trainings` (HCM Admin)
- `PUT /v1/hcm/training/trainings/{id}` (HCM Admin)
- `DELETE /v1/hcm/training/trainings/{id}` (HCM Admin)

Catatan validasi Phase 1:
- `startDate` dan `endDate` **wajib** (duration mandatory).

### Trainings for employee detail

- `GET /v1/hcm/training/users/{userId}/trainings`
  - HCM Admin: boleh untuk semua user
  - Karyawan: hanya `userId=self`

### Employee Picker (UI helper)

UI picker participants memanggil:
- `GET /v1/hcm/employees?search=&page=&perPage=` (HCM Admin)

Response mengikuti envelope umum:
- `{ success: true, data: ... }`
- `{ success: false, error: { code, message } }`

## Database

Migrasi: `backend/database/migrations/2026_04_09_000030_create_hcm_training_tables.php`

Tabel:
- `hcm_training_types`
- `hcm_trainings`
- `hcm_training_participants` (pivot)

## RBAC (target)

Selaras matriks:
- `docs/planning/active-hcm-templates-and-permissions.md`
- `.cursor/rules/role-permissions-with-features.mdc`

Ringkas:
- Halaman `/training` dan `/training-type`: target **HCM Admin**
- API mutasi: **HCM Admin**
- List types (`GET /types`): semua authenticated (non-admin hanya aktif)

## Testing

- `backend/tests/Feature/TrainingApiTest.php`
- `backend/tests/Feature/TrainersApiTest.php`

