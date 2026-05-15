# Subscriptions Module

## Ringkasan

Modul ini mengelola hubungan langganan company ke package SaaS, termasuk lifecycle subscription, billing cycle, renewal, dan status transisi seperti `trial`, `pending_payment`, `active`, hingga `suspended` atau `expired`. Subscription menjadi titik tengah antara package catalog, invoice/transaction billing, dan enforcement limit tenant.

## Akses

- Web admin: `/subscription` dan `/saas/subscriptions`.
- API admin-only: semua endpoint `/v1/saas/subscriptions*`, termasuk renew.
- Non-admin tetap harus ditolak untuk list, detail, maupun mutasi subscription.

## UI Aktif

- Entry points: `/saas/subscriptions` dan `/subscription`.
- Manager script aktif: `frontend/resources/js/subscriptions-management.js`.
- `pending_payment` tetap menjadi lifecycle aktif di backend, tetapi tidak lagi dibuat manual dari UI subscriptions; state ini dihasilkan oleh checkout, onboarding, atau job sistem.
- Dokumen perilaku dan audit tambahan tersedia di [SCENARIOS.md](SCENARIOS.md), [IMPLEMENTATION.md](IMPLEMENTATION.md), [E2E-TESTING.md](E2E-TESTING.md), dan [tracker.md](tracker.md).

## Flow Bisnis End-to-End

1. Super admin atau billing admin membuat subscription untuk company pada package tertentu.
2. Subscription menyimpan `package_uuid`, `plan_code`, billing cycle, amount, dan status lifecycle.
3. Selama lifecycle berjalan, company dapat berada di status trial, pending payment, active, suspended, expired, atau cancelled.
4. Override operasional untuk mengaktifkan ulang subscription dapat dipicu dari baris tabel atau deep link reactivate-by-id.
5. Saat invoice terkait ditandai paid, subscription `pending_payment` diaktifkan menjadi `active`.
6. Default window `pending_payment` untuk flow onboarding/checkout adalah **24 jam** sejak proses billing dimulai.

## Lifecycle Dan Keputusan Bisnis

- Pending payment: status transisi sebelum invoice dibayar.
- Active/trial: hanya status tertentu yang boleh memakai package aktif.
- Suspend/terminate automation: modul ini mengelola enforcement terhadap invoice overdue atau pelanggaran batas employee.
- Package guard: hanya `packages.status=active` yang boleh dipakai untuk subscription baru atau transisi tertentu.

## Integrasi

- Packages: subscription selalu bergantung pada package catalog dan `package_uuid`. Lihat `docs/features/packages/README.md`.
- Purchase Transactions dan Invoice: status billing serta invoice paid memengaruhi aktivasi subscription. Lihat `docs/features/purchase-transaction/README.md`.
- Trial & Billing Dashboard: dashboard menampilkan health lifecycle trial/subscription berdasarkan data subscription aktif. Lihat `docs/features/trial-billing-dashboard/README.md`.
- Identity/Auth dan onboarding: company-mode login dan onboarding trial berujung pada tenant yang nantinya memiliki subscription aktif. Lihat `docs/features/identity-auth/README.md` dan `docs/features/landing-pages/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Documentation Structure

1. **[README.md](README.md)** (dokumen ini)
Ringkasan modul dan navigasi cepat.

2. **[IMPLEMENTATION.md](IMPLEMENTATION.md)**
Detail teknis backend + frontend: route, API contract, mapping data UI, validasi, dan guard akses.

3. **[SCENARIOS.md](SCENARIOS.md)** ⭐ START HERE (Behaviour)
Happy path & negative scenario handling: apa yang harus terjadi di UI dan apa yang wajib di-enforce di backend.

4. **[E2E-TESTING.md](E2E-TESTING.md)**
Checklist dan skenario end-to-end UI untuk Super User/HCM Admin.

5. **[tracker.md](tracker.md)**
Snapshot status audit, gap aktif, dan evidence validasi terbaru.

---

## Existing Vs Target

- Existing: CRUD subscription, renew, auto-management, employee limit enforcement, dan pending-payment activation sudah aktif.
- Existing: deep link dari packages ke subscription pending payment sudah tersedia.
- Existing (F4, 2026-04-23): tenant-initiated plan change flow aktif via `/v1/hcm/subscriptions/preview-change`, `change-plan`, `cancel-change`, serta approval super-admin via `/v1/saas/subscription-change-requests/{id}/approve|reject`. Web page `/upgrade` dijadikan redirect target dari `EnsureCompanyFeatureForWebPage` saat gate fitur menolak akses.
- Existing (F4+, 2026-04-24): endpoint global queue approval (`GET /v1/saas/subscription-change-requests`, `POST approve/reject`) diperketat menjadi **primary super admin code-1 only** (`hcm.admin_email`) untuk mencegah approval dari akun super-admin sekunder.
- Existing (F4+, 2026-04-24): halaman `/upgrade?blocked=<feature>` kini menampilkan rekomendasi target paket yang punya feature tersebut, plus daftar riwayat pengajuan tenant; admin code-1 juga mendapat panel queue pending request.
- Existing (F4++, 2026-04-24): approve action `upgrade` tidak lagi auto-apply package untuk mencegah bypass payment gate; apply otomatis via scheduler hanya untuk action `downgrade` dan `cancel`, sedangkan target package non-active ditolak `422 PACKAGE_NOT_ACTIVE` pada `preview-change` dan `change-plan`.
- Existing (UI parity, 2026-05-15): riwayat request pada `/upgrade` dan panel history `/saas/subscriptions` kini menampilkan catatan/alasan request, serta panel subscriptions primary-super-admin bisa toggle `Semua status` vs `Pending saja` untuk review history per company tanpa kehilangan row yang sudah approved/rejected/cancelled.
- Existing (2026-05-13): recurring renewal invoice generator aktif dengan hardening tax snapshot + schema parity terhadap invoice runtime (`amount_due`, `billing_tax_rate_snapshot`, `status=draft`, pricing breakdown di notes).
- Target: renewal notification orchestration lanjutan dan workflow upgrade/downgrade wizard masih backlog.

## Scope Singkat

Fitur utama modul saat ini:
- ✅ List subscriptions dengan pagination
- ✅ Create subscription (admin)
- ✅ Update subscription (admin)
- ✅ Cancel subscription (admin)
- ✅ Hard delete subscription dinonaktifkan untuk mencegah penghapusan record lifecycle secara tidak sengaja
- ✅ Reactivate subscription manually (admin override) — per baris di tabel atau **Reactivate by ID** (`GET` detail + `POST` renew jika baris tidak terlihat karena filter/halaman)
- ✅ Filter berdasarkan status, billing cycle, dan search
- ✅ Auto-management (terminate expired, suspend overdue invoice, suspend employee violation)
- ✅ Employee limit enforcement pada create/bulk employee (HCM API)
- ✅ Status **`pending_payment`** + aktivasi otomatis ke **`active`** saat invoice terkait **mark paid** (kolom `invoices.subscription_id`)
- ✅ Default timeout `pending_payment` flow onboarding/checkout: **24 jam** (invoice due date default H+1)
- ✅ Gate paket: `active|trial|pending_payment` hanya boleh memakai package **`packages.status=active`**
- ✅ `pending_payment` dibuat oleh flow checkout/onboarding/job sistem, bukan tombol manual di halaman subscriptions

Out of scope saat ini:
- ⏳ Renewal notification automation
- ⏳ Workflow upgrade/downgrade terpisah (wizard)
- ⏳ Auto-upgrade package + auto-generate invoice upgrade + proration penuh
- ✅ Recurring invoice generator bulanan (auto-billing) — tax-aware dan schema-safe

---

## 🗂️ Data Model

### Subscription (`subscriptions`)
- `id`
- `company_id`
- `package_uuid`
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

Catatan: `DELETE /v1/saas/subscriptions/{subscription}` tetap ada untuk backward compatibility, tetapi runtime sekarang mengembalikan `409 SUBSCRIPTION_DELETE_DISABLED`; operator harus memakai cancel/lifecycle action, bukan hard delete.

Semua endpoint di atas admin-only (return `403 ADMIN_REQUIRED` jika non-admin, termasuk list/detail).

---

## 🧭 UI Entry Points

- `GET /saas/subscriptions`
- `GET /subscription`

Halaman memakai manager script `frontend/resources/js/subscriptions-management.js`.

---

## Status

Module version: `1.1`
Status: `Production-ready baseline`
Last updated: `2026-04-24`

Mulai dari [SCENARIOS.md](SCENARIOS.md) untuk pemahaman flow + negative handling, lanjut ke [IMPLEMENTATION.md](IMPLEMENTATION.md) untuk detail teknis, [E2E-TESTING.md](E2E-TESTING.md) untuk QA flow, dan [tracker.md](tracker.md) untuk snapshot audit terakhir.

## Test Coverage

**Backend** (PHPUnit feature + service tests):
- `SubscriptionServiceTest.php` — auto-terminate/suspend/employee-count logic
- `SaasSubscriptionsAdminOnlyTest.php` — list/detail/create/update/delete/renew admin-only enforcement
- `SubscriptionManagementTest.php` — create/update/filter contracts
- `InvoicePaidActivatesSubscriptionTest.php` — pending_payment → active flow
- Run: `cd backend && php artisan test tests/Feature/SubscriptionServiceTest.php tests/Feature/SaasSubscriptionsAdminOnlyTest.php tests/Feature/SubscriptionManagementTest.php tests/Feature/InvoicePaidActivatesSubscriptionTest.php`

**Frontend wiring** (Vitest):
- `subscriptions-api-contract.test.js` — endpoint/path/payload mapping checks
- Run: `cd backend && npm run test:ui`
