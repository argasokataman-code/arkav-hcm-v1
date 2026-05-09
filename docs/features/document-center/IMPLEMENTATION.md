# Document Center — Implementation

Status: Implemented (Document Category + Document CRUD + Download)
Updated: 2026-05-08

## Overview

Document Center menyimpan dokumen karyawan yang dapat di-upload oleh admin (atau karyawan sendiri jika diizinkan), dikategorikan, dan diunduh. Dokumen dikunci dengan feature gate `employee_document_center`.

## Controller

- `backend/app/Http/Controllers/Api/HcmEmployeeDocumentController.php`

## Web Surfaces

- Halaman document center terintegrasi dalam detail karyawan atau halaman dokumen HCM.

## Route File

`backend/routes/api/document-center.php` — prefix `v1/hcm/document-center`, middleware: `api.token`, `tenant.context`, `hcm.api.feature:employee_document_center`

## Main API Endpoints

### Categories (admin only)
- `GET /v1/hcm/document-center/categories` — daftar kategori dokumen
- `POST /v1/hcm/document-center/categories` — buat kategori
- `PUT /v1/hcm/document-center/categories/{id}` — update kategori
- `DELETE /v1/hcm/document-center/categories/{id}` — hapus kategori

### Documents
- `GET /v1/hcm/document-center/documents` — daftar dokumen (admin: semua; employee: milik sendiri)
- `POST /v1/hcm/document-center/documents` — upload dokumen baru
- `PUT /v1/hcm/document-center/documents/{id}` — update metadata dokumen
- `DELETE /v1/hcm/document-center/documents/{id}` — hapus dokumen
- `GET /v1/hcm/document-center/documents/{id}/download` — unduh dokumen (dikunci: pemilik atau admin)

## Data Models

- `HcmEmployeeDocumentCategory` — kategori dokumen per tenant
- `HcmEmployeeDocument` — record dokumen karyawan (nama, path storage, kategori, karyawan, metadata)

## Feature Gate

Endpoint dilindungi `hcm.api.feature:employee_document_center`. Tenant yang tidak memiliki fitur ini aktif akan mendapat error akses.

## Storage

File disimpan di storage Laravel (`storage/app/private` atau `storage/app/public` tergantung visibilitas). Download via endpoint streaming dengan auth check — tidak boleh akses langsung dari URL storage.

## Tenant Scope

Semua dokumen dikunci ke `company_id` aktif. Admin tidak dapat mengakses dokumen tenant lain.
