# Export Governance Tracker

## Snapshot 2026-05-07

Status: In progress
Owner: Engineering (Backend + Frontend) + QA

## Evidence Yang Sudah Diverifikasi

1. Employee export controller sudah mendukung `xlsx/csv/pdf` dan default `xlsx`.
2. Payroll items export mendukung `xlsx/csv` dan default sudah `xlsx`.
3. Leave request export mendukung `xlsx/csv` dengan default `xlsx`.
4. User management export mendukung `xlsx/csv` dengan default `xlsx`.
5. Notification delivery export mendukung `xlsx/csv` dengan default `xlsx`.
6. SaaS transaction export mendukung `xlsx/csv` dengan default `xlsx`.
7. BPJS governance export attachment masih `json`.
8. Allowance governance export attachment masih `json`.
9. Report snapshot mendukung `csv/excel/pdf`.
10. SPT Masa export tetap `csv` (regulatory flow DJP-style).
11. Shared helper backend `App\Support\Exports\TabularExportResponse` sudah dipakai untuk leave, user management, notifications, dan transactions export.

Detail endpoint dan temuan ada di [EXPORT-FORMAT-AUDIT-2026-05-07.md](./EXPORT-FORMAT-AUDIT-2026-05-07.md).

## Gap Prioritas

P0

- Kontrak default format export tabular belum seragam ke `xlsx` untuk modul lain di luar scope migrasi saat ini (mis. attendance/tickets/holidays).

P1

- UI beberapa modul masih hardcode filename `.csv` dan belum menghormati format API (di luar leaves/user management/notifications yang sudah diselaraskan).
- OpenAPI belum memuat pedoman seragam format export lintas endpoint.

P2

- Naming konvensi file export belum seragam 100% antar modul.

## Rencana Eksekusi

1. Implement helper shared `TabularExportResponse` di backend.
2. Migrasikan endpoint tabular `csv-only` menjadi default `xlsx` dengan fallback `csv`.
3. Samakan parameter request export (`format`) dan validasi lintas modul.
4. Sinkronkan OpenAPI + docs API per modul untuk endpoint yang berubah.
5. Rapikan frontend agar tidak hardcode `.csv` jika endpoint mendukung format dinamis.
6. Tambahkan test matrix export: auth, tenant scope, format, header, filename.

## Kriteria Selesai

1. Semua endpoint export tabular default `xlsx` (kecuali pengecualian regulatori terdokumentasi).
2. Semua endpoint export tabular pakai helper shared yang sama.
3. OpenAPI dan docs API sinkron untuk semua endpoint yang terdampak.
4. Tidak ada regresi authorization/tenant scope pada export.