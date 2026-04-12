# Training API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmTrainingController.php`.

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

Query:
- `status` optional `active|inactive`
- `q` optional string max 200 (name/email/phone/description)
- `perPage` optional int 1..100

### POST `/trainers`

RBAC:
- HCM Admin only

Body:
- `name` required string max 200
- `email` optional email max 200
- `phone` optional string max 50
- `description` optional string max 5000
- `isActive` optional boolean

Success `201`: `{ success: true, data: { id } }`

## Training Types

- `GET /types` (Authenticated; non-admin hanya `isActive=true`)
- `POST /types` (HCM Admin)
- `PUT /types/{id}` (HCM Admin)
- `DELETE /types/{id}` (HCM Admin)

### GET `/types`

RBAC:
- Authenticated: allowed
- Non-admin: hanya tipe aktif (`is_active=true`)

### POST `/types`

RBAC:
- HCM Admin only

Body:
- `name` required string max 200 (unique by name; duplicate → `422 VALIDATION_ERROR`)
- `description` optional string max 5000
- `isActive` optional boolean

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

Query:
- `status` optional `active|inactive|completed`
- `trainingTypeId` optional integer
- `q` optional string max 200 (trainerName/description)
- `perPage` optional int 1..100

Success `200`:
- `data[]` item menyertakan `type` dan `participants[]` (id,name,email)
- `meta` paginated (`currentPage/lastPage/perPage/total`)

### POST `/trainings`

RBAC:
- HCM Admin only

Body:
- `trainingTypeId` optional integer exists `hcm_training_types.id`
- `trainerName` optional string max 200
- `participantUserIds` optional array max 200; items integer exists `users.id`
- `startDate` required date
- `endDate` required date `after_or_equal:startDate`
- `description` optional string max 5000
- `costCents` optional int min 0 max 1000000000
- `status` optional enum `active|inactive|completed` (default `active`)

Success `201`: `{ success: true, data: { id } }`

### PUT `/trainings/{id}`

RBAC:
- HCM Admin only

Body:
- field `sometimes` (same keys as POST)
- jika `participantUserIds` dikirim (termasuk null/[]) → sync participants sesuai payload

### DELETE `/trainings/{id}`

RBAC:
- HCM Admin only

## Trainings for user (employee detail)

- `GET /users/{userId}/trainings`
  - HCM Admin: boleh semua
  - Karyawan: hanya `userId=self`

### GET `/users/{userId}/trainings`

RBAC:
- HCM Admin: any userId
- Non-admin: hanya self (`userId == auth.id`)

Query:
- `perPage` optional int 1..50

Success `200`:
- list trainings yang user tersebut ikut sebagai participant

