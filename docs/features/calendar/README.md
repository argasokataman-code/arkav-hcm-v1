# Feature: Calendar

## Ringkasan Bisnis

Halaman Calendar menampilkan gabungan tiga sumber data:
- Event custom personal milik user (CRUD)
- Holiday tenant (read-only)
- Leave request (read-only)

Tujuan bisnis:
- User bisa mencatat agenda kerja pribadi langsung di aplikasi.
- Tim HR/manager tetap dapat melihat konteks tanggal penting karena holiday dan leave tetap ditampilkan sebagai overlay.
- Tidak ada risiko user mengubah data leave/holiday dari halaman ini.

## Status Implementasi dan Tracker

Implementasi CRUD event custom sudah aktif.
Tracker perubahan status dan evidence test ada di:
- docs/features/calendar/tracker.md

## Flow End-to-End

1. User buka halaman /calendar.
2. Frontend load 3 sumber data:
   - GET /v1/hcm/holidays
   - GET /v1/hcm/leave-requests
   - GET /v1/hcm/calendar/events
3. Semua event dirender di FullCalendar.
4. User dapat membuat event baru dari modal Create.
5. User dapat klik event custom untuk lihat detail, lalu Edit/Delete.
6. User dapat drag/drop atau resize event custom, perubahan dikirim ke API update.
7. Holiday dan leave tetap read-only; jika dicoba drag/drop, perubahan direvert di frontend.

## Lifecycle dan Makna Bisnis

- Draft/create: event custom dibuat oleh user untuk pengingat agenda.
- Update: user menyesuaikan waktu/lokasi/deskripsi agenda saat ada perubahan.
- Delete: event custom dihapus permanen jika tidak relevan.
- Read-only overlays:
  - Holiday: representasi kalender hari libur perusahaan/tenant.
  - Leave request: representasi jadwal cuti agar agenda personal bisa menyesuaikan.

## Existing vs Target

Existing sebelum perbaikan:
- Modal Create/Edit/Delete bersifat template statis.
- Data kalender hanya overlay holiday + leave (tanpa CRUD custom event).
- Tidak ada dokumentasi feature khusus Calendar.

Target sesudah perbaikan:
- CRUD custom event fully wired ke backend API.
- Modal kalender terhubung ke field id yang dipakai JS runtime.
- Event custom bisa create, update form, delete, drag-drop, dan resize.
- Dokumentasi feature + API + OpenAPI sinkron.

## API dan Permission Cross-check

API custom event:
- GET /v1/hcm/calendar/events
- POST /v1/hcm/calendar/events
- PUT /v1/hcm/calendar/events/{id}
- DELETE /v1/hcm/calendar/events/{id}

Permission model:
- Tidak ada role khusus per modul untuk event personal.
- Security dijaga oleh auth + tenant context + ownership check di controller:
  - user_id harus user login
  - company_id harus tenant aktif

Halaman aktif:
- Web route /calendar menampilkan view applications.calendar.

## Struktur Data Event Custom

Tabel: calendar_events
- id, uuid
- user_id, company_id
- title, location, description
- start_at, end_at
- all_day
- created_at, updated_at

## File yang Terdampak

Backend:
- backend/database/migrations/2026_05_07_000200_create_calendar_events_table.php
- backend/app/Models/CalendarEvent.php
- backend/app/Http/Controllers/Api/HcmCalendarEventController.php
- backend/routes/api/calendar.php
- backend/routes/api.php
- backend/tests/Feature/CalendarEventApiTest.php

Frontend/UI:
- frontend/resources/plugins/fullcalendar/calendar-data.js
- backend/public/build/plugins/fullcalendar/calendar-data.js
- backend/resources/views/components/modal-popup.blade.php

Dokumentasi:
- docs/api/calendar-api.md
- docs/api/openapi.yaml
- docs/features/calendar/tracker.md
- docs/features/calendar/README.md

## Known Gap

- Event custom saat ini masih scoped personal user, belum ada mode shared team event.
- Reminder/notifikasi otomatis untuk event custom belum diaktifkan.
