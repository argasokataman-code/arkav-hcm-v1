# Core HCM — Master Data API (Phase 1)

Mencakup: departments, designations, policies.

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmEmployeeController.php` (method departments/designations/policies).

## Base path

`/v1/hcm`

## Departments

- `GET /departments`
- `POST /departments` (admin only)
- `PUT /departments/{id}` (admin only)
- `DELETE /departments/{id}` (admin only)

### GET `/departments`

RBAC:
- HCM Admin only

Query:
- `search` optional (string)

Success `200`:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "HR",
      "name": "Human Resources",
      "designationCount": 2,
      "isActive": true
    }
  ]
}
```

### POST `/departments`

RBAC:
- HCM Admin only

Body:
- `name` required string max 150
- `code` optional string max 50 unique (default auto slug dari `name`)
- `isActive` optional boolean

Success `201`: `{ success: true, data: <department> }`

### PUT `/departments/{id}`

RBAC:
- HCM Admin only

Body:
- `name` required string max 150
- `code` optional string max 50 unique (ignore current id)
- `isActive` optional boolean

Success `200`: `{ success: true, data: <department> }`

## Designations

- `GET /designations`
- `POST /designations` (admin only)
- `PUT /designations/{id}` (admin only)
- `DELETE /designations/{id}` (admin only)

### GET `/designations`

RBAC:
- HCM Admin only

Query:
- `search` optional

Success `200` (ringkas):
- item menyertakan `departmentId`, `department`, `isActive`

### POST `/designations`

RBAC:
- HCM Admin only

Body:
- `name` required string max 150
- `code` optional string max 50 unique (default auto slug dari `name`)
- `departmentId` optional integer exists `departments.id`
- `isActive` optional boolean

Success `201`: `{ success: true, data: <designation> }`

### PUT `/designations/{id}`

RBAC:
- HCM Admin only

Body:
- `name` required
- `code` optional unique ignore current id
- `departmentId` optional
- `isActive` optional

## Policies

- `GET /policies`
- `POST /policies` (admin only)
- `PUT /policies/{id}` (admin only)
- `DELETE /policies/{id}` (admin only)

### GET `/policies`

RBAC:
- HCM Admin only

Query:
- `search` optional (name/description)

Success `200`:
- item menyertakan `attachmentUrl` (relative `/storage/...`) jika ada

### POST `/policies`

RBAC:
- HCM Admin only

Content-Type:
- `multipart/form-data` (support attachment)

Body:
- `name` required string max 150
- `description` required string
- `departmentId` optional integer exists `departments.id`
- `effectiveDate` optional date (default today jika kosong)
- `attachment` optional file max 12288KB, mimetypes:
  - `application/pdf`
  - `image/jpeg|png|gif|webp`

Error:
- `422` jika attachment invalid (message dari media validator)

### PUT `/policies/{id}`

Sama seperti POST (multipart) + replace attachment jika dikirim.

