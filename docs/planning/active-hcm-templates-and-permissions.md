# Template aktif HCM — indeks halaman & pandangan role / permission

Dokumen ini menjadi **peta tunggal** untuk tim: halaman Blade mana yang dianggap **produk HCM aktif** (bukan demo theme semata), modul JS/API apa yang menyangkutnya, dan **siapa yang boleh** (target perilaku). Dipakai saat planning fitur baru, audit keamanan, dan review PR.

**Mirror di Cursor:** agar agent selalu membawa konteks yang sama, isi **§2 (model role) dan §3 (tabel matriks)** juga disalin ke `.cursor/rules/role-permissions-with-features.mdc` (`alwaysApply: true`). **Ubah matriks → update kedua file** (docs + rule) dalam satu PR.

**Cara update:** setiap kali menambah route Blade baru ke menu HCM atau menambah endpoint yang dipanggil halaman tersebut, **baris tabel + dokumen fitur** (`docs/features/<fitur>/README.md` atau `USE-CASES.md`) wajib ikut diperbarui di PR yang sama.

---

## 1. Definisi “template aktif HCM”

| Kriteria | Penjelasan |
|----------|------------|
| Masuk menu HRMS / HCM | Tautan ada di `sidebar` / `header` pada blok Employees, Leave, Attendance, Payroll terkait, atau dashboard karyawan. |
| Terhubung API | Halaman memuat `api-client.js` / modul HCM dan memanggil `/v1/hcm/*` atau `/v1/identity/*` untuk data nyata. |
| Bukan katalog demo | Halaman layout/UI showcase, CRM penuh, Super Admin tenant, dll. **tidak** masuk tabel utama kecuali secara eksplisit dijadikan produk. |

**Catatan:** `login` / halaman auth adalah template aktif untuk **Identity**, bukan HCM bisnis; tetap dicantumkan di bawah agar role “anon vs authenticated” jelas.

Sejak 2026-04-25, route template out-of-scope V1 untuk Project Management/CRM demo sudah ditakeout dari surface aktif (route + menu): `deals-dashboard`, `leads-dashboard`, `todo`, `todo-list`, `kanban-view`, `projects-grid`, `projects`, `project-details`, `tasks`, `task-board`, `task-details`, `deals-grid`, `deals`, `deals-details`, `leads-grid`, `leads`, `leads-details`, `pipeline`, `analytics`.

---

## 2. Model role (target — diselaraskan ke kode bertahap)

| Role / kapabilitas | Arti singkat |
|--------------------|----------------|
| **Anonim** | Belum login; hanya halaman auth publik. |
| **Authenticated** | Punya cookie/token valid; belum tentu admin. |
| **Karyawan** | `Authenticated` dan **bukan** `hcmAdmin` (lihat `GET /v1/identity/auth/me` → `hcmAdmin`). |
| **HCM Admin** | `hcmAdmin === true` (saat ini heuristik di `User::isHcmAdmin()`; bisa diganti role DB nanti tanpa mengubah struktur tabel dokumen ini). |

**Catatan seed admin:** akun QA utama memakai `hcm.admin_email` dan tetap melihat **katalog demo/template internal** (blok sidebar “PAGES” tema, Authentication UI showcase, dll. — flag `showTemplateCatalogMenus`). Akun super admin kedua memakai `hcm.secondary_admin_email` dan diperlakukan sebagai **active-only admin** untuk **katalog demo itu saja** (bukan untuk hub SaaS). Sejak hardening 2026-04-21, **submenu Super Admin platform** (Dashboard, Companies, Trial & Billing, Subscriptions, Packages, Domain, Purchase Transaction) memakai **`$isGlobalHcmAdmin`** pada header/sidebar dan route web guard `hcm.web.global-admin`, sehingga **hanya global HCM admin** yang melihat hub SaaS lintas tenant. Tenant HCM admin tetap memakai flow billing self-service mereka sendiri (`/subscription`, `/company/invoices`) dan tetap melihat **Menu CONTENT website** (`/pages`, Blogs, Locations, Testimonials, FAQ) karena area itu memang masih memakai `$isHcmAdmin`. **`/pages`** saat ini berisi **peta halaman HCM** (indeks navigasi ke route produk; sumber data `config/hcm_portal_hub.php` + `pages-hcm-hub.js` untuk filter), bukan daftar CMS berisi nama bisnis palsu.

**Prinsip keamanan:** izin **wajib** dicek di **backend**; UI hanya menyembunyikan tombol (UX), bukan sumber kebenaran.

Sejak 2026-04-18, route web admin kritikal untuk **Reports** dan **Administration Settings** (contoh: `/expenses-report`, `/daily-report`, `/business-settings`, `/payment-gateways`, `/cronjob`, `/clear-cache`) dikunci dengan middleware `hcm.web.admin` agar tidak hanya bergantung pada visibilitas menu.

Sejak 2026-04-21 (hardening lanjutan), **Website Settings platform** (`/business-settings` ≡ `/bussiness-settings`, `/seo-settings`, `/localization-settings`) dan **Financial Settings platform** (`/currencies`, `/payment-gateways`) dipindah ke middleware **`hcm.web.global-admin`**. Link menu Website Settings/Financial Settings yang mengarah ke item platform ini dibungkus `@if ($isGlobalHcmAdmin)` di semua layout sidebar + header. `/tax-rates` tetap tenant-admin karena dipakai payroll Indonesia.

Sejak 2026-04-21 (hardening pass 3), **Website Settings platform lanjutan** (`/language`, `/language-web`, `/add-language`, `/authentication-settings`, `/ai-settings`) juga dipindah ke middleware **`hcm.web.global-admin`**. Link menu Language, Authentication, dan AI Settings dibungkus `@if ($isGlobalHcmAdmin)` di semua 4 layout sidebar + header. Item `/prefixes`, `/preferences`, `/appearance` tetap tenant-admin (kustomisasi per-tenant).

Sejak 2026-04-21 (hardening pass 4), submenu **Other Settings** (`/custom-css`, `/custom-js`, `/cronjob`, `/storage-settings`, `/ban-ip-address`, `/backup`, `/clear-cache`) diselaraskan dengan guard route **`hcm.web.global-admin`** di sidebar, header, dan tab Profile Settings. Tenant HCM admin tidak lagi melihat menu platform-only tersebut.

---

## 3. Matriks halaman aktif HCM (menu & wiring utama)

Legenda kolom **Target akses halaman**: siapa boleh **membuka halaman** (setelah auth guard).  
Legenda **Target API**: siapa boleh **memanggil mutasi / data sensitif** — harus selaras dengan `api-spec` + controller.

| Path (web) | Nama fitur | JS / catatan muat | Area API `/v1/hcm` (ringkas) | Target akses halaman | Target API (ringkas) | Gap / catatan |
|------------|------------|-------------------|------------------------------|----------------------|----------------------|---------------|
| `/login` (root `/`) | Login | `auth-login.js` (per halaman), `api-client.js` | `POST /v1/identity/auth/login` | Anonim | Publik login + set cookie | — |
| `/index` | Admin dashboard (classic) | `chart-*`, template | (varies; banyak demo chart) | **HCM Admin** (`hcm.web.admin`) | Non-admin → `/employee-dashboard` | Sejak 2026-04-23 dikunci `hcm.web.admin`; sebelumnya tanpa guard |
| `/permission` | Permissions catalog (administrasi) | `users-management.js` / inline | `GET /v1/hcm/user-management/permissions` (via `/roles-permissions` flow) | **HCM Admin** (`hcm.web.admin`) | Lihat permission catalog (`role.view`) | Guard disamakan dengan `/roles-permissions` sejak 2026-04-23 |
| `/company/invoices` | Billing self-service tenant (daftar invoice company) | `company-invoices.js` | `GET /v1/saas/invoices?scope=company`, `GET /v1/saas/invoices/{id}` | **HCM Admin tenant** (`hcm.web.admin`) — role OWNER/ADMIN/HR_ADMIN/OPS_ADMIN di company aktif, atau Global super-admin | List + unduh invoice tenant sendiri; scope dipagari di API lewat `tenant.context` | Sejak 2026-04-23 diberi guard `hcm.web.admin`; karyawan biasa diarahkan ke `/employee-dashboard` |
| `/dashboard`, `/saas-dashboard` | Super Admin Dashboard | `super-admin-dashboard-data.js` | `GET /v1/saas/dashboard/*` | **Global HCM Admin** | **Global HCM Admin only** (`isGlobalHcmAdmin`) | Web guard + layout menu sekarang sama-sama global-admin only; analytics lanjutan aktif via API tetapi belum punya tab UI khusus |
| `/employee-dashboard` | Dashboard karyawan | template | (bila di-wire ke summary) | Authenticated | Prefer **self** data | Perlu selaraskan dengan milestone dashboard |
| `/users` | User Management | `users-management.js` | `GET /v1/hcm/user-management/users`, `GET /roles`, `POST /users/{id}/roles`, `GET /users/export` | **HCM Admin** | **HCM Admin only** (`EnsuresHcmAdmin`) | UI aktif untuk daftar user + assignment role |
| `/roles-permissions` | Role & Permissions | `users-management.js` | `GET /v1/hcm/user-management/roles`, `GET /permissions`, `POST /roles/{id}/permissions:sync` | **HCM Admin** | **HCM Admin only** (`EnsuresHcmAdmin`) | Halaman tetap tenant-admin accessible, tetapi katalog `module=system` sekarang hanya dikirim ke **global HCM admin**; tenant admin tidak lagi melihat permission System di builder role |
| `/saas/transactions` | Purchase Transactions | `purchase-transactions-data.js` | `GET/POST/PUT /transactions`, `GET /transactions/export` | **Global HCM Admin** | **Global HCM Admin only** (`isGlobalHcmAdmin`) | List/detail/export transaksi billing SaaS lintas tenant; web guard sekarang `hcm.web.global-admin` |
| `/saas/billing-overview` dan `/saas/billing-overview/invoices/{invoice}` | Trial & Billing Dashboard | `saas-billing-overview.js` | `GET /v1/saas/companies/billing-overview`, `GET /v1/saas/invoices/{invoice}`, `POST /v1/saas/invoices/{invoice}/send-email` | **Global HCM Admin** | **Global HCM Admin only** (`isGlobalHcmAdmin`) | Overview company trial/subscribed, badge mismatch state, halaman detail invoice terpisah, resend email invoice, dan review pendapatan global lintas tenant |
| *(API only)* `/v1/reconciliation/exports*` | Export Reconciliation Evidence | Belum ada halaman web final | `POST/GET /v1/reconciliation/exports`, `GET /v1/reconciliation/exports/{id}/download` | N/A | **HCM Admin only** (tenant-scoped) | Operator/admin flow internal; customer/subscriber tidak diwajibkan export sebelum aksi mereka |
| `/employees` | Daftar employee | `employees-data.js` (footer) | `GET/POST /employees`, bulk `bulk-*` | **HCM Admin** (non-admin → `/employee-dashboard`) | List/create/bulk hanya **hcmAdmin** | Selaras `USE-CASES.md` + `HcmEmployeeApiTest`; bulk sekarang strict **all-or-nothing** dan masih memakai template single-sheet |
| `/employees-grid` | Employee grid | sama | sama | **HCM Admin** | sama | — |
| `/employee-details` | Detail employee | `hcm-pages-data.js` | `GET/PUT /employees/{id}`, `GET /training/users/{id}/trainings`, `GET /promotions/users/{id}/promotions`, `GET /resignations/users/{id}/resignations`, `GET /terminations/users/{id}/terminations` | Admin: semua ID; Karyawan: **self** | `GET/PUT` by id dengan ownership di API; training/promotion/resignation/termination list per user mengikuti RBAC endpoint terkait | Detail sekarang menampilkan riwayat normalized (employment/assignment/compensation/contract/bank) + personal data lengkap |
| `/employee-report` | Laporan employee | `employees-data.js` | `GET /employees` (paginated) | **HCM Admin** | sama | Non-admin redirect |
| `/departments` | Master department | `hcm-pages-data.js` | `GET/POST/PUT/DELETE /departments` | **HCM Admin** (non-admin → `/employee-dashboard`) | Semua verb **hcmAdmin** | UI + API |
| `/designations` | Master designation | `hcm-pages-data.js` | `GET/POST/PUT/DELETE /designations` | **HCM Admin** | Semua verb **hcmAdmin** | UI + API |
| `/teams` | Master team | `team-master-data.js` | `GET/POST/GET/PUT/DELETE /teams` | **HCM Admin** | Semua verb **hcmAdmin** (`team.manage`, fallback transisi: `employee.manage`) | Team lead assignment opsional via `team_lead_id`; target reassign ke team inactive ditolak (`TEAM_INACTIVE_NOT_ASSIGNABLE`) |
| `/teams/{id}/members` | Detail anggota per team | `team-master-data.js` (mode members) | `GET /teams/{id}/members` | Authenticated (deep-link) | **HCM Admin** atau **team lead team tersebut** | Team lead wajib punya permission `team.lead` **dan** ownership (`team_lead_id == user login`) |
| `/policy` | Policies | `hcm-pages-data.js` | `GET/POST/PUT/DELETE /policies` | **HCM Admin** | Semua verb **hcmAdmin** | UI + API |
| `/ticket-master` | Master Ticket | `tickets-data.js` | `GET/POST/PUT/DELETE /tickets/categories`, `GET /tickets/category-options` | **HCM Admin** | Semua verb kategori **hcmAdmin** | Master kategori dipakai create ticket |
| `/tickets-admin` | Tickets (Admin) | `tickets-data.js` | `GET/POST /tickets`, `PUT/DELETE /tickets/{id}` | **HCM Admin** (non-admin → `/tickets-employee`) | Admin all scope + manage status/assign/SLA | Jalur utama operasional admin |
| `/tickets-employee` | Tickets (Employee) | `tickets-data.js` | `GET/POST /tickets`, `GET /tickets/{id}` | Karyawan + Admin | Karyawan own scope; admin boleh lihat | Jalur utama operasional karyawan |
| `/tickets-grid` | Tickets grid | `tickets-data.js` | `GET /tickets` | Karyawan + Admin | scope mengikuti role | Shared create modal |
| `/ticket-details/{id}` | Ticket detail | `tickets-data.js` | `PUT/DELETE /tickets/{id}`, comments/attachments/history | Karyawan + Admin | Karyawan own + lock saat `closed`; Admin assign/status/delete | Resource-oriented URL |
| `/holidays` | Hari libur | `hcm-extras-data.js` | `GET/POST/PUT/DELETE /holidays`, `POST /holidays/sync-indonesia` | **HCM Admin** (non-admin → `/employee-dashboard`) | Semua verb **hcmAdmin** (`EnsuresHcmAdmin`) | UI cek `me.hcmAdmin`; sync baseline nasional + override manual |
| `/leaves` | Cuti (admin) | `hcm-extras-data.js` | `GET/POST/PUT/DELETE /leave-requests` | **HCM Admin** (non-admin di-redirect ke `/leaves-employee`) | List semua + approve/decline hanya **hcmAdmin**; `POST` dengan `userId` hanya **hcmAdmin** | UI mengikuti `GET /auth/me` → `hcmAdmin` |
| `/leaves-employee` | Cuti (karyawan) | `hcm-extras-data.js` | `leave-requests?scope=me` | Karyawan + Admin | **Self** untuk non-admin | — |
| `/leave-settings` | Pengaturan cuti | `leave-settings-data.js` | `GET/PUT/POST/DELETE /leave-settings/*` | **HCM Admin** (non-admin → `/employee-dashboard`) | Semua verb **hcmAdmin** (`EnsuresHcmAdmin`) | UI cek `me.hcmAdmin` |
| `/leave-type` | Katalog leave type | inline script pada halaman `leave-type.blade.php` | `GET/POST/PUT/DELETE /leave-types` | **HCM Admin** (`hcm.web.admin`) | Semua verb **hcmAdmin** (`EnsuresHcmAdmin`) | Sumber katalog `hcm_leave_type_settings`; dipakai juga oleh `GET /leave-type-options` |
| `/email-settings` | Email Settings | inline script pada `email-settings.blade.php` + modal di `components/modal-popup.blade.php` | `GET/PUT /email-settings`, `POST /email-settings/test-connection`, `GET /email-settings/mailtrap-status` | **Global HCM Admin** (`hcm.web.global-admin`) | Semua endpoint email settings runtime sekarang **global-only** (`ensureGlobalHcmAdmin`) | API profile + test-connection aktif; wiring UI form masih belum selesai |
| `/email-template` | Email Templates | halaman + modal di `email-template.blade.php` / `components/modal-popup.blade.php` | (belum ada endpoint runtime khusus) | **Global HCM Admin** (`hcm.web.global-admin`) | N/A (CRUD template belum ter-wire API) | Katalog template masih statis (UI template) |
| `/cronjob` | Cronjob scheduler configuration | form Blade pada `cronjob.blade.php` | Web form `GET/POST /cronjob` (persist ke `settings` group `cronjob`) | **HCM Admin** (non-admin → `lock-screen`) | Mutasi konfigurasi cronjob **hcmAdmin** | Konfigurasi jadwal tidak hardcoded lagi; dipakai oleh Laravel scheduler (`Kernel` + `routes/console.php`) |
| `/attendance-admin` | Absensi admin | `attendance-data.js` | `attendance/admin`, timesheets, schedule | **HCM Admin** | Data semua karyawan | — |
| `/attendance-employee` | Absensi karyawan | `attendance-data.js` + **Leaflet** (unpkg) + OSM | `attendance/me/*` | Karyawan + Admin | **Self** punch untuk non-admin; punch wajib GPS | — |
| `/timesheets` | Timesheet | `attendance-data.js` | `GET /timesheets` | **HCM Admin** | Admin view | — |
| `/schedule-timing` | Jadwal / shift per user | `attendance-data.js` | `schedule-timing`, `shifts`, `smart-attendance-shifting/generate` | **HCM Admin** | Mutasi jadwal orang lain + generate rekomendasi jadwal cerdas tenant-scope | — |
| `/shift-master` | Master shift | `shift-master-data.js` | `GET/POST/PUT/DELETE /shifts` | **HCM Admin** | Sudah `EnsuresHcmAdmin` di controller | — |
| `/overtime-master` | Master overtime | `overtime-master-data.js` | `overtime-types` | **HCM Admin** | Mutasi admin | — |
| `/overtime` | Overtime (admin) | `hcm-extras-data.js` | `overtime-requests`, `calculate` | **HCM Admin** | Semua request + kalkulator dengan pilih karyawan | Non-admin diarahkan ke `/overtime-employee` |
| `/overtime-employee` | Overtime (karyawan) | `hcm-extras-data.js` | `overtime-requests?scope=me`, `calculate` | Karyawan | Hanya data sendiri; kalkulator manual (tanpa dropdown karyawan) | — |
| `/salary-component-master` | Master komponen gaji | `salary-component-master-data.js` | `GET/POST/PUT/DELETE /salary-components` | **HCM Admin** (`hcm.web.admin`) | **hcmAdmin** | Halaman admin aktif untuk CRUD master `hcm_salary_components`; dipakai juga sebagai sumber taut di `/payroll` |
| `/payroll` | Payroll items (katalog + CRUD) | `payroll-items-data.js` | `GET/POST/PUT/DELETE /payroll-items`; `GET /payroll-items/export`; `GET /salary-components?isActive=1` (opsi taut) | **HCM Admin** (`hcm.web.admin`) | **hcmAdmin** | Satu layar untuk komponen payroll; taut/kustom + export CSV/XLSX |
| `/payroll-overtime` | Payroll — lembur (filter tanggal + link absensi admin) | `payroll-overtime-data.js` | `GET /overtime-requests` (query `workDate`, `status`, …), `GET /overtime-types` | **HCM Admin** (`hcm.web.admin`) | Sama | Tanggal selaras `?date=` dengan `/attendance-admin`; daftar lembur per hari kerja |
| `/payroll-deduction` | Payroll — potongan | `payroll-items-data.js` | `GET/POST/PUT/DELETE /payroll-items?kind=deduction`, `GET /payroll-items/export`, `GET /salary-components?isActive=1` | **HCM Admin** (`hcm.web.admin`) | **hcmAdmin** | Filter `kind=deduction`; modal sama `/payroll` + export |
| `/payroll-thr` | THR — pengaturan per tahun + estimasi + mass disburse/post | `payroll-thr-data.js`, `thr-payroll-batch.js` | `GET/PUT /payroll/thr-settings/{calendarYear}`, `POST /payroll/thr-calculate`, `GET /payroll/thr-batch`, `POST /payroll/thr-batch/generate`, `POST /payroll/thr-batch/disburse`, `POST /payroll/thr-batch/post-payroll`, `POST /payroll/thr-batch/send-slip`, `GET /payroll/thr-batch/lines/{line}/slip` (admin) | **HCM Admin** (`hcm.web.admin`) | **hcmAdmin** (batch); karyawan: slip lewat **`GET /payroll/my-thr-slip`** + unduhan PDF **`GET /payroll/thr-batch/lines/{line}/slip`** (self) — tidak ada halaman web terpisah | Disburse gateway (stub) → slip PDF → post run `purpose=thr`; gate reconciliation bila aktif hanya untuk admin/operator pada action batch |
| `/payroll-pkwt-compensation` | PKWT compensation — preview karyawan jatuh tempo per bulan + kalkulator cepat | `pkwt-compensation-data.js` | `GET /payroll/pkwt-compensations?periodYear&periodMonth`, `POST /payroll/pkwt-calculate` | **HCM Admin** (`hcm.web.admin`) | **hcmAdmin** | Daftar karyawan PKWT yang `contract_end_date` jatuh pada bulan terpilih; memakai data kontrak dari profil karyawan |
| `/payroll-run` | Payroll — Run Bulanan (periode aktif) | `payroll-run.ts` | `GET /payroll-periods/active`, `POST /payroll-periods/calculate-draft`, `POST /payroll-runs/{id}/finalize`, `POST /payroll-runs/{id}/disburse` | **HCM Admin** (`hcm.web.admin`) | **hcmAdmin** | UI terkunci ke periode aktif; histori dipisah ke halaman `/payroll-run-history`; saat gate reconciliation aktif, kewajiban export hanya untuk admin/operator |
| `/payroll-run-history` | History Monthly Payroll | `payroll-run-history-data.js` | `GET /payroll-runs/history`, `GET /payroll-runs/{id}` | **HCM Admin** (`hcm.web.admin`) | **hcmAdmin** | Daftar historis run + detail audit trail |
| `/employee-salary` | Gaji karyawan (kompensasi) | `employee-salary-data.js` (footer) | `GET /employees` (list + filter), `PUT /employees/{id}` (baseSalary, fixedAllowance, contractType, contractStartDate, contractEndDate), `GET /payroll-items` (opsi assignment), `GET/POST/PUT/DELETE /payroll-item-assignments` (assignment payroll item per karyawan) | **HCM Admin** (`hcm.web.admin`) | Mutasi kompensasi + assignment custom **hcmAdmin** | Kontrak di UI ini sekarang distandardkan ke `pkwt` / `pkwtt`; assignment custom aktif ikut masuk draft payroll bulanan |
| `/payslip` | Slip gaji mandiri | `payslip-data.js` | `GET /payroll/my-slip-latest-period`, `GET /payroll/my-slip?periodYear&periodMonth`, `GET /payroll/my-slip-pdf?periodYear&periodMonth` | Authenticated | **Self** — hanya slip milik pemanggil; data muncul jika ada run **finalized** untuk bulan tersebut | UI sudah live: filter periode, ringkasan earnings/deductions/net pay, tombol unduh PDF, fallback awal ke periode slip terbaru; user `hcmAdmin` diarahkan ke `/payslip-report` agar tidak salah konteks self vs report |
| `/attendance-report` | Laporan absensi | `attendance-data.js` | timesheets / admin | **HCM Admin** | — | — |
| `/leave-report` | Laporan cuti | `hcm-extras-data.js` | `GET /leave-requests`, `GET /reports/snapshots/{id}` | **HCM Admin** (`hcm.web.admin`) | List leave tetap tenant-scoped; archive snapshot admin-only | Mode live menghitung agregat dari seluruh halaman `leave-requests`, mode archive hanya menerima snapshot `completed` bertipe `leave` |
| `/performance-indicator` | Performance Indicator (master template) | `performance-data.js` | `performance/indicator-templates`, `indicator-items` | **HCM Admin** | CRUD template+items **hcmAdmin** | — |
| `/performance-appraisal` | Performance Appraisal (cycles + create review) | `performance-data.js` | `performance/cycles`, `performance/reviews (create)` | **HCM Admin** | CRUD cycle + create review **hcmAdmin** | — |
| `/performance-review` | Performance Review (workflow) | `performance-data.js` | `performance/reviews` + submit/manager/finalize | Authenticated | Owner self draft/submit; Manager team review; Admin final/finalize | Workflow Phase 1 |
| `/goal-type` | Goal Type (master) | `goal-data.js` | `performance/goal-types` | **HCM Admin** | CRUD goal types **hcmAdmin** | List boleh semua auth (untuk dropdown/filter) |
| `/goal-tracking` | Goal Tracking | `goal-data.js` | `performance/goals`, `performance/goal-types` | Authenticated | scope: `me` (self), `team` (manager), `all` (admin) | Phase 1: export CSV dari UI |
| `/training-type` | Training Type (master) | `training-data.js` | `training/types` | **HCM Admin** | CRUD training types **hcmAdmin** | List type untuk dropdown/filter di Training |
| `/training` | Training | `training-data.js` | `training/trainings`, `training/types` | **HCM Admin** | CRUD trainings **hcmAdmin** | Phase 1: participants via employee picker modal, tenant-scoped |
| `/trainers` | Trainers (master) | `training-data.js` | `training/trainers` | **HCM Admin** | CRUD trainers **hcmAdmin** | Dipakai sebagai master trainer untuk menu training (Phase 2) |
| `/promotion` | Promotion | `promotion-data.js` | `GET/POST/PUT/DELETE /promotions` | **HCM Admin** | Semua verb **hcmAdmin** | Web: middleware `hcm.web.admin` → non-admin redirect `/employee-dashboard` |
| `/resignation` | Resignation | `resignation-data.js` | `GET/POST/PUT/DELETE /resignations` | **HCM Admin** | Semua verb **hcmAdmin** | Web: sama (`hcm.web.admin`) |
| `/termination` | Termination | `termination-data.js` | `GET/POST/PUT/DELETE /terminations`, `GET /terminations/settlement-preview`, `GET /terminations/{id}/settlement-preview`, `POST /terminations/{id}/clearance-items/{assignmentId}/return` | **HCM Admin** | Semua verb **hcmAdmin**; endpoint detail/per-user list tetap ownership-aware untuk self | Web: sama (`hcm.web.admin`); finalization sekarang punya preview settlement + action return asset dari context Termination |
| `/countries` | Locations - Provinces | - | local wilayah cache | Authenticated | Read-only local DB | Data lokal dari `wilayah.id`; tidak ada mutasi web |
| `/states` | Locations - Regencies | - | local wilayah cache | Authenticated | Read-only local DB | Data lokal dari `wilayah.id`; tidak ada mutasi web |
| `/cities` | Locations - Districts | - | local wilayah cache | Authenticated | Read-only local DB | Data lokal dari `wilayah.id`; data villages disimpan di backend |
| `/knowledgebase` | Knowledge Base (bantuan) | — (`config/hcm_knowledgebase.php`) | N/A | Authenticated | Tidak ada API | Subpath `knowledgebase/category/{slug}`, `knowledgebase/article/{slug}`; redirect legacy `knowledgebase-view` / `knowledgebase-details` bila query slug valid |

**Halaman di menu yang bukan inti HCM bisnis** (Super Admin, CRM, Applications, Layout): tidak wajib diisi di matriks ini sampai diputuskan jadi produk; jika disentuh untuk data nyata, tambahkan baris baru di tabel.

---

## 4. Hubungan dengan dokumen fitur

| Dokumen | Fungsi |
|---------|--------|
| `docs/features/README.md` | Index fitur |
| `docs/features/<fitur>/README.md` | Flow UI + endpoint + edge case |
| `docs/features/employees-organization/USE-CASES.md` | Detail UC + hak akses employee |
| `docs/planning/hcm-permission-scope-reference.md` | Katalog code permission HCM assignable + gap legacy fallback super admin |
| **Dokumen ini** | **Indeks lintas-halaman** + target role per URL |

Saat menulis use case baru untuk fitur lain, tambahkan referensi balik: “Lihat juga `planning/active-hcm-templates-and-permissions.md` baris …”.

---

## 5. Checklist untuk developer / reviewer (setiap PR yang menyentuh HCM)

- [ ] Baris tabel §3 untuk path yang berubah sudah di-update (target akses + API).
- [ ] Dokumen fitur di `docs/features/*` selaras (minimal README fitur).
- [ ] Endpoint baru punya aturan **403** untuk non-admin jika halaman admin-only.
- [ ] UI: tombol admin disembunyikan atau dinonaktifkan jika `hcmAdmin === false` (setelah `/auth/me` tersedia).
- [ ] Test feature: minimal happy path + forbidden untuk route berisiko.

### Gate eksekusi wajib sebelum task dinyatakan selesai

Jalankan berurutan (tidak boleh loncat):

1. [ ] **Role permission + use case check**
	- Verifikasi use case per role sesuai matriks §3.
	- Pastikan backend tetap jadi source of truth untuk izin (bukan UI saja).
2. [ ] **UIUX cross-check tiap role**
	- Uji minimal role HCM Admin dan Karyawan/company user pada halaman yang diubah.
	- Cek aksi, visibilitas tombol, redirect guard, empty/loading/error states, dan stabilitas modal/tabel/pagination.
3. [ ] **Manual UI E2E execution**
	- Jalankan skenario browser click-by-click di `docs/features/<feature>/E2E-TESTING.md`.
	- Catat snapshot eksekusi (tanggal, role, skenario pass/fail, catatan deviasi).

---

## 6. Riwayat singkat

- **2026-04:** Dokumen dibuat agar semua template HCM aktif punya pandangan role/permission terpusat; employee UC detail di `USE-CASES.md`.
- **2026-04-24:** Ditambahkan kontrak API observability notifikasi `GET /v1/hcm/notifications/delivery-summary` sebagai endpoint internal **global HCM admin only** untuk ringkasan delivery channel (`sent/failed/dropped`).
- **2026-04-25:** Surface template Project Management/CRM demo yang di luar scope V1 dinonaktifkan dari route + menu agar tidak tampil lintas akun.
- **2026-04-25 (revision):** Halaman `activity` dipulihkan sebagai audit trail detail khusus **primary super admin code 1** (route `hcm.web.primary-super-admin` + menu/CTA ter-wire ulang ke `activity-data.js`).
