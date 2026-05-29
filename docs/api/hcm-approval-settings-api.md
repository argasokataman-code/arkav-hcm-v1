# HCM Approval Settings API

**Prefix**: `/v1/hcm/approval-settings`
**Auth**: Bearer token + `X-Company-Id` header (tenant context required)
**RBAC**: Admin-only (`owner` or `admin` role, or global admin)

## Supported Modules

`leave` | `expense` | `offer` | `overtime` | `resignation` | `termination`

---

## GET /v1/hcm/approval-settings

Returns the current approval configuration for all supported modules of the active company.

### Response (200)

```json
{
  "success": true,
  "data": {
    "leave": {
      "module": "leave",
      "approvalMode": "simultaneous",
      "isActive": true,
      "approvers": [
        {
          "userId": 26,
          "userUuid": "...",
          "name": "King",
          "email": "king@mail.com",
          "sequenceOrder": 1
        }
      ]
    },
    "overtime": { "..." },
    "resignation": { "..." },
    "termination": { "..." },
    "expense": { "..." },
    "offer": { "..." }
  }
}
```

If no config exists for a module, it returns `isActive: false` with an empty `approvers` array.

### Error Responses

| Code | Status | Meaning |
|------|--------|---------|
| `TENANT_REQUIRED` | 400 | Missing or invalid `X-Company-Id` |
| — | 401 | Unauthenticated |
| — | 403 | Not an HCM admin for this company |

---

## PUT /v1/hcm/approval-settings/{module}

Upserts (create or update) the approval config for the given module.

### Path Parameter

| Name | Type | Description |
|------|------|-------------|
| `module` | string | One of the supported modules |

### Request Body

```json
{
  "approvalMode": "simultaneous",
  "approverUserIds": [26, 19]
}
```

| Field | Type | Rules |
|-------|------|-------|
| `approvalMode` | string | Required. `simultaneous` or `sequence` |
| `approverUserIds` | integer[] | Required, 1–10 items. Each must be an active member of the company. |

### Response (200)

```json
{
  "success": true,
  "data": {
    "module": "leave",
    "approvalMode": "simultaneous",
    "isActive": true,
    "approvers": [
      { "userId": 26, "name": "King", "sequenceOrder": 1 },
      { "userId": 19, "name": "Sembrani", "sequenceOrder": 2 }
    ]
  }
}
```

### Error Responses

| Code | Status | Meaning |
|------|--------|---------|
| `INVALID_MODULE` | 422 | Module not in supported list |
| `TENANT_REQUIRED` | 400 | Missing or invalid company context |
| `APPROVER_NOT_IN_COMPANY` | 422 | One or more approver IDs are not active members of this company |
| — | 422 | Validation failure (missing fields, wrong types) |

---

## GET /v1/hcm/approval-settings/eligible-approvers

Returns a list of users eligible to be approvers for the active company. Used by Select2 AJAX search in the UI.

### Query Parameters

| Name | Type | Default | Description |
|------|------|---------|-------------|
| `q` | string | `""` | Search term matched against `name`, `email`, and `designation` (LIKE) |

### Response (200)

```json
{
  "success": true,
  "data": [
    { "id": 26, "name": "King", "email": "king@mail.com", "designation": "QA Engineer" },
    { "id": 19, "name": "Sembrani", "email": "sembrani@mail.com", "designation": "HR Manager" }
  ]
}
```

Max 20 results. Returns only active `CompanyUser` members with a `user` relation.

---

## Notes

- **Approval mode `sequence`**: Approvers are notified one-by-one in `sequence_order`. Each must approve before the next is notified.
- **Approval mode `simultaneous`**: All approvers are notified at once; any one approval satisfies the flow.
- The `isActive` flag is `true` whenever at least one approver is configured.
- Module visibility on the settings page depends on whether the company has the corresponding feature package (e.g., `resignation` module requires the `resignation` package).
