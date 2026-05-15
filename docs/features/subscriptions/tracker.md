# Subscriptions Audit Tracker

## Status Snapshot

- Tanggal: 2026-05-15
- Status: subscription change history UI parity fix
- Scope audit: visibilitas catatan/alasan request tenant, history review di halaman super-admin subscriptions, dan UX cancel subscription pada halaman upgrade

## Changes Closed In This Audit

- Halaman `/upgrade` sekarang menampilkan `notes` pada riwayat request tenant dan queue pending admin code-1, sehingga alasan tenant atau alasan keputusan admin bisa dibaca langsung tanpa inspeksi API.
- UX action `cancel` pada `/upgrade` sekarang memakai copy yang spesifik ke pembatalan subscription dan akan meminta alasan pembatalan bila field catatan masih kosong saat submit.
- Panel `/saas/subscriptions` untuk primary super admin tidak lagi terkunci ke `status=pending`; UI sekarang bisa toggle `Semua status` vs `Pending saja`, menampilkan history status, catatan/alasan, dan membatasi tombol approve/reject hanya untuk row pending.
- Vitest wiring ditambah untuk memastikan panel history subscriptions memuat endpoint tanpa filter pending by default dan merender notes/alasan.

## Evidence

- `cd backend && npx vitest run tests/ui/subscriptions-management.wiring.test.js` → `PASS (4 tests)`.
- `cd backend && npm run build` → `PASS`.

## Status Snapshot

- Tanggal: 2026-05-13
- Status: recurring renewal billing tax + schema hardening
- Scope audit: renewal invoice generator, tax snapshot parity, schema compatibility invoice/payment, dan trial-expiry billing amount parity

## Changes Closed In This Audit

- `ProcessRecurringSubscriptionBilling` tidak lagi memakai field invoice legacy (`amount`, `type`, `pending`) dan sekarang menulis kontrak invoice aktif (`amount_due`, `billing_tax_rate_snapshot`, `status=draft`, `notes` pricing breakdown).
- Renewal amount sekarang tax-aware menggunakan policy aktif (`HcmBillingTaxPolicy`) dengan snapshot rate yang disimpan ke invoice.
- Dependensi tabel legacy `payment_attempts` dihapus dari recurring flow; retry state dipindahkan ke metadata record `payments` yang sudah ada.
- Renewal payment collection tidak lagi menulis kolom invoice non-eksis (`gateway_reference`, `metadata`), melainkan menyimpan referensi gateway di `payments`.
- Trial expiry conversion (`ConvertExpiredTrialsToPendingPaymentJob`) sekarang menghitung total invoice tax-inclusive berdasarkan snapshot rate (parity dengan flow checkout).
- Regression test ditambah untuk recurring renewal tax-inclusive invoice dan trial-expiry tax snapshot amount.

## Evidence

- `tests/Feature/ProcessRecurringSubscriptionBillingJobTest.php` — renewal invoice harus tax-inclusive + schema valid.
- `tests/Feature/ConvertExpiredTrialsJobTest.php` — trial-expiry invoice harus tax-inclusive + snapshot tersimpan.

## Status Snapshot

- Tanggal: 2026-04-24
- Status: payment-safe hardening for subscription change flow
- Scope audit: queue approval guard code-1, payment gate bypass prevention pada upgrade approval, validasi package aktif, dan scoping notifikasi approver

## Changes Closed In This Audit

- Route web `/pages`, `/blogs`, `/testimonials` kini dibatasi middleware `hcm.web.primary-super-admin` (hanya admin code-1).
- Endpoint queue approval global subscription change (`GET /v1/saas/subscription-change-requests`, `POST approve/reject`) kini menolak super-admin non-code-1 dengan `403 PRIMARY_SUPER_ADMIN_REQUIRED`.
- Approval action `upgrade` tidak lagi auto-apply package agar tidak bypass payment gate; request berhenti di status `approved` sampai tenant menjalankan checkout/invoice payment flow.
- Scheduler `saas-apply-subscription-plan-changes` kini hanya memproses action `downgrade` dan `cancel`.
- Endpoint `preview-change` dan `change-plan` kini menolak target package non-active dengan `422 PACKAGE_NOT_ACTIVE`.
- Notifikasi approval request kini dikirim hanya ke primary super admin code-1 (email `config('hcm.admin_email')`).
- Halaman `/upgrade` kini menampilkan target paket rekomendasi saat redirect `?blocked=<feature>`, riwayat request tenant, dan queue pending untuk admin code-1.
- Subscription list/detail API sekarang menolak non-admin dengan `403 ADMIN_REQUIRED`, termasuk direct bearer-token access.
- `subscriptions-management.js` sekarang mengirim `company_id` sebagai UUID company dan `package_uuid` sebagai UUID package pada create flow.
- Manager JS tidak lagi memanggil list subscriptions sensitif saat permission `subscription.manage` tidak ada; halaman masuk unauthorized/read-only state.
- Vitest regression ditambah untuk memastikan manager JS mengirim payload UUID yang selaras dengan backend.

## Remaining Gaps

- Browser Playwright E2E untuk subscriptions ditunda pada audit 2026-04-19 ini atas arahan user; bukan blocker untuk closure non-E2E.
- Wizard upgrade/downgrade, recurring invoice generator, dan kebijakan global feature-gating by subscription masih belum ada.
- Konsistensi istilah legacy status seperti `paused` vs `suspended` masih perlu perapian seed/UI lintas modul.

## Evidence

- `vendor/bin/phpunit tests/Feature/HcmSubscriptionChangeApiTest.php tests/Feature/NotifySubscriptionChangeApproverJobTest.php` → `OK (8 tests, 36 assertions)`.
- `vendor/bin/phpunit tests/Feature/ConsoleScheduleRegistrationTest.php` → `OK (2 tests, 17 assertions)`.
- `bash scripts/check-api-docs-sync.sh` → `check-api-docs-sync: no changed files`.
- Runtime source of truth: `backend/app/Http/Controllers/Api/HcmSubscriptionChangeController.php`, `backend/routes/web.php`, `frontend/resources/js/upgrade-data.js`, `frontend/resources/js/subscriptions-management.js`