# 🌐 Domain Management Module

Mengelola custom domain tenant untuk SaaS (registrasi domain, verifikasi DNS/file, dan status verifikasi domain).

---

## 📚 Documentation Structure

1. **[README.md](README.md)** (dokumen ini)
Ringkasan modul dan navigasi cepat.

2. **[IMPLEMENTATION.md](IMPLEMENTATION.md)**
Detail teknis backend + frontend: route, API contract, alur UI manager, validasi, dan catatan akses.

3. **[E2E-TESTING.md](E2E-TESTING.md)**
Checklist dan skenario end-to-end UI untuk Super User/HCM Admin.

---

## 🎯 Scope Singkat

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

## ✅ Status

Module version: `1.0`
Status: `Production-ready baseline`
Last updated: `2026-04-13`

Lanjutkan ke [IMPLEMENTATION.md](IMPLEMENTATION.md) untuk detail teknis dan [E2E-TESTING.md](E2E-TESTING.md) untuk QA flow.
