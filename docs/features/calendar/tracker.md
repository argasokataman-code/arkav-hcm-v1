# Calendar Feature Tracker

## 2026-05-02

Snapshot:
- CRUD event custom kalender sudah aktif end-to-end (API + modal + FullCalendar interactions).
- Overlay holiday dan leave tetap read-only.

Gap yang diselesaikan:
- Modal calendar sebelumnya masih template statis tanpa koneksi API.
- Tidak ada entity backend untuk menyimpan event custom.
- Dokumentasi feature calendar belum tersedia.

Evidence:
- Backend test: CalendarEventApiTest (CRUD + isolation)
- Sinkron docs API: docs/api/calendar-api.md + docs/api/openapi.yaml

Open gap lanjutan:
- Shared team event belum tersedia.
- Reminder/notifikasi event custom belum tersedia.
