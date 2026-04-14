# 📦 Packages Module

Mengelola paket subscription SaaS (pricing, status, dan feature assignment) dari sisi Super User/HCM Admin.

---

## 📚 Documentation Structure

1. **[README.md](README.md)** (dokumen ini)
Ringkasan modul dan navigasi cepat.

2. **[IMPLEMENTATION.md](IMPLEMENTATION.md)**
Detail teknis backend + frontend: route, API contract, alur JS manager, validasi, dan catatan security.

3. **[E2E-TESTING.md](E2E-TESTING.md)**
Checklist dan skenario end-to-end UI untuk Super User dan Company user.

---

## 🎯 Scope Singkat

Fitur utama modul Packages saat ini:
- ✅ Package list dengan pagination, status filter, dan search
- ✅ CRUD package (admin-only untuk create/update/delete)
- ✅ Feature assignment per package via modal composer bertingkat (module + detail feature, search, bulk select, dan summary)
- ✅ Detail features viewer per package
- ✅ Package add-on catalog CRUD via halaman Packages
- ✅ Validasi server-side + normalisasi payload UI

Out of scope saat ini:
- ⏳ Pricing simulation calculator

---

## 🗂️ Data Model

### Package (`packages`)
- `id`
- `code` (unique)
- `name`
- `description`
- `monthly_price`
- `yearly_price`
- `billing_unit` (`user|company|flat`)
- `status` (`active|inactive|archived`)
- `color`
- `sort_order`
- `created_at`, `updated_at`

### PackageFeature (`package_features`)
- `id`
- `package_id`
- `feature_code`
- `feature_name`
- `limit`
- `created_at`, `updated_at`

### PackageAddon (`package_addons`)
- `id`
- `code` (unique)
- `name`
- `description`
- `price_per_unit`
- `unit_name`
- `status` (`active|inactive`)
- `created_at`, `updated_at`

---

## 🔌 Endpoint Summary

Packages:
- `GET /v1/saas/packages`
- `GET /v1/saas/packages/{package}`
- `POST /v1/saas/packages`
- `PUT /v1/saas/packages/{package}`
- `DELETE /v1/saas/packages/{package}`

Package features:
- `GET /v1/saas/packages/{package}/features`
- `POST /v1/saas/packages/{package}/features`
- `PUT /v1/saas/packages/features/{feature}`
- `DELETE /v1/saas/packages/features/{feature}`

Package add-ons:
- `GET /v1/saas/package-addons`
- `GET /v1/saas/package-addons/{addon}`
- `POST /v1/saas/package-addons`
- `PUT /v1/saas/package-addons/{addon}`
- `DELETE /v1/saas/package-addons/{addon}`

---

## 🧭 UI Entry Points

- `GET /saas/packages`
- `GET /packages`

Halaman menggunakan manager script `frontend/resources/js/packages-management.js`.

---

## ✅ Status

Module version: `1.1`
Status: `Production-ready baseline`
Last updated: `2026-04-13`

Lanjutkan ke [IMPLEMENTATION.md](IMPLEMENTATION.md) untuk detail teknis dan [E2E-TESTING.md](E2E-TESTING.md) untuk QA flow.
