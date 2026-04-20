# SaaS Billing Overview API (Admin)

Endpoint admin-only untuk dashboard “Trial & Billing”: daftar company berdasarkan tab **trial** vs **subscribed** (active/pending_payment), diambil dari subscription terbaru milik tiap company, lengkap dengan invoice terakhir, status email invoice terakhir, badge mismatch state, dan link ke halaman detail invoice terpisah.

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
- `subscribed` mencakup status `active` dan `pending_payment` pada subscription terbaru.
- `email.status` adalah status log email terakhir untuk invoice terakhir (`sent`, `failed`, atau `not_sent`).
- `stateBadges` menandai mismatch penting pada row, misalnya `STATE_MISMATCH` dan `INVOICE_MISSING`.
- Jika `latestInvoice` ada, row juga membawa `uuid` dan `detailUrl` untuk membuka halaman detail invoice.

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
        "amount": 199000
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
- Halaman detail mengambil data lengkap dari `GET /v1/saas/invoices/{invoice}` termasuk riwayat email penuh.

### Errors

- `401 UNAUTHORIZED`: token tidak ada/invalid
- `403 AUTH_FORBIDDEN`: non-admin mencoba akses (error code standardized 2026-04-17)
- `422 VALIDATION_ERROR`: query param invalid (mis. `tab` bukan enum)

