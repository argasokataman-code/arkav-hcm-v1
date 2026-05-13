# Runtime Feature Classification

## Tujuan

Dokumen ini menjadi sumber tunggal untuk mengklasifikasikan fitur produk menjadi 3 lapis:

1. Default aplikasi (otomatis didapat saat tenant berlangganan).
2. MVP package (inti produk yang dijual sebagai baseline).
3. Add-ons (seluruh fitur di luar MVP, baik yang sudah aktif maupun kandidat runtime berikutnya).

Dokumen ini dibuat untuk mencegah feature drift, fitur hantu, dan kebingungan saat compose package.

## Sumber Kebenaran

- Mapping package runtime: [backend/config/saas_package_feature_catalog.php](backend/config/saas_package_feature_catalog.php)
- Kontrak feature vs permission: [backend/config/hcm_feature_permission_contract.php](backend/config/hcm_feature_permission_contract.php)
- Indeks fitur aktif dan status modul: [docs/features/README.md](docs/features/README.md)
- Matriks halaman aktif HCM: [docs/planning/active-hcm-templates-and-permissions.md](docs/planning/active-hcm-templates-and-permissions.md)

## Kategori 1 - Default Aplikasi

Default aplikasi adalah kemampuan baseline yang tenant dapatkan setelah subscribe, tanpa perlu add-on khusus pada package catalog.

Default yang dicakup di dokumen ini hanya surface yang memang ada di repo saat ini (berdasarkan route/docs aktif), yaitu:

1. Identity dan akses awal
- Login, session, auth me, dan tenant context resolver.
- Referensi: [docs/features/identity-auth/README.md](docs/features/identity-auth/README.md)

2. Company billing self-service baseline
- Tenant subscription page dan company invoices.
- Referensi: [docs/features/subscriptions/README.md](docs/features/subscriptions/README.md)

3. Governance tenant baseline
- User/role/permission management dan organisasi inti (department/designation/team/policy).
- Referensi: [docs/features/user-management/README.md](docs/features/user-management/README.md)
- Referensi: [docs/features/employees-organization/README.md](docs/features/employees-organization/README.md)
- Referensi: [docs/features/team-management/README.md](docs/features/team-management/README.md)
- Referensi: [docs/features/policies/README.md](docs/features/policies/README.md)

Catatan:
- Default aplikasi berfokus pada kemampuan supaya tenant bisa langsung operasional.
- Item default tidak diperlakukan sebagai add-on package catalog.

## Kategori 2 - MVP Package

MVP package saat ini mencakup 7 modul: Employee, Attendance, Leave Management, Payroll, Notifications, Billing Dashboard, dan Tax Governance.

### 2.1 Employee MVP

Feature code MVP:
- max_employees
- employee_management

Ruang lingkup bisnis:
- Direktori karyawan inti.
- Profil employee inti.
- Kontrol kuota employee aktif per paket.

Surface operasional yang terkait:
- Employee list, employee detail, employee report.
- Foundation untuk modul lain yang membutuhkan employee identity.

Di luar MVP pada domain employee (masuk add-on):
- employee_document_center
- employee_lifecycle

### 2.2 Attendance MVP

Feature code MVP:
- attendance

Ruang lingkup bisnis:
- Attendance dashboard harian.
- Punch in/out flow inti.
- Monitoring attendance dasar tenant.

Surface operasional yang terkait:
- Attendance employee.
- Attendance admin.
- Attendance report dasar.

Di luar MVP pada domain attendance (masuk add-on):
- attendance_shift_scheduling

### 2.3 Leave Management MVP

Feature code MVP:
- leave_management
- holiday_calendar

Ruang lingkup bisnis:
- Pengajuan leave inti.
- Monitoring leave list dasar tenant.

Surface operasional yang terkait:
- Leave employee.
- Leave admin.

Di luar MVP pada domain leave (masuk add-on):
- leave_approval_flow

### 2.4 Payroll MVP

Feature code MVP:
- payroll
- payroll_components
- payroll_thr

Ruang lingkup bisnis:
- Payroll run bulanan inti.
- Manajemen komponen kompensasi inti (allowance dan deduction).

Surface operasional yang terkait:
- Payroll run.
- Employee salary compensation baseline.
- Payroll component baseline.

Di luar MVP pada domain payroll (masuk add-on):
- Tidak ada pada baseline saat ini.

### 2.5 Notifications MVP

Feature code MVP:
- notifications

Ruang lingkup bisnis:
- Inbox notifikasi tenant.
- Preferensi notifikasi user.
- Delivery observability notifikasi.

Surface operasional yang terkait:
- Notifications module runtime.
- Referensi: [docs/features/notifications/README.md](docs/features/notifications/README.md)

### 2.6 Billing Dashboard MVP

Feature code MVP:
- trial_billing_dashboard

Ruang lingkup bisnis:
- Monitoring trial dan health billing tenant.
- Ringkasan invoice lifecycle untuk keputusan upgrade/renew.

Surface operasional yang terkait:
- Trial and billing dashboard module.
- Referensi: [docs/features/trial-billing-dashboard/README.md](docs/features/trial-billing-dashboard/README.md)

### 2.7 Tax Governance MVP

Feature code MVP:
- tax_governance

Ruang lingkup bisnis:
- Governance kepatuhan pajak payroll dan billing.
- Monitoring dan kontrol tax policy runtime tenant.

Surface operasional yang terkait:
- Entry tax governance via route tax employees.
- Referensi: [docs/planning/active-hcm-templates-and-permissions.md](docs/planning/active-hcm-templates-and-permissions.md)

## Kategori 3 - Add-ons

Prinsip utama:
- Semua fitur di luar mapping MVP otomatis dikategorikan add-on.
- Add-on dibagi dua kelompok: add-on aktif runtime dan add-on kandidat/ekspansi runtime.

### 3.1 Add-ons Aktif Runtime (sudah tersedia pada surface runtime)

Berasal dari package feature catalog non-MVP:

- employee_document_center
- employee_lifecycle
- attendance_shift_scheduling
- leave_approval_flow
- performance
- goal_tracking
- performance_goal_tracking
- training
- asset_management
- tickets
- overtime
- salary_components
- allowance_governance
- bpjs_governance
- spt_masa_pph21
- calendar_events
- promotion
- resignation
- termination
- data_privacy
- notes
- faq

Contoh modul add-on aktif yang sudah dipakai runtime saat ini:

1. Document Center
- Business value: penyimpanan dokumen employee dengan visibility control.
- Dokumen: [docs/features/document-center/README.md](docs/features/document-center/README.md)

2. Asset Management
- Business value: lifecycle aset perusahaan (assignment, return, monitoring).
- Dokumen: [docs/features/asset-management/README.md](docs/features/asset-management/README.md)

3. Performance and Goals
- Business value: appraisal dan objective tracking di atas baseline HR operasional.
- Dokumen: [docs/features/performance/README.md](docs/features/performance/README.md)
- Dokumen: [docs/features/goal-tracking/README.md](docs/features/goal-tracking/README.md)

4. Training
- Business value: manajemen pelatihan sebagai kapabilitas pengembangan SDM lanjutan.
- Dokumen: [docs/features/training/README.md](docs/features/training/README.md)

5. Tickets
- Business value: helpdesk internal lintas employee dan admin.
- Dokumen: [docs/features/tickets/README.md](docs/features/tickets/README.md)

### 3.2 Add-ons Kandidat atau Ekspansi Runtime

Kelompok ini adalah modul non-MVP yang sudah ada di landscape repo saat ini dan dapat dipaketkan sebagai add-on lanjutan berdasarkan status masing-masing modul.

Contoh utama:

1. AI Assistant
- Dokumen: [docs/features/ai-assistant/README.md](docs/features/ai-assistant/README.md)
- Posisi: add-on cerdas lintas intent.

2. Reporting Advanced
- Dokumen: [docs/features/reporting/README.md](docs/features/reporting/README.md)

3. Recovery Vault
- Dokumen: [docs/features/recovery-vault/README.md](docs/features/recovery-vault/README.md)

4. Domain Management
- Dokumen: [docs/features/domain-management/README.md](docs/features/domain-management/README.md)

Catatan:
- Status implementasi masing-masing add-on tetap mengikuti tracker per modul.
- Klasifikasi add-on tidak otomatis berarti siap dijual; kesiapan jual mengikuti test gate dan evidence modul terkait.

## Kategori 4 - Mapping Khusus Global Super Admin (RBAC)

Bagian ini merangkum surface yang memang khusus untuk Global Super Admin agar rule akses tidak ambigu.

### 4.1 Rule RBAC Global Super Admin

1. Rule web global-only
- Harus melewati middleware `hcm.web.global-admin`.

2. Rule API global-only
- Menggunakan salah satu pola berikut:
  - Route group middleware `hcm.api.global-admin`.
  - Controller guard `ensureGlobalHcmAdmin(...)`.
  - Controller guard eksplisit `isGlobalHcmAdmin()` dengan response `403 AUTH_FORBIDDEN`/`ADMIN_REQUIRED`.

3. Rule tenant admin biasa
- Tenant admin tidak boleh mengakses surface platform global lintas tenant.

4. Rule primary super admin
- `hcm.web.primary-super-admin` adalah scope khusus tambahan, bukan pengganti global admin.

### 4.2 Web Surface Global-Only

#### A. SaaS Hub dan Billing Platform

Source route: [backend/routes/web/saas.php](backend/routes/web/saas.php)

- `/dashboard`, `/saas-dashboard`
- `/saas/packages`, `/packages`, `/packages-grid`
- `/saas/subscriptions`
- `/saas/billing-overview`, `/saas/billing-overview/invoices/{invoice}`
- `/saas/domains`, `/domain`
- `/saas/transactions`, `/purchase-transaction`
- `/saas/invoices`, `/saas/payments`, `/saas/reports`, `/saas/reminders`
- `/saas/pricing`, `/saas/pricing/reports`
- `/companies`

Semua path di atas menggunakan middleware `hcm.web.global-admin`.

#### B. Platform Settings Global-Only

Source route: [backend/routes/web/settings.php](backend/routes/web/settings.php)

- `/notification-observability`
- `/bussiness-settings`, `/business-settings`
- `/seo-settings`, `/localization-settings`
- `/language`, `/language-web`, `/add-language`
- `/authentication-settings`
- `/ai-settings`
- `/email-settings`, `/email-template`
- `/sms-settings`, `/sms-template`, `/otp-settings`
- `/gdpr`, `/maintenance-mode`
- `/storage-settings`
- `/custom-css`, `/custom-js`
- `/platform-tax-compliance/policies`, `/platform-tax-compliance/reports`

Semua path di atas menggunakan middleware `hcm.web.global-admin`.

#### C. Platform Cronjob dan Payment Report

Source route:
- [backend/routes/web/cronjob.php](backend/routes/web/cronjob.php)
- [backend/routes/web/reports.php](backend/routes/web/reports.php)

Path global-only:
- `/cronjob` (GET/POST)
- `/cronjob-schedule` (view jadwal)
- `/payment-report`

#### D. Primary Super Admin Only (scope tambahan)

Source route: [backend/routes/web/dashboard.php](backend/routes/web/dashboard.php)

- `/activity` menggunakan middleware `hcm.web.primary-super-admin`.

Catatan:
- Ini bukan semua global admin, hanya primary super admin code-1.

### 4.3 API Surface Global-Only

#### A. SaaS Platform API (middleware route-level)

Source route: [backend/routes/api/saas.php](backend/routes/api/saas.php)

Route group global-only:
- Prefix `/v1/saas/*` di dalam `Route::middleware('hcm.api.global-admin')`.

Cakupan endpoint global-only utama:
- Subscription change approvals (`/subscription-change-requests/*`).
- Purchase transactions (`/transactions*`).
- Domain management (`/domains*`).
- Invoices (`/invoices*`).
- Payments (`/payments*`, `/payments/bulk-upload`).
- Billing overview lintas company (`/companies/billing-overview`).
- Super admin dashboard metrics (`/dashboard/*`).

#### B. Package dan Subscription admin mutation (controller-level)

Source controller:
- [backend/app/Http/Controllers/Api/PackageController.php](backend/app/Http/Controllers/Api/PackageController.php)
- [backend/app/Http/Controllers/Api/SubscriptionController.php](backend/app/Http/Controllers/Api/SubscriptionController.php)

Rule:
- Mutation package/add-on/features dan operasi subscriptions memakai check `isGlobalHcmAdmin()`.
- Bila tidak lolos, response `403 ADMIN_REQUIRED` atau mask akses sesuai contract.

#### C. Email Settings API global-only (controller-level)

Source route: [backend/routes/api/email-settings.php](backend/routes/api/email-settings.php)

Source controller: [backend/app/Http/Controllers/Api/HcmEmailSettingsController.php](backend/app/Http/Controllers/Api/HcmEmailSettingsController.php)

Rule:
- Route berada di `api.token + tenant.context`.
- Semua operasi di-guard lagi oleh `ensureGlobalHcmAdmin(...)` di controller.

Endpoint:
- `GET /v1/hcm/email-settings`
- `PUT /v1/hcm/email-settings`
- `GET /v1/hcm/email-settings/mailtrap-status`
- `POST /v1/hcm/email-settings/test-connection`
- `POST /v1/hcm/email-settings/compose`

#### D. Tax Governance platform endpoints global-only (controller-level)

Source route: [backend/routes/api/tax-governance.php](backend/routes/api/tax-governance.php)

Source controller: [backend/app/Http/Controllers/Api/HcmTaxGovernanceController.php](backend/app/Http/Controllers/Api/HcmTaxGovernanceController.php)

Rule:
- Route berada di `api.token + tenant.context`.
- Endpoint platform policy/compliance memakai check eksplisit `isGlobalHcmAdmin()`.

Kelompok endpoint global-only:
- `/v1/hcm/tax-governance/platform-billing/*`
- `/v1/hcm/tax-governance/platform-tax-compliance/*`

### 4.4 Ringkasan Rule Implementasi

1. Jika fitur lintas-tenant platform
- Wajib global-only (web: `hcm.web.global-admin`, api: `hcm.api.global-admin` atau guard controller global).

2. Jika fitur tenant-scoped operasional HCM
- Tetap tenant admin/role permission biasa; tidak otomatis global-only.

3. Jika endpoint global-only tapi route tidak pakai middleware global
- Controller wajib punya guard `ensureGlobalHcmAdmin` atau check `isGlobalHcmAdmin()`.

4. Semua deny global-only
- Gunakan response contract konsisten (`AUTH_FORBIDDEN` atau `ADMIN_REQUIRED`) agar FE mudah menangani.

### 4.5 Snapshot Wiring Runtime (2026-05-02)

Status snapshot ini mencatat hasil hardening wiring UI untuk surface Global Super Admin pada layout aktif.

1. SaaS Reports
- Status: connected.
- Perubahan: halaman memakai `layout.mainlayout` dan data diambil langsung dari endpoint reports aktif (`/v1/saas/reports/revenue`, `/v1/saas/reports/aging`, `/v1/saas/reports/churn`).
- Catatan: referensi script legacy `js/reports-management.js` dihapus karena file runtime tidak tersedia.

2. SaaS Reminders
- Status: connected.
- Perubahan: halaman reminders sekarang membaca sumber data dari `/v1/saas/invoices` dan mengeksekusi aksi kirim reminder melalui `/v1/saas/invoices/{invoice}/send-email`.
- Catatan: referensi script legacy `js/reminders-management.js` dihapus karena file runtime tidak tersedia.

3. Sidebar Variants (Global Super Admin)
- Status: aligned.
- Perubahan: menu Global Super Admin disamakan pada semua varian utama dan section source (main, horizontal, stacked, two-col + section partial) agar active matcher dan daftar menu tidak drift.

4. Duplikasi Payment Report
- Status: resolved.
- Perubahan: entri Payment Report di Website Settings (Administration) dihapus dari partial sidebar agar tidak terjadi duplikasi dengan grup Super Admin.

## Ringkasan Aturan Klasifikasi

1. Default aplikasi:
- Kapabilitas baseline tenant yang selalu ada untuk operasional dasar setelah subscribe.

2. MVP package:
- Hanya code yang masuk daftar mvp_feature_codes.

3. Add-ons:
- Semua code di luar mvp_feature_codes.
- Berlaku baik untuk add-on yang sudah live maupun yang sedang dipersiapkan.

## Dampak ke Operasional Product dan Sales

1. Product
- Scope MVP menjadi jelas dan tidak melebar diam-diam.

2. Engineering
- Compose package tidak lagi mengambil fitur liar dari data custom.

3. Sales dan Commercial
- Penawaran bisa dibedakan tegas:
  - baseline default app,
  - package MVP,
  - add-on upsell.

4. Governance
- Tidak ada fitur skip karena klasifikasi bersifat deterministic:
  - in MVP list atau otomatis add-on.
