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
- Detail teknis dan QA flow ada di [IMPLEMENTATION.md](IMPLEMENTATION.md) dan [E2E-TESTING.md](E2E-TESTING.md).

## Flow Bisnis End-to-End

1. Admin mendaftarkan custom domain tenant.
2. Sistem menghasilkan instruksi verifikasi DNS atau file sesuai metode yang dipilih.
3. Admin menjalankan verifikasi manual melalui UI atau endpoint verify.
4. Status domain diperbarui menjadi `verified`, `pending`, atau `failed`.

## Lifecycle Dan Keputusan Bisnis

- Verification type membedakan mekanisme DNS dan file verification.
- Hanya admin yang boleh mengubah domain karena perubahan domain berdampak ke akses tenant dan operasional platform.
- SSL automation dan health monitor masih di luar scope fase ini.

## Integrasi

- Subscriptions: domain aktif biasanya dipantau bersama lifecycle tenant/subscription untuk operasional SaaS. Lihat `docs/features/subscriptions/README.md`.
- Trial & Billing Dashboard: dashboard operasional company sering membutuhkan konteks domain bersama status billing tenant. Lihat `docs/features/trial-billing-dashboard/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Documentation Structure

1. **[README.md](README.md)** (dokumen ini)
Ringkasan modul dan navigasi cepat.

2. **[IMPLEMENTATION.md](IMPLEMENTATION.md)**
Detail teknis backend + frontend: route, API contract, alur UI manager, validasi, dan catatan akses.

3. **[E2E-TESTING.md](E2E-TESTING.md)**
Checklist dan skenario end-to-end UI untuk Super User/HCM Admin.

---

## Existing Vs Target

- Existing: domain CRUD, manual verify, verification details, dan admin guard sudah aktif.
- Target: SSL issuing/renew automation, domain health monitor periodik, dan flow purchase domain masih out of scope.

## Scope Singkat

Fitur utama modul saat ini:
- ✅ Domain list dengan pagination
- ✅ CRUD domain untuk admin
- ✅ Verifikasi domain manual (`verify now`)
- ✅ Endpoint instruksi verifikasi DNS/File
- ✅ Guard admin untuk seluruh endpoint domain

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
Status: `Production-ready baseline`
Last updated: `2026-04-13`

Lanjutkan ke [IMPLEMENTATION.md](IMPLEMENTATION.md) untuk detail teknis dan [E2E-TESTING.md](E2E-TESTING.md) untuk QA flow.
