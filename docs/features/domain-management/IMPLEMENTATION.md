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
- `company_id` (numeric internal `companies.id`)
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
- `domain_name` required, unique, host-only valid, otomatis di-trim dan di-lowercase
- `company_id` required UUID, exists `companies.uuid`
- `verification_type` required (`dns|file`)
- `notes` optional

Behavior:
- `verification_token` dibuat otomatis
- `status` default `pending`

### 4) Update Domain (Admin)

`PUT /v1/saas/domains/{domain}`

Validation:
- `domain_name` unique except current id, host-only valid, otomatis di-trim dan di-lowercase bila field dikirim
- `company_id` UUID exists bila field dikirim
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

## Identifier Dan Data Mapping

- Route `{domain}` aktif memakai `domains.uuid` via `AssignsUuid::getRouteKeyName()`.
- Response list/detail masih mengembalikan `companyId` numeric internal dan `companyName`.
- Frontend mengambil company list dari `GET /v1/company`, lalu memakai `company.uuid` untuk payload create/update dan `company.id` untuk filter list.
- Modal edit memetakan `domain.companyId` numeric ke `company.uuid` yang sudah dimuat frontend.

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
8. `validateDomainPayload()` menolak host invalid di client sebelum request dikirim.
9. `formatApiError()` mengangkat pesan validation error Laravel pertama ke toast UI.

## Known Gaps

- Verifikasi domain saat ini masih simulasi (mark verified) belum cek DNS/file real.
- API list/detail belum mengembalikan `companyUuid`; frontend masih perlu melakukan map ulang dari company list saat edit.
- Dokumen lama `docs/api/custom-domain-api.md` sebelumnya mengacu ke controller/model `custom_domains`; audit 2026-04-21 menyelaraskannya ke runtime aktif `domains`.

## Verification Checklist

Manual minimum:
- Open `/saas/domains` dan list tampil.
- Add domain baru sukses dan status awal pending.
- Add domain dengan uppercase/whitespace -> backend simpan lowercase-trimmed.
- Coba input `https://bad.example.com/path` -> FE blok, dan BE juga return `422` bila payload dipaksa lewat API.
- Klik verify pada domain pending -> status jadi verified.
- Buka verification details -> instruksi DNS/File tampil.
- Coba aksi mutasi dengan user non-admin -> `403 ADMIN_REQUIRED`.
