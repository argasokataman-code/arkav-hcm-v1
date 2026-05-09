# Calendar — Implementation

Status: Implemented (Calendar Event CRUD)
Updated: 2026-05-08

Tracker: [tracker.md](tracker.md)

## Overview

Modul calendar menyediakan event kalender per tenant untuk keperluan internal (rapat, acara perusahaan, pengingat, dll.). Event dapat dibuat, diedit, dan dihapus oleh admin. Karyawan dapat melihat event.

## Controller

- `backend/app/Http/Controllers/Api/HcmCalendarEventController.php`

## Web Surfaces

- `backend/resources/views/calendar.blade.php` — halaman kalender

## Route File

`backend/routes/api/calendar.php` — prefix `v1/hcm`, middleware: `api.token`, `tenant.context`

## Main API Endpoints

- `GET /v1/hcm/calendar/events` — daftar event kalender (dengan filter tanggal)
- `POST /v1/hcm/calendar/events` — buat event baru
- `PUT /v1/hcm/calendar/events/{id}` — update event
- `DELETE /v1/hcm/calendar/events/{id}` — hapus event

## Data Model

- `CalendarEvent` — event kalender
  - `id` bigint PK
  - `company_id` — tenant scope
  - `title` — judul event
  - `description` — deskripsi
  - `start_datetime` / `end_datetime` — waktu mulai dan selesai
  - `all_day` — boolean, apakah event seharian penuh
  - `color` / `type` — kategori visual event
  - `created_by` — user yang membuat

## Tenant Scope

Semua event dikunci ke `company_id` aktif.
