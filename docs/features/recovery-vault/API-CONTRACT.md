# Recovery Vault - API Contract (Draft v0.1)

Status: draft for implementation planning.

Scope dokumen ini adalah kontrak API yang cukup preskriptif untuk mulai implementasi backend service Recovery Vault.

---

## Conventions

- Base URL internal: `/v1/internal/recovery`
- Base URL admin: `/v1/saas/recovery-vault`
- Response envelope:
  - success:
    - `success: true`
    - `data: ...`
    - `meta: ...` (optional)
  - error:
    - `success: false`
    - `error.code`
    - `error.message`
    - `error.details` (optional)

---

## Authentication and Authorization

### Internal endpoints

Wajib:

- mTLS atau private network only.
- `Authorization: Bearer <service_token>`.
- `X-Signature: <hmac_sha256(payload)>`.
- `X-Request-Id: <uuid>`.
- `X-Idempotency-Key: <uuid>`.
- `X-Source-Service: <service_name>`.

### Admin endpoints

Wajib:

- `api.token` middleware.
- super admin policy check server-side.
- audit semua akses endpoint admin.

---

## Internal API

### POST `/v1/internal/recovery/events`

Tujuan:
Menyimpan audit event immutable dari service aplikasi.

Request body:

```json
{
  "eventUuid": "7a54b9f4-8c45-4db8-9c20-d6f7a4e4ef77",
  "occurredAt": "2026-04-14T10:22:11Z",
  "companyId": 1,
  "actor": {
    "userId": 12,
    "role": "hcm_admin"
  },
  "entity": {
    "type": "employee_profiles",
    "id": "44"
  },
  "action": "updated",
  "before": {
    "phone": "08120000111"
  },
  "after": {
    "phone": "08120000999"
  },
  "request": {
    "requestId": "3f8b7fbb-6a18-46e2-8efa-f08f71076f7a",
    "route": "PUT /v1/hcm/employees/44",
    "ip": "10.10.2.44",
    "userAgent": "arcav-backend/1.0"
  },
  "tags": ["hcm", "employees"],
  "sensitivity": "normal"
}
```

Response:

- `201` event accepted and persisted.
- `202` event accepted async via queue/outbox.
- `409` duplicate idempotency key.
- `422` invalid payload.

---

### POST `/v1/internal/recovery/snapshots`

Tujuan:
Membuat snapshot recovery point untuk scope tertentu.

Request body:

```json
{
  "snapshotUuid": "1d52fc28-6b3d-4f26-a167-b67c7863ee75",
  "companyId": 1,
  "scope": "company",
  "fromEventId": 10001,
  "toEventId": 11234,
  "reason": "daily_backup",
  "requestedBy": "scheduler"
}
```

Response:

- `201` snapshot record created (`status=pending` or `completed`).
- `202` snapshot queued.

---

### POST `/v1/internal/recovery/restores`

Tujuan:
Menjalankan restore dari snapshot.

Request body:

```json
{
  "restoreUuid": "d81c7bce-5f4d-4be8-9d22-7fd63099ff40",
  "snapshotUuid": "1d52fc28-6b3d-4f26-a167-b67c7863ee75",
  "target": {
    "mode": "staging_clone",
    "database": "arcav_hcm_restore_stg"
  },
  "options": {
    "dryRun": false,
    "verifyChecksum": true
  },
  "reason": "incident_recovery",
  "requestedBy": "super_admin"
}
```

Response:

- `202` restore job queued.
- `409` restore already running for same target.
- `422` invalid state.

---

## Admin API

### GET `/v1/saas/recovery-vault/events`

Query params:

- `companyId`
- `entityType`
- `entityId`
- `action`
- `from`
- `to`
- `page`
- `perPage`

Response:

```json
{
  "success": true,
  "data": [
    {
      "id": 11234,
      "eventUuid": "7a54b9f4-8c45-4db8-9c20-d6f7a4e4ef77",
      "companyId": 1,
      "entityType": "employee_profiles",
      "entityId": "44",
      "action": "updated",
      "actorUserId": 12,
      "occurredAt": "2026-04-14T10:22:11Z"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 1
  }
}
```

---

### GET `/v1/saas/recovery-vault/snapshots`

Tujuan:
List snapshot yang tersedia untuk restore.

Response fields minimum:

- `snapshotUuid`
- `companyId`
- `scope`
- `eventRange`
- `status`
- `checksum`
- `createdAt`

---

### GET `/v1/saas/recovery-vault/snapshots/{snapshotUuid}`

Tujuan:
Melihat detail snapshot + verifikasi readiness.

---

### POST `/v1/saas/recovery-vault/snapshots/{snapshotUuid}/restore`

Tujuan:
Trigger restore dari panel super admin.

Request body:

```json
{
  "targetMode": "staging_clone",
  "targetDatabase": "arcav_hcm_restore_stg",
  "dryRun": false,
  "reason": "incident_recovery"
}
```

Response:

- `202` restore queued.
- `403` not super admin.
- `409` restore conflict.

---

## Error Codes (recommended)

- `RECOVERY_UNAUTHORIZED`
- `RECOVERY_FORBIDDEN`
- `RECOVERY_INVALID_SIGNATURE`
- `RECOVERY_DUPLICATE_IDEMPOTENCY`
- `RECOVERY_SNAPSHOT_NOT_FOUND`
- `RECOVERY_SNAPSHOT_NOT_READY`
- `RECOVERY_RESTORE_CONFLICT`
- `RECOVERY_VALIDATION_FAILED`

---

## Idempotency Rules

- Internal create event/snapshot/restore wajib idempotent.
- Key uniqueness:
  - event ingest: `X-Idempotency-Key` unique per source service.
  - snapshot create: `snapshotUuid` unique globally.
  - restore create: `restoreUuid` unique globally.

---

## Rate Limits

- Internal ingest: high throughput, queue-friendly.
- Admin endpoints: strict rate-limit per super admin account.

---

## Audit on Audit

Setiap call admin endpoint Recovery Vault juga harus dicatat sebagai audit event dengan `entity_type=recovery_vault` agar akses terhadap data recovery dapat ditelusuri.