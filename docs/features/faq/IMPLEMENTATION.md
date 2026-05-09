# FAQ — Implementation

Status: Implemented (FAQ CRUD + Bulk Delete)
Updated: 2026-05-08

## Overview

Modul FAQ menyediakan konten tanya-jawab internal per tenant. Admin dapat membuat, mengedit, menghapus, dan bulk-delete FAQ. Karyawan dapat membaca FAQ yang tersedia.

## Controller

- `backend/app/Http/Controllers/Api/HcmFaqController.php`

## Web Surfaces

- FAQ ditampilkan di halaman knowledge/FAQ dalam portal HCM.

## Route File

`backend/routes/api/faq.php` — prefix `v1/hcm`, middleware: `api.token`, `tenant.context`

## Main API Endpoints

- `GET /v1/hcm/faqs` — daftar FAQ (dapat diakses semua user tenant)
- `POST /v1/hcm/faqs` — buat FAQ (admin only)
- `PUT /v1/hcm/faqs/{id}` — update FAQ (admin only)
- `DELETE /v1/hcm/faqs/{id}` — hapus FAQ (admin only)
- `POST /v1/hcm/faqs/bulk-delete` — hapus banyak FAQ sekaligus (admin only)

## Data Model

- `Faq` (atau `HcmFaq`) — item FAQ
  - `id` bigint PK
  - `company_id` — tenant scope
  - `question` — pertanyaan
  - `answer` — jawaban (rich text)
  - `category` — kategori FAQ (opsional)
  - `is_published` — status publikasi
  - `sort_order` — urutan tampil

## Tenant Scope

Semua FAQ dikunci ke `company_id` aktif.
