# Company Billing Invoices API

## GET `/v1/hcm/billing/invoices`

Mengembalikan daftar invoice tenant milik company aktif. Mulai 2026-04-21, payload invoice tenant juga menyertakan metadata subscription agar user bisa melihat detail billing tanpa harus menebak paket atau jadwal charge berikutnya.

Field tambahan penting pada setiap item `data[]`:

- `subscriptionId`: relasi subscription jika invoice berasal dari recurring billing.
- `packageCode`: kode package billing, misalnya `starter`.
- `packageName`: nama package billing, misalnya `Starter`.
- `packageDisplay`: ringkasan siap tampil seperti `Starter - Bulanan`.
- `billingCycle`: nilai machine-readable `monthly` atau `yearly`.
- `billingCycleLabel`: label tampilan seperti `Bulanan` atau `Tahunan`.
- `currentPeriodStart`: awal periode aktif subscription saat ini.
- `currentPeriodEnd`: akhir periode aktif subscription saat ini.
- `nextBillingDate`: tanggal charge/perpanjangan berikutnya untuk recurring billing.

Contoh respons ringkas:

```json
{
  "success": true,
  "data": [
    {
      "id": 89,
      "invoiceNumber": "INV-202604-0089",
      "company": "Tenant Pro",
      "subscriptionId": 11,
      "packageCode": "starter",
      "packageName": "Starter",
      "packageDisplay": "Starter - Bulanan",
      "billingCycle": "monthly",
      "billingCycleLabel": "Bulanan",
      "currentPeriodStart": "2026-04-21",
      "currentPeriodEnd": "2026-05-21",
      "nextBillingDate": "2026-05-21",
      "amountDue": 199000,
      "issueDate": "2026-04-21",
      "dueDate": "2026-04-28",
      "status": "paid",
      "isPaid": true
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 50,
    "total": 1
  }
}
```

## GET `/v1/hcm/billing/invoices/{id}`

Mengembalikan detail satu invoice tenant untuk company aktif. Struktur `data` sama dengan item invoice pada endpoint list, sehingga UI list dan modal detail bisa menampilkan metadata package, billing cycle, dan next billing date secara konsisten.

## POST `/v1/hcm/billing/invoices/{id}/mock-hosted-checkout`

Memulai hosted checkout untuk invoice tenant. Nama path tetap `mock-hosted-checkout` demi kompatibilitas frontend lama, tetapi runtime sekarang **Midtrans Snap**:

- Jika konfigurasi Midtrans aktif (`MIDTRANS_SERVER_KEY` set), endpoint membuat transaksi Snap Midtrans dan mengembalikan `flow.snapToken` (untuk popup) dan `flow.hostedCheckoutUrl` (redirect fallback).
- Runtime policy checkout:
  - **Local/ngrok:** Midtrans Snap dipakai jika `MIDTRANS_SERVER_KEY` tersedia. Mock hanya aktif jika `APP_MOCK_PAYMENTS_ENABLED=true`.
  - **Production:** Midtrans Snap; `is_production=true` di config.
- Jika ada pending payment Midtrans yang belum expired, endpoint mengembalikan token yang sama (idempoten).

Request body opsional:

- `gatewayMode`: `auto` (default), `midtrans`, atau `mock`.
- `paymentMethod`: `bank_transfer`, `credit_card`, `gopay`, `qris` (dipakai sebagai metadata hint).

Contoh respons sukses (Midtrans Snap):

```json
{
  "success": true,
  "data": {
    "id": 123,
    "invoiceNumber": "INV-202605-0123"
  },
  "payment": {
    "id": 998,
    "gateway": "midtrans",
    "gatewayReference": "invoice-123-abc123xyz",
    "status": "pending"
  },
  "flow": {
    "mode": "hosted",
    "provider": "midtrans",
    "snapToken": "3e1555ce-4ffc-4ccb-872b-1a373278992d",
    "hostedCheckoutUrl": "https://app.sandbox.midtrans.com/snap/v4/redirection/3e1555ce-...",
    "finishRedirectUrl": "...",
    "unfinishRedirectUrl": "...",
    "errorRedirectUrl": "..."
  }
}
```

Error codes: `MIDTRANS_INIT_FAILED` (502) jika service tidak bisa diinisialisasi, `MIDTRANS_CREATE_FAILED` (502) jika API Midtrans gagal, `GATEWAY_NOT_CONFIGURED` (422) jika tidak ada gateway aktif.

## Catatan bisnis

- Untuk invoice one-time atau invoice yang tidak berasal dari subscription aktif, field package/cycle/next billing bisa `null`.
- Untuk subscription trial, `nextBillingDate` mengikuti akhir masa trial.
- Untuk subscription active recurring, `nextBillingDate` mengikuti `subscriptions.ends_at` saat ini sebagai jadwal charge/perpanjangan berikutnya.