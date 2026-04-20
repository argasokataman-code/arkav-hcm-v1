# Training API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmTrainingController.php`.

Semua endpoint di bawah modul ini membaca **active company context**. Jika tenant context tidak aktif, backend mengembalikan `422 TENANT_CONTEXT_REQUIRED`.

## Base path

`/v1/hcm/training`

## Trainers

- `GET /trainers` (HCM Admin)
- `POST /trainers` (HCM Admin)
- `PUT /trainers/{id}` (HCM Admin)
- `DELETE /trainers/{id}` (HCM Admin)

### GET `/trainers`

RBAC:
- HCM Admin only
- tenant-scoped ke company aktif

Query:
- `status` optional `active|inactive`
- `q` optional string max 200 (name/email/phone/description)
- `perPage` optional int 1..100

### POST `/trainers`

RBAC:
- HCM Admin only
- butuh permission `training.manage`

Body:
- `name` required string max 200
- `email` optional email max 200
- `phone` optional string max 50
- `description` optional string max 5000
- `isActive` optional boolean

Success `201`: `{ success: true, data: { id } }`

Validasi tambahan:
- trainer hanya dibuat di company aktif

## Training Types

- `GET /types` (Authenticated; non-admin hanya `isActive=true`)
- `POST /types` (HCM Admin)
- `PUT /types/{id}` (HCM Admin)
- `DELETE /types/{id}` (HCM Admin)

### GET `/types`

RBAC:
- Authenticated: allowed
- Non-admin: hanya tipe aktif (`is_active=true`)
- semua hasil tenant-scoped ke company aktif

### POST `/types`

RBAC:
- HCM Admin only
- butuh permission `training.manage`

Body:
- `name` required string max 200 (unique by name; duplicate → `422 VALIDATION_ERROR`)
- `description` optional string max 5000
- `isActive` optional boolean

Catatan:
- uniqueness `name` berlaku per company aktif, bukan global semua tenant.

Success `201`: `{ success: true, data: { id } }`

## Trainings

- `GET /trainings` (HCM Admin)
- `POST /trainings` (HCM Admin)
- `PUT /trainings/{id}` (HCM Admin)
- `DELETE /trainings/{id}` (HCM Admin)

Catatan validasi:
- `startDate` dan `endDate` **mandatory**

### GET `/trainings`

RBAC:
- HCM Admin only
- tenant-scoped ke company aktif

Query:
- `status` optional `active|inactive|completed`
- `trainingTypeId` optional integer
- `q` optional string max 200 (trainerName/description)
- `perPage` optional int 1..100

Success `200`:
- `data[]` item menyertakan `type` dan `participants[]` (id,name,email)
- `data[]` item menyertakan `trainerId` dan `trainer` (id,name,isActive) untuk referensi relasi
- `meta` paginated (`currentPage/lastPage/perPage/total`)

### POST `/trainings`

RBAC:
- HCM Admin only
- butuh permission `training.manage`

Body:
- `trainingTypeId` optional integer exists `hcm_training_types.id` **di company aktif**
- `trainerId` optional integer exists `hcm_trainers.id` **di company aktif** (disarankan)
- `trainerName` optional string max 200
- `participantUserIds` optional array max 200; items integer harus merupakan `company_users.user_id` aktif di company aktif
- `startDate` required date
- `endDate` required date `after_or_equal:startDate`
- `description` optional string max 5000
- `costCents` optional int min 0 max 1000000000
- `status` optional enum `active|inactive|completed` (default `active`)

Catatan relasi:
- Jika `trainerId` dikirim, backend menyimpan FK `hcm_trainings.trainer_id` dan sinkronkan `trainerName` dari master trainer.
- Jika hanya `trainerName` dikirim (legacy payload), backend tetap menerima; FK diisi bila nama trainer cocok.
- record training disimpan dengan `company_id` milik company aktif.

Success `201`: `{ success: true, data: { id } }`

### PUT `/trainings/{id}`

RBAC:
- HCM Admin only
- hanya bisa mengubah record training di company aktif

Body:
- field `sometimes` (same keys as POST, termasuk `trainerId`)
- jika `participantUserIds` dikirim (termasuk null/[]) → sync participants sesuai payload

### DELETE `/trainings/{id}`

RBAC:
- HCM Admin only
- hanya bisa menghapus record training di company aktif

## Trainings for user (employee detail)

- `GET /users/{userId}/trainings`
  - HCM Admin: boleh semua
  - Karyawan: hanya `userId=self`

### GET `/users/{userId}/trainings`

RBAC:
- HCM Admin: any userId
- Non-admin: hanya self (`userId == auth.id`)
- user target juga harus member aktif di company aktif

Query:
- `perPage` optional int 1..50

Success `200`:
- list trainings yang user tersebut ikut sebagai participant

Negative responses penting:
- `403 FORBIDDEN` untuk user tanpa `training.manage` saat mutasi, atau non-admin yang mengakses `userId` lain
- `404 USER_NOT_FOUND` jika `userId` tidak menjadi member aktif di company aktif
- `422 TENANT_CONTEXT_REQUIRED` jika request tidak membawa tenant context aktif

