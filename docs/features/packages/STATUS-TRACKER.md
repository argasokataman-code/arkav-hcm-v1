# Packages Status Tracker

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