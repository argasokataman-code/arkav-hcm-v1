# SaaS Billing Overview API (Admin)

Endpoint admin-only untuk dashboard “Trial & Billing”: daftar company berdasarkan tab **trial** vs **subscribed** (active/pending_payment), diambil dari subscription terbaru milik tiap company, lengkap dengan invoice terakhir, status email invoice terakhir, badge state operasional, metadata pembatalan subscription, dan link ke halaman detail invoice terpisah.

## Base Path

```
/v1/saas/companies
```

## Authentication

Wajib `api.token` (bearer token).

## Authorization

Hanya **HCM Admin**.

## GET /v1/saas/companies/billing-overview

### Query Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| tab | enum | ✓ | `trial` atau `subscribed` |
| search | string | - | Cari `company.name` atau `company.code` |
| page | integer | - | Default Laravel pagination |
| per_page | integer | - | 1–100 (default 15) |

### Catatan perilaku

- Satu company hanya muncul sekali per tab, mengikuti subscription terbaru miliknya.
- `subscribed` mencakup status non-trial (`active`, `pending_payment`, `inactive`, `expired`, `cancelled`, `suspended`) pada subscription terbaru, ditambah company legacy yang sudah punya invoice tetapi belum punya subscription aktif.
- `email.status` adalah status log email terakhir untuk invoice terakhir (`sent`, `failed`, `not_sent`). Jika company belum punya invoice pada context tab tersebut, nilai menjadi `no_invoice`.
- Jika status subscription `cancelled`, API mengirim metadata pembatalan: `cancellationReason`, `cancellationDescription`, `cancelledAt`.
  - Nilai `cancellationReason` yang dipakai saat ini: `trial_expired`, `payment_overdue`, `tenant_request`, `system_webhook`, `manual_stop`, `seeded_demo_state`, `unknown`.
- `stateBadges` menandai kondisi operasional penting pada row, contoh saat ini: `STATE_MISMATCH`, `INVOICE_MISSING`, `PAYMENT_OVERDUE`, `TRIAL_EXPIRING_SOON`, `CANCELLED_TRIAL_EXPIRED`, `CANCELLED_PAYMENT_OVERDUE`.
- Jika `latestInvoice` ada, row juga membawa `uuid` dan `detailUrl` untuk membuka halaman detail invoice.
- Aksi pada overview dibatasi ke audit/detail invoice, kirim ulang email invoice saat `email.status = not_sent`, preview PDF (`/v1/saas/invoices/{invoice}/pdf/preview`), dan download PDF (`/v1/saas/invoices/{invoice}/pdf`). Tidak ada aksi manual mark paid dari layar overview.

### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "company": { "id": 1, "code": "acme", "name": "ACME Corp" },
      "subscription": {
        "id": 5,
        "status": "pending_payment",
        "billingCycle": "monthly",
        "startsAt": "2026-04-16T00:00:00.000000Z",
        "endsAt": "2026-04-23T00:00:00.000000Z",
        "trialEndsAt": null,
        "planCode": "pro",
        "packageId": 1,
        "packageName": "Pro Plan",
        "amount": 199000,
        "cancellationReason": null,
        "cancellationDescription": null,
        "cancelledAt": null
      },
      "latestInvoice": {
        "id": 99,
        "uuid": "d6f8f0e7-3b2e-4f59-9ff1-1d0b3b7c5aca",
        "invoiceNumber": "INV-000099",
        "issueDate": "2026-04-16",
        "dueDate": "2026-04-23",
        "amountDue": 199000,
        "isPaid": false,
        "status": "draft",
        "detailUrl": "https://example.test/saas/billing-overview/invoices/d6f8f0e7-3b2e-4f59-9ff1-1d0b3b7c5aca"
      },
      "email": {
        "status": "sent",
        "sentAt": "2026-04-16T12:00:00.000000Z",
        "lastError": null
      },
      "stateBadges": [
        {
          "code": "STATE_MISMATCH",
          "label": "State Mismatch",
          "kind": "warning",
          "message": "Invoice sudah paid tetapi subscription masih pending payment."
        }
      ]
    }
  ],
  "pagination": { "total": 1, "per_page": 15, "current_page": 1, "last_page": 1 }
}
```

### Kaitan dengan halaman detail

- Halaman list memakai `latestInvoice.detailUrl` untuk membuka halaman detail invoice terpisah di area admin.
- Halaman detail mengambil data lengkap dari `GET /v1/saas/invoices/{invoice}` termasuk riwayat email penuh, metadata pembatalan subscription, dan `stateBadges`.

## PDF Preview dan Download

- **Preview inline:** `GET /v1/saas/invoices/{invoice}/pdf/preview`
  - Response `application/pdf` dengan `Content-Disposition: inline`.
  - Dipakai tombol `View PDF` di dashboard.
- **Download file:** `GET /v1/saas/invoices/{invoice}/pdf`
  - Response download attachment.
  - Dipakai tombol `Download PDF` di dashboard.

### Errors

- `401 UNAUTHORIZED`: token tidak ada/invalid
- `403 AUTH_FORBIDDEN`: non-admin mencoba akses (error code standardized 2026-04-17)
- `422 VALIDATION_ERROR`: query param invalid (mis. `tab` bukan enum)

