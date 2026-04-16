# 🔁 Subscriptions Module

Mengelola langganan company ke package SaaS, termasuk lifecycle status, billing cycle, dan renewal.

---

## 📚 Documentation Structure

1. **[README.md](README.md)** (dokumen ini)
Ringkasan modul dan navigasi cepat.

2. **[IMPLEMENTATION.md](IMPLEMENTATION.md)**
Detail teknis backend + frontend: route, API contract, mapping data UI, validasi, dan guard akses.

3. **[SCENARIOS.md](SCENARIOS.md)** ⭐ START HERE (Behaviour)
Happy path & negative scenario handling: apa yang harus terjadi di UI dan apa yang wajib di-enforce di backend.

4. **[E2E-TESTING.md](E2E-TESTING.md)**
Checklist dan skenario end-to-end UI untuk Super User/HCM Admin.

---

## 🎯 Scope Singkat

Fitur utama modul saat ini:
- ✅ List subscriptions dengan pagination
- ✅ Create subscription (admin)
- ✅ Update subscription (admin)
- ✅ Cancel/Delete subscription (admin)
- ✅ Renew subscription (admin) — per baris di tabel atau **Renew by ID** (`GET` detail + `POST` renew jika baris tidak terlihat karena filter/halaman)
- ✅ Filter berdasarkan status, billing cycle, dan search
- ✅ Auto-management (terminate expired, suspend overdue invoice, suspend employee violation)
- ✅ Employee limit enforcement pada create/bulk employee (HCM API)
- ✅ Status **`pending_payment`** + aktivasi otomatis ke **`active`** saat invoice terkait **mark paid** (kolom `invoices.subscription_id`)
- ✅ Gate paket: `active|trial|pending_payment` hanya boleh memakai package **`packages.status=active`**
- ✅ Deep link dari **Packages** → **Subscriptions** (`/subscription?packageId=&status=pending_payment`)

Out of scope saat ini:
- ⏳ Renewal notification automation
- ⏳ Workflow upgrade/downgrade terpisah (wizard)
- ⏳ Auto-upgrade package + auto-generate invoice upgrade + proration penuh
- ⏳ Recurring invoice generator bulanan (auto-billing)

---

## 🗂️ Data Model

### Subscription (`subscriptions`)
- `id`
- `company_id`
- `package_id`
- `plan_code`
- `status` (`active|trial|pending_payment|inactive|expired|cancelled|suspended` + nilai legacy lain sesuai DB)
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
Last updated: `2026-04-16`

Mulai dari [SCENARIOS.md](SCENARIOS.md) untuk pemahaman flow + negative handling, lanjut ke [IMPLEMENTATION.md](IMPLEMENTATION.md) untuk detail teknis, dan [E2E-TESTING.md](E2E-TESTING.md) untuk QA flow.
