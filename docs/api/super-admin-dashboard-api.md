# Super Admin Dashboard API Documentation

## Overview

The Super Admin Dashboard API provides comprehensive analytics, monitoring, and reporting capabilities for SaaS platform administrators. This enables real-time visibility into KPIs, company metrics, user activity, revenue trends, and audit logs.

**Base URI:** `/v1/saas`  
**Authentication:** Bearer Token (API Token)  
**Response Format:** JSON  
**Admin Required:** All endpoints require super admin access (qa.login@example.com)

---

## Data Models

### DashboardMetric

Cached metrics table for performance optimization (updated hourly):

```json
{
  "id": 1,
  "metricDate": "2026-04-13",
  "metricKey": "mrr",
  "metricValue": 15234.50,
  "metricMetadata": {
    "currency": "USD",
    "breakdown": {
      "basic_plan": 2000,
      "pro_plan": 8000,
      "enterprise_plan": 5234.50
    }
  },
  "calculatedAt": "2026-04-13T10:00:00Z",
  "nextCalculationAt": "2026-04-13T11:00:00Z",
  "createdAt": "2026-04-13T10:00:00Z",
  "updatedAt": "2026-04-13T10:00:00Z"
}
```

### AuditLog

User action audit trail for compliance and security:

```json
{
  "id": 1,
  "superAdminId": 1,
  "superAdminName": "Admin User",
  "action": "modify_subscription",
  "actionLabel": "Modified Subscription",
  "targetType": "subscription",
  "targetId": 42,
  "details": {
    "field": "status",
    "oldValue": "active",
    "newValue": "cancelled"
  },
  "ipAddress": "192.168.1.1",
  "createdAt": "2026-04-13T10:15:00Z"
}
```

---

## Endpoints

### KPI Management

#### 1. Get All KPIs

Retrieve all top-level KPIs in a single call.

**Endpoint:** `GET /dashboard/kpi`

**Authentication:** Required (Admin)

**Query Parameters:** None

**Example Request:**

```http
GET /v1/saas/dashboard/kpi
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "totalCompanies": 156,
    "totalUsers": 3247,
    "mrr": 15234.50,
    "arr": 182814.00,
    "activeSubscriptions": 287,
    "churnRate": 2.14,
    "customerLifetimeValue": 97.66,
    "netRevenueRetention": 105.32
  }
}
```

**KPI Definitions:**

| KPI | Description | Calculation |
|-----|-------------|-------------|
| totalCompanies | Active company count | COUNT(*) FROM companies |
| totalUsers | Active user count | COUNT(*) FROM users WHERE company_id IS NOT NULL |
| mrr | Monthly Recurring Revenue | SUM(amount) FROM subscriptions WHERE status='active' AND billing_cycle='monthly' |
| arr | Annual Recurring Revenue | MRR * 12 |
| activeSubscriptions | Count of active subscriptions | COUNT(*) FROM subscriptions WHERE status='active' |
| churnRate | Monthly churn percentage | (Cancelled this month / Active last month) * 100 |
| customerLifetimeValue | Average revenue per company | Total Revenue / Company Count |
| netRevenueRetention | Revenue retention rate | (Current month revenue / Previous month revenue) * 100 |

---

#### 2. Get Metric Trend

Get historical data for a specific metric with trend analysis.

**Endpoint:** `GET /dashboard/kpi/{metricKey}`

**Authentication:** Required (Admin)

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| metricKey | string | Metric identifier (mrr, arr, totalCompanies, etc) |

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| periods | integer | Number of historical periods (default: 12) |

**Example Request:**

```http
GET /v1/saas/dashboard/kpi/mrr?periods=12
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "metricKey": "mrr",
    "currentValue": 15234.50,
    "metadata": {
      "currency": "USD"
    },
    "trend": [
      {
        "date": "2025-05-13",
        "value": 12000.00
      },
      {
        "date": "2025-06-13",
        "value": 13500.00
      },
      {
        "date": "2025-07-13",
        "value": 15234.50
      }
    ]
  }
}
```

---

### Company Analytics

#### 3. List Companies

Retrieve paginated list of all companies with basic stats.

**Endpoint:** `GET /dashboard/companies`

**Authentication:** Required (Admin)

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| page | integer | Page number (default: 1) |
| per_page | integer | Items per page (default: 15, max: 100) |

**Example Request:**

```http
GET /v1/saas/dashboard/companies?page=1&per_page=20
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "COMP001",
      "name": "Acme Corporation",
      "email": "admin@acme.com",
      "userCount": 145,
      "subscriptionCount": 3,
      "createdAt": "2026-01-15T10:00:00Z"
    }
  ],
  "pagination": {
    "total": 156,
    "per_page": 20,
    "current_page": 1,
    "last_page": 8
  }
}
```

---

#### 4. Get Top Performing Companies

Retrieve top 10 companies ranked by revenue.

**Endpoint:** `GET /dashboard/companies/top-performers`

**Authentication:** Required (Admin)

**Query Parameters:** None

**Example Request:**

```http
GET /v1/saas/dashboard/companies/top-performers
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Enterprise Inc",
      "code": "ENT001",
      "totalRevenue": 45000.00,
      "subscriptionCount": 12,
      "avgRevenuePerSubscription": 3750.00
    }
  ]
}
```

---

#### 5. Get Company Details

Deep dive into company metrics and subscription breakdown.

**Endpoint:** `GET /dashboard/companies/{companyId}/details`

**Authentication:** Required (Admin)

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| companyId | integer | Company ID |

**Example Request:**

```http
GET /v1/saas/dashboard/companies/1/details
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "COMP001",
    "name": "Acme Corporation",
    "email": "admin@acme.com",
    "country": "US",
    "industry": "Technology",
    "userCount": 145,
    "totalRevenue": 2999.97,
    "activeSubscriptions": 3,
    "subscriptionsByStatus": {
      "active": {
        "count": 3,
        "revenue": 2999.97
      },
      "cancelled": {
        "count": 0,
        "revenue": 0
      },
      "trial": {
        "count": 1,
        "revenue": 0
      }
    },
    "createdAt": "2026-01-15T10:00:00Z"
  }
}
```

---

### User Analytics

#### 6. Get User Statistics

Get overall user metrics and verification status.

**Endpoint:** `GET /dashboard/users`

**Authentication:** Required (Admin)

**Query Parameters:** None

**Example Request:**

```http
GET /v1/saas/dashboard/users
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "totalUsers": 3247,
    "verifiedUsers": 3100,
    "unverifiedUsers": 147,
    "newUsersThisMonth": 312,
    "verificationRate": 95.48
  }
}
```

---

### Revenue Analytics

#### 7. Get Monthly Revenue Trend

Retrieve MRR trends for the last 12 months.

**Endpoint:** `GET /dashboard/revenue/monthly`

**Authentication:** Required (Admin)

**Query Parameters:** None

**Example Request:**

```http
GET /v1/saas/dashboard/revenue/monthly
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "month": "2025-06-13",
      "mrr": 12000.00
    },
    {
      "month": "2025-07-13",
      "mrr": 13500.00
    },
    {
      "month": "2025-08-13",
      "mrr": 15234.50
    }
  ]
}
```

---

#### 8. Get Revenue by Plan

Revenue breakdown by subscription plan.

**Endpoint:** `GET /dashboard/revenue/by-plan`

**Authentication:** Required (Admin)

**Query Parameters:** None

**Example Request:**

```http
GET /v1/saas/dashboard/revenue/by-plan
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "packageId": 1,
      "packageName": "Basic Plan",
      "subscriptionCount": 145,
      "revenue": 4350.00
    },
    {
      "packageId": 2,
      "packageName": "Pro Plan",
      "subscriptionCount": 98,
      "revenue": 7840.00
    },
    {
      "packageId": 3,
      "packageName": "Enterprise",
      "subscriptionCount": 44,
      "revenue": 3044.50
    }
  ]
}
```

---

### Subscription Analytics

#### 9. Get Subscription Status Breakdown

Breakdown of subscriptions by status.

**Endpoint:** `GET /dashboard/subscriptions/status`

**Authentication:** Required (Admin)

**Query Parameters:** None

**Example Request:**

```http
GET /v1/saas/dashboard/subscriptions/status
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "active": {
      "count": 287,
      "revenue": 15234.50
    },
    "trial": {
      "count": 45,
      "revenue": 0
    },
    "expired": {
      "count": 12,
      "revenue": 0
    },
    "cancelled": {
      "count": 8,
      "revenue": 0
    }
  }
}
```

---

### Audit & Compliance

#### 10. Get Audit Logs

List all super admin actions with filtering options.

**Endpoint:** `GET /dashboard/audit-logs`

**Authentication:** Required (Admin)

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| super_admin_id | integer | Filter by admin ID |
| action | string | Filter by action type |
| target_type | string | Filter by target type (company, subscription, user, etc) |
| date_from | date | Start date (YYYY-MM-DD) |
| date_to | date | End date (YYYY-MM-DD) |
| page | integer | Page number (default: 1) |
| per_page | integer | Items per page (default: 20, max: 100) |

**Example Request:**

```http
GET /v1/saas/dashboard/audit-logs?action=modify_subscription&date_from=2026-04-01&page=1
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "superAdminId": 1,
      "superAdminName": "Admin User",
      "action": "modify_subscription",
      "actionLabel": "Modified Subscription",
      "targetType": "subscription",
      "targetId": 42,
      "details": {
        "field": "status",
        "oldValue": "active",
        "newValue": "cancelled",
        "reason": "Customer requested"
      },
      "ipAddress": "192.168.1.1",
      "createdAt": "2026-04-13T10:15:00Z"
    }
  ],
  "pagination": {
    "total": 127,
    "per_page": 20,
    "current_page": 1,
    "last_page": 7
  }
}
```

**Supported Actions:**

- `view_dashboard` - Viewed dashboard
- `modify_subscription` - Modified subscription
- `delete_company` - Deleted company
- `refund_transaction` - Processed refund
- `reset_user_password` - Reset password
- `delete_user` - Deleted user
- `modify_billing` - Modified billing
- `export_report` - Exported report
- `view_audit_logs` - Viewed audit logs

---

## Error Handling

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Bad request |
| 403 | Forbidden (admin required) |
| 404 | Not found |
| 422 | Validation error |
| 500 | Server error |

### Error Response Structure

```json
{
  "success": false,
  "error": {
    "code": "ADMIN_REQUIRED",
    "message": "Admin access required."
  }
}
```

---

## Common Patterns

### Admin Authorization

All dashboard endpoints require super admin status. Users are identified as super admin if their email matches `qa.login@example.com`.

### Pagination

All list endpoints support pagination with:

- `page` — Current page number
- `per_page` — Items per page (default 15-20, typically max 100)
- Response includes: `total`, `per_page`, `current_page`, `last_page`

### Response Format

All responses follow consistent structure:

```json
{
  "success": true/false,
  "data": {},
  "pagination": {},
  "error": {},
  "message": ""
}
```

### Date Handling

- All dates returned in ISO 8601 format: `YYYY-MM-DDTHH:MM:SSZ`
- Query parameters use date format: `YYYY-MM-DD`
- Timezone: UTC

---

## Caching Strategy

Dashboard metrics are cached hourly to improve performance:

- Metrics calculated at top of each hour
- Next calculation time provided in response
- Manual recalculation can be triggered for urgent updates
- Metadata includes calculation timestamp for audit trail

---

## Performance Considerations

1. **Pagination** — Always paginate company/subscription lists for large datasets
2. **Date Ranges** — Filter audit logs by date to reduce result set
3. **Caching** — Metrics are cached; real-time data may lag by up to 1 hour
4. **Indexes** — Queries optimized with database indexes on common filters

---

## Compliance & Security

1. **Audit Trail** — All sensitive actions logged to `audit_logs` table
2. **IP Tracking** — User IP address captured with each action
3. **Sensitive Actions** — Flagged for enhanced monitoring (deletes, refunds, etc.)
4. **Admin Only** — All endpoints restricted to super admin users

---

**API Version:** 1.0  
**Last Updated:** 2026-04-13  
**Status:** Production Ready
