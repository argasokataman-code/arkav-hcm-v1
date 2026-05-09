# Resignation — Implementation

Status: Implemented (Resignation CRUD + UUID identifier)
Updated: 2026-05-08

## Overview

Modul resignation mengelola proses pengunduran diri karyawan. Admin dan karyawan dapat membuat, melihat, mengupdate, dan menghapus record pengunduran diri. Identifier menggunakan UUID (bukan numeric ID) sesuai pola migrasi UUID repo ini.

## Controller

- `backend/app/Http/Controllers/Api/HcmResignationController.php`

## Web Surfaces

- `backend/resources/views/resignation.blade.php` — halaman pengunduran diri (admin + employee)

## Route File

`backend/routes/api/resignation.php` — prefix `v1/hcm/resignations`, middleware: `api.token`, `tenant.context`

## Main API Endpoints

- `GET /v1/hcm/resignations` — list semua pengunduran diri (admin: semua; employee: milik sendiri)
- `GET /v1/hcm/resignations/users/{userId}/resignations` — riwayat pengunduran diri per user (UUID)
- `POST /v1/hcm/resignations` — buat record pengunduran diri
- `GET /v1/hcm/resignations/{id}` — detail pengunduran diri (UUID)
- `PUT /v1/hcm/resignations/{id}` — update (tanggal efektif, status, catatan)
- `DELETE /v1/hcm/resignations/{id}` — hapus record (UUID)

## Data Model

- `HcmResignation` — record pengunduran diri
  - `id` UUID PK
  - `user_id` — karyawan yang mengundurkan diri
  - `company_id` — tenant scope
  - `effective_date` — tanggal efektif
  - `reason` — alasan pengunduran diri
  - `status` — `pending|approved|rejected`
  - `notes` — catatan HR/admin

## Identifier

Route menggunakan UUID (`[0-9a-fA-F\-]+`), bukan numeric ID. Sinkron dengan pola migrasi UUID repo.

## Tenant Scope

Query dikunci ke `company_id` aktif. Admin tidak bisa melihat/memodifikasi pengunduran diri dari tenant lain.
