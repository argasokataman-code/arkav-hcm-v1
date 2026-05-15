# SaaS Renewal Monitoring API (Global Super Admin)

## Overview

Dokumen ini menjelaskan kontrak endpoint monitoring renewal lintas tenant untuk kebutuhan operasional global super admin.

Fokus endpoint:

1. Ringkasan status renewal global.
2. Daftar record renewal lintas tenant.
3. Detail timeline per renewal period key.
4. Daftar anomali renewal (misalnya gateway down atau crash worker).

Catatan kebijakan gateway saat ini:
- Runtime renewal monitoring/reconciliation yang aktif di production saat ini adalah Xendit-only.
- Event Stripe legacy masih bisa muncul pada reason tertentu (contoh webhook historis), tetapi jalur reconcile periodik aktif tetap memantau payment gateway Xendit.

## Base Path

`/v1/saas/renewal-monitoring`

## Authentication

Wajib bearer token (`api.token`).

## Authorization

Wajib global admin dengan middleware route `hcm.api.global-admin`.

- Global HCM Admin: diizinkan.
- Non-global admin: `403`.

## Endpoints

### 1) GET /v1/saas/renewal-monitoring/summary

Ringkasan metrik renewal pada jendela hari tertentu.

#### Query Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| days | integer | - | Rentang data (1-90), default 30 |

#### Response 200

```json
{
  "success": true,
  "data": {
    "windowDays": 30,
    "summary": {
      "totalRecords": 120,
      "paid": 80,
      "retrying": 12,
      "gracePeriod": 10,
      "inactive": 5,
      "suspended": 1,
      "anomalies": 13
    }
  }
}
```

Catatan lifecycle:
- `inactive` dipakai untuk billing delinquency, misalnya grace period renewal berakhir tanpa pembayaran berhasil.
- `suspended` dipakai untuk enforcement non-renewal, misalnya suspend manual karena pelanggaran atau policy action.

### 2) GET /v1/saas/renewal-monitoring/records

Daftar record renewal lintas tenant dengan pagination.

#### Query Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| days | integer | - | Rentang data (1-90), default 30 |
| status | enum | - | `paid`, `pending`, `failed` |
| reason_code | string | - | Filter kode reason renewal |
| company_id | integer | - | Filter company legacy id |
| page | integer | - | Minimum 1 |
| per_page | integer | - | 1-100, default 20 |

#### Response 200 (shape)

```json
{
  "success": true,
  "data": [
    {
      "renewalPeriodKey": "sub_99_2026_05",
      "invoice": {
        "id": 501,
        "uuid": "4db7b0b3-6e0f-4f31-8f07-8f0e8b29a1e6",
        "number": "INV-000501",
        "issueDate": "2026-05-14",
        "dueDate": "2026-05-21",
        "amountDue": 250000,
        "status": "sent",
        "isPaid": false
      },
      "company": {
        "id": 10,
        "uuid": "7a3926de-6960-4b95-8af2-2f35ce7c7ab6",
        "code": "ACME",
        "name": "ACME Corp"
      },
      "subscription": {
        "id": 99,
        "uuid": "a528d149-4ff1-43d9-b074-46b10f6f6fc8",
        "status": "grace_period",
        "billingCycle": "monthly",
        "graceStartedAt": "2026-05-12T08:00:00Z",
        "graceEndsAt": "2026-05-15T08:00:00Z"
      },
      "reason": {
        "code": "RENEWAL_RETRY_SCHEDULED",
        "message": "Retry scheduled in 24 hours."
      }
    }
  ],
  "pagination": {
    "total": 1,
    "per_page": 20,
    "current_page": 1,
    "last_page": 1
  }
}
```

### 3) GET /v1/saas/renewal-monitoring/records/{renewalPeriodKey}

Detail satu lifecycle renewal berdasarkan renewal period key.

#### Path Parameter

| Name | Type | Required | Description |
|------|------|----------|-------------|
| renewalPeriodKey | string | ✓ | Contoh: `sub_99_2026_05` |

#### Response 200 (shape)

```json
{
  "success": true,
  "data": {
    "renewalPeriodKey": "sub_99_2026_05",
    "company": {
      "id": 10,
      "uuid": "7a3926de-6960-4b95-8af2-2f35ce7c7ab6",
      "code": "ACME",
      "name": "ACME Corp"
    },
    "subscription": {
      "id": 99,
      "uuid": "a528d149-4ff1-43d9-b074-46b10f6f6fc8",
      "status": "active"
    },
    "invoice": {
      "id": 501,
      "uuid": "4db7b0b3-6e0f-4f31-8f07-8f0e8b29a1e6",
      "number": "INV-000501",
      "issueDate": "2026-05-14",
      "dueDate": "2026-05-21",
      "amountDue": 250000,
      "status": "paid",
      "isPaid": true
    },
    "reason": {
      "code": "WEBHOOK_INVOICE_PAID",
      "message": "Renewal paid from webhook."
    },
    "timeline": [
      {
        "event_type": "renewal_paid",
        "reason_code": "WEBHOOK_INVOICE_PAID",
        "reason_message": "Renewal paid from webhook.",
        "occurred_at": "2026-05-14T09:00:00Z",
        "invoice_id": 501,
        "payment_id": 789
      }
    ]
  }
}
```

#### Response 404

```json
{
  "success": false,
  "error": {
    "code": "RENEWAL_RECORD_NOT_FOUND",
    "message": "Renewal monitoring record not found."
  }
}
```

### 4) GET /v1/saas/renewal-monitoring/anomalies

Daftar anomali renewal lintas tenant.

#### Query Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| days | integer | - | Rentang data (1-90), default 30 |
| page | integer | - | Minimum 1 |
| per_page | integer | - | 1-100, default 20 |

#### Response 200 (shape)

```json
{
  "success": true,
  "data": [
    {
      "renewalPeriodKey": "sub_99_2026_05",
      "invoiceId": 501,
      "invoiceUuid": "4db7b0b3-6e0f-4f31-8f07-8f0e8b29a1e6",
      "company": {
        "id": 10,
        "uuid": "7a3926de-6960-4b95-8af2-2f35ce7c7ab6",
        "code": "ACME",
        "name": "ACME Corp"
      },
      "reasonCode": "XENDIT_DOWN",
      "reasonMessage": "Xendit reconciliation unavailable.",
      "issueDate": "2026-05-14",
      "dueDate": "2026-05-21",
      "isPaid": false
    }
  ],
  "pagination": {
    "total": 1,
    "per_page": 20,
    "current_page": 1,
    "last_page": 1
  }
}
```

## Reason Code Semantics (Monitoring)

Contoh reason code yang muncul pada monitoring:

- `WEBHOOK_CHARGE_SUCCEEDED`
- `WEBHOOK_INVOICE_PAID`
- `RECONCILIATION_PAID`
- `AWAITING_GATEWAY_SETTLEMENT`
- `RENEWAL_RETRY_SCHEDULED`
- `RENEWAL_MAX_RETRY_EXCEEDED`
- `RENEWAL_GRACE_EXPIRED`
- `RENEWAL_PROCESS_EXCEPTION`
- `XENDIT_PAYMENT_FAILED`
- `XENDIT_INVOICE_EXPIRED`
- `XENDIT_DOWN`
- `STALE_INVOICE_DETECTED`
- `RENEWAL_WORKER_CRASHED`
- `FEATURE_CRASH`

Catatan: daftar ini bisa bertambah mengikuti runtime reason code baru, tetapi perubahan contract endpoint tetap di OpenAPI.

## Error Codes

- `401` Unauthorized: token tidak ada/invalid.
- `403` Forbidden: bukan global admin.
- `404` Not Found: renewal period key tidak ditemukan.
- `422` Validation Error: query/path parameter invalid.

Contoh payload 422:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": [
      "days must be between 1 and 90"
    ]
  }
}
```

## Canonical Contract

- OpenAPI canonical: `docs/api/openapi.yaml`
- Runtime route: `backend/routes/api/saas.php`
- Runtime controller: `backend/app/Http/Controllers/Api/Saas/RenewalMonitoringController.php`
