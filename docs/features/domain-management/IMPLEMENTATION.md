# Domain Management Module - Implementation Guide

## Overview

Domain Management dipakai untuk mengelola custom domain tenant SaaS dari sisi admin: registrasi domain, update domain, verifikasi manual, serta retrieval instruksi verifikasi.

## Architecture

Backend:
- Controller: `backend/app/Http/Controllers/Api/DomainController.php`
- Model: `Domain`
- API route group: `backend/routes/api.php` (`/v1/saas/domains*`)

Frontend:
- View: `backend/resources/views/saas/domains.blade.php`
- Manager: `frontend/resources/js/domain-management.js`

Web routes:
- `/saas/domains`
- `/domain`

## API Contract

### 1) List Domains

`GET /v1/saas/domains`

Filters:
- `status`
- `company_id`
- `search` (domain_name)

Response:
- `success`
- `data[]`
- `pagination`

### 2) Domain Detail

`GET /v1/saas/domains/{domain}`

Return payload domain + company info.

### 3) Create Domain (Admin)

`POST /v1/saas/domains`

Validation:
- `domain_name` required, unique
- `company_id` required, exists companies
- `verification_type` required (`dns|file`)
- `notes` optional

Behavior:
- `verification_token` dibuat otomatis
- `status` default `pending`

### 4) Update Domain (Admin)

`PUT /v1/saas/domains/{domain}`

Validation:
- `domain_name` unique except current id
- `company_id` exists
- `verification_type` in `dns|file`
- `status` in `pending|verified|failed`

### 5) Delete Domain (Admin)

`DELETE /v1/saas/domains/{domain}`

Response success dengan message.

### 6) Verify Domain (Admin)

`POST /v1/saas/domains/{domain}/verify`

Current behavior:
- Jika status `pending`, backend set `status=verified` dan `verified_at=now()`.
- Jika status bukan pending, endpoint tetap return success dengan status existing.

### 7) Verification Details (Admin)

`GET /v1/saas/domains/{domain}/verification-details`

Return:
- `domainName`
- `verificationType`
- `instructions` (step-by-step)
- `token`

## Access Control

Semua endpoint domain memakai guard admin di level controller:
- `isHcmAdmin()` memeriksa `request()->user()`
- Non-admin akan mendapat `403` dengan error code `ADMIN_REQUIRED`

## Frontend Flow

File: `frontend/resources/js/domain-management.js`

Alur utama:
1. `init()` bind form/events dan load data awal.
2. `loadDomains()` memanggil list API dengan pagination.
3. `renderDomains()` membentuk tabel + action button.
4. `handleAddDomain()` kirim create payload.
5. `editDomain()` dan `handleEditDomain()` proses update.
6. `verifyDomain()` trigger endpoint verify.
7. `showVerificationDetails()` menampilkan instruksi verifikasi.

## Known Gaps

- Mapping field di frontend belum konsisten dengan response backend untuk beberapa key (contoh `domain` vs `domainName`, `verificationMethod` vs `verificationType`) dan perlu harmonisasi pada task lanjutan.
- Filter UI (`search/status/company`) sudah ada di blade, namun sebagian belum terhubung penuh di manager script.
- Verifikasi domain saat ini masih simulasi (mark verified) belum cek DNS/file real.

## Verification Checklist

Manual minimum:
- Open `/saas/domains` dan list tampil.
- Add domain baru sukses dan status awal pending.
- Klik verify pada domain pending -> status jadi verified.
- Buka verification details -> instruksi DNS/File tampil.
- Coba aksi mutasi dengan user non-admin -> `403 ADMIN_REQUIRED`.
