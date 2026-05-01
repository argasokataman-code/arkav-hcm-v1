# Packages API

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/PackageController.php`.

## Base path

- `/v1/saas/packages`
- `/v1/saas/package-addons`

## Authentication

Semua endpoint memakai middleware `api.token`.

- Header auth: `Authorization: Bearer <token>`
- Read/list endpoint dapat diakses user bertoken non-admin.
- Operasi mutasi package, feature, dan add-on tetap admin-only.

## Packages

### GET `/packages/feature-catalog`

Behavior:
- Mengembalikan katalog fitur package yang dipakai runtime UI `/packages` saat compose/edit package.
- Source of truth utama ada di backend config catalog, lalu runtime menambahkan feature code custom yang sudah pernah tersimpan di `package_features` tetapi belum dikenali catalog bawaan.
- Endpoint ini read-only dan mencegah drift daftar fitur antara frontend dan backend.

Success `200` (ringkas):
```json
{
  "success": true,
  "data": [
    {
      "module": "assets",
      "title": "Asset Management",
      "description": "Inventaris aset dan lifecycle aset.",
      "features": [
        {
          "code": "asset_management",
          "name": "Asset Management",
          "description": "Master aset, assignment, dan stock overview.",
          "requiresLimit": false,
          "limitLabel": null,
          "limitPlaceholder": null,
          "limitSuffix": null
        }
      ]
    },
    {
      "module": "custom",
      "title": "Custom Features",
      "description": "Fitur tambahan yang terdeteksi dari konfigurasi package existing.",
      "features": [
        {
          "code": "custom_ai_workflows",
          "name": "Custom AI Workflows",
          "description": "Feature code custom yang sudah pernah dipakai package existing.",
          "requiresLimit": false,
          "limitLabel": null,
          "limitPlaceholder": null,
          "limitSuffix": null
        }
      ]
    }
  ]
}
```

### GET `/packages`

Query:
- `page` optional int, default `1`
- `per_page` optional int, default `15`, max `100`
- `status` optional enum `active|inactive|archived|all`, default logical `active`
- `search` optional string, filter `name|code|description`

Behavior:
- Return list package dengan nested features.
- `data[].id` adalah UUID package.
- `data[].isGlobalAdminOnly` menandai paket internal khusus global admin.
- `data[].activeSubscriptionsCount` menghitung subscription `active|trial`.
- `data[].totalSubscriptionsCount` menghitung seluruh histori subscription.
- Bila `status=all`, runtime tidak memfilter status.
- Caller non-global-admin tidak akan menerima package dengan `isGlobalAdminOnly=true` pada list ini.

Success `200` (ringkas):
```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "code": "basic",
      "name": "Basic Plan",
      "description": "Basic plan untuk startup",
      "monthlyPrice": 99000,
      "yearlyPrice": 990000,
      "billingUnit": "flat",
      "status": "active",
      "isGlobalAdminOnly": false,
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
  ],
  "pagination": {
    "total": 1,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

### GET `/packages/{package}`

Path:
- `package` required UUID

Behavior:
- Mengembalikan detail package + seluruh features.
- Counter subscription memakai agregat runtime yang sama dengan list endpoint.
- Jika package bertanda `isGlobalAdminOnly=true`, caller non-global-admin menerima `404 NOT_FOUND` (masked).

Success `200`:
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "code": "pro",
    "name": "Pro Plan",
    "description": "Professional plan",
    "monthlyPrice": 199000,
    "yearlyPrice": 1990000,
    "billingUnit": "flat",
    "status": "active",
    "isGlobalAdminOnly": false,
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

### POST `/packages`

RBAC:
- Admin only.

Body:
- `code` required string unique max 50
- `name` required string max 100
- `description` optional string
- `monthly_price` required numeric >= 0
- `yearly_price` required numeric >= 0
- `billing_unit` required enum `user|company|flat`
- `status` optional enum `active|inactive|archived`
- `is_global_admin_only` optional boolean (default `false`)
- `color` optional string max 7
- `sort_order` optional int >= 0

Request contoh:
```json
{
  "code": "enterprise",
  "name": "Enterprise Plan",
  "description": "Enterprise plan untuk perusahaan besar",
  "monthly_price": 499000,
  "yearly_price": 4990000,
  "billing_unit": "flat",
  "is_global_admin_only": true,
  "color": "#dc3545",
  "sort_order": 3
}
```

Success `201`:
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "code": "enterprise",
    "name": "Enterprise Plan",
    "description": "Enterprise plan untuk perusahaan besar",
    "monthlyPrice": 499000,
    "yearlyPrice": 4990000,
    "billingUnit": "flat",
    "isGlobalAdminOnly": true,
    "color": "#dc3545",
    "sortOrder": 3,
    "createdAt": "2026-04-13T10:00:00Z"
  }
}
```

### PUT `/packages/{package}`

RBAC:
- Admin only.

Path:
- `package` required UUID

Body:
- Semua field bersifat optional (`sometimes`).
- Field yang diterima sama dengan create endpoint.
- `is_global_admin_only` dapat diubah hanya oleh global admin.

Catatan UI aktif:
- Modal packages hanya mengekspos satu harga berbasis billing cycle pada satu waktu.
- Jika admin hanya mengubah metadata tanpa menyentuh input harga atau cycle, FE mempertahankan `monthly_price` dan `yearly_price` existing agar tidak terjadi rewrite diam-diam.

Success `200`:
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
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

### DELETE `/packages/{package}`

RBAC:
- Admin only.

Path:
- `package` required UUID

Guard:
- Package tidak boleh dihapus jika masih direferensikan histori subscription.
- Runtime mengembalikan `422 PACKAGE_IN_USE`, bukan 500 database.

Success `200`:
```json
{
  "success": true,
  "message": "Package deleted successfully."
}
```

## Package add-ons

### GET `/package-addons`

Query:
- `page` optional int, default `1`
- `per_page` optional int, default `15`, max `100`
- `status` optional enum `active|inactive|all`, default logical `active`
- `search` optional string, filter `name|code|description|unit_name`

Behavior:
- Return list add-on dengan pagination.
- `id` pada response adalah numeric add-on id aktif.

Success `200` (ringkas):
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
  ],
  "pagination": {
    "total": 1,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

### GET `/package-addons/{addon}`

Path:
- `addon` required identifier

Behavior:
- `{addon}` menerima numeric add-on id aktif dengan UUID fallback untuk caller transisi.
- Jika add-on tidak ditemukan, runtime mengembalikan `404 NOT_FOUND`.

### POST `/package-addons`

RBAC:
- Admin only.

Body:
- `code` required string unique
- `name` required string
- `description` optional string
- `price_per_unit` required numeric >= 0
- `unit_name` optional string
- `status` optional enum `active|inactive`

Request contoh:
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

### PUT `/package-addons/{addon}`

RBAC:
- Admin only.

Path:
- `addon` required identifier, numeric aktif dengan UUID fallback.

### DELETE `/package-addons/{addon}`

RBAC:
- Admin only.

Path:
- `addon` required identifier, numeric aktif dengan UUID fallback.

## Package features

### GET `/packages/{package}/features`

Path:
- `package` required UUID

Behavior:
- Mengembalikan daftar feature yang sudah terpasang pada package.

Success `200`:
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

### POST `/packages/{package}/features`

RBAC:
- Admin only.

Path:
- `package` required UUID

Body:
- `feature_code` required string
- `feature_name` required string
- `limit` optional integer, `null` untuk unlimited, `0` untuk disabled/excluded

Request contoh:
```json
{
  "feature_code": "leave_management",
  "feature_name": "Leave Management",
  "limit": null
}
```

Success `201`:
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

### PUT `/packages/features/{feature}`

RBAC:
- Admin only.

Path:
- `feature` required identifier, numeric feature id aktif dengan UUID fallback.

Body:
- `feature_name` optional string
- `limit` optional integer

Success `200`:
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

### DELETE `/packages/features/{feature}`

RBAC:
- Admin only.

Path:
- `feature` required identifier, numeric feature id aktif dengan UUID fallback.

Success `200`:
```json
{
  "success": true,
  "message": "Feature removed successfully."
}
```

## Error responses

### `403 ADMIN_REQUIRED`

```json
{
  "success": false,
  "error": {
    "code": "ADMIN_REQUIRED",
    "message": "Admin access required."
  }
}
```

Dipakai saat user non-admin mencoba create, update, atau delete package/add-on/feature.

### `422 PACKAGE_IN_USE`

```json
{
  "success": false,
  "error": {
    "code": "PACKAGE_IN_USE",
    "message": "Package cannot be deleted while subscription history still references it."
  }
}
```

Dipakai saat delete package ditolak karena histori subscription masih mereferensikan package.

### `422 VALIDATION_ERROR`

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

Dipakai untuk payload request yang gagal validasi.

### `404 NOT_FOUND`

```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Package addon not found."
  }
}
```

Dipakai saat identifier add-on tidak ditemukan.

## Feature codes

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

## Catatan access check

Admin user ditentukan oleh:
1. Email `qa.login@example.com`, atau
2. Designation atau team mengandung: `admin`, `hr`, `lead`, `supervisor`, `owner`.

Implementasi aktif tetap mengembalikan `ADMIN_REQUIRED` pada operasi mutasi, sehingga dokumentasi packages mengikuti kontrak runtime itu.
