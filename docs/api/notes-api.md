# Notes API

## Base

- **Prefix**: `/v1/hcm`
- **Middleware**: `api.token`, `tenant.context`
- **Auth**: Bearer token via `Authorization` header
- **Tenant**: `X-Company-Id` header (integer company ID)

---

## Endpoints

### GET `/v1/hcm/notes`

List notes for the authenticated user in the active company.

**Query parameters:**

| Param | Values | Default | Description |
|---|---|---|---|
| `tab` | `all`, `important`, `trash` | `all` | Filter set |

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Team sync tomorrow",
      "content": "Discuss Q3 roadmap",
      "tag": "work",
      "priority": "high",
      "isImportant": false,
      "isTrashed": false,
      "createdAt": "2026-01-01T08:00:00.000000Z"
    }
  ]
}
```

**Error responses:** `401` (missing/invalid token), `400` (missing X-Company-Id)

---

### POST `/v1/hcm/notes`

Create a new note.

**Request body (JSON):**

| Field | Type | Required | Validation |
|---|---|---|---|
| `title` | string | yes | max 300 chars |
| `content` | string | no | |
| `tag` | string | no | `personal`, `social`, `work`, `others` (default: `personal`) |
| `priority` | string | no | `low`, `medium`, `high` (default: `medium`) |

**Response 201:**
```json
{
  "success": true,
  "data": { ...note }
}
```

**Error responses:** `422` (validation), `401`, `400`

---

### PUT `/v1/hcm/notes/{id}`

Update a note. Supports partial updates.

**URL params:** `id` — numeric note ID

**Request body (JSON, all optional):**

| Field | Type | Description |
|---|---|---|
| `title` | string | Update title |
| `content` | string | Update content |
| `tag` | string | Update tag |
| `priority` | string | Update priority |
| `is_important` | boolean | Toggle important flag |
| `is_trashed` | boolean | Move to/restore from trash |

**Response 200:**
```json
{
  "success": true,
  "data": { ...note }
}
```

**Error responses:** `404` (not found in user+company scope), `422`, `401`

---

### DELETE `/v1/hcm/notes/{id}`

Permanently delete a note.

**URL params:** `id` — numeric note ID

**Response 200:**
```json
{ "success": true }
```

**Error responses:** `404` (not found in user+company scope), `401`

---

## Security

- All endpoints enforce **user-level ownership**: `user_id = auth()->id()` — users cannot read or modify other users' notes.
- All endpoints enforce **tenant isolation**: `company_id = activeCompanyId()` — no cross-company data access.
- No role/permission matrix restriction — any authenticated user within a company can manage their own notes.
