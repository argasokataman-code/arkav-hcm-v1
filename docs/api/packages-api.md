# Packages API

Endpoints untuk mengelola subscription tiers dan package features dalam sistem SaaS.

## Base Path

```
/v1/saas/packages
```

## Authentication

Semua endpoints memerlukan `api.token` middleware (bearer token atau cookie).

```
Authorization: Bearer <token>
```

## Endpoints

### List Packages (Public)

```
GET /v1/saas/packages
```

Mendapatkan daftar semua package aktif dengan features.

**Response (200 OK)**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "basic",
      "name": "Basic Plan",
      "description": "Basic plan untuk startup",
      "monthlyPrice": 99000,
      "yearlyPrice": 990000,
      "billingUnit": "flat",
      "color": "#007bff",
      "sortOrder": 1,
      "activeSubscriptionsCount": 24,
      "totalSubscriptionsCount": 31,
      "features": [
        {
          "id": 1,
          "code": "employee_management",
          "name": "Employee Management",
          "limit": 50,
          "isIncluded": true,
          "isUnlimited": false
        }
      ],
      "createdAt": "2026-04-13T10:00:00Z"
    }
  ]
}
```

### Get Package Details

```
GET /v1/saas/packages/{id}
```

Mendapatkan detail package dengan semua features.

**Parameters**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| id | integer | ✓ | Package ID |

**Response (200 OK)**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "pro",
    "name": "Pro Plan",
    "description": "Professional plan",
    "monthlyPrice": 199000,
    "yearlyPrice": 1990000,
    "billingUnit": "flat",
    "status": "active",
    "color": "#28a745",
    "sortOrder": 2,
    "activeSubscriptionsCount": 80,
    "totalSubscriptionsCount": 95,
    "features": [
      {
        "id": 1,
        "code": "employee_management",
        "name": "Employee Management",
        "limit": 500,
        "isIncluded": true,
        "isUnlimited": false
      },
      {
        "id": 2,
        "code": "payroll",
        "name": "Payroll Processing",
        "limit": null,
        "isIncluded": true,
        "isUnlimited": true
      }
    ],
    "createdAt": "2026-04-13T10:00:00Z",
    "updatedAt": "2026-04-13T10:00:00Z"
  }
}
```

### Create Package (Admin Only)

```
POST /v1/saas/packages
```

Membuat package baru.

**Request**
```json
{
  "code": "enterprise",
  "name": "Enterprise Plan",
  "description": "Enterprise plan untuk perusahaan besar",
  "monthly_price": 499000,
  "yearly_price": 4990000,
  "billing_unit": "flat",
  "color": "#dc3545",
  "sort_order": 3
}
```

**Parameters**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| code | string | ✓ | Unique package code |
| name | string | ✓ | Display name |
| description | string | - | Package description |
| monthly_price | number | ✓ | Monthly price |
| yearly_price | number | ✓ | Yearly price |
| billing_unit | enum | ✓ | `flat`, `user`, `company` |
| color | string | - | Hex color code |
| sort_order | integer | - | Display order |

**Response (201 Created)**
```json
{
  "success": true,
  "data": {
    "id": 3,
    "code": "enterprise",
    "name": "Enterprise Plan",
    "description": "Enterprise plan untuk perusahaan besar",
    "monthlyPrice": 499000,
    "yearlyPrice": 4990000,
    "billingUnit": "flat",
    "color": "#dc3545",
    "sortOrder": 3,
    "createdAt": "2026-04-13T10:00:00Z"
  }
}
```

### Update Package (Admin Only)

```
PUT /v1/saas/packages/{id}
```

Mengupdate package.

**Parameters**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| code | string | - | Unique package code |
| name | string | - | Display name |
| description | string | - | Package description |
| monthly_price | number | - | Monthly price |
| yearly_price | number | - | Yearly price |
| billing_unit | enum | - | `flat`, `user`, `company` |
| status | enum | - | `active`, `inactive`, `archived` |
| color | string | - | Hex color code |
| sort_order | integer | - | Display order |

**Response (200 OK)**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "basic",
    "name": "Basic Plan Updated",
    "monthlyPrice": 109000,
    "yearlyPrice": 1090000,
    "billingUnit": "flat",
    "status": "inactive",
    "color": "#007bff",
    "sortOrder": 1,
    "updatedAt": "2026-04-13T11:00:00Z"
  }
}
```

### Delete Package (Admin Only)

```
DELETE /v1/saas/packages/{id}
```

Menghapus package (cascade delete features).

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Package deleted successfully."
}
```

## Package Add-ons

### List Add-ons

```
GET /v1/saas/package-addons
```

Mendapatkan daftar add-on aktif dengan pagination.

**Response (200 OK)**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "extra_users",
      "name": "Extra Users",
      "description": "Add more active users beyond the package limit.",
      "pricePerUnit": 25000,
      "unitName": "user / month",
      "status": "active",
      "createdAt": "2026-04-13T10:00:00Z"
    }
  ]
}
```

### Get Add-on Details

```
GET /v1/saas/package-addons/{id}
```

### Create Add-on (Admin Only)

```
POST /v1/saas/package-addons
```

**Request**
```json
{
  "code": "storage_gb",
  "name": "Storage Pack",
  "description": "Additional storage for documents",
  "price_per_unit": 12000,
  "unit_name": "GB",
  "status": "active"
}
```

### Update Add-on (Admin Only)

```
PUT /v1/saas/package-addons/{id}
```

### Delete Add-on (Admin Only)

```
DELETE /v1/saas/package-addons/{id}
```

## Features Management

### Get Package Features

```
GET /v1/saas/packages/{id}/features
```

Mendapatkan daftar features dalam package.

**Response (200 OK)**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "employee_management",
      "name": "Employee Management",
      "limit": 100,
      "isIncluded": true,
      "isUnlimited": false
    }
  ]
}
```

### Add Feature to Package (Admin Only)

```
POST /v1/saas/packages/{id}/features
```

Menambahkan feature ke package.

**Request**
```json
{
  "feature_code": "leave_management",
  "feature_name": "Leave Management",
  "limit": null
}
```

**Parameters**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| feature_code | string | ✓ | Feature code |
| feature_name | string | ✓ | Feature name |
| limit | integer | - | Feature limit (null=unlimited, 0=excluded) |

**Response (201 Created)**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "code": "leave_management",
    "name": "Leave Management",
    "limit": null,
    "isIncluded": true,
    "isUnlimited": true
  }
}
```

### Update Feature Limit (Admin Only)

```
PUT /v1/saas/packages/features/{id}
```

Mengupdate limit feature.

**Parameters**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| feature_name | string | - | Feature name |
| limit | integer | - | Feature limit |

**Response (200 OK)**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "employee_management",
    "name": "Employee Management",
    "limit": 150,
    "isIncluded": true,
    "isUnlimited": false
  }
}
```

### Delete Feature (Admin Only)

```
DELETE /v1/saas/packages/features/{id}
```

Menghapus feature dari package.

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Feature removed successfully."
}
```

## Error Responses

### 403 Forbidden (Admin Required)

```json
{
  "success": false,
  "error": {
    "code": "ADMIN_REQUIRED",
    "message": "Admin access required."
  }
}
```

### 422 Unprocessable Entity (Validation Error)

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "validation": {
      "code": ["The code field is required."],
      "name": ["The name field is required."]
    }
  }
}
```

## Feature Codes

Daftar feature codes yang tersedia:

- `employee_management` — Employee data management
- `payroll` — Payroll processing
- `attendance` — Attendance tracking
- `leave_management` — Leave request management
- `performance` — Performance management
- `training` — Training management
- `analytics` — Analytics & reporting
- `api_access` — API access
- `custom_domain` — Custom domain support
- `sso` — Single Sign-On

## Admin Check

Admin user ditentukan oleh:
1. Email `qa.login@example.com`, OR
2. Designation atau Team mengandung: admin, hr, lead, supervisor, owner

## Error Responses

### 403 Forbidden

All admin-only endpoints return `AUTH_FORBIDDEN` when user lacks permissions:

```json
{
  "success": false,
  "error": {
    "code": "AUTH_FORBIDDEN",
    "message": "Forbidden."
  }
}
```

**Note:** Error code standardized (2026-04-17) to `AUTH_FORBIDDEN` for consistency with HCM controllers and OpenAPI schema.

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
