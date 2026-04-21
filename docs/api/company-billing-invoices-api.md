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

## Catatan bisnis

- Untuk invoice one-time atau invoice yang tidak berasal dari subscription aktif, field package/cycle/next billing bisa `null`.
- Untuk subscription trial, `nextBillingDate` mengikuti akhir masa trial.
- Untuk subscription active recurring, `nextBillingDate` mengikuti `subscriptions.ends_at` saat ini sebagai jadwal charge/perpanjangan berikutnya.