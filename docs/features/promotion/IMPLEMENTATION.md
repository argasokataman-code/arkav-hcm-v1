# Promotion — Implementation

Status: Implemented (Promotion CRUD + Numeric ID)
Updated: 2026-05-08

## Overview

Modul promotion mencatat riwayat promosi jabatan atau kenaikan posisi karyawan. Admin dapat membuat, melihat, dan mengelola record promosi per karyawan dalam tenant.

## Controller

- `backend/app/Http/Controllers/Api/HcmPromotionController.php`

## Web Surfaces

- `backend/resources/views/promotion.blade.php` — halaman manajemen promosi (admin)

## Route File

`backend/routes/api/promotion.php` — prefix `v1/hcm/promotions`, middleware: `api.token`, `tenant.context`

## Main API Endpoints

- `GET /v1/hcm/promotions` — list semua promosi tenant (admin: semua; employee: milik sendiri)
- `GET /v1/hcm/promotions/users/{userId}/promotions` — riwayat promosi per user (numeric userId)
- `POST /v1/hcm/promotions` — buat record promosi
- `GET /v1/hcm/promotions/{id}` — detail promosi (numeric id)
- `PUT /v1/hcm/promotions/{id}` — update record promosi
- `DELETE /v1/hcm/promotions/{id}` — hapus record

## Data Model

- `HcmPromotion` — record promosi
  - `id` bigint PK
  - `user_id` — karyawan yang dipromosikan
  - `company_id` — tenant scope
  - `from_position` / `to_position` — posisi sebelum dan sesudah
  - `from_department` / `to_department` — departemen sebelum dan sesudah
  - `effective_date` — tanggal efektif promosi
  - `notes` — catatan admin

## Identifier

Route menggunakan numeric ID (`whereNumber('id')`).

## Tenant Scope

Query dikunci ke `company_id` aktif.

## Integrasi

- Data promosi dapat dipakai oleh modul performance dan reporting untuk riwayat karir karyawan.
