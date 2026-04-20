# Attendance

## Ringkasan

Feature ini menjadi dokumen utama untuk menu absensi yang dipakai harian oleh employee dan admin. Scope utamanya mencakup punch in/out berbasis GPS, kartu status hari ini di halaman employee, daftar absensi admin, laporan absensi live/archive, koreksi attendance, dan detail lokasi yang dibaca lintas menu.

Dokumen teknis pendamping:
- `IMPLEMENTATION.md` untuk controller, route, data model, dan test surface.
- `tracker.md` untuk snapshot readiness, evidence, dan gap aktif.
- `LOCATION-FEATURE.md` untuk detail khusus reverse geocoding dan penyimpanan nama lokasi.

## Akses

- Employee: `/attendance-employee`, `GET /attendance/me/*`, `POST /attendance/me/punch`, `POST /attendance/me/break`, `POST /attendance/me/correction-request`.
- HCM Admin: `/attendance-admin`, `/attendance-report`, `GET /attendance/admin`, `PUT /attendance/admin/record`, dan akses baca selfie download admin.
- Archive snapshot report tetap admin-only karena menggunakan snapshot HCM di Reports Hub.

## UI Aktif

- `/attendance-employee` — kartu status hari ini, punch in/out, break toggle, peta lokasi, riwayat 30 hari, koreksi, dan tombol selfie.
- `/attendance-admin` — daftar absensi harian semua karyawan, filter tanggal/departemen/status, edit manual, dan unduh selfie record.
- `/attendance-report` — report absensi admin dengan mode `Live Data` atau `Archive Snapshot` dari Reports Hub.
- JS aktif: `frontend/resources/js/attendance-data.js`.
- Blade aktif: `backend/resources/views/attendance-employee.blade.php`, `attendance-admin.blade.php`, `attendance-report.blade.php`.

## Flow Bisnis End-to-End

1. Employee membuka `/attendance-employee` dan frontend memanggil `GET /v1/hcm/attendance/me/today`, `stats`, dan `history` untuk memuat status kerja hari ini.
2. Saat employee melakukan punch in/out, browser meminta GPS lalu mengirim `latitude` dan `longitude` ke `POST /v1/hcm/attendance/me/punch`.
3. Backend menyimpan record attendance tenant aktif, menghitung status dasar (`present`, `needs_review`, selesai), dan mengembalikan payload yang langsung dipakai UI.
4. Jika break dimulai atau diakhiri, employee menekan tombol break dan backend mengubah `break_minutes` pada record hari itu.
5. Jika ada anomali, employee dapat meminta koreksi setelah check-out; admin membaca detail correction dari halaman `/attendance-admin`.
6. Admin dapat melakukan upsert manual untuk memperbaiki jam kerja pada tanggal tertentu tanpa mengubah fakta bahwa sumber GPS berasal dari perangkat employee.
7. Data attendance live dipakai lagi oleh `/attendance-report` untuk analitik cepat, atau dibaca dari snapshot archive bila admin ingin point-in-time evidence.

## Lifecycle Dan Keputusan Bisnis

- `punchState` di UI employee mengikuti alur `none` → `in` → `done`.
- Punch in/out wajib membawa GPS; input manual admin tidak mengisi koordinat GPS.
- Punch out yang terlalu cepat dapat ditandai `needs_review` agar tidak terlihat sebagai hari kerja normal.
- Correction request hanya boleh diajukan setelah check-out ada.
- Report archive hanya boleh membaca snapshot bertipe `attendance` dengan status `completed`; snapshot salah tipe atau belum selesai harus ditolak di UI.
- Tenant scope bersifat ketat: employee hanya membaca record miliknya sendiri, dan admin hanya membaca tenant aktif.

## Integrasi

- Attendance Selfie: selfie hanya boleh diambil setelah attendance hari itu dimulai. Lihat `../attendance-selfie/README.md`.
- Attendance Shift Schedule: jadwal per-user dan timesheet admin memengaruhi konteks absensi dan report. Lihat `../attendance-shift-schedule/README.md`.
- Reporting: report live/archive absensi memakai snapshot HCM dan tidak boleh bocor lintas tenant. Lihat `../reporting/README.md`.
- Leave & Holidays: approval leave pada tenant yang sama dapat memengaruhi interpretasi status absence. Lihat `../leave-and-holidays/README.md`.
- Performance: metrik hadir/terlambat/leave menjadi input review cycle. Lihat `../performance/README.md`.

## Kontrak API

Base path: `/v1/hcm`

- Employee/self:
  - `GET /attendance/me/today`
  - `GET /attendance/me/history`
  - `GET /attendance/me/stats`
  - `POST /attendance/me/punch`
  - `POST /attendance/me/break`
  - `POST /attendance/me/correction-request`
- Admin:
  - `GET /attendance/admin`
  - `PUT /attendance/admin/record`
  - `GET /attendance/admin/records/{id}/selfie/download`
- Source of truth kontrak: `docs/api/hcm-attendance-api.md`.

## Existing Vs Target

- Existing: halaman employee/admin/report sudah aktif, tenant scope write admin sudah diperketat, location name sudah tersimpan, dan negative flow archive snapshot salah tipe sudah ditolak di UI.
- Existing: input manual admin memakai numeric `users.id` dan tetap ditolak jika target user bukan member tenant aktif.
- Target: approval chain koreksi, analytics report yang lebih kaya, dan evidence E2E browser yang lebih formal masih bisa ditambah tanpa mengubah kontrak absensi inti.
