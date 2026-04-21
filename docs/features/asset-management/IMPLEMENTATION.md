# Asset Management - Implementation Guide

## Overview

Modul asset management menambahkan kategori asset, asset master, assignment history, log audit, attachment, dan flow issue reporting.

## Backend Surface

- API routes: `backend/routes/api.php`
- Controllers:
  - `backend/app/Http/Controllers/Api/HcmAssetCategoryController.php`
  - `backend/app/Http/Controllers/Api/HcmAssetController.php`
- Service: `backend/app/Services/AssetService.php`
- Models:
  - `backend/app/Models/AssetCategory.php`
  - `backend/app/Models/Asset.php`
  - `backend/app/Models/AssetAssignment.php`
  - `backend/app/Models/AssetLog.php`
  - `backend/app/Models/AssetAttachment.php`

## Data Model

- `asset_categories`
- `assets`
- `asset_assignments`
- `asset_logs`
- `asset_attachments`

## Runtime Rules

- `asset_management` wajib aktif lewat package feature.
- `asset_attachments` dipakai untuk upload file attachment.
- `assets` memakai soft delete untuk retirement.
- `asset_assignments` menyimpan histori assignment dan return.
- `issue-report` membuat ticket baru di `tickets`.

## Access Control

- Endpoint read memakai permission `asset.view`.
- Endpoint mutasi category/asset/assign/return/issue-report/attachment memakai permission `asset.manage`.
- Semua endpoint tetap tenant-scoped via `activeCompanyId` dari `ResolveTenantContext`.
- Active company diambil dari `ResolveTenantContext`.

## Validation / Testing

- Feature test: `backend/tests/Feature/HcmAssetApiTest.php`
- Vitest wiring: `backend/tests/ui/asset-management.wiring.test.js`
- Validasi syntax: migration, controller, service, test, dan OpenAPI YAML sudah dicek.

## Notes

- Endpoint return asset memakai `POST /v1/hcm/assets/{asset}/return`.
- Endpoint issue reporting memakai `POST /v1/hcm/assets/{asset}/issue-report`.
- Page `/assets` dan `/asset-categories` sudah live ke API melalui `frontend/resources/js/asset-management-data.js`.
- Halaman asset sekarang punya surface admin untuk issue-report dan upload attachment langsung dari daftar asset.
- Halaman web asset/category tetap admin-only lewat `hcm.web.admin`; permission `asset.view` relevan untuk API read access, bukan membuka Blade admin page.
- Validasi penting yang aktif sekarang: `asset_category_id` wajib berasal dari company aktif, warranty date tidak boleh mundur dari purchase date, dan `returned_date` tidak boleh lebih awal dari `assigned_date`.