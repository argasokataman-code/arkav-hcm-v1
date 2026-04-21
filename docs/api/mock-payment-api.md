# Mock Payment API (Development Only)

Base path utilitas dev: `/v1/mock`

## Runtime Source Of Truth

Dokumen ini hanya mencakup endpoint utilitas development-only di `/v1/mock/*`.

Untuk flow tenant invoice yang paling mendekati payment real, lihat juga:

- `POST /v1/hcm/billing/invoices/{id}/mock-pay`
- `POST /v1/hcm/billing/invoices/{id}/mock-hosted-checkout`
- `docs/api/openapi.yaml`
- `docs/features/mock-payment/README.md`

## Otorisasi Dan Scope

- Endpoint ini hanya aktif saat aplikasi local atau saat mock payments diaktifkan eksplisit untuk environment dev.
- Semua endpoint memakai bearer token melalui middleware `api.token`.
- Endpoint juga memakai `tenant.context`, sehingga request harus membawa company aktif yang valid.
- Surface ini ditujukan untuk development dan smoke test, bukan payment production.

## POST `/v1/mock/payments/create`

Membayar invoice existing via mock gateway utilitas.

### Request body

```json
{
  "invoice_id": "550e8400-e29b-41d4-a716-446655440000",
  "amount": 500000,
  "payment_method": "mock_card",
  "simulate_failure": false
}
```

### Catatan kontrak

- `invoice_id` mengikuti UUID invoice runtime.
- `payment_method` menerima `mock_card`, `mock_bank`, atau `mock_ewallet`.
- Saat payment sukses, record `payments` disimpan memakai enum persistence production-like (`credit_card`, `bank_transfer`, `e_wallet`), walaupun input API tetap berbentuk `mock_*`.

### Success response

```json
{
  "success": true,
  "message": "Mock payment processed successfully",
  "data": {
    "payment": {
      "id": 456,
      "uuid": "11111111-2222-4333-8444-555555555555",
      "gateway_reference": "mock_69e6c2ab7f081",
      "status": "completed",
      "amount": 500000,
      "paid_at": "2026-04-21T00:00:00+07:00"
    },
    "invoice": {
      "id": 123,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "status": "paid",
      "paid_at": "2026-04-21T00:00:00+07:00"
    }
  }
}
```

### Error utama

- `403 MOCK_DISABLED`
- `403 FORBIDDEN` bila invoice bukan milik company aktif
- `422 VALIDATION_ERROR` bila UUID invoice tidak valid atau body tidak sesuai

## POST `/v1/mock/invoices/create-and-pay`

Membuat invoice baru lalu langsung memproses payment mock dalam satu call.

### Request body

```json
{
  "amount": 500000,
  "description": "Dev billing smoke test",
  "currency": "IDR",
  "simulate_failure": false,
  "flow_mode": "instant",
  "success_url": "http://localhost:8007/mock-payment-tester.html",
  "failure_url": "http://localhost:8007/mock-payment-tester.html"
}
```

### Success response

```json
{
  "success": true,
  "message": "Mock invoice and payment created successfully",
  "data": {
    "invoice": {
      "id": 123,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "number": "MOCK-202604210001",
      "amount": 500000,
      "status": "paid"
    },
    "payment": {
      "id": 456,
      "uuid": "11111111-2222-4333-8444-555555555555",
      "gateway_reference": "mock_69e6c2ab7f081",
      "status": "completed",
      "amount": 500000,
      "callback_token": null,
      "hosted_checkout_url": null
    },
    "subscription": {
      "id": 99,
      "status": "active",
      "activated": true
    },
    "flow": {
      "mode": "instant",
      "hosted_checkout_url": null,
      "success_redirect_url": "http://localhost:8007/mock-payment-tester.html",
      "failure_redirect_url": "http://localhost:8007/mock-payment-tester.html",
      "callback_token": null,
      "webhook": {
        "simulate_success_url": "http://localhost:8007/v1/mock/webhook/charge-succeeded",
        "requires_callback_token": false
      }
    }
  }
}
```

### Hosted mode response highlights

Jika `flow_mode=hosted`, contract berubah pada bagian berikut:

- `data.payment.status` menjadi `pending`
- `data.payment.callback_token` terisi
- `data.payment.hosted_checkout_url` terisi
- `data.flow.mode=hosted`
- `data.flow.webhook.requires_callback_token=true`

### Catatan kontrak

- Endpoint ini tetap shortcut dev, tetapi sekarang mendukung hosted simulation lokal lewat `flow_mode=hosted`.
- Response menyediakan `invoice.uuid`, `payment.uuid`, hosted checkout URL, callback token, dan webhook simulation metadata agar helper dev bisa lanjut ke action berikutnya tanpa menebak identifier.

## POST `/v1/hcm/billing/invoices/{id}/mock-hosted-checkout`

Membuka atau me-reuse hosted checkout URL mock untuk invoice tenant yang masih unpaid.

### Otorisasi dan identifier

- Memakai bearer token + tenant context seperti endpoint tenant billing lain.
- Parameter path `{id}` mengikuti numeric invoice id runtime tenant billing, bukan UUID.
- Endpoint ini hanya aktif di local environment atau saat `app.mock_payments_enabled=true`.

### Request body

Body kosong. Runtime cukup menerima `POST` tanpa payload tambahan.

### Success response

```json
{
  "success": true,
  "data": {
    "id": 123,
    "invoiceNumber": "INV-000123",
    "issueDate": "2026-04-21",
    "dueDate": "2026-04-28",
    "amountDue": 1200000,
    "isPaid": false,
    "status": "draft"
  },
  "payment": {
    "id": 456,
    "uuid": "11111111-2222-4333-8444-555555555555",
    "gateway": "mock",
    "gatewayReference": "mock_123_1713633123",
    "paymentMethod": "credit_card",
    "status": "pending",
    "amount": 1200000
  },
  "flow": {
    "mode": "hosted",
    "hostedCheckoutUrl": "http://localhost:8000/mock-hosted-payment.html?payment_uuid=11111111-2222-4333-8444-555555555555",
    "callbackToken": "generated-callback-token",
    "successRedirectUrl": "http://localhost:8000/subscription?mock_payment_status=completed&invoice_id=123",
    "failureRedirectUrl": "http://localhost:8000/subscription?mock_payment_status=failed&invoice_id=123"
  }
}
```

### Catatan kontrak

- Jika invoice sudah punya payment `pending` dengan `hosted_checkout_url`, runtime mengembalikan URL yang sama agar checkout tidak dobel.
- Jika invoice sudah `paid`, runtime menolak request dengan `422 INVOICE_ALREADY_PAID`.
- Hosted page mock memakai `payment.uuid` + `callbackToken`, lalu settlement tetap terjadi lewat `POST /v1/mock/webhook/charge-succeeded`.

### Error utama

- `403 MOCK_DISABLED`
- `403 FORBIDDEN` bila invoice bukan milik company aktif atau caller tidak lolos gate tenant admin/owner
- `422 TENANT_CONTEXT_REQUIRED` bila company aktif tidak ada
- `422 INVOICE_ALREADY_PAID` bila invoice sudah lunas
- `404 NOT_FOUND` bila invoice id tidak ditemukan pada tenant aktif

## GET `/v1/mock/test-cards`

Mengambil daftar kartu uji mock untuk UI helper atau smoke test manual.

### Success response singkat

```json
{
  "success": true,
  "data": [
    {
      "number": "4242 4242 4242 4242",
      "name": "Visa Success",
      "result": "success",
      "description": "Payment will succeed"
    }
  ]
}
```

## POST `/v1/mock/webhook/charge-succeeded`

Mensimulasikan webhook sukses untuk payment yang sudah ada.

### Request body

```json
{
  "payment_id": "11111111-2222-4333-8444-555555555555",
  "callback_token": "optional-for-instant-required-for-hosted"
}
```

### Success response

```json
{
  "success": true,
  "message": "Webhook simulated",
  "data": {
    "payment_id": 456,
    "payment_uuid": "11111111-2222-4333-8444-555555555555",
    "invoice_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "completed",
    "paid_at": "2026-04-21T00:00:00+07:00"
  }
}
```

## Known Notes

- `/mock-payment-tester.html` sekarang seharusnya memakai UUID invoice untuk tab `Pay Invoice`.
- `/mock-payment-tester.html` sekarang juga punya token generator tenant dan tombol copy token.
- `/mock-hosted-payment.html` dipakai helper untuk mensimulasikan hosted invoice URL, callback token, redirect balik, dan settlement webhook lokal.
- Jika tujuanmu adalah memvalidasi flow yang paling dekat ke payment real, utamakan endpoint tenant invoice `POST /v1/hcm/billing/invoices/{id}/mock-hosted-checkout` lalu selesaikan webhook settlement, atau minimal `POST /v1/hcm/billing/invoices/{id}/mock-pay` untuk shortcut instant pay.