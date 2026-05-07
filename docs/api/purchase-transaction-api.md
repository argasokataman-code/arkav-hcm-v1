# Purchase Transaction API

## Runtime Source Of Truth

Path `/v1/saas/transactions` saat ini melayani **dua contract runtime** yang sama-sama aktif:

1. **Legacy SaaS admin ledger contract** untuk halaman web aktif `/saas/transactions` dan alias `/purchase-transaction` melalui API-token cookie / same-origin web flow.
2. **Purchase transaction bearer contract** untuk consumer API yang memakai header `Authorization: Bearer <token>`.

Kontrak ini belum disatukan. Dokumentasi berikut memisahkan keduanya secara eksplisit agar audit tidak salah baca.

## Base Path

```text
/v1/saas
```

## Authentication

- Semua endpoint transaksi butuh autentikasi dan role admin.
- Flow web admin aktif memakai API-token cookie / same-origin credentials.
- Flow API terprogram memakai `Authorization: Bearer <token>`.

## Surface A: Legacy SaaS Admin Ledger Contract

Ini adalah contract yang dipakai oleh halaman aktif `backend/resources/views/saas/transactions.blade.php` dan JS `frontend/resources/js/purchase-transactions-data.js`.

### GET /transactions

List ledger transaksi untuk halaman admin aktif.

**Query parameters aktif:**

- `invoice_number` — cari invoice number
- `company_search` — cari nama company
- `status` — filter status legacy `pending|completed|failed|refunded`
- `payment_method` — filter metode bayar
- `date_from` — filter tanggal mulai (`created_at >=`)
- `date_to` — filter tanggal akhir (`created_at <=`)
- `page` — pagination
- `per_page` — pagination

**Response shape:**

```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "invoiceNumber": "INV-202604-0042",
      "subscriptionId": 12,
      "companyName": "PT Nusantara Labs",
      "packageName": "Pro",
      "amount": 150000,
      "status": "completed",
      "paymentMethod": "bank_transfer",
      "paymentGateway": "midtrans",
      "transactionId": "TRX-2026-0042",
      "notes": null,
      "createdAt": "2026-04-19T08:00:00Z",
      "updatedAt": "2026-04-19T08:05:00Z"
    }
  ],
  "pagination": {
    "total": 1,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

### GET /transactions/{transaction}

Ambil detail transaksi legacy.

- Path identifier menerima UUID atau numeric legacy fallback.
- Response memakai shape yang sama dengan list item di atas.

### POST /transactions

Create legacy transaction.

**Request body:**

```json
{
  "subscription_id": "550e8400-e29b-41d4-a716-446655440000",
  "invoice_number": "INV-TEST-001",
  "amount": 150000,
  "status": "completed",
  "payment_method": "bank_transfer",
  "payment_gateway": "midtrans",
  "transaction_id": "TRX-2026-0042",
  "notes": "Manual settlement"
}
```

**Notes:**

- `subscription_id` dikirim sebagai UUID eksternal.
- Controller sekarang meresolusikan UUID subscription ke FK integer internal sebelum insert, sehingga create tidak lagi gagal FK.

### PUT /transactions/{transaction}

Update legacy transaction status/metode bayar.

**Allowed fields:**

- `status` — `pending|completed|failed|refunded`
- `payment_method` — `credit_card|bank_transfer|e_wallet|other`
- `payment_gateway`
- `transaction_id`
- `notes`

### GET /transactions/export

Export ledger legacy dengan default Excel (`xlsx`) dan fallback `csv` via query `format`.

Query opsional:

- `format` — `xlsx` (default) | `csv`

## Surface B: Purchase Transaction Bearer Contract

Ini adalah contract yang dipakai bila request membawa Bearer token.

### GET /transactions

List purchase transactions.

**Query parameters:**

- `status` — `draft|issued|sent|paid|overdue|cancelled`
- `company_id` — UUID company, dengan numeric legacy fallback tetap diterima
- `transaction_type` — `subscription|addon|refund|credit|manual`
- `from_date` — filter tanggal mulai (`created_at >=`)
- `to_date` — filter tanggal akhir (`created_at <=`)
- `page` — pagination
- `per_page` — pagination

**Response shape:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "transactionCode": "TXN-2026-123456",
      "companyId": 10,
      "company": {
        "id": 10,
        "code": "ACME",
        "name": "ACME Corp"
      },
      "subscriptionId": 5,
      "subscription": {
        "id": 5,
        "planCode": "pro",
        "status": "active"
      },
      "packageAddonId": null,
      "packageAddon": null,
      "transactionType": "subscription",
      "description": "Monthly billing",
      "amount": 100000,
      "taxAmount": 10000,
      "discountAmount": 5000,
      "totalAmount": 105000,
      "billingPeriodStart": null,
      "billingPeriodEnd": null,
      "dueDate": null,
      "paidAt": null,
      "paymentMethod": null,
      "paymentReference": null,
      "status": "issued",
      "isPaid": false,
      "isOverdue": false,
      "notes": null,
      "createdAt": "2026-04-19T08:00:00Z",
      "updatedAt": "2026-04-19T08:00:00Z"
    }
  ],
  "pagination": {
    "total": 1,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

### GET /transactions/{transaction}

Ambil detail purchase transaction.

- Path identifier menerima UUID atau numeric legacy fallback.

### POST /transactions

Create purchase transaction baru.

**Request body:**

```json
{
  "company_id": "550e8400-e29b-41d4-a716-446655440000",
  "subscription_id": "5f28e4f7-b45f-4f73-a5d9-92bf33e765f0",
  "transaction_type": "subscription",
  "description": "Monthly subscription charge",
  "amount": 100000,
  "tax_amount": 10000,
  "discount_amount": 5000,
  "status": "issued"
}
```

**Rules yang diverifikasi:**

- `company_id`, `subscription_id`, dan `package_addon_id` memakai UUID eksternal.
- `package_addon_id` wajib bila `transaction_type=addon`.
- `subscription_id` yang berasal dari company lain sekarang ditolak dengan `422 SUBSCRIPTION_COMPANY_MISMATCH`.
- `totalAmount` dihitung otomatis sebagai `amount + taxAmount - discountAmount`.

### PUT /transactions/{transaction}

Update purchase transaction.

**Allowed fields:**

- `status` — `draft|issued|sent|paid|overdue|cancelled`
- `paid_at`
- `payment_method` — `bank_transfer|credit_card|e_wallet|cash`
- `payment_reference`
- `notes`

## Error Responses

### Admin Access Required (403)

```json
{
  "success": false,
  "error": {
    "code": "ADMIN_REQUIRED",
    "message": "Admin access required."
  }
}
```

### Subscription / Company Mismatch (422)

```json
{
  "success": false,
  "error": {
    "code": "SUBSCRIPTION_COMPANY_MISMATCH",
    "message": "Selected subscription does not belong to the selected company."
  }
}
```

### Validation Error (422)

```json
{
  "success": false,
  "errors": {
    "company_id": [
      "The company id field must be a valid UUID."
    ]
  },
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed"
  }
}
```

## Identifier Notes

- List filter `company_id` pada bearer contract: **UUID-first**, numeric legacy fallback masih diterima.
- Path parameter `{transaction}`: **UUID + numeric fallback** untuk legacy dan bearer flow.
- Request body create/update yang menarget subscription/company/add-on: **UUID eksternal**.
