# Packages Module

## Ringkasan

Modul Packages mengelola katalog paket SaaS, pricing, status paket, dan assignment feature per package dari sisi Super User/HCM Admin. Modul ini menjadi sumber kebenaran plan yang dipilih di landing/trial onboarding dan paket yang nanti dipakai subscription tenant.

## Akses

- Web admin: `/packages` dan `/saas/packages`.
- API admin: seluruh endpoint `/v1/saas/packages*` dan `/v1/saas/package-addons*`.
- User non-admin tidak boleh membuat, mengubah, atau menghapus package maupun add-on.

## UI Aktif

- Entry points: `/saas/packages` dan `/packages`.
- Manager script aktif: `frontend/resources/js/packages-management.js`.
- Dokumen detail teknis dan E2E ada di [IMPLEMENTATION.md](IMPLEMENTATION.md) dan [E2E-TESTING.md](E2E-TESTING.md).
- Tracker status audit dan evidence terbaru ada di [STATUS-TRACKER.md](STATUS-TRACKER.md).

## Flow Bisnis End-to-End

1. Super admin membuat atau mengubah package beserta pricing dan statusnya.
2. Super admin menyusun feature assignment per package dan add-on yang relevan.
3. Package aktif muncul sebagai pilihan di landing/trial onboarding, kecuali package internal yang ditandai `is_global_admin_only=true`.
4. Saat tenant membuat subscription atau onboarding baru, sistem mereferensikan package yang dipilih untuk menentukan plan code, limit, dan feature gate.

## Lifecycle Dan Keputusan Bisnis

- Active vs inactive: hanya package aktif yang boleh dipakai onboarding/subscription baru.
- Global-admin-only package: package internal (`is_global_admin_only=true`) hanya boleh terlihat untuk login global admin dan disembunyikan dari katalog tenant/public.
- Feature assignment: disusun di level package agar gating fitur tenant tidak disebar ke banyak tempat.
- Add-on catalog: dipisah dari package inti agar billing tambahan bisa diatur tanpa mengubah paket dasar.
- Delete guard: package yang masih direferensikan riwayat subscription tidak boleh dihapus agar histori billing, dashboard, dan onboarding audit trail tidak rusak.

## Integrasi

- Landing Pages: CTA pricing dan onboarding company membaca package aktif sebagai pilihan plan. Lihat `docs/features/landing-pages/README.md`.
- Subscriptions: package menjadi dasar `package_uuid`, `plan_code`, dan feature gate subscription tenant. Lihat `docs/features/subscriptions/README.md`.
- Trial & Billing Dashboard: dashboard billing perlu mengetahui package yang dipakai company untuk menampilkan health billing dan lifecycle trial. Lihat `docs/features/trial-billing-dashboard/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

- Base path: `/v1/saas/packages` dan `/v1/saas/package-addons`.
- Package detail memakai path UUID: `/v1/saas/packages/{package}`.
- Package feature mutation memakai numeric feature id aktif dengan UUID fallback: `/v1/saas/packages/features/{feature}`.
- Package add-on detail/mutasi memakai numeric add-on id aktif dengan UUID fallback: `/v1/saas/package-addons/{addon}`.
- Delete package yang masih direferensikan subscription wajib ditolak dengan `422 PACKAGE_IN_USE`.
- Detail kontrak teknis dan contoh payload ada di [IMPLEMENTATION.md](IMPLEMENTATION.md) dan `docs/api/packages-api.md`.

## Existing Vs Target

- Existing: CRUD package, feature assignment, detail viewer, dan add-on catalog sudah aktif.
- Existing: relasi utama package feature dibaca lewat `package_uuid`, dengan `package_id` masih dipertahankan untuk kompatibilitas legacy.
- Existing: path package runtime memakai `package` UUID, sedangkan route feature mutation memakai numeric feature id aktif dengan fallback UUID bila ada caller lama.
- Existing: path add-on menerima numeric id aktif dengan fallback UUID legacy/transisi.
- Existing: package internal `unlimited` ditandai `is_global_admin_only=true` agar tidak bocor ke katalog publik.
- Target: pricing simulation calculator dan workflow pricing yang lebih kompleks masih out of scope.

## Ringkasan Fungsi

Fitur utama modul Packages saat ini:
- Package list dengan pagination, status filter, dan search.
- CRUD package untuk admin.
- Feature assignment per package via modal composer bertingkat.
- Detail features viewer per package.
- Package add-on catalog CRUD via halaman Packages.
- Validasi server-side dan normalisasi payload UI.

Out of scope saat ini:
- Pricing simulation calculator.

## Data Model

### Package (`packages`)
- `id`
- `code` (unique)
- `name`
- `description`
- `monthly_price`
- `yearly_price`
- `billing_unit` (`user|company|flat`)
- `status` (`active|inactive|archived`)
- `is_global_admin_only` (boolean, default `false`)
- `color`
- `sort_order`
- `created_at`, `updated_at`

### PackageFeature (`package_features`)
- `id`
- `package_uuid`
- `package_id` (legacy compatibility, nullable in transitional environments)
- `feature_code`
- `feature_name`
- `limit`
- `created_at`, `updated_at`

Catatan: relasi utama package feature harus dibaca lewat `package_uuid`. `package_id` hanya kolom legacy/transisi untuk menjaga data lama tetap terbaca selama cutover.

### PackageAddon (`package_addons`)
- `id`
- `code` (unique)
- `name`
- `description`
- `price_per_unit`
- `unit_name`
- `status` (`active|inactive`)
- `created_at`, `updated_at`

## Endpoint Ringkas

Packages:
- `GET /v1/saas/packages`
- `GET /v1/saas/packages/{package}` (`{package}` = UUID)
- `POST /v1/saas/packages`
- `PUT /v1/saas/packages/{package}` (`{package}` = UUID)
- `DELETE /v1/saas/packages/{package}` (`{package}` = UUID, blocked with `PACKAGE_IN_USE` when subscription history exists)

Package features:
- `GET /v1/saas/packages/{package}/features`
- `POST /v1/saas/packages/{package}/features`
- `PUT /v1/saas/packages/features/{feature}` (`{feature}` = numeric id aktif, UUID fallback didukung runtime)
- `DELETE /v1/saas/packages/features/{feature}` (`{feature}` = numeric id aktif, UUID fallback didukung runtime)

Package add-ons:
- `GET /v1/saas/package-addons`
- `GET /v1/saas/package-addons/{addon}` (`{addon}` = numeric id aktif, UUID fallback didukung runtime)
- `POST /v1/saas/package-addons`
- `PUT /v1/saas/package-addons/{addon}` (`{addon}` = numeric id aktif, UUID fallback didukung runtime)
- `DELETE /v1/saas/package-addons/{addon}` (`{addon}` = numeric id aktif, UUID fallback didukung runtime)

## UI Entry Points

- `GET /saas/packages`
- `GET /packages`

Halaman menggunakan manager script `frontend/resources/js/packages-management.js`.

## Status

- Module version: `1.1`
- Status: `Production-ready baseline`
- Last updated: `2026-04-20`

Lihat [STATUS-TRACKER.md](STATUS-TRACKER.md) untuk snapshot audit terbaru, evidence test, dan gap yang masih tersisa.

Lanjutkan ke [IMPLEMENTATION.md](IMPLEMENTATION.md) untuk detail teknis dan [E2E-TESTING.md](E2E-TESTING.md) untuk QA flow.
