# HCM Data Privacy API

Dokumen ini merangkum endpoint data privacy untuk consent, right-to-erasure, dan security incidents.

## Base Path

- `/v1/hcm/data-privacy/*` untuk endpoint karyawan/tenant data privacy.
- `/v1/admin/security-incidents/*` untuk lifecycle insiden keamanan (admin).

## Authentication

Semua endpoint di bawah membutuhkan:

- Header `Authorization: Bearer <token>`
- Header `X-Company-Id: <company_id>` untuk konteks tenant

## Envelope Response

Semua endpoint mengikuti bentuk:

- Success: `{ success: true, data: ... }`
- Error: `{ success: false, error: { code, message } }`

## Employee Consent Endpoints

### POST `/v1/hcm/data-privacy/me/biometric-consent`

Menyimpan persetujuan biometrik + GPS untuk absensi.

Request body:

- `selfie_consent` (boolean, required)
- `gps_consent` (boolean, required)

### DELETE `/v1/hcm/data-privacy/me/biometric-consent`

Mencabut consent biometrik/GPS user aktif.

### POST `/v1/hcm/data-privacy/me/ai-consent`

Menyimpan consent AI chat untuk user aktif.

### DELETE `/v1/hcm/data-privacy/me/ai-consent`

Mencabut consent AI chat user aktif.

### GET `/v1/hcm/data-privacy/me/ai-consent-status`

Mengambil status consent AI chat user aktif.

### POST `/v1/hcm/data-privacy/me/withdraw-consent`

Endpoint terpadu pencabutan consent (Cycle 6 - M4a).

Request body:

- `scope` (string, required): `ai_chat`, `biometric`, atau `all`

Response data:

- `scope`
- `withdrawn.ai_chat` (boolean)
- `withdrawn.biometric` (boolean)
- `withdrawnAt` (datetime)

## Erasure Endpoints

### POST `/v1/hcm/data-privacy/me/erasure-requests`

Ajukan right-to-erasure (subjek data sendiri).

### GET `/v1/hcm/data-privacy/me/erasure-requests`

List request erasure milik user aktif.

### GET `/v1/hcm/data-privacy/erasure-requests`

List semua request erasure tenant (admin).

### POST `/v1/hcm/data-privacy/erasure-requests/{uuid}/process`

Proses request erasure (approve/reject) oleh admin.

## Security Incident Endpoints (Cycle 5 - H2)

### GET `/v1/admin/security-incidents`

List insiden keamanan per tenant (admin only).

### POST `/v1/admin/security-incidents`

Membuat insiden keamanan data.

Field utama:

- `title` (string, required)
- `description` (string, required)
- `affected_data_types` (array<string>, optional)
- `affected_subjects_count` (integer, optional)
- `affected_user_uuids` (array<string>, optional)
- `detected_at` (datetime, required)
- `reported_to_bssn_at` (datetime, optional)

### GET `/v1/admin/security-incidents/{uuid}`

Detail insiden keamanan tertentu.

### POST `/v1/admin/security-incidents/{uuid}/notify-subjects`

Queue job pengiriman notifikasi breach ke subjek terdampak.

### POST `/v1/admin/security-incidents/{uuid}/resolve`

Menandai incident menjadi `resolved`.

## Photo Consent Endpoints (Cycle 6 — M6)

### POST `/v1/hcm/data-privacy/me/photo-consent`

Memberikan persetujuan untuk penyimpanan foto profil sebagai data biometrik (Pasal 4 ayat 2 UU PDP).

Response:
- `photoConsent` (boolean)
- `consentGivenAt` (datetime)

### DELETE `/v1/hcm/data-privacy/me/photo-consent`

Mencabut persetujuan foto profil.

Response:
- `withdrawn` (boolean)

## Cookie Consent Endpoints (Cycle 6 — H7)

### POST `/v1/hcm/data-privacy/me/cookie-consent`

Menyimpan preferensi cookie user.

Request body:
- `essential` (boolean, optional) — default true, forced
- `analytics` (boolean, optional)
- `marketing` (boolean, optional)

Response:
- `essential` (boolean)
- `analytics` (boolean)
- `marketing` (boolean)
- `consentedAt` (datetime)

### GET `/v1/hcm/data-privacy/me/cookie-consent`

Mengambil preferensi cookie user.

Response:
- `data` (null jika belum ada preferensi)

## Session Re-verification (Cycle 6 — M8)

### POST `/v1/hcm/data-privacy/me/session-check`

Re-authentication untuk operasi sensitif (Pasal 35 UU PDP). Membutuhkan password.

Request body:
- `password` (string, required)

Response success:
- `verified` (true)
- `verifiedAt` (datetime)

Error:
- 401 jika tidak terautentikasi
- 422 jika password salah (`INVALID_CREDENTIALS`)

## Data Saya Portal (Cycle 6 — L2)

### GET `/v1/hcm/data-privacy/me/my-data`

Menampilkan seluruh data pribadi user (Pasal 8 — Hak Akses).

Response data sections:
- `identity` — nama, email, uuid, created_at
- `profile` — NIK, phone, address, bank, salary (nullable jika belum ada profil)
- `consent` — biometric consent status, AI chat consent status

### GET `/v1/hcm/data-privacy/me/my-data/export`

Export data pribadi user dalam format JSON (Pasal 13 — Hak Portabilitas).

Response:
- `format` (string) — `"json"`
- `exportedAt` (datetime)
- `payload` — seluruh data dari my-data endpoint
