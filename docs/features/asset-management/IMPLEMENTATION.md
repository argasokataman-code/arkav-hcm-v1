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

- Semua endpoint mutasi memakai `isHcmAdmin()`.
- List/view asset masih dibatasi ke admin pada implementasi saat ini.
- Active company diambil dari `ResolveTenantContext`.

## Validation / Testing

- Feature test: `backend/tests/Feature/HcmAssetApiTest.php`
- Validasi syntax: migration, controller, service, test, dan OpenAPI YAML sudah dicek.

## Notes

- Endpoint return asset memakai `POST /v1/hcm/assets/{asset}/return`.
- Endpoint issue reporting memakai `POST /v1/hcm/assets/{asset}/issue-report`.
- Frontend placeholder masih perlu dihubungkan bila page asset admin ingin benar-benar live.