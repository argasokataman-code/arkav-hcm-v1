# Super Admin Dashboard API

## Overview

API ini menyediakan analytics **global SaaS** untuk super admin platform. Seluruh endpoint bersifat **read-only** dan memakai guard **global admin**, bukan admin tenant biasa.

**Base URI:** `/v1/saas/dashboard`

**Source of truth runtime:**

- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/SuperAdminDashboardController.php`

## Access Rule

- Semua endpoint memerlukan bearer token valid.
- Semua endpoint memerlukan `isGlobalHcmAdmin()`.
- Company owner / tenant admin biasa akan menerima `403 ADMIN_REQUIRED`.

## Identifier Policy

- `GET /companies/{company}/details` memakai **UUID route binding** untuk company.
- List company mengembalikan `id` legacy **dan** `uuid` agar FE bisa migrasi bertahap.
- `GET /audit-logs/{auditLog}` menerima **UUID** bila tabel audit sudah bermigrasi, dan tetap menerima **numeric legacy id** sebagai fallback kompatibilitas.

## Endpoints Aktif

### 1. KPI summary

`GET /v1/saas/dashboard/kpi`

Response fields utama:

- `totalCompanies`
- `totalUsers`
- `mrr`
- `arr`
- `activeSubscriptions`
- `churnRate`
- `customerLifetimeValue`
- `netRevenueRetention`

### 2. Metric trend

`GET /v1/saas/dashboard/kpi/{metricKey}`

Response:

- `metricKey`
- `currentValue`
- `metadata`
- `trend[]` berisi `date` dan `value`

### 3. Company list

`GET /v1/saas/dashboard/companies?page=1&per_page=20`

Query params:

- `page` minimum `1`
- `per_page` minimum `1`, maximum `100`, default `15`

Setiap item `data[]` saat ini mengembalikan:

- `id`
- `uuid`
- `code`
- `name`
- `email`
- `userCount`
- `subscriptionCount`
- `totalRevenue`
- `createdAt`

### 4. Top companies

`GET /v1/saas/dashboard/companies/top-performers`

Setiap item:

- `id`
- `name`
- `code`
- `totalRevenue`
- `subscriptionCount`
- `avgRevenuePerSubscription`

### 5. Company details

`GET /v1/saas/dashboard/companies/{companyUuid}/details`

Response utama:

- `id`
- `code`
- `name`
- `email`
- `country`
- `industry`
- `userCount`
- `totalRevenue`
- `activeSubscriptions`
- `subscriptionsByStatus`
- `createdAt`

### 6. User statistics

`GET /v1/saas/dashboard/users`

Response utama:

- `totalUsers`
- `verifiedUsers`
- `unverifiedUsers`
- `newUsersThisMonth`
- `verificationRate`

### 7. User retention

`GET /v1/saas/dashboard/users/retention`

Response utama:

- `cohortMonth`
- `previousCohortUsers`
- `retainedUsers`
- `churnedUsers`
- `newUsersThisMonth`
- `activeUsersCurrent`
- `retentionRate`

### 8. Monthly revenue

`GET /v1/saas/dashboard/revenue/monthly`

Response: array 12 bulan terakhir dengan item:

- `month` format `YYYY-MM`
- `mrr`

### 8. Revenue by plan

`GET /v1/saas/dashboard/revenue/by-plan`

Setiap item:

- `packageId`
- `packageName`
- `subscriptionCount`
- `revenue`

### 9. Revenue forecast

`GET /v1/saas/dashboard/revenue/forecast`

Response utama:

- `method`
- `history[]` berisi `month` dan `mrr`
- `forecast[]` berisi `month` dan `projectedMrr`

### 10. Subscription status breakdown

`GET /v1/saas/dashboard/subscriptions/status`

Response object keyed by status, misalnya:

- `active.count`
- `active.revenue`
- `cancelled.count`
- `cancelled.revenue`

### 11. Subscription health

`GET /v1/saas/dashboard/subscriptions/health`

Response utama:

- `healthScore`
- `totalSubscriptions`
- `activeSubscriptions`
- `expiringSoon`
- `autoRenewDisabled`
- `expiredButNotClosed`
- `breakdown`

### 12. Custom summary report

`GET /v1/saas/dashboard/reports/custom?from=2026-01-01&to=2026-03-31&group_by=status`

Query params aktif:

- `from` format `YYYY-MM-DD`, default 90 hari terakhir
- `to` format `YYYY-MM-DD`, default hari ini
- `group_by` salah satu: `month`, `plan`, `status`

Response utama:

- `filters.from`
- `filters.to`
- `filters.groupBy`
- `summary.companiesCreated`
- `summary.userMembershipsAdded`
- `summary.subscriptionsCreated`
- `summary.activeSubscriptions`
- `summary.cancelledSubscriptions`
- `summary.totalRevenue`
- `breakdown[]`

### 13. Audit logs

`GET /v1/saas/dashboard/audit-logs?action=modify_subscription&page=1&per_page=20`

Query params aktif:

- `super_admin_id`
- `action`
- `target_type`
- `date_from`
- `date_to`
- `page`
- `per_page`

Setiap item `data[]`:

- `id`
- `superAdminId`
- `superAdminName`
- `action`
- `actionLabel`
- `targetType`
- `targetId`
- `details`
- `ipAddress`
- `createdAt`

### 14. Audit log detail

`GET /v1/saas/dashboard/audit-logs/{auditLog}`

`{auditLog}` menerima UUID bila tersedia, dan numeric legacy id untuk compatibility.

Response utama:

- `id`
- `uuid`
- `superAdminId`
- `superAdminName`
- `action`
- `actionLabel`
- `targetType`
- `targetId`
- `details`
- `ipAddress`
- `userAgent`
- `isSensitiveAction`
- `createdAt`
- `updatedAt`

## Negative Scenarios Yang Sudah Ditutup

- Non-global admin mendapat `403 ADMIN_REQUIRED`.
- Web shell tanpa session valid gagal mengambil `/api-token` dan dialihkan ke `lock-screen` oleh FE.
- FE dashboard sekarang tidak lagi merender HTML mentah dari error toast message.

## Bukan Kontrak Aktif Saat Ini

Dokumen lama pernah mengklaim endpoint berikut, tetapi **masih belum aktif di runtime** dan tidak boleh diasumsikan tersedia:

- `GET /v1/saas/dashboard/users/activity`
- `GET /v1/saas/dashboard/revenue/churn`
- `GET /v1/saas/dashboard/subscriptions/upgrades`
- `GET /v1/saas/dashboard/reports/export`

## Cache & Freshness

- KPI summary sekarang akan membaca `dashboard_metrics` terlebih dahulu bila metric untuk hari berjalan masih fresh.
- Jika metric belum ada / sudah stale, controller menghitung ulang lalu menulis balik cache dengan horizon 1 jam.
- Trend endpoint tetap membaca histori dari tabel `dashboard_metrics`.

### Date Handling

- All dates returned in ISO 8601 format: `YYYY-MM-DDTHH:MM:SSZ`
- Query parameters use date format: `YYYY-MM-DD`
- Timezone: UTC

---

## Performance Considerations

1. **Pagination** — Always paginate company/subscription lists for large datasets
2. **Date Ranges** — Filter audit logs by date to reduce result set
3. **Caching** — KPI utama memakai `dashboard_metrics` dengan fallback write-back; data dapat tertinggal sampai 1 jam
4. **Indexes** — Queries optimized with database indexes on common filters

---

## Compliance & Security

1. **Audit Trail** — All sensitive actions logged to `audit_logs` table
2. **IP Tracking** — User IP address captured with each action
3. **Sensitive Actions** — Flagged for enhanced monitoring (deletes, refunds, etc.)
4. **Admin Only** — All endpoints restricted to super admin users

---

**API Version:** 1.1  
**Last Updated:** 2026-04-19  
**Status:** Production Ready
