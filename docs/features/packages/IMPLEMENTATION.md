# Packages Module - Implementation Guide

## Overview

Packages module menangani manajemen tier subscription SaaS dan assignment fitur per tier. Implementasi saat ini fokus pada alur admin-driven CRUD package dengan UI Bootstrap + JavaScript manager dan API Laravel.

## Architecture

Backend:
- Controller: `backend/app/Http/Controllers/Api/PackageController.php`
- Models: `Package`, `PackageFeature`, `PackageAddon`
- API routes: `backend/routes/api.php` (prefix `/v1/saas`)

Frontend:
- View: `backend/resources/views/saas/packages.blade.php`
- Manager: `frontend/resources/js/packages-management.js`

Web routes:
- `GET /saas/packages`
- `GET /packages`

## API Contract

### 1. List Packages

`GET /v1/saas/packages`

Query params:
- `page` (default: 1)
- `per_page` (default: 15, capped max 100)
- `status` (`active|inactive|archived|all`, default logical: active)
- `search` (optional, filter by `name|code|description`)

Response highlights:
- `data[]` berisi package + nested features
- `data[].activeSubscriptionsCount` total subscriber aktif (`active|trial`) per package
- `data[].totalSubscriptionsCount` total seluruh histori subscriber per package
- `pagination` berisi `total`, `per_page`, `current_page`, `last_page`

### 2. Package Detail

`GET /v1/saas/packages/{package}`

Mengembalikan detail package + full list feature + agregat subscriber (`activeSubscriptionsCount`, `totalSubscriptionsCount`).

### 3. Create Package (Admin)

`POST /v1/saas/packages`

Validation:
- `code` required, unique
- `name` required
- `monthly_price` numeric >= 0
- `yearly_price` numeric >= 0
- `billing_unit` in `user|company|flat`
- `status` optional in `active|inactive|archived`

Jika user bukan HCM admin -> `403 ADMIN_REQUIRED`.

### 4. Update Package (Admin)

`PUT /v1/saas/packages/{package}`

Partial update diizinkan (`sometimes`).

### 5. Delete Package (Admin)

`DELETE /v1/saas/packages/{package}`

### 6. Package Features

- `GET /v1/saas/packages/{package}/features`
- `POST /v1/saas/packages/{package}/features` (admin)
- `PUT /v1/saas/packages/features/{feature}` (admin)
- `DELETE /v1/saas/packages/features/{feature}` (admin)

### 7. Package Add-ons

- `GET /v1/saas/package-addons`
- `GET /v1/saas/package-addons/{addon}`
- `POST /v1/saas/package-addons` (admin)
- `PUT /v1/saas/package-addons/{addon}` (admin)
- `DELETE /v1/saas/package-addons/{addon}` (admin)

Add-on fields:
- `code`
- `name`
- `description`
- `price_per_unit`
- `unit_name`
- `status`

## Frontend Flow

File: `frontend/resources/js/packages-management.js`

Main flow:
1. `init()` bind event listeners, render feature chips default, dan load data awal.
2. `loadPackages()` kirim request list dengan query `page`, `per_page`, `status`, `search`.
3. `renderPackages()` tampilkan table, badge status, actions, dan pagination.
4. Submit modal -> `handleSavePackage()`:
   - normalize payload (`code`, monthly/yearly price berdasarkan billing cycle input)
   - call create/update endpoint
   - sinkronisasi feature via `syncPackageFeatures()`
5. Delete action wajib pakai `window.ArcavUi.confirmDelete`.
6. Add-on section memakai pola yang sama melalui `loadAddons()`, `renderAddons()`, `handleSaveAddon()`, `editAddon()`, dan `deleteAddon()`.

Notable implementation details:
- Search input sudah debounced 250ms untuk kurangi request noise.
- Reset filter akan reset `status`, `search`, dan page ke 1.
- Guard `isInitialized` mencegah double-binding listeners jika `init()` terpanggil lebih dari sekali.

## Security and Access Notes

- Semua endpoint `/v1/saas/*` saat ini berada di middleware `api.token`.
- Operasi mutasi package/feature dilindungi check `isHcmAdmin()` di controller.
- Operasi mutasi add-on dilindungi check `isHcmAdmin()` di controller.
- List/detail package dapat diakses user bertoken non-admin (sesuai implementasi saat ini).
- List/detail add-on juga dapat diakses user bertoken non-admin, sedangkan mutasi tetap admin-only.
- Server-side feature gating memperlakukan `PackageFeature.limit = 0` sebagai **disabled** (setara feature tidak tersedia) saat evaluasi akses modul.

## Known Gaps / Follow-up

- Halaman packages belum expose manajemen add-ons walau model/add-on concept ada di dokumen awal.
- Coverage automated test (Feature/Unit) khusus package controller belum ditambah di task ini.
- Jika policy berubah agar list/detail juga admin-only, update di controller atau middleware route-level.

## Verification Checklist

Manual checks minimum:
- Open `/saas/packages`, list muncul tanpa duplicate fetch loop.
- Ubah status filter -> data refresh sesuai status.
- Ketik search keyword -> API query menyertakan `search`.
- Reset filter -> status `all`, search kosong, page kembali 1.
- Create/edit/delete package sebagai admin -> sukses.
- Coba mutasi sebagai non-admin -> dapat `403 ADMIN_REQUIRED`.
- Buka section add-ons dan cek list tampil.
- Create/edit/delete add-on sebagai admin -> sukses.
- Coba mutasi add-on sebagai non-admin -> dapat `403 ADMIN_REQUIRED`.
- Untuk modul yang pakai feature gate (contoh Asset): set `asset_management` limit `0` pada package aktif company, lalu pastikan endpoint modul mengembalikan `403 FEATURE_DISABLED`.
