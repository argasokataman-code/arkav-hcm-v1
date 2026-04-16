# Subscriptions API

Endpoints untuk mengelola subscription companies dalam sistem SaaS.

## Base Path

```
/v1/saas/subscriptions
```

## Authentication

Semua endpoints memerlukan `api.token` middleware (bearer token).

```
Authorization: Bearer <token>
```

## Endpoints

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
      "packageId": 1,
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
  "company_id": 1,
  "package_id": 2,
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
| company_id | integer | ✓ | Company ID |
| package_id | integer | ✓ | Package ID |
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
    "packageId": 2,
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

### Get Subscription Details

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

**Request Parameters**
| Name | Type | Description |
|------|------|-------------|
| package_id | integer | New package |
| status | enum | active, trial, inactive, expired, cancelled, suspended |
| starts_at | date | New start date |
| ends_at | date | New end date (required in effect when status is active or trial) |
| trial_ends_at | date, null | Required when resulting status is trial (after `starts_at`, on/before `ends_at`); send `null` to clear when leaving trial |
| auto_renew | boolean | Enable/disable auto-renewal |
| billing_cycle | enum | monthly, yearly |

**Errors (422)** — selain validasi Laravel: `trial_ends_at is required when status is trial`, `trial_ends_at must be after starts_at`, `trial_ends_at must be on or before ends_at`, `ends_at is required when status is active or trial`.

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
      "package_id": ["Package does not exist."]
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
