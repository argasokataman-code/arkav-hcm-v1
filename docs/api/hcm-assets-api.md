# HCM Assets API

Dokumen ini merangkum kontrak aktif untuk feature Asset Management yang dipakai halaman `/assets` dan `/asset-categories`.

## Sumber kebenaran runtime

- Route: `backend/routes/api.php`
- Controller: `backend/app/Http/Controllers/Api/HcmAssetController.php`
- Controller category: `backend/app/Http/Controllers/Api/HcmAssetCategoryController.php`
- Service bisnis: `backend/app/Services/AssetService.php`

## Identifier aktif

- Path asset aktif menerima numeric ID pada flow FE/admin: `/v1/hcm/assets/{asset}`.
- Path category aktif pada flow FE/admin juga memakai numeric ID hasil response list/create.
- UUID kolom tetap ada untuk kompatibilitas internal/transisi, tetapi flow admin tenant aktif memakai identifier numerik.

## Permission boundary

- Read/list/detail: `asset.view`
- Mutasi category/asset/assign/return/issue-report/attachment: `asset.manage`
- Semua endpoint tetap tenant-scoped lewat active company context.

## Endpoint utama

### GET `/v1/hcm/asset-categories`

- Fungsi: list category asset company aktif.
- Response: `200`

### POST `/v1/hcm/asset-categories`

- Fungsi: create category asset.
- Permission: `asset.manage`
- Response: `201`

### PUT `/v1/hcm/asset-categories/{category}`

- Fungsi: update category asset existing.
- Identifier: numeric category id.

### DELETE `/v1/hcm/asset-categories/{category}`

- Fungsi: hapus category jika belum dipakai asset.
- Negative: `422 CATEGORY_IN_USE` jika category masih punya asset.

### GET `/v1/hcm/assets`

- Fungsi: list asset company aktif.
- Filter: `status`, `condition`, `categoryId`, `q`, `perPage`
- Catatan: retired asset tetap muncul saat filter `status=retired` karena record soft-deleted tetap diikutkan untuk audit/history.

### POST `/v1/hcm/assets`

- Fungsi: create asset baru.
- Permission: `asset.manage`
- Negative penting:
  - `422` jika `asset_category_id` bukan milik company aktif
  - `422` jika urutan warranty date mundur dari purchase date

### PUT `/v1/hcm/assets/{asset}`

- Fungsi: update asset.
- Permission: `asset.manage`
- Identifier: numeric asset id.

### DELETE `/v1/hcm/assets/{asset}`

- Fungsi: retire asset.
- Runtime: status diubah ke `retired` lalu asset masuk soft delete.

### POST `/v1/hcm/assets/{asset}/assign`

- Fungsi: assign asset ke employee profile tenant aktif.
- Permission: `asset.manage`
- Negative penting:
  - `422 ASSET_NOT_AVAILABLE`
  - `422 ASSET_ALREADY_ASSIGNED`

### POST `/v1/hcm/assets/{asset}/return`

- Fungsi: return asset dari assignment aktif.
- Permission: `asset.manage`
- Negative penting:
  - `422 ASSET_NOT_ASSIGNED`
  - `422 ASSET_RETURN_DATE_INVALID` jika `returned_date < assigned_date`

### POST `/v1/hcm/assets/{asset}/issue-report`

- Fungsi: eskalasi issue asset ke ticketing.
- Permission: `asset.manage`
- Integrasi: ticket hasil eskalasi wajib menyimpan `company_id` asset.

### POST `/v1/hcm/assets/{asset}/attachments`

- Fungsi: upload attachment asset.
- Permission: `asset.manage`
- Feature gate tambahan: `asset_attachments`
