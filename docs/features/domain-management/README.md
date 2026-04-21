# Domain Management Module

## Ringkasan

Modul ini mengelola custom domain tenant SaaS, mulai dari registrasi domain, instruksi verifikasi DNS/file, sampai status verifikasi domain. Domain management menjadi bagian dari operasional tenant SaaS karena domain yang valid biasanya berkaitan dengan identitas company, lifecycle subscription, dan health billing platform.

## Akses

- Web admin: `/saas/domains` dan `/domain`.
- API admin-only: semua endpoint `/v1/saas/domains*`.
- Non-admin harus ditolak dengan `403 ADMIN_REQUIRED`.

## UI Aktif

- Entry points: `/saas/domains` dan `/domain`.
- Manager script aktif: `frontend/resources/js/domain-management.js`.
- Sumber kebenaran API aktif: `backend/app/Http/Controllers/Api/DomainController.php` dan `docs/api/custom-domain-api.md`.
- Detail teknis dan QA flow ada di [IMPLEMENTATION.md](IMPLEMENTATION.md) dan [E2E-TESTING.md](E2E-TESTING.md).
- Tracker status audit dan evidence terbaru ada di [STATUS-TRACKER.md](STATUS-TRACKER.md).

## Flow Bisnis End-to-End

1. Admin mendaftarkan custom domain tenant.
2. Form memilih company dari hasil `GET /v1/company`; UI menyimpan UUID company untuk create/update, sementara filter list tetap memakai numeric `company.id` yang dikembalikan endpoint company.
2. Sistem menghasilkan instruksi verifikasi DNS atau file sesuai metode yang dipilih.
3. Admin menjalankan verifikasi manual melalui UI atau endpoint verify.
4. Status domain diperbarui menjadi `verified`, `pending`, atau `failed`.

## Lifecycle Dan Keputusan Bisnis

- Verification type membedakan mekanisme DNS dan file verification.
- Hanya admin yang boleh mengubah domain karena perubahan domain berdampak ke akses tenant dan operasional platform.
- Input domain name aktif hanya menerima host/domain valid, tanpa `http://`, slash, path, atau whitespace di awal/akhir. Input akan dinormalisasi ke lowercase sebelum disimpan.
- SSL automation dan health monitor masih di luar scope fase ini.

## Integrasi

- Subscriptions: domain aktif biasanya dipantau bersama lifecycle tenant/subscription untuk operasional SaaS. Lihat `docs/features/subscriptions/README.md`.
- Trial & Billing Dashboard: dashboard operasional company sering membutuhkan konteks domain bersama status billing tenant. Lihat `docs/features/trial-billing-dashboard/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

- Path identifier domain aktif memakai **UUID route binding** (`domains.uuid`) karena model `Domain` memakai trait `AssignsUuid`.
- Request body create/update memakai `company_id` sebagai **UUID perusahaan**.
- Filter list `company_id` tetap memakai **numeric internal company id** dari `GET /v1/company`, karena runtime list domain saat ini memang memfilter kolom FK integer `domains.company_id`.
- Response detail/list mengembalikan `companyId` numeric internal dan `companyName`; frontend memetakan ulang ke `company.uuid` dari company list saat membuka modal edit.

## Documentation Structure

1. **[README.md](README.md)** (dokumen ini)
Ringkasan modul dan navigasi cepat.

2. **[IMPLEMENTATION.md](IMPLEMENTATION.md)**
Detail teknis backend + frontend: route, API contract, alur UI manager, validasi, dan catatan akses.

3. **[E2E-TESTING.md](E2E-TESTING.md)**
Checklist dan skenario end-to-end UI untuk Super User/HCM Admin.

---

## Existing Vs Target

- Existing: domain CRUD, manual verify, verification details, admin guard, filter company/status/search, dan validasi host-only domain sudah aktif.
- Target: SSL issuing/renew automation, domain health monitor periodik, dan flow purchase domain masih out of scope.

## Scope Singkat

Fitur utama modul saat ini:
- ✅ Domain list dengan pagination
- ✅ CRUD domain untuk admin
- ✅ Verifikasi domain manual (`verify now`)
- ✅ Endpoint instruksi verifikasi DNS/File
- ✅ Guard admin untuk seluruh endpoint domain
- ✅ Create/update form sudah sinkron dengan kontrak UUID company di backend
- ✅ Negative flow invalid domain format sudah ditangani di FE dan BE

Out of scope saat ini:
- ⏳ SSL issuing/renew automation
- ⏳ Domain health monitor periodik
- ⏳ Marketplace/domain purchase flow

---

## 🗂️ Data Model

### Domain (`domains`)
- `id`
- `domain_name` (unique)
- `company_id`
- `verification_type` (`dns|file`)
- `status` (`pending|verified|failed`)
- `verification_token`
- `verification_data` (json nullable)
- `verified_at`
- `notes`
- `created_at`, `updated_at`

---

## 🔌 Endpoint Summary

- `GET /v1/saas/domains`
- `GET /v1/saas/domains/{domain}`
- `POST /v1/saas/domains`
- `PUT /v1/saas/domains/{domain}`
- `DELETE /v1/saas/domains/{domain}`
- `POST /v1/saas/domains/{domain}/verify`
- `GET /v1/saas/domains/{domain}/verification-details`

Semua endpoint di atas admin-only (return `403 ADMIN_REQUIRED` jika non-admin).

---

## 🧭 UI Entry Points

- `GET /saas/domains`
- `GET /domain`

Halaman memakai manager script `frontend/resources/js/domain-management.js`.

---

## Status

Module version: `1.0`
Status: `Production-ready baseline audited`
Last updated: `2026-04-21`

Lanjutkan ke [IMPLEMENTATION.md](IMPLEMENTATION.md) untuk detail teknis, [E2E-TESTING.md](E2E-TESTING.md) untuk QA flow, dan [STATUS-TRACKER.md](STATUS-TRACKER.md) untuk snapshot audit terbaru.
