# Asset Management

## Ringkasan

Asset Management adalah modul HCM tenant-scoped untuk mengelola asset master, kategori asset, assignment ke employee, histori pergerakan asset, attachment, dan pelaporan issue. Fitur ini penting untuk kontrol inventaris internal dan menjadi sumber data clearance saat employee keluar atau terkena termination.

## Akses

- Web admin: halaman asset dan kategori hanya untuk admin tenant yang lolos feature gate `asset_management`.
- API admin: CRUD category, asset, assignment, return, attachment, dan issue report.
- Feature gate: akses modul bergantung pada `package_features.feature_code = asset_management`.

## UI Aktif

- Halaman asset management dan kategori asset berada di area HCM admin tenant.
- Dokumen detail implementasi dan E2E tersedia di [IMPLEMENTATION.md](IMPLEMENTATION.md) dan [E2E-TESTING.md](E2E-TESTING.md).

## Flow Bisnis End-to-End

1. Admin tenant membuat kategori asset dan asset master.
2. Asset di-assign ke employee melalui assignment history, bukan menulis `employee_id` langsung di tabel asset.
3. Admin dapat mengembalikan asset, menambah attachment, atau melaporkan issue asset.
4. Bila issue relevan perlu tindak lanjut, sistem membuat tiket ke modul ticketing.
5. Saat termination/clearance, daftar asset assignment aktif menjadi sumber outstanding clearance item.

## Lifecycle Dan Keputusan Bisnis

- Asset master tetap berdiri sendiri tanpa `employee_id` langsung agar histori assignment tidak hilang saat asset berpindah tangan.
- Semua record wajib company-scoped melalui `company_id`.
- Feature gate paket menentukan apakah tenant boleh memakai modul ini.
- Asset issue diarahkan ke ticketing agar follow-up operasional memakai workflow tiket, bukan komentar liar di modul asset.

## Integrasi

- Tickets: issue asset direport ke ticketing melalui endpoint asset issue report. Lihat `docs/features/tickets/README.md`.
- Termination: settlement clearance pada termination memakai asset assignment aktif untuk menentukan item yang belum kembali. Lihat `docs/features/termination/README.md`.
- Employees & Organization: assignment asset selalu merujuk employee tenant aktif. Lihat `docs/features/employees-organization/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

- Area API utama: `/v1/hcm/asset-categories`, `/v1/hcm/assets`, `/v1/hcm/assets/{asset}/assign`, `/v1/hcm/assets/{asset}/return`, `/v1/hcm/assets/{asset}/issue-report`, dan attachment endpoints.
- Kontrak aktif runtime memakai numeric ID untuk path `{asset}`, `asset_category_id`, dan `employee_id`; UUID kolom tetap ada untuk kompatibilitas internal, tetapi FE/admin flow aktif mengirim identifier numerik.
- Detail kontrak teknis: lihat [IMPLEMENTATION.md](IMPLEMENTATION.md).

## Existing Vs Target

- Existing: asset category, asset master, assignment history, attachment, dan issue reporting sudah aktif tenant-scoped.
- Existing: issue report sudah terhubung ke ticketing dan ticket hasil eskalasi menyimpan `company_id` asset agar tetap tenant-safe.
- Target: pengayaan reporting dan otomatisasi clearance lintas lifecycle employee masih dapat diperluas tanpa mengubah boundary inti asset module.
