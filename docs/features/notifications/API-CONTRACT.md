# Notifications API Contract (Draft)

Status: Draft (belum seluruh endpoint aktif di runtime)

## Prinsip Umum

- Semua endpoint wajib auth token.
- Semua endpoint tenant-scoped mengikuti `company_uuid` user auth context.
- Endpoint admin platform memerlukan gate primary super admin code-1.

## Endpoint User Inbox

## GET /v1/hcm/notifications

Query:
- `page` integer
- `perPage` integer (max 100)
- `isRead` boolean optional
- `eventKey` string optional
- `channel` enum: `database|mail`

Response 200:

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "uuid": "...",
        "eventKey": "asset.assigned",
        "title": "Asset assigned",
        "body": "Laptop LT-0001 telah di-assign",
        "severity": "important",
        "isRead": false,
        "createdAt": "2026-04-24T09:10:00Z"
      }
    ],
    "meta": {
      "page": 1,
      "perPage": 20,
      "total": 120,
      "unreadCount": 18
    }
  }
}
```

## POST /v1/hcm/notifications/{uuid}/read

Response 200:

```json
{
  "success": true,
  "message": "Notification marked as read"
}
```

## POST /v1/hcm/notifications/read-all

Response 200:

```json
{
  "success": true,
  "data": {
    "updated": 18
  }
}
```

## GET /v1/hcm/notifications/unread-count

Response 200:

```json
{
  "success": true,
  "data": {
    "unreadCount": 18
  }
}
```

## Endpoint User Preferences

## GET /v1/hcm/notification-preferences

Response 200:

```json
{
  "success": true,
  "data": [
    {
      "eventKey": "asset.assigned",
      "channel": "database",
      "enabled": true,
      "digestMode": "instant"
    }
  ]
}
```

## PUT /v1/hcm/notification-preferences

Request:

```json
{
  "preferences": [
    {
      "eventKey": "billing.invoice.due_soon",
      "channel": "mail",
      "enabled": true,
      "digestMode": "daily"
    }
  ]
}
```

Response 200:

```json
{
  "success": true,
  "message": "Preferences updated"
}
```

## Endpoint Admin Template (Platform)

## GET /v1/hcm/notifications/templates

Status: Runtime stub aktif (admin-only).

Response 200:
- list canonical template descriptor by `eventKey`, `title`, `description`, `severity`, `channels`, `digestModes`, `isEditable`.
- `meta.mode = "stub"` menandakan endpoint ini fondasi sebelum editor template full diaktifkan.

## PUT /v1/admin/notification-templates/{uuid}

Status: Target phase berikutnya (belum aktif runtime).

Request:

```json
{
  "titleTemplate": "[{{companyName}}] Invoice overdue",
  "bodyTemplate": "Invoice {{invoiceNumber}} overdue {{daysOverdue}} hari",
  "isActive": true
}
```

Response 200:
- template updated + version bump.

## POST /v1/admin/notification-templates/{uuid}/preview

Status: Target phase berikutnya (belum aktif runtime).

Request payload:
- sample context JSON.

Response 200:
- rendered preview title/body.

## Error Contract Minimum

- `401 UNAUTHENTICATED`
- `403 FORBIDDEN`
- `404 NOT_FOUND`
- `422 VALIDATION_ERROR`
- `429 TOO_MANY_REQUESTS` (endpoint read-all / update preference perlu throttling)

## Catatan Sinkronisasi

- Saat endpoint ini mulai diimplementasikan, sinkronkan ke:
  - `docs/api/openapi.yaml`
  - dokumen API feature terkait (billing, asset, subscriptions) jika event payload berubah.
