# Tickets API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmTicketController.php`.

## Global Super Admin bypass

Pengguna dengan `users.is_super_admin = 1` (Global Developer / Platform Maintainer) melewati pengecekan feature gate `SUBSCRIPTION_REQUIRED` dan tenant scoping `company_id`. Akun ini melihat seluruh ticket lintas tenant meski company aktif tidak punya subscription paket `tickets`.

## Base path

`/v1/hcm`

## Tickets

Status:
- `open|in_progress|resolved|closed`

Priority:
- `low|medium|high|urgent`

### GET `/tickets`

Query:
- `status` optional enum
- `priority` optional enum
- `q` optional string max 120 (subject/description/code)
- `perPage` optional int 1..100 (default 20)

RBAC:
- HCM Admin: all tickets
- Non-admin: scope ke ticket milik sendiri (`user_id`)

Tenant:
- Request wajib membawa active company context.
- Ticket baru yang sudah tersimpan dengan `company_id` hanya muncul di tenant tempat ticket dibuat.
- Row legacy yang belum punya `company_id` masih difallback ke membership reporter sampai backfill histori selesai.

Success `200` (ringkas):
- `meta.summary`: `total/open/inProgress/resolved/closed`
- `data[]` item menyertakan `reporter`, `assignee`, `commentsCount`, `attachmentsCount`

### POST `/tickets`

Body:
- `subject` required string max 255
- `description` required string max 10000
- `categoryId` optional int exists `ticket_categories.id` (disarankan)
- `category` optional string max 120
- `priority` required enum
- `slaDueAt` optional date
- `assigneeUserId` optional int exists `users.id` (**admin only**)

Catatan relasi:
- Jika `categoryId` dikirim, backend menyimpan FK `tickets.category_id` dan sinkronkan `category` dengan nama master.
- Jika hanya `category` dikirim (legacy payload), backend tetap menerima; FK diisi jika nama cocok dengan master.

RBAC:
- Non-admin mengirim `assigneeUserId` → `403 AUTH_FORBIDDEN`

Tenant:
- Ticket baru disimpan dengan `tickets.company_id = activeCompanyId`.
- `assigneeUserId` hanya valid jika user tersebut aktif di tenant yang sama.

Success `201`:

```json
{ "success": true, "data": { "id": 1, "code": "TIC-20260409-001" } }
```

### GET `/tickets/{id}`

RBAC:
- HCM Admin: any
- Non-admin: only own

Success `200`:
- `data.comments[]`, `data.attachments[]` (dengan `downloadUrl` dan `previewUrl`)
- `data.assignmentHistory[]`
- `data.permissions`: `canManage/canEdit/canDelete`

### PUT `/tickets/{id}`

Body (semua optional via `sometimes`, tergantung role):
- `subject` string max 255
- `description` string max 10000
- `categoryId` optional int exists `ticket_categories.id`
- `category` optional string max 120
- `priority` enum
- `status` enum (**admin only**)
- `slaDueAt` optional date (**admin only**)
- `assigneeUserId` optional int (**admin only**)

RBAC:
- Non-admin mengirim `status|slaDueAt|assigneeUserId` → `403 AUTH_FORBIDDEN`
- Non-admin edit ticket dengan status `closed` → `422 TICKET_CLOSED_LOCKED`
- Admin assign/reassign user dari tenant lain → `422` validation error pada `assigneeUserId`

Success `200`:

```json
{ "success": true }
```

### DELETE `/tickets/{id}`

RBAC:
- HCM Admin: allowed
- Non-admin: only own

Guard:
- Non-admin delete ticket `closed` → `422 TICKET_CLOSED_LOCKED`

Success `200`:

```json
{ "success": true }
```

### POST `/tickets/{id}/comments`

Body:
- `body` required string max 5000

Guard:
- Non-admin comment ticket `closed` → `422 TICKET_CLOSED_LOCKED`

Success `201`:

```json
{ "success": true, "data": { "id": 1 } }
```

### POST `/tickets/{id}/attachments`

Multipart:
- `file` required file, max 5120KB, mimes: `jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv,txt`

Guard:
- Non-admin upload attachment ke ticket `closed` → `422 TICKET_CLOSED_LOCKED`

Success `201`:

```json
{ "success": true, "data": { "id": 1 } }
```

### GET `/tickets/{id}/attachments/{attachmentId}/preview`

Behavior:
- Inline preview via `response()->file(...)`

### GET `/tickets/{id}/attachments/{attachmentId}/download`

Errors:
- `404 FILE_NOT_FOUND`

### GET `/tickets/assignable-users`

RBAC:
- HCM Admin only

Tenant:
- Hanya return user aktif pada active company.

Success `200`:

```json
{
  "success": true,
  "data": [{ "id": 10, "name": "Budi", "email": "budi@company.com" }]
}
```

## Ticket categories

### GET `/tickets/categories`

RBAC:
- HCM Admin only

### POST `/tickets/categories`

RBAC:
- HCM Admin only

Body:
- `name` required string max 120 unique
- `isActive` optional boolean
- `sortOrder` optional int 0..100000

### PUT `/tickets/categories/{id}`

RBAC:
- HCM Admin only

### DELETE `/tickets/categories/{id}`

RBAC:
- HCM Admin only

### GET `/tickets/category-options`

RBAC:
- Authenticated: allowed (dipakai untuk dropdown create ticket)

Behavior:
- hanya kategori `is_active=true`, return `{ id, name }`

