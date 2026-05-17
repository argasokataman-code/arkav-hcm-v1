# Email Settings API

Dokumen ini adalah kontrak runtime untuk surface email settings yang saat ini aktif di backend. Fokusnya ada pada profile settings email, probe koneksi SMTP/Mailtrap, dan observability Mailtrap dasar.

## Auth & Role Scope

- Semua endpoint wajib `Authorization: Bearer <token>`.
- Endpoint berada di group `api.token + tenant.context`.
- Semua endpoint email settings dibatasi ke **global HCM admin** melalui `ensureGlobalHcmAdmin()`.
- Halaman web aktif `/email-settings` sekarang me-wire load profile, save profile, Mailtrap status, dan test connection ke endpoint API ini.

## Current Runtime Boundary

- Surface web aktif: `GET /email-settings` menampilkan control-plane runtime baseline untuk profile email aktif, Mailtrap health, dan referensi fallback ENV/config Laravel.
- Compose email manual untuk halaman `/email` **bukan** bagian dari group email-settings; source of truth-nya adalah `POST /v1/hcm/notifications/send-email` di dokumen [docs/api/notifications-api.md](docs/api/notifications-api.md).
- Route `/email-template` sudah dihapus dari surface aktif dan belum punya runtime API CRUD.

## Endpoints

### GET /v1/hcm/email-settings

Mengambil profile settings email yang tersimpan di group `settings=email`.

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

Catatan:
- Secret tidak pernah dikembalikan utuh.
- `provider` saat ini memakai baseline `smtp` atau `mailtrap`.

### PUT /v1/hcm/email-settings

Menyimpan profile settings email aktif ke tabel settings group `email`.

Request body contoh:

```json
{
  "provider": "smtp",
  "fromAddress": "noreply@example.com",
  "fromName": "Arkav Mail",
  "smtp": {
    "host": "smtp.example.com",
    "port": 587,
    "encryption": "tls",
    "username": "smtp-user",
    "password": "smtp-secret-1234"
  },
  "mailtrap": {
    "accountId": 3229,
    "apiToken": "mailtrap-token-7890"
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
  },
  "meta": {
    "updatedBy": {
      "id": 101,
      "uuid": "1f9cfde4-8085-4c79-b4b7-4c63d4210268",
      "email": "owner@example.com"
    },
    "updatedAt": "2026-05-17T08:00:00+00:00"
  }
}
```

Catatan:
- Secret disimpan terenkripsi at rest dengan prefix `enc::`.
- Response tetap mengembalikan nilai masked, bukan plaintext.

### GET /v1/hcm/email-settings/mailtrap-status

Mengembalikan status konektivitas Mailtrap dasar dan metadata token yang visible dari akun Mailtrap.

Response 200 contoh:

```json
{
  "success": true,
  "data": {
    "provider": "mailtrap",
    "accountId": 3229,
    "credentialSource": "settings",
    "tokenConfigured": true,
    "tokenLast4": "7890",
    "connected": true,
    "visibleTokenCount": 2,
    "visibleTokens": [
      {
        "id": 1,
        "name": "Primary SMTP",
        "last4": "7890",
        "expiresAt": null
      }
    ],
    "error": null,
    "mode": "settings"
  }
}
```

Catatan:
- `credentialSource` dan `mode` menunjukkan apakah probe memakai credential dari `settings` atau fallback `env`.
- Jika credential tidak lengkap atau koneksi gagal, endpoint tetap mengembalikan envelope success dengan `connected=false` dan `data.error` terisi.

### POST /v1/hcm/email-settings/test-connection

Menguji koneksi SMTP atau Mailtrap tanpa otomatis menyimpan credential dari request ke profile settings.

Rate limit:
- `5 requests / minute` per caller via middleware `throttle:5,1`.

Request SMTP contoh:

```json
{
  "provider": "smtp",
  "timeout": 10,
  "smtp": {
    "host": "smtp.example.com",
    "port": 587,
    "encryption": "tls",
    "username": "smtp-user",
    "password": "smtp-secret-1234"
  }
}
```

Request Mailtrap contoh:

```json
{
  "provider": "mailtrap",
  "timeout": 10,
  "mailtrap": {
    "accountId": 3229,
    "apiToken": "mailtrap-token-7890"
  }
}
```

Response 200 contoh:

```json
{
  "success": true,
  "data": {
    "provider": "smtp",
    "mode": "ephemeral",
    "connected": false,
    "testedAt": "2026-05-17T08:05:00+00:00",
    "details": {
      "host": "smtp.example.com",
      "port": 587,
      "usernameMasked": "s*******r",
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
      "testedAt": "2026-05-17T08:05:00+00:00",
      "details": {
        "host": "smtp.example.com",
        "port": 587,
        "timeout": 10
      },
      "error": {
        "code": "TIMEOUT",
        "message": "SMTP connection timed out."
      },
      "updatedBy": {
        "id": 55,
        "uuid": "f3ca8b20-1551-43f9-a9dd-2f039a412366",
        "email": "tester@example.com"
      }
    }
  }
}
```

Catatan sanitasi:
- Probe SMTP tidak mengembalikan username mentah; response hanya memuat `details.usernameMasked`.
- Error Mailtrap dan SMTP dinormalisasi ke kode/pesan generik agar credential atau raw upstream message tidak bocor ke response.
- Snapshot `email_last_test_details` juga difilter ulang di service layer agar field seperti `password`, `apiToken`, `secret`, atau `username` mentah tidak ikut tersimpan.

Catatan update profile:
- Jika request `PUT /v1/hcm/email-settings` mengubah metadata profile tetapi menghilangkan field secret (`smtp.password`, `mailtrap.apiToken`), secret lama dipertahankan. Untuk mengosongkan secret, client harus mengirim field tersebut secara eksplisit dengan nilai kosong/null sesuai kontrak runtime yang dipakai.

Response 422:
- `VALIDATION_ERROR` jika payload provider tidak valid atau field wajib untuk probe kosong.

Response 429:
- request probe melebihi rate limit endpoint.

Normalized error code yang terkonfirmasi saat ini:
- `TIMEOUT`
- `DNS_ERROR`
- `CONNECTION_REFUSED`
- `TLS_ERROR`
- `AUTH_FAILED`
- `CONNECTION_FAILED`
- `CONFIGURATION_ERROR`

## Non-Goals Saat Ini

- Belum ada CRUD runtime untuk email template.
- Halaman `/email-settings` sekarang mengaktifkan submit save/test dari Blade aktif untuk SMTP dan Mailtrap baseline.
- Belum ada UI control-plane provider yang fully wired di surface web aktif.