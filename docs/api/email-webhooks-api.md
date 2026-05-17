# Email Webhooks API

Dokumen ini adalah kontrak runtime untuk webhook email eksternal yang aktif di backend. Endpoint ini berada di luar auth bearer biasa dan diamankan dengan token webhook khusus di header atau payload.

## Current Runtime Boundary

- Endpoint berada di surface publik backend dan tidak memakai middleware `api.token`.
- Validasi akses memakai token statis per webhook:
  - inbound: `services.email_inbound.webhook_token`
  - delivery status: `services.email_delivery.webhook_token`
- Payload provider dibuat longgar supaya Mailtrap atau provider lain bisa diadaptasi tanpa mematahkan contract inti ARCAV.

## Endpoints

### POST /webhooks/email-inbound

Menerima email inbound dari provider lalu menyimpannya ke `notification_deliveries` sebagai event `email.inbound.received`.

Auth:
- Header `X-Email-Inbound-Token: <token>` atau field body `token`

Request body contoh:

```json
{
  "message_id": "msg-123",
  "from": "Sender <sender@example.com>",
  "to": "recipient@example.com",
  "subject": "Inbound hello",
  "text": "Hello from inbound webhook.",
  "received_at": "2026-05-17T11:45:00+07:00"
}
```

Response 200 baru:

```json
{
  "success": true,
  "data": {
    "duplicate": false,
    "deliveryId": 41,
    "messageId": "msg-123"
  }
}
```

Response 200 duplikat:

```json
{
  "success": true,
  "data": {
    "duplicate": true,
    "deliveryId": 41
  }
}
```

Response 401:
- `INVALID_WEBHOOK_TOKEN`

Response 503:
- `WEBHOOK_NOT_CONFIGURED`

Catatan:
- Idempotency memakai `message_id` / `messageId` / `id` / `X-Message-Id`, lalu fallback hash payload.
- Metadata inbound disimpan sebagai preview terpotong, bukan body mentah penuh.

### POST /webhooks/email-delivery-status

Menerima delivery status dari provider untuk event outbound compose email (`email.compose.sent`) dan mengupdate row `notification_deliveries` yang cocok.

Auth:
- Header `X-Email-Delivery-Token: <token>` atau field body `token`

Request body contoh:

```json
{
  "delivery_uuid": "delivery-123",
  "event": "delivered",
  "message_id": "provider-abc"
}
```

Response 200:

```json
{
  "success": true,
  "data": {
    "deliveryId": 77,
    "deliveryUuid": "delivery-123",
    "event": "delivered",
    "status": "delivered"
  }
}
```

Response 401:
- `INVALID_WEBHOOK_TOKEN`

Response 404:
- `DELIVERY_NOT_FOUND`

Response 422:
- `DELIVERY_UUID_REQUIRED`

Response 503:
- `WEBHOOK_NOT_CONFIGURED`

Mapped delivery status yang aktif saat ini:
- `delivered` -> `delivered`
- `deferred` -> `deferred`
- `opened`, `click`, `clicked`, `unique_opened`, `unique_clicked` -> `delivered`
- `hard_bounce`, `soft_bounce`, `blocked`, `invalid_email`, `spam`, `unsubscribed`, `bounce` -> `dropped`
- `error`, `rejected` -> `failed`
- selain itu -> `sent`

Catatan:
- Korelasi outbound memakai `delivery_uuid`, `deliveryUuid`, `notification_uuid`, `notificationUuid`, header `X-Arcav-Delivery-UUID`, atau field custom provider yang memuat `arcav_delivery_uuid`.
- History status provider disimpan di metadata `providerStatusHistory` maksimal 20 entri terakhir.