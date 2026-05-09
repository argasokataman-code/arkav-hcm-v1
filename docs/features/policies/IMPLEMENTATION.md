# Policies — Implementation

Status: Implemented (Company Policy CRUD — Web-only via Blade + Settings routes)
Updated: 2026-05-08

## Overview

Modul policies mengelola dokumen kebijakan perusahaan (SOP, peraturan internal, panduan). Policies diakses melalui halaman settings/admin HCM dan ditampilkan di portal karyawan.

## Controller

- Controller policies terintegrasi di dalam web routes settings (tidak ada API JSON khusus untuk policies; diakses via Blade view).

## Web Surfaces

- `backend/resources/views/policy.blade.php` — halaman kebijakan perusahaan
- Route web di `backend/routes/web/settings.php` — `/policies` dan `/policies/{policyUuid}/edit`

## Route File

`backend/routes/web/settings.php` — route web Blade, tidak ada endpoint API JSON terpisah untuk policies.

## Web Routes

- `GET /policies` — daftar kebijakan (admin dan employee — berbeda konten berdasarkan role)
- `GET /policies/{policyUuid}/edit` — edit kebijakan (admin only)

## Data Model

- `Policy` — kebijakan perusahaan
  - `uuid` UUID identifier (bukan numeric ID)
  - `company_id` — tenant scope
  - `title` — judul kebijakan
  - `content` — isi kebijakan (rich text)
  - `type` / `category` — tipe/kategori kebijakan
  - `is_published` — status publikasi
  - `effective_date` — tanggal berlaku
  - `created_by` — admin pembuat

## Identifier

Route menggunakan UUID (`policyUuid`), bukan numeric ID.

## Tenant Scope

Semua kebijakan dikunci ke `company_id` aktif. Employee hanya melihat kebijakan yang published; admin melihat semua status.

## Catatan Implementasi

Policies saat ini berjalan sebagai Blade web surface (server-rendered), bukan SPA/API JSON. Jika ada perubahan ke API JSON, perlu membuat route `backend/routes/api/` dan controller API baru.
