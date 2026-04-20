# Super Admin Dashboard

## Ringkasan

Super Admin Dashboard adalah halaman analitik global SaaS untuk membaca kesehatan platform secara lintas company. Feature ini dipakai admin global untuk memantau KPI platform, revenue, subscription health, company performance, dan audit log tanpa terikat pada satu tenant tertentu.

## Akses

- Global HCM Admin / primary super admin: full access ke web route dan API dashboard global.
- Tenant admin, company owner, dan user tenant lain: tidak boleh mengakses dashboard ini.

## UI Aktif

- Halaman aktif: `/dashboard` dan `/saas-dashboard`.
- UI memuat KPI, tab Companies, revenue analytics, subscription analytics, dan audit logs.

## Flow Bisnis End-to-End

### Flow utama

1. Global admin membuka `/dashboard` atau `/saas-dashboard`.
2. Sistem memastikan user adalah global admin, bukan sekadar admin tenant.
3. FE mengambil API token dari web session lalu memanggil endpoint dashboard SaaS.
4. Sistem menampilkan KPI platform, daftar company, revenue trends, subscription status, dan audit logs.
5. Admin dapat membuka detail company atau memantau audit platform dari satu layar global.

### Exception / skenario negatif

- Tenant admin yang membuka URL manual harus diarahkan ke `/employee-dashboard`.
- Session tidak valid harus mengarah ke `lock-screen`.
- Jika API mengirim `ADMIN_REQUIRED`, FE mengarahkan user keluar dari dashboard global.
- Pesan error harus tetap di-escape agar tidak menyuntik HTML berbahaya ke UI.

## Lifecycle Dan Keputusan Bisnis

- Dashboard ini khusus analytics global SaaS, bukan dashboard operasional tenant.
- Hanya global admin yang boleh melihat agregasi lintas company karena data yang ditampilkan bersifat platform-wide.
- Endpoint wishlist yang belum aktif diperlakukan sebagai gap, bukan dianggap live.

## Integrasi

- Subscriptions dan Packages: status subscription lintas tenant, revenue by plan, dan health subscription berasal dari modul billing inti. Lihat `docs/features/subscriptions/README.md` dan `docs/features/packages/README.md`.
- Trial & Billing Dashboard: dashboard global membaca kesehatan billing platform di level agregat, sedangkan trial billing dashboard fokus ke company list operasional. Lihat `docs/features/trial-billing-dashboard/README.md`.
- Purchase Transactions dan Reporting: analytics revenue, aging, churn, dan audit platform beririsan dengan report legacy/billing data source. Lihat `docs/features/purchase-transaction/README.md` dan `docs/features/reporting/README.md`.
- User Management: statistik user global, retention, dan role hardening bergantung pada model user/authorization lintas tenant. Lihat `docs/features/user-management/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Documentation Structure

- [README.md](README.md) — flow bisnis, role, lifecycle evaluasi, gap existing vs target
- [IMPLEMENTATION.md](IMPLEMENTATION.md) — source of truth teknis, file runtime, test, dan guard
- [tracker.md](tracker.md) — snapshot audit, evidence, dan open gaps terbaru

## Ringkasan Bisnis

Super Admin Dashboard adalah halaman analitik **global SaaS** untuk melihat kesehatan platform secara lintas company. Feature ini bukan dashboard operasional per tenant dan bukan dashboard karyawan. Tujuannya adalah membantu admin global menjawab pertanyaan bisnis seperti:

- berapa total company dan user yang benar-benar aktif di platform;
- bagaimana posisi revenue bulanan dan tahunan saat ini;
- company mana yang paling berkontribusi terhadap revenue;
- apakah status subscription lintas tenant terlihat sehat;
- aksi admin sensitif apa yang terakhir terjadi di platform.

## Aktor & Role

| Aktor | Peran bisnis | Akses runtime saat ini |
|------|---------------|------------------------|
| Global HCM Admin / primary super admin | Melihat analytics lintas tenant dan audit platform | Full access ke web route dan seluruh API dashboard |
| Tenant HCM Admin / secondary admin | Mengelola tenant/company sendiri | Tidak boleh mengakses dashboard ini walau punya hak admin di tenant |
| Company owner / user tenant | Hanya melihat data tenant sendiri lewat flow lain | Tidak boleh mengakses dashboard ini |

## Status / Lifecycle

| Area | Arti bisnis | Status runtime |
|------|-------------|----------------|
| Access guard | Dashboard hanya untuk global platform admin | Aktif |
| KPI overview | Ringkasan kesehatan platform | Aktif |
| Company list & detail | Investigasi tenant tertentu | Aktif |
| Revenue by plan / monthly | Monitoring monetisasi | Aktif |
| Audit logs | Monitoring aksi sensitif admin | Aktif |
| Forecast / retention / custom report / subscription health / audit detail | Insight lanjutan investigasi | Aktif via API |

## E2E Bisnis

### Happy path

1. Global admin login.
2. Buka `/dashboard`.
3. KPI platform tampil.
4. Pindah ke tab Companies dan cek tenant yang paling besar.
5. Buka detail company untuk melihat revenue dan subscription breakdown.
6. Pindah ke tab Audit untuk mengecek aksi admin terbaru.

### Negative / abuse scenarios

- Tenant admin biasa mencoba membuka `/dashboard` langsung dengan URL manual.
	Hasil existing: server redirect ke `/employee-dashboard`.
- Company owner punya membership aktif di company dan mencoba memanggil `/v1/saas/dashboard/*`.
	Hasil existing: API balas `403 ADMIN_REQUIRED`.
- Session web habis tetapi halaman dashboard masih terbuka.
	Hasil existing: FE gagal mengambil token dan redirect ke `lock-screen`.
- Response error mengandung karakter HTML berbahaya.
	Hasil existing: FE sekarang escape toast message agar tidak menyuntik HTML mentah ke UI.

## Role & Permission Cross-check

### Halaman aktif

| Surface | Existing target role | Catatan |
|--------|-----------------------|---------|
| `/dashboard` | Global HCM Admin only | Alias ke view yang sama dengan `/saas-dashboard` |
| `/saas-dashboard` | Global HCM Admin only | Web middleware sekarang setara dengan guard API |

### Endpoint API existing

| Endpoint | Tujuan | Existing role behavior |
|----------|--------|------------------------|
| `GET /v1/saas/dashboard/kpi` | KPI utama | Global HCM Admin only |
| `GET /v1/saas/dashboard/kpi/{metricKey}` | Trend satu metrik | Global HCM Admin only |
| `GET /v1/saas/dashboard/companies` | List company + revenue | Global HCM Admin only |
| `GET /v1/saas/dashboard/companies/top-performers` | Ranking company | Global HCM Admin only |
| `GET /v1/saas/dashboard/companies/{company}/details` | Detail company | Global HCM Admin only; route model binding via UUID |
| `GET /v1/saas/dashboard/users` | Statistik user | Global HCM Admin only |
| `GET /v1/saas/dashboard/users/retention` | Retention user lintas tenant | Global HCM Admin only |
| `GET /v1/saas/dashboard/revenue/monthly` | Trend revenue 12 bulan | Global HCM Admin only |
| `GET /v1/saas/dashboard/revenue/forecast` | Forecast revenue jangka pendek | Global HCM Admin only |
| `GET /v1/saas/dashboard/revenue/by-plan` | Breakdown revenue per paket | Global HCM Admin only |
| `GET /v1/saas/dashboard/subscriptions/status` | Breakdown status subscription | Global HCM Admin only |
| `GET /v1/saas/dashboard/subscriptions/health` | Health portfolio subscription | Global HCM Admin only |
| `GET /v1/saas/dashboard/reports/custom` | Summary report dengan filter rentang tanggal | Global HCM Admin only |
| `GET /v1/saas/dashboard/audit-logs` | Audit actions | Global HCM Admin only |
| `GET /v1/saas/dashboard/audit-logs/{auditLog}` | Detail satu audit log | Global HCM Admin only; UUID + numeric legacy fallback |

## Existing Vs Target

## Kondisi Existing vs Target Bisnis

### Existing runtime yang sudah aktif

- web route sekarang terkunci untuk global admin, tidak hanya admin tenant;
- menu “Super Admin” di layout sekarang ikut tersembunyi untuk admin tenant non-global;
- FE dan BE sudah sinkron untuk menampilkan `totalRevenue` pada daftar company;
- query `per_page` pada list company sekarang dihormati backend;
- API detail company memakai UUID route binding dan sudah dites;
- dashboard punya fallback redirect untuk session invalid dan unauthorized access;
- KPI overview sekarang membaca `dashboard_metrics` lebih konsisten, dengan fallback hitung lalu write-back cache per jam;
- endpoint forecast, retention, custom report, subscription health, dan audit-log detail sekarang aktif di runtime.

### Gap yang masih terbuka

- endpoint wishlist lama lain seperti `users/activity`, `revenue/churn`, `subscriptions/upgrades`, dan `reports/export` masih belum aktif;
- UI dashboard saat ini belum punya tab khusus untuk memanggil endpoint analytics lanjutan yang baru aktif, jadi konsumsi awalnya masih lewat API/integrasi lanjutan.

### Keputusan kompromi sementara

- dokumentasi sekarang mengikuti runtime yang sudah ada, bukan wishlist lama;
- unsupported analytics dipindahkan menjadi gap/roadmap, bukan diklaim seolah sudah live;
- dashboard tetap fokus sebagai global SaaS analytics, bukan tenant dashboard.

## Sumber Kebenaran Runtime

- Web route: `backend/routes/web.php`
- API route: `backend/routes/api.php`
- Controller: `backend/app/Http/Controllers/Api/SuperAdminDashboardController.php`
- UI logic: `frontend/resources/js/super-admin-dashboard-data.js`
- View: `backend/resources/views/saas-dashboard.blade.php`

## Status

- Status audit: **in progress**
- Tracker: [tracker.md](tracker.md)
- Snapshot 2026-04-19: guard global-admin sudah benar, menu visibility sudah selaras, KPI cache lebih konsisten, dan analytics lanjutan utama sudah aktif; sisa gap sekarang ada pada endpoint wishlist tambahan dan belum adanya tab UI khusus untuk endpoint baru.
