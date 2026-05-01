# Feature: Notes (`/notes`)

## Status
**Active** — CRUD fully wired to real data (migration, model, API, frontend JS).

---

## Business Overview

Notes is a personal productivity feature scoped per user per company (multi-tenant). Each user manages their own notes; no note is shared between users or companies. Notes support:
- Categorisation by **tag** (Personal, Social, Work, Others)
- **Priority** levels (Low, Medium, High)
- **Important** flag (star) for quick access
- **Trash** flow: move to trash → restore or permanently delete

This feature is non-collaborative and does not interact with other HCM modules.

---

## Data Model

**Table**: `notes`

| Column | Type | Default | Notes |
|---|---|---|---|
| `id` | bigint PK | auto | |
| `uuid` | varchar(36) | auto (boot) | public identifier |
| `user_id` | bigint | — | FK → `users.id` |
| `company_id` | bigint | — | FK → `companies.id` (cascade delete) |
| `title` | varchar(300) | — | required |
| `content` | text | null | optional |
| `tag` | enum | `personal` | `personal`, `social`, `work`, `others` |
| `priority` | enum | `medium` | `low`, `medium`, `high` |
| `is_important` | tinyint(1) | `0` | starred flag |
| `is_trashed` | tinyint(1) | `0` | soft trash flag |
| `created_at` / `updated_at` | timestamps | | |

Scope: every query filters by both `user_id` (authenticated user) AND `company_id` (active tenant from `X-Company-Id` header).

---

## API Endpoints

Base prefix: `/v1/hcm`  
Middleware: `api.token`, `tenant.context`  
Auth: bearer token (sets `request->attributes->get('activeCompanyId')`)

### GET `/v1/hcm/notes`
List notes for the authenticated user in the active company.

**Query params:**
- `tab` — `all` (default), `important` (is_important=1, not trashed), `trash` (is_trashed=1)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "...",
      "title": "...",
      "content": "...",
      "tag": "personal",
      "priority": "medium",
      "isImportant": false,
      "isTrashed": false,
      "createdAt": "2026-01-01T00:00:00.000000Z"
    }
  ]
}
```

### POST `/v1/hcm/notes`
Create a new note.

**Request body (JSON):**
```json
{
  "title": "string (required, max 300)",
  "content": "string (optional)",
  "tag": "personal|social|work|others (default: personal)",
  "priority": "low|medium|high (default: medium)"
}
```

**Response:** `{ "success": true, "data": { ...note } }`  
**Errors:** `422` if title missing, `401`/`403` if unauthenticated/tenant mismatch

### PUT `/v1/hcm/notes/{id}`
Update a note (partial update supported).

**Request body:** any subset of `title`, `content`, `tag`, `priority`, `is_important`, `is_trashed`

**Response:** `{ "success": true, "data": { ...note } }`  
**Errors:** `404` if note not found in user+company scope

### DELETE `/v1/hcm/notes/{id}`
Permanently delete a note.

**Response:** `{ "success": true }`  
**Errors:** `404` if not found in scope

---

## Frontend Architecture

**Source:** `frontend/resources/js/notes-data.js`  
**Build output:** `backend/public/build/js/notes-data.js`  
**Loaded via:** `@push('scripts')` in `backend/resources/views/applications/notes.blade.php`

### Tab Lifecycle

| Tab | Container ID | API call |
|---|---|---|
| All Notes | `#notes-all-grid` | `GET /notes?tab=all` |
| Important | `#notes-important-section`, `#notes-important-grid` | `GET /notes?tab=important` |
| Trash | `#notes-trash-grid` | `GET /notes?tab=trash` |

### Key JS Functions

| Function | Purpose |
|---|---|
| `loadAll()` | Load & render All tab |
| `loadImportant()` | Load & render Important tab (carousel + grid) |
| `loadTrash()` | Load & render Trash tab |
| `renderNotes(containerId, notes, inTrash)` | Render note cards into container |
| `noteCard(note, inTrash)` | Build Bootstrap card HTML |
| `setupAddForm()` | Wire `#note-add-submit` → POST API |
| `setupEditForm()` | Wire `#note-edit-submit` → PUT API |
| `setupDeleteModal()` | Wire `#note-delete-confirm` → DELETE API |
| `toggleImportant(id, current)` | Toggle `is_important` via PUT |
| `moveToTrash(id)` | Set `is_trashed=true` via PUT |
| `restoreFromTrash(id)` | Set `is_trashed=false` via PUT |
| `bindCardActions(container, inTrash)` | Attach click handlers to rendered cards |

### Modal IDs (in `modal-popup.blade.php`)

| Modal | ID | Purpose |
|---|---|---|
| Add | `#add_note` | Create new note |
| Edit | `#edit-note-units` | Edit existing note |
| Delete | `#delete_modal` | Confirm permanent delete |
| View | `#view-note-units` | Read-only note detail |

**Form field IDs used by JS:**

| ID | Modal | Description |
|---|---|---|
| `note-add-title` | Add | Note title input |
| `note-add-tag` | Add | Tag select |
| `note-add-priority` | Add | Priority select |
| `note-add-content` | Add | Content textarea |
| `note-add-submit` | Add | Submit button |
| `note-edit-title` | Edit | Note title input |
| `note-edit-tag` | Edit | Tag select |
| `note-edit-priority` | Edit | Priority select |
| `note-edit-content` | Edit | Content textarea |
| `note-edit-submit` | Edit | Save button |
| `note-delete-confirm` | Delete | Confirm delete button |
| `note-view-title` | View | Title display |
| `note-view-tag` | View | Tag display |
| `note-view-content` | View | Content display |
| `note-view-priority` | View | Priority badge |

---

## Permissions / RBAC

Notes is a **user-personal** feature — no role-level restriction at data access level. Any authenticated user with an active company context can CRUD their own notes. The API enforces:
1. `api.token` middleware — valid bearer token required (401 if missing)
2. `tenant.context` middleware — valid `X-Company-Id` header required
3. Controller scope: `user_id = auth()->id()` AND `company_id = activeCompanyId()` on every query

There is no admin-level view of other users' notes. This is by design.

---

## Files Changed

| File | Change |
|---|---|
| `backend/database/migrations/2026_05_07_000100_create_notes_table.php` | Created — notes table schema |
| `backend/app/Models/Note.php` | Created — Eloquent model |
| `backend/app/Http/Controllers/Api/HcmNoteController.php` | Created — CRUD controller |
| `backend/routes/api/notes.php` | Created — API routes |
| `backend/routes/api.php` | Modified — require notes.php |
| `frontend/resources/js/notes-data.js` | Created — frontend CRUD JS |
| `backend/public/build/js/notes-data.js` | Built artifact |
| `backend/resources/views/applications/notes.blade.php` | Modified — dynamic containers + script push |
| `backend/resources/views/components/modal-popup.blade.php` | Modified — notes modals with proper field IDs |

---

## Known Gaps / Future Work

- Notes do not support file attachments.
- No search/filter within notes (full-text search not implemented).
- No pagination — all notes returned at once (acceptable for personal notes volume).
- The 8 static note partials in `backend/resources/views/partials/notes/` are no longer used by notes.blade.php but have not been deleted.
