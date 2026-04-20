# Performance API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmPerformanceController.php`.

## Base path

`/v1/hcm/performance`

## Goal Types

### GET `/goal-types`

RBAC:
- Authenticated: allowed

Success `200`:
- `data[]`: `{ id, name, description, isActive }`

### POST `/goal-types`

RBAC:
- HCM Admin only

Body:
- `name` required string max 120
- `description` optional string max 5000
- `isActive` optional boolean

Success `201`:

```json
{ "success": true, "data": { "id": 1 } }
```

### PUT `/goal-types/{id}`

RBAC:
- HCM Admin only

Body: sama seperti POST (name required)

### DELETE `/goal-types/{id}`

RBAC:
- HCM Admin only

## Goals

Status:
- `active|inactive|completed`

### GET `/goals`

Query:
- `scope` optional: `me|team|all` (default `me`)
- `status` optional enum
- `goalTypeId` optional integer
- `q` optional string max 200 (subject/targetAchievement/description)
- `startDate` optional date
- `endDate` optional date
- `perPage` optional int 1..100

RBAC:
- `scope=me`: self goals
- `scope=team`: goals dengan `manager_user_id = auth.id`
- `scope=all`: HCM Admin only

Success `200`:
- `data[]` berisi `goalType`, `employee`, `manager`, `progressPercent`, `updatedAt`
- `meta` paginated: `currentPage/lastPage/perPage/total`

### POST `/goals`

Body:
- `goalTypeId` optional integer exists `performance_goal_types.id`
- `userId` optional integer exists `users.id` (**admin only**, default self)
- `subject` required string max 200
- `targetAchievement` optional string max 255
- `startDate` optional date
- `endDate` optional date, `after_or_equal:startDate`
- `description` optional string max 5000
- `status` optional enum (default `active`)
- `progressPercent` optional int 0..100

RBAC:
- Non-admin mengirim `userId` → `403 AUTH_FORBIDDEN`

Success `201`: `{ success: true, data: { id } }`

### PUT `/goals/{id}`

RBAC:
- HCM Admin: allowed
- Owner: allowed
- Manager: allowed jika `manager_user_id == auth.id`

Body: `sometimes` updates untuk field yang sama seperti POST (tanpa `userId`)

### DELETE `/goals/{id}`

RBAC:
- HCM Admin: allowed
- Owner: allowed

## Indicator templates (admin)

### GET `/indicator-templates`
### POST `/indicator-templates`
### PUT `/indicator-templates/{id}`
### DELETE `/indicator-templates/{id}`

Validasi ringkas:
- `name` required max 200
- `department` optional max 120
- `designation` optional max 150
- `isActive` optional boolean

### GET `/indicator-templates/{id}/items`

Return `data[]` item: `{ id, section, title, description, weight, ratingScaleMin, ratingScaleMax, sortOrder }`

### POST `/indicator-templates/{id}/items`
### PUT `/indicator-items/{itemId}`
### DELETE `/indicator-items/{itemId}`

Validasi ringkas:
- `section` required `kpi|behavioral`
- `title` required max 255
- `description` optional max 5000
- `weight` optional numeric 0..1000 (dipakai untuk KPI; behavioral weight null)
- `ratingScaleMin/Max` optional int 1..10 (default min 1 max 5)
- `sortOrder` optional int 0..100000

## Cycles (admin)

### GET `/cycles`
### POST `/cycles`
### PUT `/cycles/{id}`
### POST `/cycles/{id}/activate`
### POST `/cycles/{id}/close`

Validasi ringkas:
- `periodStart`, `periodEnd` required date, `periodEnd >= periodStart`
- `status` pada PUT: `draft|active|closed`

Kontrak penting:
- `activate` akan menutup cycle aktif lain (set `closed`) lalu set target jadi `active`.

## Reviews

Status:
- `draft|submitted|manager_reviewed|finalized`

### GET `/reviews`

Query:
- `scope` optional `me|team|all` (default `me`)
- `cycleId` optional integer
- `status` optional enum
- `perPage` optional int 1..100

RBAC:
- `scope=me`: self
- `scope=team`: manager melihat team
- `scope=all`: HCM Admin only

### POST `/reviews`

RBAC:
- HCM Admin only

Body:
- `cycleId` required identifier untuk `performance_cycles`.
- `userId` required identifier untuk `users`; target user wajib anggota tenant aktif.
- `templateId` required identifier untuk `performance_indicator_templates`.

Kontrak identifier aktif:
- UI admin saat ini mengirim numeric `id` untuk ketiga field di atas.
- UUID masih diterima sebagai fallback legacy.

Behavior:
- server akan pre-create score rows untuk semua template items.
- review baru disimpan dengan `company_id` tenant aktif agar akses review dan metrik leave tetap tenant-scoped.

### GET `/reviews/{id}`

RBAC:
- HCM Admin: allowed
- Owner: allowed
- Manager: allowed jika `manager_user_id == auth.id`

Return detail:
- cycle, employee, manager, template, items (dengan scores self/manager/final), notes, totals, permissions.

### PUT `/reviews/{id}`

RBAC:
- Owner only

Guard:
- hanya `draft` yang bisa diupdate; selain itu → `422 PERF_REVIEW_LOCKED`

Body:
- `selfNote` optional string max 5000
- `scores` required array
- `scores.*.itemId` required integer
- `scores.*.score` optional numeric 0..100 (behavioral akan divalidasi range ratingScaleMin..Max)
- `scores.*.comment` optional string max 3000

### POST `/reviews/{id}/submit`

RBAC:
- Owner only

Guard:
- review harus `draft`
- cycle harus `active` kalau terhubung cycle, else → `422 PERF_CYCLE_NOT_ACTIVE`

### PUT `/reviews/{id}/manager`
### POST `/reviews/{id}/manager-complete`

RBAC:
- Manager only (manager_user_id == auth.id)

Guard:
- status harus `submitted`, else `422 PERF_REVIEW_LOCKED`

### PUT `/reviews/{id}/final`
### POST `/reviews/{id}/finalize`

RBAC:
- HCM Admin only

Guard:
- final update: status harus `manager_reviewed|finalized`
- finalize: status harus `manager_reviewed`

