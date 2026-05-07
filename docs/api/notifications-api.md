# Notifications API

Dokumen ini adalah kontrak runtime Phase 1 untuk inbox notifikasi dan preferensi notifikasi personal.

## Auth & Tenant Scope

- Semua endpoint wajib `Authorization: Bearer <token>`.
- Endpoint berada di group `api.token + tenant.context`.
- User regular hanya menerima notif tenant aktif (berdasarkan `companyUuid` payload notifikasi).
- Global HCM admin tetap wajib berada dalam tenant context aktif; endpoint observability dibatasi ke tenant aktif, bukan lintas tenant.

## Identifier Policy

- `notification` path parameter menggunakan UUID dari tabel `notifications.id`.
- Endpoint preference memakai user autentikasi aktif (tanpa userId di path).

## Endpoints

### GET /v1/hcm/notifications

Query opsional:
- `page` integer min 1 (default 1)
- `perPage` integer min 1 max 100 (default 20)
- `isRead` boolean
- `eventKey` string
- `channel` enum: `database|mail|sms|webhook`

Response 200:

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "uuid": "9ad23a78-3e71-43a7-a6a8-c4a8ce43d4a1",
        "eventKey": "asset.assigned",
        "title": "Asset Assigned",
        "body": "Laptop assigned to you",
        "severity": "informational",
        "channel": "database",
        "isRead": false,
        "readAt": null,
        "createdAt": "2026-04-24T11:31:40+00:00",
        "data": {
          "eventKey": "asset.assigned",
          "companyUuid": "7f8f6db6-cba9-4af0-b6cb-8c0eb2f4dc24"
        }
      }
    ],
    "meta": {
      "page": 1,
      "perPage": 20,
      "total": 1,
      "unreadCount": 1
    }
  }
}
```

### POST /v1/hcm/notifications/{notification}/read

Menandai satu notifikasi sebagai read.

Response 200:

```json
{
  "success": true,
  "message": "Notification marked as read"
}
```

Response 404:
- Notifikasi tidak ditemukan untuk user login.

### POST /v1/hcm/notifications/read-all

Menandai semua notifikasi unread milik user login sebagai read.

Response 200:

```json
{
  "success": true,
  "data": {
    "updated": 2
  }
}
```

### GET /v1/hcm/notifications/unread-count

Response 200:

```json
{
  "success": true,
  "data": {
    "unreadCount": 0
  }
}
```

### GET /v1/hcm/notifications/delivery-summary

Endpoint observability internal untuk ringkasan delivery notifikasi dalam window waktu tertentu.

Auth & role:
- Wajib login.
- Hanya global HCM admin (`isGlobalHcmAdmin`) yang boleh akses.
- Data tetap tenant-scoped ke `activeCompanyUuid` dari middleware `tenant.context`.

Query opsional:
- `hours` integer min 1 max 720 (default 24)
- `channel` enum: `database|mail|sms|webhook`
- `eventKey` string

Response 200:

```json
{
  "success": true,
  "data": {
    "windowHours": 24,
    "from": "2026-04-24T12:00:00+00:00",
    "to": "2026-04-25T12:00:00+00:00",
    "totals": {
      "all": 12,
      "sent": 9,
      "failed": 2,
      "dropped": 1
    },
    "breakdown": {
      "byStatus": [
        { "status": "sent", "total": 9 },
        { "status": "failed", "total": 2 },
        { "status": "dropped", "total": 1 }
      ],
      "byChannel": [
        { "channel": "mail", "total": 12 }
      ]
    },
    "topFailedEvents": [
      { "eventKey": "billing.invoice.email_failed", "total": 2 }
    ]
  }
}
```

Response 403:
- user bukan global HCM admin.

Response 422:
- tenant context aktif tidak tersedia (`TENANT_CONTEXT_REQUIRED`).

### GET /v1/hcm/notifications/delivery-export

Endpoint observability internal untuk export delivery records tenant aktif.

Auth & role:
- Wajib login.
- Hanya global HCM admin (`isGlobalHcmAdmin`) yang boleh akses.
- Data tenant-scoped ke `activeCompanyUuid` dari middleware `tenant.context`.

Query opsional:
- `status` enum: `sent|failed|dropped`
- `hours` integer min 1 max 720 (default 24)
- `channel` enum: `database|mail|sms|webhook`
- `eventKey` string
- `format` enum: `xlsx|csv` (default `xlsx`)

Response 200:
- `Content-Type`:
  - `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` (default)
  - `text/csv; charset=UTF-8` jika `format=csv`
- `Content-Disposition: attachment; filename="notification-deliveries-*.<xlsx|csv>"`

### GET /v1/hcm/notifications/templates

Endpoint stub katalog template notifikasi untuk admin global.

Auth & role:
- Wajib login.
- Hanya global HCM admin (`isGlobalHcmAdmin`) yang boleh akses.

Response 200:

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "eventKey": "leave.requested",
        "title": "Leave request submitted",
        "description": "New leave request submitted by employee awaiting approval.",
        "severity": "important",
        "channels": ["database", "mail", "sms", "webhook"],
        "digestModes": ["instant", "daily", "weekly"],
        "isEditable": false
      }
    ],
    "meta": {
      "total": 34,
      "mode": "stub"
    }
  }
}
```

Response 403:
- user bukan global HCM admin.

### GET /v1/hcm/notification-preferences

Mengambil preferensi notifikasi milik user login.

Response 200:

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "eventKey": "billing.payment_failed",
        "channel": "mail",
        "enabled": true,
        "digestMode": "instant"
      }
    ]
  }
}
```

### PUT /v1/hcm/notification-preferences

Request body:

```json
{
  "preferences": [
    {
      "eventKey": "asset.assigned",
      "channel": "database",
      "enabled": false,
      "digestMode": "weekly"
    }
  ]
}
```

Validasi:
- `preferences` wajib array min 1.
- `eventKey` wajib string.
- `channel` wajib enum `database|mail|sms|webhook`.
- `enabled` wajib boolean.
- `digestMode` opsional enum `instant|daily|weekly`.

Response 200:
- Mengembalikan payload yang sama formatnya dengan `GET /v1/hcm/notification-preferences`.

## Test Coverage

- `backend/tests/Feature/NotificationInboxApiTest.php`
- `backend/tests/Feature/NotificationPreferenceApiTest.php`
- `backend/tests/Feature/NotificationDeliverySummaryApiTest.php`
