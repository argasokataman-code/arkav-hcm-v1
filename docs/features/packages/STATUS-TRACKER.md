# Packages Status Tracker

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