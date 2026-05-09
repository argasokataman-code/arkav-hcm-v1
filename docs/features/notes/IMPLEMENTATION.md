# Notes — Implementation

Status: Implemented (Note CRUD — sticky notes internal)
Updated: 2026-05-08

## Overview

Modul notes menyediakan fitur catatan (sticky notes) internal per user dalam tenant. Setiap user dapat membuat, mengedit, dan menghapus catatan pribadi mereka.

## Controller

- `backend/app/Http/Controllers/Api/HcmNoteController.php`

## Web Surfaces

- `backend/resources/views/notes.blade.php` — halaman sticky notes
- `backend/resources/views/ui-stickynote.blade.php` — komponen UI sticky note

## Route File

`backend/routes/api/notes.php` — prefix `v1/hcm`, middleware: `api.token`, `tenant.context`

## Main API Endpoints

- `GET /v1/hcm/notes` — daftar catatan milik user aktif dalam tenant
- `POST /v1/hcm/notes` — buat catatan baru
- `PUT /v1/hcm/notes/{id}` — update catatan
- `DELETE /v1/hcm/notes/{id}` — hapus catatan

## Data Model

- `Note` — catatan user
  - `id` bigint PK
  - `user_id` — pemilik catatan
  - `company_id` — tenant scope
  - `content` — isi catatan
  - `color` — warna sticky note (untuk UI)
  - `position_x` / `position_y` — posisi pada canvas (opsional)

## Scope

Catatan bersifat per-user, bukan shared. User hanya dapat melihat dan mengedit catatan milik sendiri. Semua query dikunci ke `user_id` + `company_id` aktif.
