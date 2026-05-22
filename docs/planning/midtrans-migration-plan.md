---
title: Midtrans Migration Plan (Xendit → Midtrans)
status: draft
created: 2026-05-22
---

# Midtrans Migration Plan (Xendit → Midtrans)

Ringkasan: migrasi provider pembayaran dari Xendit ke Midtrans untuk hosted checkout, invoicing, dan reconciliation. Tujuan: sediakan implementasi parallel-able, mudah diuji, rollback-friendly, dan terdokumentasi.

Scope (in-scope):
- Hosted checkout flow (`HcmCompanyInvoiceController::startXenditHostedCheckout` → Midtrans)
- Recurring billing job (`ProcessRecurringSubscriptionBilling`) dan renewal reconciliation (`ReconcilePendingRenewalPayments`)
- Payment gateway wrapper/service layer (`app/Services/XenditService.php` → `MidtransService`), dan `PaymentGatewayService` mapping
- Webhook handling (route `/webhooks/xendit` → tambah `/webhooks/midtrans` + signature verification)
- Tests (feature + unit + webhook) dan CI secrets
- Documentation: `docs/` updates and runbook for rollout

Out of scope (for this PR): payment disbursements/disburse job changes unrelated to SaaS invoice renewal, third-party accounting exports.

Inventory (key files found) — VERIFIED terhadap kode aktual:
- `backend/config/services.php` — `services.xendit` (`xendit.api_key`, `xendit.callback_token`)
- `backend/app/Services/XenditService.php` — API wrapper (createInvoice, getInvoice, verifyInvoicePayment, expireInvoice, createRecurringInvoice, stopRecurringInvoice, createDisbursement)
- `backend/app/Http/Controllers/Api/Billing/HcmCompanyInvoiceController.php` — `startXenditHostedCheckout()`, `shouldUseXenditCheckout()`, `canUseMockCheckout()`, `shouldForceLocalMockCheckout()`, `isNgrokRuntime()`, error code `XENDIT_NOT_CONFIGURED` (L302), metadata key `checkout_mode: 'xendit_hosted'` (L560)
- `backend/app/Jobs/ProcessRecurringSubscriptionBilling.php` — `chargeViaXendit()`, `$failureCodes` array hard-coded dengan `XENDIT_DOWN`, `XENDIT_PAYMENT_FAILED`, `XENDIT_INVOICE_EXPIRED` (L819–824)
- `backend/app/Jobs/ReconcilePendingRenewalPayments.php` — `->where('gateway', 'xendit')` hard-coded (L27), semua anomaly codes di-hardcode ke `XENDIT_DOWN`, `XENDIT_INVOICE_EXPIRED`
- `backend/app/Http/Controllers/Api/Payment/PaymentWebhookController.php` — ⚠️ file utama webhook: `handleXendit()`, `findPaymentByXenditIdentifiers()` dengan `->where('gateway', 'xendit')` di 2 tempat (L527, L542)
- `backend/routes/api/webhooks.php` — ⚠️ deklarasi route `Route::post('/webhooks/xendit', ...)` — perlu route baru untuk Midtrans
- `backend/app/Services/PaymentGatewayService.php` — payment routing; sudah ditambah skeleton `midtrans` branch
- `frontend/resources/ts/thr-payroll-batch/helpers.ts` — ⚠️ display name mapping `if (normalized === "xendit") return "Xendit"` (L295) — perlu tambah entri Midtrans
- `backend/database/seeders/RenewalMonitoringSeeder.php` — ⚠️ hard-coded scenario `XENDIT_DOWN` di demo data (L213)
- Tests: `backend/tests/Feature/XenditWebhookControllerTest.php` ✅ confirmed, `backend/tests/Feature/HcmCompanyInvoiceHostedCheckoutTest.php` ✅ confirmed

High-level migration steps

1) Prep & Config
   - Add Midtrans config in `backend/config/services.php` and new env variables:
     - `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`.
   - Add `config/hcm.php` option `payment.driver` (values: `stub|xendit|midtrans|auto`) and use it where appropriate.
   - Add CI/CD secrets for Midtrans keys (staging/prod).

2) Library & Service
   - Add composer dependency: `composer require midtrans/midtrans-php` (or minimal HTTP wrapper if preferred).
   - Implement `app/Services/MidtransService.php` exposing same facade as `XenditService` methods used by codebase (createInvoice, getInvoice, verify, recurring if needed).
   - Keep `XenditService` intact (no deletion) for rollback and parallel testing.
   - Update `app/Services/PaymentGatewayService.php` to dispatch to appropriate service by gateway/provider key.

3) Controller & Job changes
   - ⚠️ Update `HcmCompanyInvoiceController` L283 — `gatewayMode` validation: saat ini `'in:auto,xendit,mock'`. WAJIB tambah `'midtrans'` → `'in:auto,xendit,midtrans,mock'`. Jika tidak, request dengan `gatewayMode=midtrans` akan reject 422 SEBELUM controller logic berjalan.
   - ⚠️ Update atau refactor `shouldUseXenditCheckout()` (L586) — saat ini: jika `gatewayMode=midtrans`, method akan fall-through ke `return (bool) config('services.xendit.api_key')` dan MALAH mengaktifkan Xendit. Butuh explicit branch: `if ($gatewayMode === 'midtrans') { return false; }` DAN tambah `shouldUseMidtransCheckout()` method yang mengecek `config('services.midtrans.server_key')`.
   - Update `HcmCompanyInvoiceController::startXenditHostedCheckout` to be provider-agnostic: call `PaymentGatewayService->startHostedCheckout($invoice, $provider)`. Atau tambah `startMidtransHostedCheckout()` parallel method.
   - Update error code `XENDIT_NOT_CONFIGURED` (HcmCompanyInvoiceController L302) → `GATEWAY_NOT_CONFIGURED` (provider-agnostic).
   - ⚠️ Update `checkout_mode` metadata value dari `'xendit_hosted'` → `'midtrans_hosted'` untuk payment Midtrans baru. Note: `midtrans_transaction_id` belum tersedia saat create (tidak ada di Snap create response — hanya `token` + `redirect_url`); baru bisa diisi dari webhook atau status polling.
   - ⚠️ Update `ProcessRecurringSubscriptionBilling.php` match di L379 — saat ini hanya punya case `'xendit'` dan `'stripe'`; jika `gateway='midtrans'` sudah ada di subscription metadata, THROWS RuntimeException. Tambah `'midtrans' => $this->chargeViaMidtrans(...)` ke match; tambah `chargeViaMidtrans()` method; tambah `MIDTRANS_DOWN`, `MIDTRANS_PAYMENT_FAILED` ke `$failureCodes` array (L819–824).
   - Update `ReconcilePendingRenewalPayments.php`: ubah `->where('gateway', 'xendit')` (L27) → `->whereIn('gateway', ['xendit', 'midtrans'])`; tambah MIDTRANS anomaly codes; peta metadata keys (`midtrans_order_id` / `gateway_reference`).

4) Webhooks
   - Tambah route `Route::post('/webhooks/midtrans', ...)` di `backend/routes/api/webhooks.php`.
   - Tambah handler `handleMidtrans()` di `PaymentWebhookController` (ATAU buat `MidtransWebhookController` terpisah — pilih satu). Implementasikan verifikasi signature Midtrans (`signature_key` field dari payload = SHA512(order_id + status_code + gross_amount + ServerKey)).
   - Update `findPaymentByXenditIdentifiers()` atau tambah `findPaymentByMidtransIdentifiers()` — harus query dengan `gateway='midtrans'`; dua query eksisting di L527 & L542 saat ini hard-coded `gateway='xendit'`.
   - Mirror idempotency: cache key berbasis Midtrans transaction id atau `order_id` (fallback deterministic hash jika tidak ada).
   - Update webhook tests: tambah `MidtransWebhookControllerTest.php` (parity dengan `XenditWebhookControllerTest`), keep Xendit tests unchanged.

5) Tests
   - Unit: mock `MidtransService` for `PaymentGatewayService` and controller tests.
   - Feature: add hosted-checkout flow using midtrans mock in `HcmCompanyInvoiceHostedCheckoutTest` variant.
   - Reconciliation & renewal tests: add cases for `gateway: midtrans` paths and gateway-down anomalies (`MIDTRANS_DOWN`).
   - Update `scripts/local-test-gate.sh` docs to include required MIDTRANS env vars for local runs (allow empty for mock mode).

6) CI / Secrets / Staging rollout
   - Add staging Midtrans credentials to CI secrets.
   - ⚠️ **Konfigurasi manual di MAP Dashboard (wajib sebelum staging/production):**
     - Settings → Configuration → **Payment Notification URL** = `https://{domain}/api/webhooks/midtrans`
     - Settings → Snap Preference → **Finish URL, Unfinish URL, Error URL** (atau pass `callbacks.finish_url`, `callbacks.unfinish_url`, `callbacks.error_url` per-request di Snap params).
     - Lakukan ini untuk environment Sandbox DAN Production (settings terpisah).
   - Deploy to staging with `config('hcm.payment.driver')=midtrans` behind feature flag.
   - Run full test-suite + manual E2E smoke: create invoice → hosted checkout URL → simulate webhook (staging) → invoice mark paid → subscription activation.
   - Canary: route a small percent of renewal/hosted-checkout traffic to Midtrans in staging if possible (or manual acceptance testing teams).
   - Production: schedule maintenance window, flip `payment.driver` to `midtrans` after final smoke verification.

7) Rollback
   - Revert `payment.driver` to `xendit` (no DB changes required). Keep both services and webhook endpoints active for a period.
   - If issues persist, revert deployed code and keys to previous verified commit.

8) Observability & Monitoring
   - Tambah `MIDTRANS_DOWN`, `MIDTRANS_PAYMENT_FAILED`, `MIDTRANS_INVOICE_EXPIRED` ke `$failureCodes` array di `ProcessRecurringSubscriptionBilling` (L819) dan `ReconcilePendingRenewalPayments`.
   - Update `NotificationService.php` docblock (L553) yang saat ini hanya mention `XENDIT_DOWN` — tambah `MIDTRANS_DOWN`.
   - Update `RenewalMonitoringSeeder.php` — Scenario E saat ini hard-coded `XENDIT_DOWN` (L213); tambah scenario baru atau jadikan generic.
   - Ensure logs include `midtrans_order_id` / `gateway_reference`; add metrics for webhook latency and reconciliation error rate.

9) Documentation & Runbook
   - Update `docs/engineering/CODEBASE-ANALYSIS.md` and `docs/planning/implementation-status.md` with migration summary.
   - Add this file `docs/planning/midtrans-migration-plan.md` as the migration source of truth.
   - Create short runbook for on-call (how to manually set subscription active if webhook missed, how to re-run reconciliation job).

10) Checklist before production cutover
    - [ ] `MidtransService` implemented and unit-tested
    - [ ] Webhook controller + tests green
    - [ ] Staging smoke tests passed (hosted checkout → simulate pay → webhook → invoice paid → subscription active)
    - [ ] CI secrets configured for prod and staging
    - [ ] Monitoring/alert rules deployed
    - [ ] Rollback plan and owner assigned

Owner: Billing team / Backend engineer (assign PR author)
Estimated effort: 2–4 dev-days (implement service + wiring + tests + docs + staging verification)

Notes & risks:
- Maintain existing Xendit code for at least one release for safe rollback.
- Watch metadata key mapping differences (Xendit `xendit_invoice_id` vs Midtrans order/payment ids).
- Ensure idempotency keys and webhook dedup logic align with Midtrans webhook semantics.

Next immediate action: implement `MidtransService` skeleton and update `PaymentGatewayService` mapping. 
