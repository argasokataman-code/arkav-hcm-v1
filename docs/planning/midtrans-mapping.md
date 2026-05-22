---
title: Xendit → Midtrans Mapping (draft)
status: draft
created: 2026-05-22
---

# Xendit → Midtrans Mapping (draft)

Tujuan: ringkas titik-titik integrasi Xendit di codebase dan berikan peta (conceptual) ke API/semantik Midtrans agar implementasi `MidtransService` bisa dibuat tanpa mengubah konsumsi kode di service layer.

Catatan penting: ini adalah mapping konseptual. Sebelum implementasi kode, verifikasi endpoint/payload Midtrans terbaru (Snap/Core/Server-to-Server notification) dan adaptasikan payload/field sesuai akun merchant.

1) Ringkasan per-fungsi Xendit (apa yang dipanggil sekarang) — VERIFIED
- `XenditService::createInvoice($params)`
  - Memanggil Xendit `/v2/invoices` untuk hosted checkout.
  - Mengembalikan `id` (xendit invoice id) dan `invoice_url`.
  - Codebase menyimpan `xendit_invoice_id` di `payment.metadata` dan `gateway_reference`; juga `checkout_mode: 'xendit_hosted'`.

- `XenditService::getInvoice($id)`
  - Dipakai untuk polling/reconciliation dan status check (SETTLED/PAID/EXPIRED/FAILED).

- `XenditService::verifyInvoicePayment($id)` — exists di service, tapi TIDAK ditemukan active caller saat ini (dead method).

- `XenditService::expireInvoice($id)` — exists di service, tapi TIDAK ditemukan active caller saat ini (dead method).

- `XenditService::createRecurringInvoice()` — exists di service, tapi TIDAK ditemukan active caller; renewal saat ini memakai `createInvoice()` langsung per-renewal.

- `XenditService::stopRecurringInvoice()` — exists di service, tapi TIDAK ditemukan active caller saat ini (dead method).

- `XenditService::createDisbursement()` — payouts, out of scope untuk migrasi ini.

2) Mapping konseptual ke Midtrans
- Hosted checkout / one-off invoice
  - Xendit: `/v2/invoices` → returns `id` + `invoice_url`.
  - Midtrans: gunakan **Snap API** — `\Midtrans\Snap::createTransaction($params)` → returns `token` + `redirect_url`.
  - Setup config Midtrans (wajib sebelum setiap call):
    ```php
    \Midtrans\Config::$serverKey = config('services.midtrans.server_key');
    \Midtrans\Config::$isProduction = config('services.midtrans.is_production', false);
    \Midtrans\Config::$isSanitized = true;
    \Midtrans\Config::$is3ds = true;
    ```
  - Transaction params wajib:
    ```php
    ['transaction_details' => ['order_id' => $orderId, 'gross_amount' => (int) $amount]]
    ```
  - `order_id` = merchant-defined unique ID (max 50 chars, allowed: alphanumeric, `-`, `_`, `~`, `.`). Format `invoice-{id}-{random10}` aman.
  - ⚠️ **Create response HANYA dua field**: `{"token": "...", "redirect_url": "..."}`. Tidak ada `transaction_id` di create response! `transaction_id` Midtrans baru tersedia setelah webhook notification masuk atau status API di-poll.
  - ⚠️ **Tiga redirect URLs** (bukan dua seperti Xendit): Midtrans butuh `finish_url` (paid/done), `unfinish_url` (closed without paying), `error_url` (error). Pass via `callbacks` parameter ATAU set di MAP Dashboard → Settings → Snap Preference.
  - ⚠️ **Notification URL wajib dikonfigurasi manual di MAP Dashboard** (Settings → Configuration → Payment Notification URL). Tidak bisa via API. Wajib masuk deployment checklist.
  - Mapping fields:
    - `xendit_invoice_id`  → tidak tersedia saat create; diisi dari `transaction_id` setelah webhook
    - `xendit_external_id` → `order_id` (merchant order id — kita yang generate)
    - `invoice_url`        → `redirect_url` (response dari `Snap::createTransaction()`)
    - `xendit_channel_hint` → `payment_type` (pada notification, bukan pada create)

- Polling / reconciliation
  - Xendit: `getInvoice(id)` → status values `SETTLED`, `PAID`, `EXPIRED`, `FAILED`, `PENDING`.
  - Midtrans: `\Midtrans\Transaction::status($orderId)` → field `transaction_status`:
    - Treat as **paid**: `settlement` (semua metode); `capture` dengan `fraud_status === 'accept'`
    - Treat as **failed**: `cancel`, `deny`, `expire`
    - Treat as **pending**: `pending`, `authorize`
    - ⚠️ **`capture` wajib cek `fraud_status`**: jika `fraud_status === 'challenge'`, jangan langsung mark paid — tunggu review manual atau tolak.

- Recurring invoices
  - Midtrans recurring (subscription) workstream differs: may require customer/card tokenization and merchant-side subscription setup. Recommendation: keep current renewal-by-invoice design and use Midtrans transaction creation for each renewal, or implement Midtrans recurring features if merchant account supports it.

- Webhook / notification
  - Xendit: auth via `X-Callback-Token` header. Idempotency via `xendit-webhook-id` header (fallback: deterministic hash dari payload).
  - Midtrans:
    - Auth / signature: **TIDAK ada dedicated auth header**. Verifikasi dengan `signature_key` field dari payload body:
      ```php
      $signature = hash('sha512', $order_id . $status_code . $gross_amount . $serverKey);
      // cocokkan dengan $payload['signature_key']
      ```
    - `gross_amount` di payload adalah STRING dengan 2 desimal, e.g. `"145000.00"`.
    - ⚠️ **Idempotency berbeda dari Xendit**: Midtrans tidak mengirim webhook-id header. Gunakan `order_id` (merchant key yang kita buat sendiri) sebagai cache key: `"midtrans_webhook:{$orderId}"`.
    - Notification fields wajib diproses: `order_id`, `transaction_id`, `transaction_status`, `fraud_status`, `status_code`, `signature_key`, `gross_amount`.
    - Gunakan `\Midtrans\Notification()` PHP SDK class ATAU parse raw JSON dan verifikasi manual.
    - Event type tidak ada di Midtrans (berbeda dari Xendit `event: invoice.paid`); routing berdasarkan `transaction_status` + `fraud_status`.

3) Code changes required (high level) — VERIFIED terhadap kode aktual
- Tambah `app/Services/MidtransService.php` dengan method: `createTransaction()` (untuk hosted checkout via Snap), `getTransaction($orderId)` (polling status via Transaction Status API). Dead methods dari XenditService (`expireInvoice`, `stopRecurringInvoice`, `verifyInvoicePayment`) tidak perlu diimplementasikan.
  - ✅ **Naming decision: gunakan `createTransaction`/`getTransaction`** — ini sesuai dengan Midtrans PHP SDK (`\Midtrans\Snap::createTransaction()` dan `\Midtrans\Transaction::status()`). Bukan `createInvoice`/`getInvoice` (itu naming Xendit). `PaymentGatewayService` skeleton SUDAH menggunakan naming yang benar.
- Update `app/Services/PaymentGatewayService.php` — SUDAH ditambah skeleton branch `midtrans` untuk `charge`, `verify`, dan `handleWebhook`.
- Tambah route `Route::post('/webhooks/midtrans', ...)` di `backend/routes/api/webhooks.php`.
- Tambah `handleMidtrans()` di `PaymentWebhookController` dengan verifikasi signature Midtrans (`signature_key` = SHA512(order_id + status_code + gross_amount + ServerKey)) dan idempotensi berbasis order_id/transaction_id.
- Update `findPaymentByXenditIdentifiers()` atau tambah `findPaymentByMidtransIdentifiers()` — dua query existing di `PaymentWebhookController` L527 & L542 hard-coded `->where('gateway', 'xendit')`.
- Update `ProcessRecurringSubscriptionBilling.php`: tambah `chargeViaMidtrans()` ke match di L379 (saat ini hanya `'xendit'`/`'stripe'` — jika tidak diupdate akan throw RuntimeException saat `gateway='midtrans'`); tambah `MIDTRANS_DOWN`, `MIDTRANS_PAYMENT_FAILED` ke `$failureCodes` array (saat ini L819–824 hanya ada XENDIT_*).
- Update `ReconcilePendingRenewalPayments.php`: ubah `->where('gateway', 'xendit')` L27 → `->whereIn('gateway', ['xendit', 'midtrans'])`; ganti semua hardcoded `XENDIT_DOWN` string dengan logic provider-agnostic.
- Update `HcmCompanyInvoiceController.php`:
  - ⚠️ L283 `gatewayMode` validation: ganti `'in:auto,xendit,mock'` → `'in:auto,xendit,midtrans,mock'` (jika tidak, request Midtrans langsung 422).
  - ⚠️ `shouldUseXenditCheckout()` L586: tambah early-return `if ($gatewayMode === 'midtrans') return false;` agar tidak fall-through ke xendit config check; tambah `shouldUseMidtransCheckout()` yang check `config('services.midtrans.server_key')`.
  - Ganti error code `XENDIT_NOT_CONFIGURED` (L302) → `GATEWAY_NOT_CONFIGURED`.
  - Update `checkout_mode` metadata value `'xendit_hosted'` → `'midtrans_hosted'` untuk payment Midtrans.
- Update `frontend/resources/ts/thr-payroll-batch/helpers.ts` L295: tambah `if (normalized === "midtrans") return "Midtrans";`.
- Update `database/seeders/RenewalMonitoringSeeder.php`: scenario E hard-coded `XENDIT_DOWN` (L213) — update atau buat generic.

4) Metadata & DB considerations — VERIFIED
- Preserve existing `payment.gateway` values (`xendit`) untuk history. Untuk payment Midtrans baru, set `gateway='midtrans'` dan simpan:
  - `gateway_reference` = Midtrans `order_id` (merchant-generated, kita yang buat)
  - metadata pada saat CREATE: `midtrans_order_id`, `midtrans_redirect_url`, `checkout_mode: 'midtrans_hosted'`
  - metadata ditambah setelah WEBHOOK masuk: `midtrans_transaction_id`, `midtrans_payment_type`, `midtrans_fraud_status`
  - ⚠️ `midtrans_transaction_id` belum tersedia saat `Payment` record dibuat (tidak ada di create response). Baru bisa diisi saat handleMidtrans webhook atau reconciliation polling. Schema harus allow NULL untuk field ini pada initial creation.
- Jangan rename/hapus key Xendit lama (`xendit_invoice_id`, `xendit_external_id`, `xendit_channel_hint`) agar rollback tetap bisa bekerja.
- `ReconcilePendingRenewalPayments` menggunakan `gateway_reference` untuk polling: simpan `order_id` di `gateway_reference` agar bisa langsung memanggil `Transaction::status($gateway_reference)` saat reconciliation.

5) Tests
- Add `MidtransWebhookControllerTest` (parity with `XenditWebhookControllerTest`) asserting signature verification, idempotency, and invoice/payment matching.
- Add hosted-checkout feature test for Midtrans (mock service or mocked HTTP client).

6) Config / env
- New env variables: `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`.
- Add `midtrans` block to `config/services.php` and `config/hcm.php` `payment.driver` option.

7) Risks & open items
- Recurring/subscription model differences (Midtrans recurring may require tokenization). Decision required: keep invoice-per-renewal approach or implement server-side subscription.
- Signature verification differences: implement according to Midtrans docs and test thoroughly.
- E2E smoke required: create invoice → redirect → simulate notification → reconciliation → subscription extension.

Next steps:

1. Implement `app/Services/MidtransService.php` skeleton and update `app/Services/PaymentGatewayService.php` to route calls.
2. Update webhook handler and reconciliation job.
3. Run E2E smoke test: invoice → Snap popup → payment → webhook → subscription active.
