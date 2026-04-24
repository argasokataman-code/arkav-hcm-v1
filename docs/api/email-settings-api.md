# Email Settings API

Kontrak runtime awal untuk observability koneksi provider email pada halaman `/email-settings`.

## Auth & Access

- Semua endpoint wajib `Authorization: Bearer <token>`.
- Endpoint berada di grup `api.token + tenant.context`.
- Akses endpoint saat ini: global HCM admin only (`isGlobalHcmAdmin`).

## Identifier Policy

- Endpoint status saat ini tidak memakai path identifier.
- Scope policy adalah platform-level, bukan tenant-level.

## Endpoints

### POST /webhooks/email-inbound

Endpoint webhook inbound untuk menerima balasan email dari provider, lalu menyimpannya ke mailbox runtime (folder Inbox).

Keamanan:

- Endpoint ini berada di luar auth middleware.
- Request wajib mengirim header `X-Email-Inbound-Token` yang cocok dengan `EMAIL_INBOUND_WEBHOOK_TOKEN`.
- Route dibatasi rate-limit `throttle:60,1`.

Request contoh:

```json
{
  "message_id": "msg-2026-04-25-001",
  "from": "customer@example.com",
  "to": "qa.login@example.com",
  "subject": "Re: UI Runtime Send Test",
  "text": "Halo, ini balasan dari recipient.",
  "received_at": "2026-04-25T01:30:00+00:00"
}
```

Response 200 (created):

```json
{
  "success": true,
  "data": {
    "duplicate": false,
    "deliveryId": 123,
    "messageId": "msg-2026-04-25-001"
  }
}
```

Response 200 (duplicate/idempotent):

```json
{
  "success": true,
  "data": {
    "duplicate": true,
    "deliveryId": 123
  }
}
```

Response 401:

- token webhook tidak valid.

Response 503:

- token webhook belum dikonfigurasi di runtime.

### POST /webhooks/email-delivery-status

Endpoint webhook outbound status dari provider (contoh Brevo) untuk update status final delivery agar tidak berhenti di status "accepted by transport" saja.

Keamanan:

- Endpoint ini berada di luar auth middleware.
- Request wajib mengirim header `X-Email-Delivery-Token` yang cocok dengan `EMAIL_DELIVERY_WEBHOOK_TOKEN`.
- Route dibatasi rate-limit `throttle:60,1`.

Correlation key:

- Sistem compose runtime mengirim header `X-Arcav-Delivery-UUID` + `X-Mailin-custom`.
- Nilai UUID tersebut disimpan ke `notification_deliveries.notification_uuid`.
- Webhook status wajib mengirim `delivery_uuid` (atau metadata setara) agar event provider dapat dipetakan ke row delivery yang benar.

Request contoh:

```json
{
  "event": "delivered",
  "delivery_uuid": "7f06554e-9f34-41a5-9691-88f4f9e68181",
  "message-id": "<202604241900.49273623265@smtp-relay.sendinblue.com>",
  "status": "delivered"
}
```

Response 200:

```json
{
  "success": true,
  "data": {
    "deliveryId": 125,
    "deliveryUuid": "7f06554e-9f34-41a5-9691-88f4f9e68181",
    "event": "delivered",
    "status": "delivered"
  }
}
```

Response 401:

- token webhook tidak valid.

Response 404:

- `delivery_uuid` tidak ditemukan pada outbound compose log.

Response 422:

- payload status webhook tidak membawa delivery UUID yang bisa dipakai untuk korelasi.

Mapping status default:

- `delivered` -> `delivered`
- `deferred` -> `deferred`
- `hard_bounce|soft_bounce|blocked|invalid_email|spam|unsubscribed` -> `dropped`
- `error|rejected` -> `failed`

### Polling IMAP (opsional)

Untuk provider yang tidak menyediakan inbound webhook, sistem menyediakan command polling:

- `php artisan email:poll-imap-inbox`

Command ini membaca mailbox IMAP lalu menyimpan pesan baru ke `notification_deliveries` dengan `event_key=email.inbound.received`, sehingga tampil di folder Inbox runtime pada halaman `/email`.

### GET /v1/hcm/email-settings

Mengambil profil settings email aktif yang akan dipakai panel admin email settings.

Response 200:

```json
{
  "success": true,
  "data": {
    "provider": "smtp",
    "fromAddress": "noreply@example.com",
    "fromName": "Arkav System",
    "smtp": {
      "host": "smtp.example.com",
      "port": 587,
      "encryption": "tls",
      "username": "smtp-user",
      "passwordMasked": "****1234",
      "configured": true
    },
    "mailtrap": {
      "accountId": 3229,
      "apiTokenMasked": "****7890",
      "configured": true
    }
  }
}
```

Response 403:

- user bukan global HCM admin.

### PUT /v1/hcm/email-settings

Menyimpan profile settings email aktif.

Aturan validasi utama:

- `provider` wajib: `smtp` atau `mailtrap`.
- Jika `provider=smtp`, `smtp.host` dan `smtp.username` wajib terisi.
- Jika `provider=mailtrap`, `mailtrap.accountId` dan `mailtrap.apiToken` wajib terisi.
- Secret (`smtp.password`, `mailtrap.apiToken`) tidak dikembalikan utuh di response.

Request contoh (SMTP):

```json
{
  "provider": "smtp",
  "fromAddress": "noreply@example.com",
  "fromName": "Arkav Mail",
  "smtp": {
    "host": "smtp.example.com",
    "port": 465,
    "encryption": "ssl",
    "username": "smtp-user",
    "password": "smtp-password-5678"
  }
}
```

Response 200:

```json
{
  "success": true,
  "data": {
    "provider": "smtp",
    "fromAddress": "noreply@example.com",
    "fromName": "Arkav Mail",
    "smtp": {
      "host": "smtp.example.com",
      "port": 465,
      "encryption": "ssl",
      "username": "smtp-user",
      "passwordMasked": "****5678",
      "configured": true
    },
    "mailtrap": {
      "accountId": null,
      "apiTokenMasked": null,
      "configured": false
    }
  },
  "meta": {
    "updatedBy": {
      "id": 99,
      "uuid": "c89e4b80-fb84-4f7b-98ba-7d4dc358dc32",
      "email": "global-admin@example.com"
    },
    "updatedAt": "2026-04-24T12:00:00+00:00"
  }
}
```

Response 422:

- payload tidak valid atau field wajib per provider tidak terpenuhi.

Response 403:

- user bukan global HCM admin.

### POST /v1/hcm/email-settings/compose

Mengirim email runtime dari halaman `/email` memakai provider aktif yang sudah tersimpan di email settings.

Keputusan runtime:

- Endpoint ini dipanggil UI compose melalui `Authorization: Bearer <token>`, bukan submit form web berbasis sesi.
- Akses tetap global HCM admin only (`isGlobalHcmAdmin`).
- Payload hanya menerima satu recipient email per request baseline.
- Tidak memakai path identifier.

Request contoh:

```json
{
  "to": "argasokataman@gmail.com",
  "subject": "UI Runtime Send Test",
  "message": "Halo, ini email test yang dikirim langsung dari UI Arkav."
}
```

Response 200:

```json
{
  "success": true,
  "data": {
    "to": "argasokataman@gmail.com",
    "subject": "UI Runtime Send Test",
    "sentAt": "2026-04-24T18:10:00+00:00"
  }
}
```

Response 422:

- `to`, `subject`, atau `message` tidak valid / kosong.

Response 500:

```json
{
  "success": false,
  "error": {
    "code": "EMAIL_SEND_FAILED",
    "message": "Email gagal dikirim. Periksa email settings aktif lalu coba lagi."
  }
}
```

Response 403:

- user bukan global HCM admin.

### GET /v1/hcm/email-settings/mailtrap-status

Membaca status koneksi Mailtrap API berdasarkan konfigurasi environment runtime:

- `MAILTRAP_API_TOKEN`
- `MAILTRAP_ACCOUNT_ID`

Response 200 (connected):

```json
{
  "success": true,
  "data": {
    "provider": "mailtrap",
    "accountId": 3229,
    "tokenConfigured": true,
    "tokenLast4": "e5f6",
    "connected": true,
    "visibleTokenCount": 1,
    "visibleTokens": [
      {
        "id": 12345,
        "name": "My API Token",
        "last4": "x7k9",
        "expiresAt": null
      }
    ],
    "error": null
  }
}
```

Response 200 (credential env belum lengkap):

```json
{
  "success": true,
  "data": {
    "provider": "mailtrap",
    "accountId": null,
    "tokenConfigured": false,
    "tokenLast4": null,
    "connected": false,
    "visibleTokenCount": 0,
    "visibleTokens": [],
    "error": null
  },
  "message": "Mailtrap credentials are not fully configured in environment."
}
```

Response 200 (provider check gagal):

```json
{
  "success": true,
  "data": {
    "provider": "mailtrap",
    "accountId": 3229,
    "tokenConfigured": true,
    "tokenLast4": "e5f6",
    "connected": false,
    "visibleTokenCount": 0,
    "visibleTokens": [],
    "error": "Mailtrap API request failed (401): Unauthorized"
  }
}
```

Response 403:

- user bukan global HCM admin.

### POST /v1/hcm/email-settings/test-connection

Menjalankan uji koneksi provider menggunakan payload sementara tanpa menyimpan credential ke persistence settings.

Keputusan runtime:

- Endpoint ini **boleh menerima payload sementara** untuk test koneksi.
- Credential yang dikirim hanya dipakai dalam request saat itu dan tidak disimpan otomatis.
- Response tetap memakai envelope `success: true` walaupun koneksi gagal; hasil kegagalan dibaca dari `data.connected=false` dan `data.error`.
- Snapshot hasil test terakhir disimpan ke settings group `email` dan dikembalikan di `meta.lastTestStatus`.

Request contoh (SMTP):

```json
{
  "provider": "smtp",
  "timeout": 10,
  "smtp": {
    "host": "smtp.example.com",
    "port": 587,
    "encryption": "tls",
    "username": "smtp-user",
    "password": "smtp-password-5678"
  }
}
```

Response 200 (SMTP success):

```json
{
  "success": true,
  "data": {
    "provider": "smtp",
    "mode": "ephemeral",
    "persisted": false,
    "connected": true,
    "testedAt": "2026-04-24T13:00:00+00:00",
    "details": {
      "host": "smtp.example.com",
      "port": 587,
      "encryption": "tls",
      "username": "smtp-user",
      "timeout": 10
    },
    "error": null
  },
  "meta": {
    "lastTestStatus": {
      "provider": "smtp",
      "mode": "ephemeral",
      "connected": true,
      "testedAt": "2026-04-24T13:00:00+00:00",
      "details": {
        "host": "smtp.example.com",
        "port": 587,
        "encryption": "tls",
        "username": "smtp-user",
        "timeout": 10
      },
      "error": {
        "code": null,
        "message": null
      },
      "updatedBy": {
        "id": 99,
        "uuid": "c89e4b80-fb84-4f7b-98ba-7d4dc358dc32",
        "email": "global-admin@example.com"
      }
    }
  }
}
```

Response 200 (SMTP timeout):

```json
{
  "success": true,
  "data": {
    "provider": "smtp",
    "mode": "ephemeral",
    "persisted": false,
    "connected": false,
    "testedAt": "2026-04-24T13:01:00+00:00",
    "details": {
      "host": "smtp.example.com",
      "port": 587,
      "encryption": null,
      "username": "smtp-user",
      "timeout": 10
    },
    "error": {
      "code": "TIMEOUT",
      "message": "SMTP connection timed out."
    }
  },
  "meta": {
    "lastTestStatus": {
      "provider": "smtp",
      "mode": "ephemeral",
      "connected": false,
      "testedAt": "2026-04-24T13:01:00+00:00",
      "details": {
        "host": "smtp.example.com",
        "port": 587,
        "encryption": null,
        "username": "smtp-user",
        "timeout": 10
      },
      "error": {
        "code": "TIMEOUT",
        "message": "SMTP connection timed out."
      },
      "updatedBy": {
        "id": 99,
        "uuid": "c89e4b80-fb84-4f7b-98ba-7d4dc358dc32",
        "email": "global-admin@example.com"
      }
    }
  }
}
```

Request contoh (Mailtrap):

```json
{
  "provider": "mailtrap",
  "mailtrap": {
    "accountId": 3229,
    "apiToken": "mt_live_xxx"
  }
}
```

Response 200 (Mailtrap auth gagal):

```json
{
  "success": true,
  "data": {
    "provider": "mailtrap",
    "mode": "ephemeral",
    "persisted": false,
    "accountId": 3229,
    "tokenConfigured": true,
    "connected": false,
    "visibleTokenCount": 0,
    "visibleTokens": [],
    "testedAt": "2026-04-24T13:02:00+00:00",
    "error": {
      "code": "AUTH_FAILED",
      "message": "Mailtrap authentication failed."
    }
  },
  "meta": {
    "lastTestStatus": {
      "provider": "mailtrap",
      "mode": "ephemeral",
      "connected": false,
      "testedAt": "2026-04-24T13:02:00+00:00",
      "details": null,
      "error": {
        "code": "AUTH_FAILED",
        "message": "Mailtrap authentication failed."
      },
      "updatedBy": {
        "id": 99,
        "uuid": "c89e4b80-fb84-4f7b-98ba-7d4dc358dc32",
        "email": "global-admin@example.com"
      }
    }
  }
}
```

Response 422:

- `provider=smtp` tetapi `smtp.host`, `smtp.username`, atau `smtp.password` kosong.
- `provider=mailtrap` tetapi `mailtrap.accountId` atau `mailtrap.apiToken` kosong.

Response 403:

- user bukan global HCM admin.

## Planned Next Endpoints (Backlog)

- `POST /v1/hcm/email-settings/test-connection` (uji koneksi sebelum save)
- Endpoint persist hasil `last test` masih backlog opsional.
