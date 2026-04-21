# Mock Payment Gateway Guide (Development)

## Ringkasan

Guide ini adalah quick-start dev untuk menjalankan mock payment yang cukup dekat dengan flow billing nyata tanpa memanggil hosted payment Xendit. Source of truth yang lebih lengkap ada di:

- `docs/features/mock-payment/README.md`
- `docs/features/mock-payment/IMPLEMENTATION.md`
- `docs/features/mock-payment/tracker.md`

## Kapan Pakai Flow Yang Mana

### 1. Flow yang paling mendekati payment real

Pakai ini jika tujuanmu adalah membuktikan invoice tenant benar-benar menjadi paid dan subscription `pending_payment` berubah menjadi `active`.

Urutan yang disarankan:

1. Buat checkout/upgrade sampai menghasilkan invoice unpaid.
2. Buka `/company/invoices`.
3. Trigger `POST /v1/hcm/billing/invoices/{id}/mock-pay`.
4. Reload halaman atau cek API profile/invoice untuk memastikan state sukses.

Kenapa ini yang direkomendasikan:

- invoice berasal dari flow tenant yang benar;
- payment disimpan dengan gateway `xendit_mock`;
- invoice berubah paid di DB;
- activation subscription berjalan bila invoice terkait subscription.

### 2. Flow utilitas dev cepat

Pakai ini jika tujuanmu adalah seeding cepat atau smoke test endpoint tanpa melewati seluruh journey user.

- `POST /v1/mock/invoices/create-and-pay`
- `POST /v1/mock/payments/create`
- `GET /v1/mock/test-cards`
- `POST /v1/mock/webhook/charge-succeeded`

### 3. HTML tester statis

Tersedia di `/mock-payment-tester.html`.

Bagus untuk:

- quick pay satu langkah;
- generate bearer token tenant langsung dari helper;
- hosted simulation lokal dengan redirect + callback token + settlement webhook;
- load test cards;
- smoke test bearer token dan tenant context.

Catatan penting:

- tab `Pay Invoice` di halaman ini sekarang sudah memakai UUID invoice, sesuai kontrak runtime `POST /v1/mock/payments/create`.
- untuk proof flow yang lebih realistis, tetap utamakan `/company/invoices`.

## Prasyarat

- Environment local, atau `app.mock_payments_enabled` aktif.
- User sudah login dan punya bearer token/API token yang valid.
- User punya company aktif karena endpoint mock memakai `tenant.context`.

## Quick Start

### A. Shortcut: create invoice and pay in one call

```bash
curl -X POST http://localhost:8007/v1/mock/invoices/create-and-pay \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 500000,
    "description": "Dev billing smoke test",
    "currency": "IDR",
    "simulate_failure": false
  }'
```

Hal yang perlu dicek di response:

- `data.invoice.id`
- `data.invoice.status`
- `data.payment.id`
- `data.payment.status`
- `data.subscription.status` bila ada subscription terkait

### A2. Hosted simulation lokal

Kalau ingin meniru hosted invoice URL, redirect browser, callback token, dan settlement webhook lokal:

```bash
curl -X POST http://localhost:8007/v1/mock/invoices/create-and-pay \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Company-Code: YOUR_COMPANY_CODE" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 500000,
    "description": "Hosted dev billing smoke test",
    "currency": "IDR",
    "flow_mode": "hosted",
    "success_url": "http://localhost:8007/mock-payment-tester.html",
    "failure_url": "http://localhost:8007/mock-payment-tester.html"
  }'
```

Yang perlu dicek di response hosted mode:

- `data.payment.status=pending`
- `data.flow.hosted_checkout_url`
- `data.flow.callback_token`
- `data.flow.webhook.simulate_success_url`

### B. Flow yang direkomendasikan: mock pay existing tenant invoice

1. Ambil daftar invoice tenant:

```bash
curl http://localhost:8007/v1/hcm/billing/invoices?perPage=50 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

2. Ambil `id` invoice yang ingin dibayar, lalu trigger mock pay:

```bash
curl -X POST http://localhost:8007/v1/hcm/billing/invoices/INVOICE_ID/mock-pay \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "paymentMethod": "mock_card",
    "gateway": "xendit_mock"
  }'
```

Success signal yang penting:

- `success=true`
- `payment.gateway=xendit_mock`
- `payment.status=completed`
- invoice kembali sebagai `paid`

### C. Pay an existing invoice via dev utility endpoint

Gunakan endpoint ini hanya jika kamu memang sudah punya UUID invoice dari flow lain.

```bash
curl -X POST http://localhost:8007/v1/mock/payments/create \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_id": "INVOICE_UUID",
    "amount": 500000,
    "payment_method": "mock_card",
    "simulate_failure": false
  }'
```

Catatan:

- `invoice_id` mengikuti kontrak runtime UUID.
- jangan pakai numeric invoice id untuk endpoint ini.

### D. Simulate failure

```bash
curl -X POST http://localhost:8007/v1/mock/payments/create \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_id": "INVOICE_UUID",
    "amount": 500000,
    "payment_method": "mock_card",
    "simulate_failure": true
  }'
```

Expected outcome:

- endpoint mengembalikan failure response untuk path `payments/create`;
- invoice harus tetap unpaid.

## HTML Tester Notes

Halaman `/mock-payment-tester.html`:

- bisa generate token tenant langsung dari email/password/company code;
- menyimpan `auth_token`, `arcav_access_token`, dan `arcav_active_tenant` ke localStorage;
- memanggil API base `window.location.origin + '/v1'`;
- tab `Quick Pay` memanggil `POST /v1/mock/invoices/create-and-pay` dengan mode `instant` atau `hosted`;
- tombol hosted action bisa membuka `/mock-hosted-payment.html` dari hosted URL response;
- tab `Test Cards` memanggil `GET /v1/mock/test-cards`.

Tester ini berguna untuk eksperimen cepat, tetapi bukan bukti utama parity dengan payment real.

## Perbedaan Dengan Xendit Real

Yang ditiru dengan baik:

- payment dan invoice tetap ditulis ke database;
- ada `gateway_reference` mock;
- flow billing bisa lanjut ke state sukses yang dibaca modul lain.

Yang belum ditiru penuh:

- domain hosted eksternal provider;
- signature/callback secret provider nyata;
- settlement asynchronous dari network provider real.

## Referensi

- `docs/features/mock-payment/README.md`
- `docs/features/mock-payment/IMPLEMENTATION.md`
- `docs/features/mock-payment/tracker.md`
- `docs/features/subscriptions/README.md`
- `docs/features/trial-billing-dashboard/README.md`