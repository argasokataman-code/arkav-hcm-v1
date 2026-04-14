# Tickets API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmTicketController.php`.

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

Success `201`:

- `data[]` item menyertakan `reporter`, `assignee`, `commentsCount`, `attachmentsCount`, `categoryId`
{ "success": true, "data": { "id": 1 } }
```

### POST `/tickets/{id}/attachments`

Multipart:
- `file` required file, max 5120KB, mimes: `jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv,txt`

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

