# Packages Status Tracker

## Snapshot 2026-05-14

- Status umum: compliance guard pada package composer sudah ditambah melalui snapshot realtime berbasis backend rules agar super admin tidak menyusun package yang melanggar baseline regulasi/PDP/dependency.

### Evidence Runtime

- Endpoint baru `GET /v1/saas/packages/check-compliance` (global admin only) mengevaluasi `feature_codes[]` dan mengembalikan snapshot terstruktur: section `regulatory`, `pdp`, `dependency`, plus summary `errors/warnings/passes`.
- `frontend/resources/js/packages-management.js` sekarang memanggil endpoint compliance snapshot setiap perubahan checklist fitur atau limit `max_employees`, lalu merender panel **Package Compliance** di modal Add/Edit.
- `backend/resources/views/saas/packages.blade.php` sekarang punya panel compliance di sisi kanan composer fitur agar warning/missing terlihat sebelum klik Save.

### Evidence Test

- `php artisan test tests/Feature/PackageServiceTest.php` (ditambah test endpoint compliance check: admin success + non-admin forbidden).

### Dokumen Yang Disinkronkan

- `docs/api/packages-api.md`
- `docs/api/openapi.yaml`
- `docs/features/packages/README.md`
- `docs/features/packages/STATUS-TRACKER.md`

### Gap Yang Masih Tersisa

- Snapshot saat ini berfungsi sebagai guard observability (warning/error) di modal, belum memblok submit package secara hard validation saat ada error compliance.

## Snapshot 2026-05-09

- Status umum: hardening katalog package ditutup dengan sinkronisasi fallback frontend ke katalog kanonik backend, plus arsip fitur non-aktif (`api_access`, `priority_support`) agar tidak lagi muncul sebagai entitlement package aktif.

### Evidence Runtime

- `frontend/resources/js/packages-management.js` sekarang menjadikan API runtime catalog sebagai sumber utama, lalu fallback derivasi dari payload package runtime (recognized feature codes only) saat endpoint catalog belum tersedia.
	- fallback statis frontend tetap dihapus (tidak ada daftar hardcoded seeder/frontend).
- Endpoint `GET /v1/saas/packages/feature-catalog` sekarang membangun katalog dari runtime repo (`hcm.api.feature:*` + `hcm.web.feature:*` pada routes) dan klasifikasi docs (`RUNTIME-FEATURE-CLASSIFICATION.md`).
- Endpoint `GET /v1/saas/packages/feature-catalog/healthcheck` ditambahkan untuk audit drift route-vs-docs (global admin only) dan ditampilkan lewat tombol **Healthcheck** di modal compose/edit package.
- Endpoint mutasi add-on (`POST/PUT /v1/saas/package-addons`) sekarang menolak `code` yang bentrok dengan namespace `feature_code` katalog runtime (`FEATURE_CODE_NAMESPACE_CONFLICT`) untuk mencegah baseline/add-on ganda.
- Endpoint list add-on (`GET /v1/saas/package-addons`) sekarang mengecualikan row add-on yang bentrok dengan namespace feature catalog supaya UI Packages tidak menampilkan entri ganda baseline vs add-on.
- Migrasi baru menambahkan tabel arsip `package_feature_archives`, memindahkan rows `api_access` + `priority_support` dari `package_features`, lalu menghapusnya dari entitlement aktif.
- Migrasi yang sama menormalkan assignment feature katalog yang sempat bolong di beberapa package (`attendance_shift_scheduling`, `leave_approval_flow`, `payroll_components`, `payroll_thr`, `employee_lifecycle`, `performance_goal_tracking` + trio platform MVP).
- Migrasi tambahan menyinkronkan assignment governance add-on (`allowance_governance`, `bpjs_governance`, `spt_masa_pph21`) ke `package_features` agar coverage matrix/fallback runtime konsisten.
- Seeder package runtime (`SaasUiFlowSeeder`, `LandingPackagesSeeder`) tidak lagi menulis `api_access` dan `priority_support` sebagai fitur aktif.

### Evidence Test

- `php artisan test tests/Feature/PackageServiceTest.php` (19 tests, 84 assertions) ✅
- `npx vitest run tests/ui/packages-management.wiring.test.js` (9 tests) ✅
- `php artisan migrate --force` (migrasi arsip feature + sinkron assignment) ✅

### Dokumen Yang Disinkronkan

- `docs/features/packages/STATUS-TRACKER.md`

### Gap Yang Masih Tersisa

- Entitlement add-on tenant berbasis transaksi pembelian add-on tetap menjadi fase berikutnya; perubahan ini fokus di katalog dan baseline assignment package.

## Snapshot 2026-05-02

- Status umum: hardening mapping package composer dipisah tegas menjadi 2 kelompok (`MVP package` vs `add-on`) supaya tidak ada fitur hantu, tidak ada custom leakage, dan semua fitur non-MVP otomatis masuk klasifikasi add-on.

### Evidence Runtime

- Endpoint `GET /v1/saas/packages/feature-catalog` tetap canonical-only dari backend config (tanpa append custom feature dari database).
- Mapping MVP diperbarui: `notifications`, `trial_billing_dashboard`, dan `tax_governance` dipromosikan ke `meta.mvp_feature_codes` agar tidak lagi diklasifikasikan sebagai add-on.
- Response feature catalog sekarang menyertakan:
	- `meta.mvp_feature_codes`
	- `meta.addon_feature_codes` (derived dari seluruh feature katalog di luar MVP)
	- `features[].tier` (`mvp` atau `addon`)
- Middleware asset web access dan sidebar feature bypass di tenant context sekarang tidak lagi memberi bypass otomatis untuk global admin; tenant package gate tetap dipatuhi.

### Evidence Test

- `php artisan test tests/Feature/HcmTaxGovernanceApiTest.php`
- `php artisan test tests/Feature/SidebarAssetMenuVisibilityTest.php`
- `php artisan test tests/Feature/PackageServiceTest.php`
- Regression baru:
	- tax governance history list assertion tidak brittle terhadap baseline row.
	- QA/global admin pada tenant context tidak melihat asset menu ketika feature tenant nonaktif.
	- package feature catalog expose mapping MVP/add-on dan tier per feature.

### Dokumen Yang Disinkronkan

- `docs/api/packages-api.md`
- `docs/api/openapi.yaml`
- `docs/features/packages/README.md`
- `docs/features/packages/STATUS-TRACKER.md`

### Gap Yang Masih Tersisa

- Mapping saat ini masih level katalog (classification source of truth). Entitlement runtime add-on per subscription/tenant masih perlu implementasi lanjutan agar pembelian add-on bisa otomatis mengubah akses/limit tenant.

## Snapshot 2026-05-01

- Status umum: sinkronisasi katalog fitur package dipindah ke backend agar `/packages` selalu membaca daftar fitur terbaru, termasuk feature code asset, tickets, dan custom feature existing.

### Evidence Runtime

- Endpoint baru `GET /v1/saas/packages/feature-catalog` mengembalikan katalog fitur backend-driven untuk UI packages.
- `frontend/resources/js/packages-management.js` sekarang fetch katalog fitur dari endpoint backend itu sebelum render composer package.
- Feature code custom yang sudah ada di `package_features` tetapi belum ada di katalog default tetap dimunculkan dalam grup `Custom Features`.

### Evidence Test

- `php artisan test tests/Feature/PackageServiceTest.php`
- `npx vitest run tests/ui/packages-management.wiring.test.js`
- Regression baru:
	- endpoint feature catalog memuat feature backend terbaru (`tickets`, `asset_management`) dan custom feature persisted.
	- packages management UI merender feature dari backend catalog, bukan bergantung penuh pada daftar frontend statis.

### Dokumen Yang Disinkronkan

- `docs/api/packages-api.md`
- `docs/api/openapi.yaml`
- `docs/features/packages/README.md`
- `docs/features/packages/STATUS-TRACKER.md`

### Gap Yang Masih Tersisa

- Metadata deskripsi feature tetap berbasis config backend; bila product menambah feature gate baru, config catalog ini tetap harus ikut diperbarui agar grouping/deskripsi UI tetap rapi.

## Snapshot 2026-04-27

- Status umum: hardening visibility package internal selesai untuk mencegah package super-admin bocor ke katalog tenant/public.

### Evidence Runtime

- Migration menambah kolom `packages.is_global_admin_only` + seed package internal `unlimited` + feature unlimited:
	- `backend/database/migrations/2026_04_27_210000_add_global_admin_only_to_packages_and_seed_unlimited.php`
- Endpoint `/v1/saas/packages` sekarang otomatis filter package internal untuk caller non-global-admin.
- Endpoint `/v1/saas/packages/{package}` sekarang return `404 NOT_FOUND` bila caller non-global-admin mengakses package internal.

### Evidence Test

- `php artisan test tests/Feature/PackageServiceTest.php`
- Regression baru:
	- non-admin tidak melihat package `is_global_admin_only=true` di list.
	- non-admin menerima `404` saat akses detail package internal.
	- global admin tetap bisa melihat package internal.

### Dokumen Yang Disinkronkan

- `docs/api/packages-api.md`
- `docs/api/openapi.yaml`
- `docs/features/packages/README.md`
- `docs/features/packages/IMPLEMENTATION.md`

### Gap Yang Masih Tersisa

- UI packages management belum menampilkan toggle eksplisit untuk `is_global_admin_only`; saat ini field tetap bisa diatur via API admin.

## Snapshot 2026-04-20

- Status umum: runtime packages flow sudah diaudit dan di-hardening untuk package CRUD, feature mutation, identifier add-on, dan guard penghapusan package yang masih dipakai subscription.

### Evidence FE

- `npm run test:ui -- --run tests/ui/packages-management.wiring.test.js`
- Edit package mempertahankan `yearly_price` existing saat admin hanya mengubah metadata.
- Toast/error package merender pesan backend sebagai text, bukan HTML eksekusi.

### Evidence BE

- `php artisan test tests/Feature/PackageServiceTest.php`
- Delete package mengembalikan `PACKAGE_IN_USE`, bukan 500 database, saat histori subscription masih mereferensikan package.
- Route update/delete package feature menerima numeric feature id aktif yang dipakai surface runtime.

### Dokumen Yang Sudah Disinkronkan

- `docs/api/packages-api.md`
- `docs/api/openapi.yaml`
- `docs/features/packages/README.md`
- `docs/features/packages/IMPLEMENTATION.md`
- `docs/features/packages/E2E-TESTING.md`

### Gap Yang Masih Tersisa

- Modal package masih memakai satu input harga, sehingga admin hanya mengubah satu dimensi harga per save; harga counterpart yang tidak disentuh sekarang dipertahankan otomatis.