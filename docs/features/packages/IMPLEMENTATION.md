# Packages Module - Implementation Guide

## Ringkasan Teknis

Packages module menangani manajemen tier subscription SaaS dan assignment fitur per tier. Implementasi saat ini fokus pada alur admin-driven CRUD package dengan UI Bootstrap + JavaScript manager dan API Laravel.

## Arsitektur

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

## Kontrak API

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

Kontrak aktif memakai `package` UUID. Mengembalikan detail package + full list feature + agregat subscriber (`activeSubscriptionsCount`, `totalSubscriptionsCount`).

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

Catatan UI aktif: modal packages hanya mengedit satu harga berbasis `Billing Cycle` pada satu waktu. Jika admin hanya mengubah metadata package tanpa menyentuh input harga/cycle, manager FE sekarang mempertahankan `monthly_price` dan `yearly_price` existing agar tidak terjadi rewrite diam-diam.

### 5. Delete Package (Admin)

`DELETE /v1/saas/packages/{package}`

Jika package masih direferensikan oleh row subscription apa pun, controller mengembalikan `422 PACKAGE_IN_USE` agar histori billing dan relasi dashboard tidak rusak. Ini mencegah 500 dari FK restrict database bocor ke UI.

### 6. Package Features

- `GET /v1/saas/packages/{package}/features`
- `POST /v1/saas/packages/{package}/features` (admin)
- `PUT /v1/saas/packages/features/{feature}` (admin; `{feature}` numeric aktif, UUID fallback didukung)
- `DELETE /v1/saas/packages/features/{feature}` (admin; `{feature}` numeric aktif, UUID fallback didukung)

### 7. Package Add-ons

- `GET /v1/saas/package-addons`
- `GET /v1/saas/package-addons/{addon}`
- `POST /v1/saas/package-addons` (admin)
- `PUT /v1/saas/package-addons/{addon}` (admin; `{addon}` numeric aktif, UUID fallback didukung)
- `DELETE /v1/saas/package-addons/{addon}` (admin; `{addon}` numeric aktif, UUID fallback didukung)

Add-on fields:
- `code`
- `name`
- `description`
- `price_per_unit`
- `unit_name`
- `status`

## Flow Frontend

File: `frontend/resources/js/packages-management.js`

Alur utama:
1. `init()` bind event listeners, render feature chips default, dan load data awal.
2. `loadPackages()` kirim request list dengan query `page`, `per_page`, `status`, `search`.
3. `renderPackages()` tampilkan table, badge status, actions, dan pagination.
4. Submit modal -> `handleSavePackage()`:
   - normalize payload (`code`, monthly/yearly price berdasarkan billing cycle input)
   - saat edit dan admin tidak menyentuh field harga/cycle, payload mempertahankan pasangan monthly/yearly existing agar harga counterpart tidak berubah diam-diam
   - call create/update endpoint
   - sinkronisasi feature via `syncPackageFeatures()`
5. Delete action wajib pakai `window.ArcavUi.confirmDelete`.
6. Add-on section memakai pola yang sama melalui `loadAddons()`, `renderAddons()`, `handleSaveAddon()`, `editAddon()`, dan `deleteAddon()`.
7. Paket **`status=active`**: tombol **Subscribe** mengarah ke `/subscription?packageId={id}&status=pending_payment` — form langganan terbuka dengan paket + status *pending payment* (alur bayar dulu; lihat `docs/features/subscriptions/IMPLEMENTATION.md` §2b).

Catatan implementasi penting:
- Search input sudah debounced 250ms untuk kurangi request noise.
- Reset filter akan reset `status`, `search`, dan page ke 1.
- Guard `isInitialized` mencegah double-binding listeners jika `init()` terpanggil lebih dari sekali.

## Security Dan Akses

- Semua endpoint `/v1/saas/*` saat ini berada di middleware `api.token`.
- Operasi mutasi package/feature dilindungi check `isHcmAdmin()` di controller.
- Operasi mutasi add-on dilindungi check `isHcmAdmin()` di controller.
- List/detail package dapat diakses user bertoken non-admin (sesuai implementasi saat ini).
- List/detail add-on juga dapat diakses user bertoken non-admin, sedangkan mutasi tetap admin-only.
- Server-side feature gating memperlakukan `PackageFeature.limit = 0` sebagai **disabled** (setara feature tidak tersedia) saat evaluasi akses modul.
- FE toast/error packages merender pesan backend sebagai text node, bukan `innerHTML`, agar pesan error yang mengandung HTML tidak menjadi XSS UI.

## Integrasi Yang Perlu Diperhatikan

- Landing/onboarding membaca package aktif dengan identifier UUID package.
- Subscription management, billing dashboard, dan revenue dashboard mengandalkan `package_uuid` + `plan_code` untuk relasi historis.
- Delete package tidak boleh memutus histori subscription karena data itu masih dipakai oleh billing dan reporting.

## Gap Yang Masih Tersisa

- Modal packages masih memakai satu input harga, sehingga admin hanya mengubah satu dimensi harga per save.
- Pricing simulation calculator belum ada dan masih out of scope.
- Jika policy berubah agar list/detail juga admin-only, update di controller atau middleware route-level.

## Validasi Dan Checklist

Manual checks minimum:
- Open `/saas/packages`, list muncul tanpa duplicate fetch loop.
- Ubah status filter -> data refresh sesuai status.
- Ketik search keyword -> API query menyertakan `search`.
- Reset filter -> status `all`, search kosong, page kembali 1.
- Create/edit/delete package sebagai admin -> sukses.
- Coba hapus package yang masih punya subscription history -> dapat `422 PACKAGE_IN_USE`, data tetap utuh.
- Coba mutasi sebagai non-admin -> dapat `403 ADMIN_REQUIRED`.
- Buka section add-ons dan cek list tampil.
- Create/edit/delete add-on sebagai admin -> sukses.
- Coba mutasi add-on sebagai non-admin -> dapat `403 ADMIN_REQUIRED`.
- Untuk modul yang pakai feature gate (contoh Asset): set `asset_management` limit `0` pada package aktif company, lalu pastikan endpoint modul mengembalikan `403 FEATURE_DISABLED`.

Automated evidence terbaru:
- `php artisan test tests/Feature/PackageServiceTest.php`
- `npm run test:ui -- --run tests/ui/packages-management.wiring.test.js`
