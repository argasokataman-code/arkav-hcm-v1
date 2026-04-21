# Custom Domains API

Status: Runtime-aligned with active controller on 2026-04-21

## Sumber kebenaran

- Route aktif: `backend/routes/api.php`
- Controller aktif: `backend/app/Http/Controllers/Api/DomainController.php`
- Model aktif: `backend/app/Models/Domain.php`
- Frontend aktif: `frontend/resources/js/domain-management.js`

Dokumen ini menggambarkan **runtime aktif** `domains` table. Dokumen lama yang menggambarkan `custom_domains` + verification logs tidak lagi mewakili route yang sedang dipakai UI `/saas/domains`.

## Ringkasan kontrak aktif

- Base path: `/v1/saas/domains`
- Auth: bearer token (`/api-token` dipakai page web untuk mengambil token)
- Access: global HCM admin only; selain admin akan menerima `403 ADMIN_REQUIRED`
- Path `{domain}`: **UUID route binding** (`domains.uuid`)
- Create / update `company_id`: **UUID perusahaan**
- List filter `company_id`: **numeric internal `companies.id`**

## Data shape aktif

### Domain list/detail payload

```json
{
  "id": 12,
  "domainName": "portal.example.com",
  "companyId": 3,
  "companyName": "Acme Corp",
  "verificationType": "dns",
  "status": "pending",
  "verificationToken": "5f4dcc3b5aa765d61d8327deb882cf99",
  "verificationData": null,
  "verifiedAt": null,
  "notes": "Production portal",
  "createdAt": "2026-04-21T10:00:00Z",
  "updatedAt": "2026-04-21T10:00:00Z"
}
```

## Endpoint aktif

### GET `/domains`

List domain admin dengan pagination.

Query:
- `page` integer, default runtime `1`
- `status` enum `pending|verified|failed`
- `company_id` integer internal `companies.id`
- `search` string, partial match ke `domain_name`

Response `200`:
- `success`
- `data[]`
- `pagination.total`
- `pagination.per_page`
- `pagination.current_page`
- `pagination.last_page`

### GET `/domains/{domain}`

Lookup detail satu domain by UUID.

### POST `/domains`

Create domain baru.

Request body:
- `domain_name` string, required, unique, host-only valid, akan di-trim dan di-lowercase
- `company_id` string UUID perusahaan, required
- `verification_type` enum `dns|file`, required
- `notes` nullable string

Behavior:
- server generate `verification_token`
- status default `pending`
- `company_id` UUID di-resolve ke FK integer internal sebelum insert

Contoh request:

```json
{
  "domain_name": "portal.example.com",
  "company_id": "550e8400-e29b-41d4-a716-446655440000",
  "verification_type": "dns",
  "notes": "Production portal"
}
```

### PUT `/domains/{domain}`

Update domain existing.

Field optional:
- `domain_name` string valid host-only, unique except current row
- `company_id` string UUID perusahaan
- `verification_type` enum `dns|file`
- `notes` nullable string
- `status` enum `pending|verified|failed`

### DELETE `/domains/{domain}`

Hard delete domain row. Response `200` dengan `message`.

### POST `/domains/{domain}/verify`

Verify manual.

Runtime aktif:
- bila status saat ini `pending`, server set `status=verified` dan `verified_at=now()`
- bila status bukan `pending`, server return success dengan status existing apa adanya

Response `200`:

```json
{
  "success": true,
  "data": {
    "id": 12,
    "domainName": "portal.example.com",
    "status": "verified",
    "verifiedAt": "2026-04-21T10:30:00Z"
  }
}
```

### GET `/domains/{domain}/verification-details`

Return instruksi verifikasi berdasarkan `verification_type`.

Response `200`:
- `domainName`
- `verificationType`
- `instructions.step1..step4`
- `token`

## Negative flow penting

- `403 ADMIN_REQUIRED` untuk semua caller non-admin
- `422` bila `company_id` bukan UUID valid perusahaan
- `422` bila `domain_name` bukan host/domain valid, mengandung protocol/path, atau duplicate
- UI aktif juga melakukan validasi host-only sebelum request dikirim, tetapi backend tetap sumber kebenaran utama

## Catatan integrasi

- Halaman domain memuat pilihan company dari `GET /v1/company`.
- `GET /v1/company` mengembalikan `id` numeric dan `uuid`; frontend menggunakan:
  - `uuid` untuk payload create/update domain
  - `id` numeric untuk filter list domain
- Response domain list/detail belum mengembalikan `companyUuid`, jadi frontend melakukan map dari company list saat membuka modal edit.
