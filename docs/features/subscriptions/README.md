# 🔁 Subscriptions Module

Mengelola langganan company ke package SaaS, termasuk lifecycle status, billing cycle, dan renewal.

---

## 📚 Documentation Structure

1. **[README.md](README.md)** (dokumen ini)
Ringkasan modul dan navigasi cepat.

2. **[IMPLEMENTATION.md](IMPLEMENTATION.md)**
Detail teknis backend + frontend: route, API contract, mapping data UI, validasi, dan guard akses.

3. **[E2E-TESTING.md](E2E-TESTING.md)**
Checklist dan skenario end-to-end UI untuk Super User/HCM Admin.

---

## 🎯 Scope Singkat

Fitur utama modul saat ini:
- ✅ List subscriptions dengan pagination
- ✅ Create subscription (admin)
- ✅ Update subscription (admin)
- ✅ Cancel/Delete subscription (admin)
- ✅ Renew subscription (admin)
- ✅ Filter berdasarkan status, billing cycle, dan search

Out of scope saat ini:
- ⏳ Renewal notification automation
- ⏳ Workflow upgrade/downgrade terpisah (wizard)

---

## 🗂️ Data Model

### Subscription (`subscriptions`)
- `id`
- `company_id`
- `package_id`
- `plan_code`
- `status` (`active|trial|inactive|expired|cancelled`)
- `starts_at`
- `ends_at`
- `trial_ends_at`
- `auto_renew`
- `billing_cycle` (`monthly|yearly`)
- `amount`
- `created_at`, `updated_at`

---

## 🔌 Endpoint Summary

- `GET /v1/saas/subscriptions`
- `POST /v1/saas/subscriptions`
- `GET /v1/saas/subscriptions/{subscription}`
- `PUT /v1/saas/subscriptions/{subscription}`
- `DELETE /v1/saas/subscriptions/{subscription}`
- `POST /v1/saas/subscriptions/{subscription}/renew`

Mutasi endpoint di atas admin-only (return `403 ADMIN_REQUIRED` jika non-admin).

---

## 🧭 UI Entry Points

- `GET /saas/subscriptions`
- `GET /subscription`

Halaman memakai manager script `frontend/resources/js/subscriptions-management.js`.

---

## ✅ Status

Module version: `1.1`
Status: `Production-ready baseline`
Last updated: `2026-04-13`

Lanjutkan ke [IMPLEMENTATION.md](IMPLEMENTATION.md) untuk detail teknis dan [E2E-TESTING.md](E2E-TESTING.md) untuk QA flow.
