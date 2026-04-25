# Attendance, Shift, and Schedule

## Ringkasan

Fitur ini mendokumentasikan menu attendance admin yang berhubungan dengan master shift, override jam kerja per user, dan timesheets ringkas. Dokumen ini fokus pada surface admin operasional yang menentukan jadwal kerja, mempengaruhi interpretasi keterlambatan, dan menjadi input untuk attendance report serta audit presensi lintas tenant.

Dokumen teknis pendamping:
- `IMPLEMENTATION.md` untuk route, identifier contract, data model, dan coverage test.
- `tracker.md` untuk snapshot readiness, evidence, dan gap aktif.
- `../attendance/README.md` untuk attendance core (employee/admin/report).

## Akses

- HCM Admin: seluruh CRUD shift master, list/update schedule timing, dan halaman `/timesheets`.
- Employee tidak memiliki akses ke mutation shift master, schedule timing, maupun halaman timesheets admin.
- Guard web admin berlaku pada surface `/timesheets`, `/schedule-timing`, dan aksi admin yang memutasi attendance/jadwal.

## UI Aktif

- `/timesheets` — rekap jam kerja admin dengan filter tanggal, departemen, dan user.
- `/schedule-timing` — assign shift master atau override manual jam kerja per user.
- Surface shift master admin — CRUD `hcm_shifts` dari JS shift master aktif.
- JS aktif: `frontend/resources/js/attendance-data.js` dan `frontend/resources/js/shift-master-data.js`.

## Flow Bisnis End-to-End

1. HCM Admin menyiapkan shift master yang menjadi template jam masuk/pulang standar tenant.
2. Bila ada user yang perlu jadwal khusus, admin membuka `/schedule-timing` untuk mengaitkan `shiftId` atau menetapkan override manual jam kerja.
3. Employee melakukan attendance harian dengan konteks jadwal aktif tersebut; backend kemudian menghitung keterlambatan dan status kerja berdasarkan data shift/schedule yang berlaku.
4. Admin membuka `/timesheets` untuk memantau hasil kerja harian/range tanggal, menemukan anomali jam kerja, lalu kembali ke attendance admin atau schedule timing jika perlu koreksi.
5. Reporting dan modul payroll/lembur membaca data attendance yang sudah dipengaruhi oleh shift/schedule ini.

## Lifecycle Dan Keputusan Bisnis

- Shift master menyimpan template jam kerja reusable di tenant aktif.
- Template shift global (`company_id = null`) hanya boleh dimutasi global admin; tenant admin tidak boleh update/delete template global.
- `schedule-timing` boleh mengacu ke `shiftId` atau override manual jam kerja; keduanya tidak boleh menarget user di luar tenant aktif.
- Override `schedule-timing` disimpan per kombinasi company+user agar tidak saling menimpa antar tenant saat user yang sama aktif di lebih dari satu company.
- Timesheets hanya valid bila range tanggal masuk akal; `dateTo < dateFrom` harus ditolak.
- UI shift master hanya menampilkan aksi create/edit/delete jika user memiliki izin `schedule.manage` atau `schedule.admin`.
- Kontrak identifier aktif adalah numeric `users.id` dan numeric `hcm_shifts.id`, bukan UUID custom di payload mutation.

## Integrasi

- Leave & Holidays: approval leave mengubah attendance pada tenant yang sama menjadi `on_leave`; pembatalan approval mengembalikan status attendance yang relevan. Lihat `docs/features/leave-and-holidays/README.md`.
- Reporting: data attendance dan timesheet menjadi input agregasi snapshot/report HCM. Lihat `docs/features/reporting/README.md`.
- Performance: metrik absenteeism dan frekuensi leave di review cycle bergantung pada data attendance dan leave approval. Lihat `docs/features/performance/README.md`.
- Attendance core: punch status employee dan koreksi attendance admin membaca hasil jadwal ini. Lihat `../attendance/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

API utama (`/v1/hcm`)

- Shift master:
  - `GET/POST /shifts`
  - `PUT/DELETE /shifts/{id}`
- Schedule timing:
  - `GET /schedule-timing`
  - `PUT/DELETE /schedule-timing/{userId}` — `{userId}` memakai `users.id`, `shiftId` memakai `hcm_shifts.id`, dan write hanya boleh untuk member tenant aktif
- Timesheets:
  - `GET /timesheets` — range tanggal admin, dengan guard `dateTo >= dateFrom`

Source of truth kontrak:
- `docs/api/hcm-shift-schedule-api.md`
- `docs/api/hcm-attendance-api.md`

## Existing Vs Target

- Existing: shift master, schedule timing, dan timesheets admin sudah aktif dengan FE/BE contract numeric identifier yang seragam.
- Existing: tenant guard write schedule aktif, sorting start-time schedule stabil terhadap pagination, dan non-global tidak bisa memutasi template shift global.
- Existing: UI shift master sekarang menegakkan permission write agar user view-only tidak melihat aksi mutasi.
- Target: audit trail perubahan jadwal yang lebih kaya dan evidence manual browser E2E masih bisa ditambah tanpa mengubah kontrak inti.

## Data model ringkas

- `attendance_records` (termasuk `check_in_latitude` / `check_in_longitude` / `check_out_latitude` / `check_out_longitude`, nullable `decimal(10,7)`)
- `hcm_shifts`
- `hcm_schedule_timings` (FK opsional `hcm_shift_id`)

## Frontend flow

- `frontend/resources/js/attendance-data.js`
- `frontend/resources/js/shift-master-data.js`
- Halaman `/timesheets`: frontend memblok submit filter bila `dateTo` lebih kecil dari `dateFrom` agar query invalid tidak dikirim ke backend.
- Halaman `/schedule-timing`: frontend mengirim `shiftId` numeric sesuai kontrak backend dan menampilkan state existing vs override per user.
- Shift master tetap dikelola dari bundle admin khusus agar CRUD template jam kerja tidak tercampur dengan state attendance employee.

## Aturan bisnis penting

- `schedule-timing` dapat override manual jam atau refer ke `shiftId`.
- Aksi admin-sensitive mengikuti guard HCM admin (`EnsuresHcmAdmin`).
- Seluruh write schedule dan shift harus tetap berada di company aktif.
