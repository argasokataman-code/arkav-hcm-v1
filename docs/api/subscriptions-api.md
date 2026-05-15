# Subscriptions API

Endpoints untuk mengelola subscription companies dalam sistem SaaS.

## Base Path

```
/v1/saas/subscriptions
```

## Authentication

Semua endpoints memerlukan bearer token **dan** hak HCM admin/global admin. Non-admin menerima `403 ADMIN_REQUIRED` untuk list, detail, dan seluruh mutasi.

```
Authorization: Bearer <token>
```

## Endpoints

Catatan identifier subscription:
- `id`: numeric legacy identifier (internal).
- `uuid`: route identifier untuk endpoint detail/update/delete/renew pada `/v1/saas/subscriptions/{subscription}`.

### List Subscriptions

```
GET /v1/saas/subscriptions
```

Mendapatkan daftar subscriptions dengan filter dan pagination.

**Query Parameters**
| Name | Type | Description |
|------|------|-------------|
| status | enum | Filter by status: active, trial, inactive, expired, cancelled, suspended |
| per_page | integer | Page size (1–100, default 15) |
| billing_cycle | enum | monthly, yearly |
| search | string | Search plan/company/package |
| company_id | integer | Filter by company |
| plan_code | string | Filter by plan code |

**Response (200 OK)**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "companyId": 1,
      "company": {
        "id": 1,
        "code": "acme",
        "name": "ACME Corp"
      },
      "packageId": "d6f8f0e7-3b2e-4f59-9ff1-1d0b3b7c5aca",
      "package": {
        "id": 1,
        "code": "pro",
        "name": "Pro Plan",
        "monthlyPrice": 199000,
        "yearlyPrice": 1990000
      },
      "planCode": "pro",
      "status": "active",
      "startsAt": "2026-04-13T00:00:00Z",
      "endsAt": "2026-05-13T00:00:00Z",
      "trialEndsAt": null,
      "autoRenew": true,
      "billingCycle": "monthly",
      "amount": 199000,
      "durationDays": 30,
      "isActive": true,
      "isInTrial": false,
      "isExpired": false,
      "createdAt": "2026-04-13T10:00:00Z",
      "updatedAt": "2026-04-13T10:00:00Z"
    }
  ],
  "pagination": {
    "total": 10,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

### Create Subscription (Admin Only)

```
POST /v1/saas/subscriptions
```

Membuat subscription baru untuk company.

**Request**
```json
{
  "company_id": "7a09df6a-c2f3-4a43-a538-fd1e2127b0dd",
  "package_uuid": "d6f8f0e7-3b2e-4f59-9ff1-1d0b3b7c5aca",
  "status": "active",
  "starts_at": "2026-04-13",
  "ends_at": "2026-05-13",
  "trial_ends_at": "2026-04-27",
  "billing_cycle": "monthly",
  "amount": 199000
}
```

**Parameters**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| company_id | string (UUID) | ✓ | Company UUID |
| package_uuid | string (UUID) | ✓ | Package UUID |
| status | enum | ✓ | active, trial, inactive, expired, cancelled, suspended |
| starts_at | date | ✓ | Start date |
| ends_at | date | ✓ if status active or trial | Subscription end date (after `starts_at`) |
| trial_ends_at | date | ✓ if status trial | Last day of trial: after `starts_at`, on or before `ends_at`; omitted or null for non-trial |
| billing_cycle | enum | ✓ | monthly, yearly |
| amount | decimal | - | Subscription amount (auto-calculated if not provided) |

**Response (201 Created)**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "companyId": 1,
    "company": {...},
    "packageId": "d6f8f0e7-3b2e-4f59-9ff1-1d0b3b7c5aca",
    "package": {...},
    "planCode": "pro",
    "status": "active",
    "startsAt": "2026-04-13T00:00:00Z",
    "endsAt": "2026-05-13T00:00:00Z",
    "billingCycle": "monthly",
    "amount": 199000,
    "createdAt": "2026-04-13T10:00:00Z"
  }
}
```

**Business Rule (422)**
- `ACTIVE_SUBSCRIPTION_ALREADY_EXISTS`: company sudah punya subscription lain berstatus `active`/`trial` yang masih berlaku (`ends_at` null atau di masa depan).
- Admin harus update record aktif yang sudah ada, bukan membuat record aktif/trial baru.

### Get Subscription Details (Admin Only)

```
GET /v1/saas/subscriptions/{id}
```

Mendapatkan detail subscription spesifik.

**Response (200 OK)**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "companyId": 1,
    ...
  }
}
```

### Update Subscription (Admin Only)

```
PUT /v1/saas/subscriptions/{id}
```

Mengupdate subscription.

Catatan reactivation manual:
- Jika status berubah dari `suspended` ke `active`, server akan otomatis menghapus jejak suspension (`suspended_at`, `suspension_reason`) dan window grace (`grace_started_at`, `grace_ends_at`).
- Server juga mencatat audit event `subscription_events.event_type = resumed` dengan reason code `SUBSCRIPTION_REACTIVATED_MANUAL_UPDATE`.
- Notifikasi email reactivation dikirim ke billing contact company (`company.owner`).

**Request Parameters**
| Name | Type | Description |
|------|------|-------------|
| package_uuid | string (UUID) | New package |
| status | enum | active, trial, inactive, expired, cancelled, suspended |
| starts_at | date | New start date |
| ends_at | date | New end date (required in effect when status is active or trial) |
| trial_ends_at | date, null | Required when resulting status is trial (after `starts_at`, on/before `ends_at`); send `null` to clear when leaving trial |
| auto_renew | boolean | Enable/disable auto-renewal |
| billing_cycle | enum | monthly, yearly |

**Errors (422)** — selain validasi Laravel: `trial_ends_at is required when status is trial`, `trial_ends_at must be after starts_at`, `trial_ends_at must be on or before ends_at`, `ends_at is required when status is active or trial`, `package_uuid` must be a valid active package UUID, `ACTIVE_SUBSCRIPTION_ALREADY_EXISTS` saat update membuat konflik dengan subscription aktif/trial lain pada company yang sama.

**Response (200 OK)**
```json
{
  "success": true,
  "data": {...}
}
```

### Delete Subscription (Admin Only)

```
DELETE /v1/saas/subscriptions/{id}
```

Membatalkan/menghapus subscription.

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Subscription cancelled successfully."
}
```

### Renew Subscription (Admin Only)

```
POST /v1/saas/subscriptions/{id}/renew
```

Memperpanjang subscription: mengaktifkan kembali (`status` → `active`), `starts_at` = sekarang (server), `ends_at` dari body. Status sumber yang didukung (sama dengan UI): **expired**, **cancelled**, **suspended**, **inactive**.

Untuk sumber status `suspended`, endpoint renew akan otomatis:
- reset `suspended_at`, `suspension_reason`, `grace_started_at`, `grace_ends_at`;
- mencatat audit event `subscription_events.event_type = resumed` dengan reason code `SUBSCRIPTION_REACTIVATED_MANUAL_RENEW`;
- mengirim email notifikasi reactivation ke billing contact company.

Guard integritas: renew akan ditolak `422 ACTIVE_SUBSCRIPTION_ALREADY_EXISTS` jika company sudah punya record lain berstatus `active`/`trial` yang masih berlaku.

**UI `/saas/subscriptions`:** (1) tombol renew pada baris memakai modal tanggal; (2) **Renew by ID** memanggil `GET /v1/saas/subscriptions/{id}` lalu `POST .../renew` bila baris tidak muncul di halaman list (filter/pagination).

**Request**
```json
{
  "ends_at": "2026-06-13"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "active",
    "startsAt": "2026-04-13T00:00:00Z",
    "endsAt": "2026-06-13T00:00:00Z",
    "trialEndsAt": null,
    ...
  }
}
```

### Tenant Plan Change Preview (Owner / Tenant Admin)

```
POST /v1/hcm/subscriptions/preview-change
```

Dry-run perubahan paket tenant (upgrade / downgrade / cancel) untuk halaman `/upgrade`.
Endpoint ini tidak menulis data request; hanya mengembalikan preview harga, aksi final,
dan tanggal efektif.

Untuk skenario negatif billing, preview juga menyertakan ringkasan anomali di payload:
- `preview.anomaly_flags` (array kode anomali)
- `preview.anomaly_details` (detail invoice/subscription terkait)
- `preview.notes` sudah menggabungkan catatan anomali agar terbaca di UI.

Kebijakan downgrade: jika subscription tenant sedang non-aktif (mis. `suspended`/`expired`)
atau ada invoice telat bayar, request downgrade tetap diizinkan, namun flag anomali tetap
ditampilkan untuk awareness tenant dan approver.

Validasi keamanan: untuk action upgrade/downgrade, `to_package_uuid` harus package
yang masih `status=active`; selain itu API akan return `422 PACKAGE_NOT_ACTIVE`.
Target package `trial` ditolak dengan `422 TRIAL_PACKAGE_NOT_ALLOWED`.

**Request**
```json
{
  "action": "upgrade",
  "to_package_uuid": "d6f8f0e7-3b2e-4f59-9ff1-1d0b3b7c5aca"
}
```

### Submit Plan Change Request (Owner / Tenant Admin)

```
POST /v1/hcm/subscriptions/change-plan
```

Mencatat request tenant ke tabel `hcm_subscription_change_requests` dengan status awal
`pending`. Satu tenant hanya boleh memiliki **satu** request pending aktif; request kedua
ditolak `409 CHANGE_REQUEST_PENDING`.

Validasi keamanan: action upgrade/downgrade hanya menerima package aktif
(`422 PACKAGE_NOT_ACTIVE`).
Target package `trial` ditolak dengan `422 TRIAL_PACKAGE_NOT_ALLOWED`.

**Request**
```json
{
  "action": "upgrade",
  "to_package_uuid": "d6f8f0e7-3b2e-4f59-9ff1-1d0b3b7c5aca",
  "notes": "Need tickets feature"
}
```

### Cancel Pending Plan Change Request (Owner / Tenant Admin)

```
POST /v1/hcm/subscriptions/cancel-change
```

Membatalkan request tenant yang masih `pending`.

**Request**
```json
{
  "id": "f87cf1ee-6649-4a38-ae77-ec0ff54be5ce"
}
```

### List Tenant Plan Change Requests (Owner / Tenant Admin)

```
GET /v1/hcm/subscriptions/change-requests
```

Mengembalikan histori request perubahan paket untuk active company context tenant.

### List All Tenant Plan Change Requests (Super Admin)

```
GET /v1/saas/subscription-change-requests?status=pending
```

List global untuk approval queue super-admin.

Catatan akses: endpoint ini sekarang **khusus primary super admin code-1**
(email harus sama dengan `config('hcm.admin_email')`). Super-admin lain
akan ditolak `403 PRIMARY_SUPER_ADMIN_REQUIRED`.

### Approve / Reject Tenant Plan Change Request (Super Admin)

```
POST /v1/saas/subscription-change-requests/{id}/approve
POST /v1/saas/subscription-change-requests/{id}/reject
```

Approve akan memindahkan status `pending -> approved`.

Perilaku apply setelah approve:
- action `downgrade` / `cancel`: diterapkan lewat `ApplySubscriptionChangeJob` (langsung jika `effective_at <= now`, atau menunggu cron jika `effective_at > now`).
- action `upgrade`: **tidak auto-apply paket** untuk mencegah bypass payment gate; aktivasi paket upgrade mengikuti alur checkout/invoice payment.

Reject akan memindahkan status `pending -> rejected`.

Catatan akses: kedua endpoint ini juga **khusus primary super admin code-1**
dan akan return `403 PRIMARY_SUPER_ADMIN_REQUIRED` untuk akun global admin lain.

## Error Responses

### 403 Forbidden

```json
{
  "success": false,
  "error": {
    "code": "ADMIN_REQUIRED",
    "message": "Admin access required."
  }
}
```

### 422 Validation Error

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "validation": {
      "company_id": ["The company_id field is required."],
      "package_uuid": ["Package does not exist."]
    }
  }
}
```

## Status Values

- `active` — Subscription sedang aktif
- `trial` — Periode trial
- `inactive` — Subscription tidak aktif
- `expired` — Subscription sudah expired
- `cancelled` — Subscription dibatalkan

## Subscription Helper Methods (Model)

```php
// Get active subscription for company
$sub = Subscription::activeForCompany($companyId);

// Check if in trial
$sub->isInTrial();

// Check if active
$sub->isActive();

// Check if expired
$sub->isExpired();

// Get calculated price
$sub->getPrice();

// Get duration in days
$sub->getDurationDays();
```

## Error Responses

### 403 Forbidden

All subscriptions endpoints return `ADMIN_REQUIRED` when user lacks permissions:

```json
{
  "success": false,
  "error": {
    "code": "ADMIN_REQUIRED",
    "message": "Admin access required."
  }
}
```

### 401 Unauthorized

Missing or invalid authentication token:

```json
{
  "success": false,
  "error": {
    "code": "UNAUTHENTICATED",
    "message": "Unauthorized."
  }
}
```

---

## Tenant Checkout Endpoint

### POST /v1/hcm/billing/checkout

Tenant self-service checkout: create or **reuse** a pending subscription + invoice for the active company context.

**Auth**: Bearer token + HCM admin role. `activeCompanyId` must be set in request context (middleware).

**Request Body**

| Field | Type | Required | Description |
|---|---|---|---|
| `package_uuid` | uuid | Yes | UUID of an active Package (not `trial`). |
| `billing_cycle` | string | Yes | `monthly` or `yearly`. |
| `billingEmail` | string | No | Optional billing e-mail override. |

**Global Dedup Guard (added 2025)**

If the company has **any** unpaid invoice (`status` in `draft`/`sent`, `is_paid=false`), the endpoint returns that existing invoice with `reused: true` — **no new invoice or subscription is created**. This prevents double-billing when a user navigates back to the checkout page after payment is already pending.

**Success Response (200)**

```json
{
  "success": true,
  "data": {
    "invoice": {
      "id": 55,
      "invoiceNumber": "INV-2025-00055",
      "issueDate": "2025-06-01",
      "dueDate": "2025-06-08",
      "amountDue": 99000,
      "isPaid": false,
      "status": "sent"
    },
    "reused": true
  }
}
```

When `reused: true`, the client must display the existing invoice and lock the creation form — user must pay first.

**Error Codes**

| Code | HTTP | When |
|---|---|---|
| `TENANT_CONTEXT_REQUIRED` | 422 | Missing `activeCompanyId` in request context. |
| `VALIDATION_ERROR` | 422 | Trial package submitted, or invalid `billing_cycle`. |
| `NOT_FOUND` | 404 | `package_uuid` not found or inactive. |

### POST /v1/hcm/billing/addons/checkout

Tenant self-service add-on checkout: create invoice add-on terpisah (tanpa ganti paket).

**Auth**: Bearer token + HCM admin role. `activeCompanyId` wajib ada dari tenant context middleware.

**Request Body**

| Field | Type | Required | Description |
|---|---|---|---|
| `addon_id` | integer | Yes* | ID add-on aktif di katalog `/v1/saas/package-addons`. |
| `addon_uuid` | uuid | No* | Alternatif identifier add-on aktif. |
| `billingEmail` | string | No | Optional billing e-mail override. |

\* `addon_id` atau `addon_uuid` wajib salah satu.

**Behavior**

- Menjaga **dedup global** yang sama dengan checkout paket: jika masih ada invoice unpaid (`draft`/`sent`), endpoint akan mengembalikan invoice existing dengan `reused: true`.
- Jika invoice baru dibuat, sistem juga membuat `purchase_transactions` dengan `transaction_type = addon` dan mengaitkan invoice lewat `purchase_transaction_id`.
- Breakdown pricing add-on disimpan di `invoice.notes.pricing_breakdown` dengan komponen add-on (mis. `addon_markup_rate`) terpisah dari checkout paket.

**Success Response (201)**

```json
{
  "success": true,
  "data": {
    "addon": {
      "id": 88,
      "uuid": "d8d78593-f8d2-4c16-9d35-b9be619b4d2e",
      "code": "asset_management",
      "name": "Asset Management",
      "pricePerUnit": 49000,
      "unitName": "tenant / month"
    },
    "transaction": {
      "id": 120,
      "code": "TXN-2026-000120",
      "status": "issued",
      "amount": 49000,
      "taxAmount": 10780,
      "totalAmount": 59780
    },
    "invoice": {
      "id": 99,
      "invoiceNumber": "INV-2026-00099",
      "issueDate": "2026-05-02",
      "dueDate": "2026-05-03",
      "amountDue": 59780,
      "isPaid": false,
      "status": "draft"
    },
    "reused": false
  }
}
```

**Error Codes**

| Code | HTTP | When |
|---|---|---|
| `TENANT_CONTEXT_REQUIRED` | 422 | Missing `activeCompanyId` in request context. |
| `VALIDATION_ERROR` | 422 | `addon_id`/`addon_uuid` tidak dikirim atau format invalid. |
| `NOT_FOUND` | 404 | Add-on tidak ditemukan atau tidak aktif. |
