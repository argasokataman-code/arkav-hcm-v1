# SaaS Billing Overview API (Admin)

Endpoint admin-only untuk dashboard “Trial & Billing”: daftar company berdasarkan tab **trial** vs **subscribed** (active/pending_payment), lengkap dengan invoice terakhir dan status email invoice.

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
        "invoiceNumber": "INV-000099",
        "issueDate": "2026-04-16",
        "dueDate": "2026-04-23",
        "amountDue": 199000,
        "isPaid": false,
        "status": "draft"
      },
      "email": {
        "status": "sent",
        "sentAt": "2026-04-16T12:00:00.000000Z",
        "lastError": null
      }
    }
  ],
  "pagination": { "total": 1, "per_page": 15, "current_page": 1, "last_page": 1 }
}
```

### Errors

- `401 UNAUTHORIZED`: token tidak ada/invalid
- `403 ADMIN_REQUIRED`: non-admin mencoba akses
- `422 VALIDATION_ERROR`: query param invalid (mis. `tab` bukan enum)

