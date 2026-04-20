# Super Admin Dashboard Tracker

## Snapshot 2026-04-19

- Status: in progress
- Focus audit: truth-source docs, global-admin access guard, FE↔BE company revenue wiring, dan negative flow unauthorized access.

## Implemented

- Web route dashboard sekarang global-admin only, setara dengan API guard.
- Menu “Super Admin” di layout sekarang hanya tampil untuk global admin.
- FE dashboard sekarang redirect ke `/employee-dashboard` untuk `403 ADMIN_REQUIRED` dan ke `lock-screen` untuk sesi yang tidak valid.
- Company list API sekarang mengembalikan `totalRevenue` agar kolom revenue di UI tidak selalu `Rp 0`.
- Company list API sekarang menghormati `per_page`.
- Toast error FE sekarang escape message agar HTML mentah dari error tidak dirender.
- KPI sekarang memakai `dashboard_metrics` sebagai sumber cache utama dengan fallback write-back.
- Endpoint retention, revenue forecast, subscription health, custom report, dan audit-log detail sekarang aktif.
- README feature dan API docs diselaraskan ulang ke runtime existing.

## Evidence

- Backend feature: `backend/tests/Feature/SuperAdminDashboardTest.php`
- Tenant isolation: `backend/tests/Feature/SuperAdminCompanyUserIsolationTest.php`
- Web guard: `backend/tests/Feature/WebHcmRouteGuardTest.php`
- Sidebar visibility: `backend/tests/Feature/SidebarAssetMenuVisibilityTest.php`
- Frontend wiring: `backend/tests/ui/super-admin-dashboard-api-contract.test.js`

## Open Gaps

- Endpoint wishlist lain seperti `users/activity`, `revenue/churn`, `subscriptions/upgrades`, dan `reports/export` masih belum aktif.
- Endpoint analytics lanjutan yang baru aktif belum punya tab UI tersendiri di halaman dashboard.
- Sebagian docs lama dan asumsi produk lama masih perlu dirapikan lintas feature SaaS terkait agar tidak mengulang klaim berlebih.