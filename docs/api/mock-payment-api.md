# Mock Payment API (Development Only)

Base path utilitas dev: `/v1/mock`

## Runtime Source Of Truth

Dokumen ini hanya mencakup endpoint utilitas development-only di `/v1/mock/*`.

Untuk flow tenant invoice yang paling mendekati payment real, lihat juga:

- `POST /v1/hcm/billing/invoices/{id}/mock-pay`
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
- Jika tujuanmu adalah memvalidasi flow yang paling dekat ke payment real, utamakan endpoint tenant invoice `POST /v1/hcm/billing/invoices/{id}/mock-pay` dibanding shortcut `/v1/mock/invoices/create-and-pay`.