# Core HCM — Master Data API (Phase 2)

Mencakup: departments, designations, policies, teams.

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmEmployeeController.php` (method departments/designations/policies) + `backend/app/Http/Controllers/Api/HcmTeamController.php` (teams).

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

## Teams

- `GET /teams`
- `POST /teams` (admin only)
- `GET /teams/{id}` (admin only)
- `PUT /teams/{id}` (admin only)
- `DELETE /teams/{id}` (admin only)
- `POST /teams/reassign-members` (admin only)
- `GET /teams/{id}/members` (admin atau team lead untuk team tersebut)

### GET `/teams`

RBAC:
- HCM Admin only (`team.manage`, fallback transisi `employee.manage`)

Query:
- `page` optional (default `1`)
- `perPage` optional (default `20`, max `100`)
- `search` optional (filter by name)
- `status` optional: `all|active|inactive`

Success `200`:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "5b80c4bb-7152-45e6-8f97-0a4c0d9ebf17",
      "company_id": 1,
      "name": "Platform Squad",
      "department_id": 2,
      "department_name": "Engineering",
      "team_lead_id": 11,
      "team_lead_name": "Rina Putri",
      "member_count": 8,
      "is_active": true,
      "created_at": "2026-04-27T08:10:22+07:00",
      "updated_at": "2026-04-27T08:10:22+07:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 1
  }
}
```

### POST `/teams`

RBAC:
- HCM Admin only (`team.manage`, fallback transisi `employee.manage`)

Body:
- `name` required string max `100`
- `department_id` required integer exists `departments.id`
- `team_lead_id` optional integer exists `users.id`
- `is_active` optional boolean (default `true`)

Success `201`: `{ success: true, data: <team> }`

### PUT `/teams/{id}`

RBAC:
- HCM Admin only (`team.manage`, fallback transisi `employee.manage`)

Body:
- `name` optional|required string max `100`
- `department_id` optional|required integer exists `departments.id`
- `team_lead_id` nullable integer exists `users.id`
- `is_active` optional boolean

Success `200`: `{ success: true, data: <team> }`

### DELETE `/teams/{id}`

RBAC:
- HCM Admin only (`team.manage`, fallback transisi `employee.manage`)

Success `204`

Error:
- `409 TEAM_DELETION_BLOCKED` jika masih ada member aktif pada team

### POST `/teams/reassign-members`

RBAC:
- HCM Admin only (`team.manage`, fallback transisi `employee.manage`)

Body:
- `employee_ids` required array integer (min 1, max 200)
- `source_team_id` nullable integer (opsional guard: semua employee harus berasal dari team ini)
- `target_team_id` nullable integer (target team; `null` untuk unassign team)

Success `200`:

```json
{
  "success": true,
  "data": {
    "requested_count": 2,
    "affected_count": 2,
    "source_team_id": 11,
    "target_team": {
      "id": 12,
      "name": "Ops B"
    }
  }
}
```

Error:
- `404 TEAM_NOT_FOUND` jika `target_team_id` tidak ditemukan di tenant aktif
- `422 TEAM_INACTIVE_NOT_ASSIGNABLE` jika `target_team_id` mengacu ke team inactive
- `422 EMPLOYEE_SCOPE_MISMATCH` jika ada employee di luar tenant/scope atau tidak sesuai `source_team_id`

### GET `/teams/{id}/members`

RBAC:
- HCM Admin, atau
- Team lead dari team yang diminta (`team_lead_id == user login`) **dan** memiliki permission `team.lead`

Query:
- `page` optional (default `1`)
- `perPage` optional (default `20`, max `100`)
- `search` optional (`name`, `email`, `nik`)
- `status` optional: `all|active|probation|inactive`

Success `200`:

```json
{
  "success": true,
  "data": {
    "team": {
      "id": 1,
      "name": "Platform Squad"
    },
    "members": [
      {
        "employee_id": 14,
        "user_id": 33,
        "name": "Adi Setiawan",
        "email": "adi@example.com",
        "nik": "EMP-0014",
        "department_name": "Engineering",
        "designation_name": "Backend Engineer",
        "employment_status": "active"
      }
    ]
  },
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 1
  }
}
```

