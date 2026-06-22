# HCM Approval Settings API

**Prefix:** `/v1/hcm/approval-settings`
**Auth:** Bearer token + `X-Company-Id` header (tenant context required)
**RBAC:** Admin-only (`EnsuresHcmAdmin` middleware)
**Middleware:** `api.token`, `tenant.context`, `EnsuresHcmAdmin`

---

## Supported Modules

```
leave | expense | offer | overtime | resignation | termination
```

---

## GET /v1/hcm/approval-settings

Returns current approval configuration for all supported modules of the active company.

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
        { "userId": 26, "userUuid": "...", "name": "King", "email": "king@mail.com", "sequenceOrder": 1 }
      ]
    },
    "overtime": { "...", "isActive": false, "approvers": [] },
    "resignation": { "...", "isActive": false, "approvers": [] },
    "termination": { "...", "isActive": false, "approvers": [] },
    "expense": { "...", "isActive": false, "approvers": [] },
    "offer": { "...", "isActive": false, "approvers": [] }
  }
}
```

Modules with no saved config return `isActive: false`, `approvalMode: 'simultaneous'`, `approvers: []`.

### Error Responses

| Code | Status | Meaning |
|---|---|---|
| `TENANT_REQUIRED` | 400 | Missing or invalid `X-Company-Id` |
| — | 401 | Unauthenticated |
| — | 403 | Not an HCM admin for this company |

---

## PUT /v1/hcm/approval-settings/{module}

Upserts (create or update) the approval config for the given module.

### Path Parameter

| Name | Type | Description |
|---|---|---|
| `module` | string | One of the supported modules (regex: `[a-z_]+`) |

### Request Body

```json
{
  "approvalMode": "simultaneous",
  "approverUserIds": [26, 19]
}
```

| Field | Type | Rules |
|---|---|---|
| `approvalMode` | string | Required. `simultaneous` or `sequence` |
| `approverUserIds` | integer[] | Required, 1–10 items. Each must be active member of the company. |

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
|---|---|---|
| `INVALID_MODULE` | 422 | Module not in supported list |
| `APPROVER_NOT_IN_COMPANY` | 422 | One or more approver IDs are not active members of this company |
| `TENANT_REQUIRED` | 400 | Missing or invalid company context |
| — | 422 | Validation failure (missing fields, wrong types, >10 approvers) |

---

## GET /v1/hcm/approval-settings/eligible-approvers

Returns list of users eligible to be approvers for the active company. Used by Select2 AJAX search in UI.

### Query Parameters

| Name | Type | Default | Description |
|---|---|---|---|
| `q` | string | `""` | Search term matched against `name`, `email`, `designation` (LIKE) |

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

Max 20 results. Returns only active `CompanyUser` members.

---

## Approval Mode Semantics

| Mode | Notification | Approval Requirement |
|---|---|---|
| `sequence` | Approvers notified one-by-one in `sequenceOrder`. Each must approve before next is notified. | All must approve |
| `simultaneous` | All approvers notified at once. | Any one approval satisfies the flow |

The `isActive` flag is `true` whenever at least one approver is configured.

---

## OpenAPI Spec

Referensi: `docs/api/openapi.yaml` — path `/v1/hcm/approval-settings`.

---

## Change Log

| Date | Change |
|---|---|
| 2026-05-29 | Initial API implementation |
| 2026-05-29 | Added `APPROVER_NOT_IN_COMPANY` validation (ANOMALI-7 fix) |
| 2026-06-22 | 54 tests covering all endpoints and error codes |
