# Super Admin Dashboard Implementation

## Scope Runtime Aktif

Feature ini saat ini mencakup 15 endpoint read-only untuk analytics global SaaS:

- KPI utama
- trend metrik tunggal
- daftar company
- top companies
- detail satu company
- statistik user
- retention summary user
- revenue bulanan
- revenue forecast
- revenue by plan
- subscription status breakdown
- subscription health
- custom summary report
- audit logs
- audit log detail

## Guard & Permission

- Web route `/dashboard` dan `/saas-dashboard` memakai middleware global-admin web.
- API memakai `isGlobalHcmAdmin()` di controller untuk semua endpoint.
- Tenant admin biasa, company owner, dan user tenant lain tidak boleh mengakses feature ini.

## File Runtime Penting

- `backend/routes/web.php`
- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/SuperAdminDashboardController.php`
- `backend/app/Http/Middleware/EnsureGlobalHcmWebAdminPage.php`
- `frontend/resources/js/super-admin-dashboard-data.js`
- `backend/resources/views/saas-dashboard.blade.php`

## Data Sources

- `companies`
- `subscriptions`
- `users`
- `audit_logs`
- `dashboard_metrics` untuk trend metric cache/historical data

## Perubahan Audit 2026-04-19

- perbaiki mismatch FE↔BE pada daftar company dengan menambahkan `totalRevenue` ke response list;
- backend sekarang menghormati query `per_page` pada list company;
- web guard dashboard dinaikkan dari admin tenant biasa menjadi global-admin only;
- menu “Super Admin” di seluruh varian sidebar sekarang hanya tampil untuk global admin;
- FE menambahkan fallback redirect untuk `401` dan `403 ADMIN_REQUIRED`;
- toast error sekarang escape message agar tidak merender HTML mentah;
- KPI sekarang resolve lewat `dashboard_metrics` terlebih dahulu lalu write-back fallback per jam;
- endpoint retention, forecast, subscription health, custom report, dan audit-log detail diaktifkan;
- regression test ditambah untuk web guard global-admin, cached KPI, redirect FE, dan endpoint analytics baru.

## Validasi Utama

- `php artisan test tests/Feature/SuperAdminDashboardTest.php`
- `php artisan test tests/Feature/SuperAdminCompanyUserIsolationTest.php`
- `php artisan test tests/Feature/WebHcmRouteGuardTest.php`
- `php artisan test tests/Feature/SidebarAssetMenuVisibilityTest.php`
- `npm run test:ui -- tests/ui/super-admin-dashboard-api-contract.test.js`

## Open Gaps

- OpenAPI sebelumnya belum merepresentasikan dashboard ini secara eksplisit dan perlu dijaga sinkron bila endpoint berkembang lagi;
- endpoint wishlist lain (`users/activity`, `revenue/churn`, `subscriptions/upgrades`, `reports/export`) masih belum aktif;
- UI dashboard belum punya tab khusus untuk analytics lanjutan yang baru aktif via API.