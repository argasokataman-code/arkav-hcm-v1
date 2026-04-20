# Training (Phase 1)

## Ringkasan

Menu **Training** menyediakan master **Training Types** dan daftar **Trainings** yang bisa di-assign ke beberapa peserta (karyawan).

Phase 1 fokus pada:
- CRUD **training types** (admin-only mutasi; list types bisa dipakai semua user terautentikasi untuk dropdown/filter).
- CRUD **trainings** (admin-only).
- CRUD **trainers** sebagai master trainer (admin-only).
- Participants disimpan sebagai relasi many-to-many (training ↔ user).
- Pemilihan participants via **employee picker modal** (multi-select checkbox + search) yang memanggil `GET /v1/hcm/employees`.
- Semua data training sekarang **tenant-scoped** ke active company; request tanpa konteks tenant aktif akan ditolak `422 TENANT_CONTEXT_REQUIRED`.

## Akses

- HCM Admin: CRUD training types, trainers, trainings, dan participant assignment.
- Authenticated user: hanya boleh melihat list training types aktif dan riwayat training dirinya sendiri.
- User dengan `training.view` tanpa `training.manage` tetap tidak boleh melakukan mutasi.

## UI Aktif

## Halaman UI

- **`/training-type`**: master training types (Add/Edit/Delete via modal).
- **`/training`**: daftar trainings + filter type/status + search; Add/Edit/Delete via modal.

JS yang mem-wire UI:
- `frontend/resources/js/training-data.js` (sinkron ke `backend/public/build/js/training-data.js`)

## Flow Bisnis End-to-End

1. HCM Admin menyiapkan training types dan master trainers.
2. HCM Admin membuat training baru, menentukan tanggal, trainer, dan participants dari employee picker.
3. Sistem memvalidasi bahwa training type, trainer, dan participant berasal dari company aktif yang sama.
4. Employee detail page dapat menampilkan riwayat training user berdasarkan endpoint per-user.

## Lifecycle Dan Keputusan Bisnis

- Training types aktif menjadi kamus untuk filtering dan dropdown.
- Trainer dan training wajib tenant-scoped.
- Participant assignment hanya sah bila user merupakan member aktif company yang sama.
- Duration mandatory: `startDate` dan `endDate` wajib pada training phase 1.

## Integrasi

- Employees & Organization: employee picker dan training history pada employee detail memakai data employee tenant yang sama. Lihat `docs/features/employees-organization/README.md`.
- Performance: data training relevan sebagai konteks pengembangan kompetensi meski belum sepenuhnya dihubungkan ke scoring performance. Lihat `docs/features/performance/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

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

Integrasi UI yang memakai endpoint ini:
- Halaman `/employee-details` melalui `frontend/resources/js/hcm-pages-data.js`
- Admin melihat riwayat training employee yang dipilih dari query `id`
- Karyawan melihat riwayat training self dari route yang sama, tetap melalui endpoint per-user yang identik

### Employee Picker (UI helper)

UI picker participants memanggil:
- `GET /v1/hcm/employees?search=&page=&perPage=` (HCM Admin)

Response mengikuti envelope umum:
- `{ success: true, data: ... }`
- `{ success: false, error: { code, message } }`

## Existing Vs Target

- Existing: training types, trainers, trainings, employee picker, tenant scoping, dan endpoint riwayat per-user sudah aktif.
- Target: hubungan eksplisit training outcome ke performance review atau competency matrix masih bisa diperluas di fase berikutnya.

## Database

Migrasi:
- `backend/database/migrations/2026_04_09_000030_create_hcm_training_tables.php`
- `backend/database/migrations/2026_04_09_000031_create_hcm_trainers_table.php`
- `backend/database/migrations/2026_04_19_213900_add_company_scope_to_training_tables.php`

Tabel:
- `hcm_training_types`
- `hcm_trainings`
- `hcm_trainers`
- `hcm_training_participants` (pivot)

Catatan isolasi tenant:
- `hcm_training_types.company_id`
- `hcm_trainers.company_id`
- `hcm_trainings.company_id`
- participant yang dikirim via `participantUserIds[]` harus merupakan member aktif di company aktif.

## RBAC (target)

Selaras matriks:
- `docs/planning/active-hcm-templates-and-permissions.md`
- `.cursor/rules/role-permissions-with-features.mdc`

Ringkas:
- Halaman `/training` dan `/training-type`: target **HCM Admin**
- Halaman `/trainers`: target **HCM Admin**
- API mutasi: **HCM Admin**
- List types (`GET /types`): semua authenticated (non-admin hanya aktif)
- `training.view` tidak cukup untuk mutasi; mutasi butuh `training.manage`.

## Negative Scenario Yang Sudah Digate

- Request tanpa active company context → `422 TENANT_CONTEXT_REQUIRED`.
- User dengan `training.view` tanpa `training.manage` tetap **forbidden** untuk create/update/delete.
- `trainingTypeId` dan `trainerId` hanya valid jika milik company aktif.
- `participantUserIds[]` hanya valid jika user tersebut member aktif di company aktif.
- `GET /users/{userId}/trainings` menolak user lintas-tenant dan tetap membatasi non-admin ke self-only.

## Testing

- `backend/tests/Feature/TrainingApiTest.php`
- `backend/tests/Feature/TrainersApiTest.php`

Tambahan wiring UI ke API (frontend):
- `backend/tests/ui/auth-api.wiring.test.js` (Vitest + jsdom)
- `backend/tests/ui/training-api-contract.test.js` (kontrak endpoint training)
- Jalankan: `cd backend && npm run test:ui`

Coverage audit tambahan:
- tenant scoping untuk types, trainers, dan trainings
- guard `training.manage` vs `training.view`
- wiring employee detail untuk training self/admin via endpoint `/training/users/{userId}/trainings`

Evidence verifikasi terbaru:
- `php artisan test tests/Feature/TrainingApiTest.php`
- `npm run test:ui -- employee-details-training.wiring.test.js training-api-contract.test.js`
- Test UI baru: `backend/tests/ui/employee-details-training.wiring.test.js`

