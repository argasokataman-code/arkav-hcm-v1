# Asset Management - E2E Testing

## Objective

Memastikan alur asset management berjalan end-to-end untuk admin: kategori, asset, assignment, return, feature gate, issue reporting, dan UI live-data integration.

## Environment

- App URL: `http://localhost:8000`
- Admin user: `qa.login@example.com / StrongPass1`
- Company token requires `X-Company-Code` header when tenant context is needed.
- Kontrak aktif asset flow memakai numeric ID untuk `{asset}`, `asset_category_id`, dan `employee_id`.

## Scenario 0 - UI Smoke Check

1. Login sebagai admin.
2. Buka halaman `/assets`.
3. Expected:
   - Tabel asset terisi dari API, bukan dummy static data
   - Filter search, status, dan category bekerja
   - Tombol add, edit, delete, assign, dan return tampil sesuai status asset
   - Modal create/edit/assign/return bisa dibuka tanpa error console
4. Buka halaman `/asset-categories`.
5. Expected:
   - Tabel category terisi dari API
   - Search, add, edit, dan delete bekerja
   - Delete memakai confirm dialog standar

## Scenario 1 - Admin Creates Category

1. Login sebagai admin.
2. Panggil `POST /v1/hcm/asset-categories`.
3. Expected:
   - Response `201`
   - Kategori tersimpan dengan `companyId`

## Scenario 2 - Admin Creates Asset

1. Siapkan kategori aktif.
2. Panggil `POST /v1/hcm/assets`.
3. Expected:
   - Response `201`
   - `assetCode` ter-generate otomatis
   - Asset status default `available`

## Scenario 3 - Admin Assign Asset

1. Pastikan asset status `available`.
2. Panggil `POST /v1/hcm/assets/{asset}/assign` dengan `employee_id` valid.
3. Expected:
   - Response `201`
   - Assignment active ditandai `isActive=true`
   - Asset berubah ke status `assigned`

## Scenario 4 - Admin Return Asset

1. Panggil `POST /v1/hcm/assets/{asset}/return`.
2. Expected:
   - Response `200`
   - Assignment menjadi tidak aktif
   - Asset kembali ke status `available`

## Scenario 4b - UI Assign/Return Flow

1. Buka halaman `/assets` sebagai admin.
2. Pilih asset dengan status `available`, lalu klik assign.
3. Isi employee, tanggal assign, dan catatan.
4. Expected:
   - Assign sukses
   - Row asset refresh dan status berubah menjadi `assigned`
   - Nama assignee tampil di kolom assignment
5. Klik return pada asset yang sama.
6. Isi tanggal return dan catatan.
7. Expected:
   - Return sukses
   - Row asset refresh dan status kembali `available`
   - Kolom assignment kembali kosong / `-`

## Scenario 5 - Feature Gate

1. Login sebagai admin company yang tidak punya feature `asset_management`.
2. Panggil `GET /v1/hcm/assets`.
3. Expected:
   - Response `403`
   - Error code `FEATURE_DISABLED`

## Scenario 6 - Issue Reporting

1. Panggil `POST /v1/hcm/assets/{asset}/issue-report`.
2. Expected:
   - Ticket baru dibuat
   - Ticket mewarisi `company_id` asset yang dilaporkan
   - Asset status mengikuti issue type
   - Log asset tercatat

## Scenario 7 - Attachment Upload

1. Panggil `POST /v1/hcm/assets/{asset}/attachments` dengan file valid.
2. Expected:
   - Response `201`
   - Attachment tersimpan di storage `public`

## Scenario 8 - Permission Boundary

1. Login sebagai user non-admin company.
2. Buka halaman `/assets` dan `/asset-categories`.
3. Expected:
   - Aksi mutasi tidak tersedia atau ditolak
   - API mutasi tetap mengembalikan `403`

## Scenario 9 - Negative: user hanya punya permission view

1. Login sebagai user yang hanya punya permission `asset.view` tanpa `asset.manage`.
2. Panggil `GET /v1/hcm/assets`.
3. Expected:
   - Response `200`
   - Data asset tetap bisa dibaca.
4. Panggil `POST /v1/hcm/assets` atau `POST /v1/hcm/asset-categories`.
5. Expected:
   - Response `403 AUTH_FORBIDDEN`
   - Tidak ada asset/category baru yang tercipta.
   - Untuk web page `/assets` dan `/asset-categories`, user non-admin tetap diarahkan ke dashboard karyawan karena halaman ini admin-only.

## Scenario 10 - Negative: category tenant lain tidak boleh dipakai

1. Ambil `asset_category_id` dari company tenant lain.
2. Panggil `POST /v1/hcm/assets` memakai `asset_category_id` tersebut.
3. Expected:
   - Response `422`
   - Error validation pada `asset_category_id`
   - Asset tidak tercipta.

## Scenario 11 - Negative: returned date lebih awal dari assigned date

1. Assign asset pada tanggal `2026-04-10`.
2. Coba return asset dengan `returned_date=2026-04-01`.
3. Expected:
   - Response `422`
   - Error code `ASSET_RETURN_DATE_INVALID`
   - Assignment tetap aktif dan asset tidak berubah ke `available`.

## Scenario 12 - Issue report dan attachment dari halaman asset

1. Login sebagai admin tenant yang punya `asset.manage`.
2. Dari halaman `/assets`, buka action `Report issue` pada salah satu asset.
3. Submit issue type dan description.
4. Expected:
   - Response `201`
   - Ticket baru tercipta di tenant yang sama.
5. Dari row asset yang sama, buka action `Upload attachment`.
6. Upload file valid dengan ukuran di bawah 10 MB.
7. Expected:
   - Response `201`
   - Attachment counter/list bertambah di modal.

## Browser Automation

- Playwright scenario aktif: `backend/e2e/scenarios/asset-management-ui-flow.spec.js`
- Seed helper untuk data browser flow: `backend/e2e/helpers/seed-asset-ui-flow.php`
- Run command dari folder `backend`:
   - `npx playwright test e2e/scenarios/asset-management-ui-flow.spec.js --project=chromium`
- Flow yang diverifikasi:
   - login company admin tenant khusus test
   - buka `/assets`
   - issue-report dari action row dan verifikasi ticket detail lewat API tenant aktif
   - upload attachment dari action row dan verifikasi attachment muncul di detail asset

## Exit Criteria

- Semua request admin pass.
- UI live-data tampil di halaman assets dan categories.
- Feature gate non-enabled company ditolak.
- Asset assignment history tetap terjaga.
