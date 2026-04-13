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
| status | enum | Filter by status: active, trial, inactive, expired, cancelled |
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
  "billing_cycle": "monthly",
  "amount": 199000
}
```

**Parameters**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| company_id | integer | ✓ | Company ID |
| package_id | integer | ✓ | Package ID |
| status | enum | ✓ | active, trial, inactive, expired, cancelled |
| starts_at | date | ✓ | Start date |
| ends_at | date | - | End date |
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
| status | enum | New status |
| starts_at | date | New start date |
| ends_at | date | New end date |
| auto_renew | boolean | Enable/disable auto-renewal |
| billing_cycle | enum | monthly, yearly |

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

Memperpanjang subscription yang expired.

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
