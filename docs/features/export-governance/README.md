# Export Governance

## Ringkasan

Export Governance adalah standar lintas modul untuk memastikan semua fitur export data tabular di HCM konsisten, aman tenant, dan mudah dipakai user bisnis.

Masalah existing saat ini:

- Sebagian endpoint default `xlsx`, sebagian default `csv`, sebagian hanya `json` attachment.
- Tidak ada helper export tabular tunggal yang wajib dipakai lintas controller.
- Pola nama file, audit log, dan validasi format belum seragam.

Dokumen ini menjadi acuan wajib saat membuat modul baru yang punya fitur export.

## Status

- Status: In progress (baseline aturan disiapkan, migrasi endpoint bertahap)
- Last updated: 2026-05-07
- Tracker: [TRACKING.md](./TRACKING.md)
- Audit snapshot: [EXPORT-FORMAT-AUDIT-2026-05-07.md](./EXPORT-FORMAT-AUDIT-2026-05-07.md)

## Flow Bisnis End-to-End

1. User membuka modul dan menerapkan filter data.
2. User klik Export.
3. UI mengirim request export dengan filter aktif + format file.
4. Server memverifikasi tenant context dan permission export.
5. Server membentuk dataset dari company aktif saja.
6. Server menghasilkan file dan mengirim response attachment.
7. Sistem menyimpan jejak audit export (siapa, kapan, dataset, format).

## Lifecycle Dan Arti Bisnis

- Draft policy: aturan disepakati lintas tim backend, frontend, QA.
- Enforced on new modules: fitur baru wajib ikut standar ini.
- Gradual migration: endpoint lama yang masih `csv/json` dimigrasi bertahap ke default `xlsx`.
- Compliance-ready: semua export yang berisi data sensitif wajib punya tenant scope + audit trail.

## Keputusan Dan Percabangan

- Jika dataset tabular operasional:
  - Default format wajib `xlsx`.
  - `csv` boleh disediakan hanya untuk kompatibilitas legacy dan harus eksplisit (`?format=csv`).
- Jika output regulatori yang memang ditentukan CSV oleh otoritas:
  - Tetap CSV, tapi wajib terdokumentasi alasan regulasinya.
- Jika output bukan tabular (mis. PDF laporan naratif):
  - Boleh `pdf`, tapi tidak boleh mengganggu standar tabular default `xlsx`.

## Existing Vs Target

### Existing

- Endpoint export lintas modul masih heterogen (`xlsx`, `csv`, `json`, `pdf`).
- Sebagian UI masih hardcode nama file `.csv`.
- Helper export tabular belum distandardisasi lintas controller.

### Target

- Semua export tabular API konsisten default `xlsx`.
- Kontrak request seragam: `format` in `xlsx|csv|pdf` (dengan default `xlsx` untuk tabular).
- Satu helper reusable untuk streaming CSV/XLSX + naming + header + audit hook.
- Dokumentasi API + OpenAPI selalu sinkron saat kontrak berubah.

## Rule Wajib Untuk Modul Baru

1. Wajib server-side auth + permission check sebelum generate export.
2. Wajib tenant scoping ketat (`activeCompanyId`/`activeCompanyUuid`) pada query export.
3. Wajib default format `xlsx` untuk export tabular.
4. Wajib support fallback `csv` hanya jika ada alasan kompatibilitas.
5. Wajib nama file konsisten: `<module>-<scope>-YYYYMMDD_HHmmss.<ext>`.
6. Wajib content-type benar sesuai format.
7. Wajib audit log export untuk data sensitif/operasional.
8. Wajib update docs API + OpenAPI jika format/kontrak endpoint berubah.

## Rekomendasi Helper Teknis

Gunakan helper tunggal (target implementasi):

- `App\Support\Exports\TabularExportResponse`
- Input: filename base, headers, rows, format, optional audit context.
- Output: `StreamedResponse`/binary response sesuai format.
- Fitur minimum:
  - Streaming CSV + BOM UTF-8
  - XLSX writer
  - Header style standar untuk sheet tabular
  - Centralized filename sanitizer
  - Centralized `Content-Type`

## Cross-Check Role/Permission API + Halaman Aktif

- API route export harus dijaga permission per domain:
  - Employee export: `employee.export`/permission domain employee
  - Payroll export: `payroll.view` atau permission setara
  - Reporting export: `reports.view` + policy tenant
- Halaman aktif wajib menampilkan tombol export hanya untuk role berizin.
- UI hide-only tidak cukup; API tetap harus return `403` jika role tidak berhak.

## Scope Dokumen Terkait

- Standar dan status: file ini + tracker.
- Snapshot endpoint dan gap: [EXPORT-FORMAT-AUDIT-2026-05-07.md](./EXPORT-FORMAT-AUDIT-2026-05-07.md).
- Kontrak API utama: `docs/api/openapi.yaml` + dokumen API per modul.
