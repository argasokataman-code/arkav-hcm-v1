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

Memulai hosted checkout untuk invoice tenant. Nama path tetap `mock-hosted-checkout` demi kompatibilitas frontend lama, tetapi runtime sekarang **Xendit-first**:

- Jika konfigurasi Xendit aktif, endpoint membuat hosted invoice Xendit dan mengembalikan `flow.hostedCheckoutUrl` dari `invoice_url` Xendit.
- Runtime policy checkout:
  - **Local murni (tanpa ngrok/public host):** selalu fallback ke hosted simulator lokal (mock) untuk menghindari ketergantungan gateway eksternal saat development.
  - **Ngrok/public host aktif:** mock otomatis dinonaktifkan; checkout diarahkan ke Xendit jika API key tersedia.
  - **Production/public runtime:** gunakan Xendit; mock tidak dipakai sebagai default runtime.

Request body opsional:

- `gatewayMode`: `auto` (default), `xendit`, atau `mock`.
- `paymentMethod`: `bank_transfer`, `e_wallet`, `paylater`, `qr_code`, `card` (dipakai sebagai channel hint metadata).

Contoh respons sukses (Xendit):

```json
{
  "success": true,
  "data": {
    "id": 123,
    "invoiceNumber": "INV-202605-0123"
  },
  "payment": {
    "id": 998,
    "gateway": "xendit",
    "gatewayReference": "64f2d8b1-bf1a-4c56-acde-123456789abc",
    "status": "pending"
  },
  "flow": {
    "mode": "hosted",
    "provider": "xendit",
    "hostedCheckoutUrl": "https://checkout.xendit.co/web/..."
  }
}
```

## Catatan bisnis

- Untuk invoice one-time atau invoice yang tidak berasal dari subscription aktif, field package/cycle/next billing bisa `null`.
- Untuk subscription trial, `nextBillingDate` mengikuti akhir masa trial.
- Untuk subscription active recurring, `nextBillingDate` mengikuti `subscriptions.ends_at` saat ini sebagai jadwal charge/perpanjangan berikutnya.