# Attendance, Shift, and Schedule

## Ringkasan

Fitur ini mendokumentasikan menu attendance admin yang berhubungan dengan master shift, override jam kerja per user, dan timesheets ringkas. Dokumen ini fokus pada surface admin operasional yang menentukan jadwal kerja, mempengaruhi interpretasi keterlambatan, dan menjadi input untuk attendance report serta audit presensi lintas tenant.

Dokumen teknis pendamping:
- `IMPLEMENTATION.md` untuk route, identifier contract, data model, dan coverage test.
- `tracker.md` untuk snapshot readiness, evidence, dan gap aktif.
- `AI-WORKFORCE-AGENT-BLUEPRINT.md` untuk prompt core agent, split agent (scheduler/analyzer/insight/UI), input-output contract, dan guardrail.
- `AI-WORKFORCE-OUTPUT-SCHEMA.json` untuk schema validasi output AI sebelum dipakai UI atau mutation flow admin.
- `../attendance/README.md` untuk attendance core (employee/admin/report).

Fitur ini juga mencakup endpoint smart planner backend (`POST /v1/hcm/smart-attendance-shifting/generate`) untuk membantu admin menyusun jadwal mingguan, mendeteksi anomali attendance, dan menghasilkan rekomendasi fairness/fatigue berbasis data runtime tenant.

## Akses

- HCM Admin: seluruh CRUD shift master, list/update schedule timing, dan halaman `/timesheets`.
- Employee tidak memiliki akses ke mutation shift master, schedule timing, maupun halaman timesheets admin.
- Guard web admin berlaku pada surface `/timesheets`, `/schedule-timing`, dan aksi admin yang memutasi attendance/jadwal.

## UI Aktif

- `/timesheets` — rekap jam kerja admin dengan filter tanggal, departemen, dan user.
- `/schedule-timing` — assign shift master atau override manual jam kerja per user, plus panel Smart Attendance Planner untuk generate rekomendasi jadwal mingguan.
- `/schedule-timing` juga punya view `Calendar` untuk melihat draft planner (M/A/N/OFF) dan hari libur aktif dari menu Holidays dalam format kalender kerja.
- Smart planner di `/schedule-timing` mendukung horizon `single week` dan `end of year` (rolling per minggu) agar admin bisa menyusun draft jadwal lebih panjang tanpa pindah halaman.
- Smart planner calendar **tidak otomatis semua user**: event draft hanya mengikuti scope planner terakhir (`all`, `department`, atau `karyawan pilihan`).
- Surface shift master admin — CRUD `hcm_shifts` dari JS shift master aktif.
- JS aktif: `frontend/resources/js/attendance-data.js` dan `frontend/resources/js/shift-master-data.js`.

## Flow Bisnis End-to-End

1. HCM Admin menyiapkan shift master yang menjadi template jam masuk/pulang standar tenant.
2. Bila ada user yang perlu jadwal khusus, admin membuka `/schedule-timing` untuk mengaitkan `shiftId` atau menetapkan override manual jam kerja.
3. Employee melakukan attendance harian dengan konteks jadwal aktif tersebut; backend kemudian menghitung keterlambatan dan status kerja berdasarkan data shift/schedule yang berlaku.
4. Admin membuka `/timesheets` untuk memantau hasil kerja harian/range tanggal, menemukan anomali jam kerja, lalu kembali ke attendance admin atau schedule timing jika perlu koreksi.
5. Reporting dan modul payroll/lembur membaca data attendance yang sudah dipengaruhi oleh shift/schedule ini.
6. Admin dapat menjalankan smart planner untuk minggu tertentu dengan flow yang lebih aman untuk HR: pilih pola kerja, pilih sasaran draft (`all`, `department`, atau karyawan pilihan), lalu generate draft jadwal + alert risiko sebelum mutasi jadwal manual dilakukan.
7. Admin dapat beralih ke view kalender untuk melihat distribusi draft per tanggal, mengecek overlap dengan hari libur, dan melakukan review visual sebelum commit perubahan jadwal.
8. Untuk seasonal planning, admin dapat pilih horizon `Generate sampai akhir tahun`; sistem mengeksekusi planner per minggu sampai 31 Desember, lalu menggabungkan hasilnya menjadi satu draft agregat untuk review kalender.
9. Sebelum publish, admin meninjau `Preview Diff Dominant Shift (Before/After)` untuk melihat user mana yang benar-benar berubah dari schedule aktif ke dominant shift draft.
10. Admin meninjau `Conflict Resolver (Pre-publish)`; jika ada conflict kritikal (misalnya unmet coverage, violation planner, overlap hari libur, rest-gap, night-to-morning), tombol publish dikunci sampai checkbox `Force apply` dicentang.
11. Setelah draft dianggap cukup baik, admin dapat memilih publish mode:
- `Apply Dominant Shift per User` untuk update baseline `schedule-timing` per user.
- `Publish Roster Harian` untuk menulis roster bertanggal per user+tanggal ke `hcm_schedule_rosters`.

## Lifecycle Dan Keputusan Bisnis

- Shift master menyimpan template jam kerja reusable di tenant aktif.
- Shift master mendukung pola lintas hari (`overnight`) sehingga end time boleh lebih kecil dari start time selama tidak sama.
- Template shift global (`company_id = null`) hanya boleh dimutasi global admin; tenant admin tidak boleh update/delete template global.
- `schedule-timing` boleh mengacu ke `shiftId` atau override manual jam kerja; keduanya tidak boleh menarget user di luar tenant aktif.
- Override `schedule-timing` disimpan per kombinasi company+user agar tidak saling menimpa antar tenant saat user yang sama aktif di lebih dari satu company.
- Smart planner settings (`/smart-attendance-shifting/settings`) tetap menerima kontrak request/response yang sama, tetapi persistence backend sekarang menyimpan tenant dan actor dalam dual-key (`*_id` legacy + `*_uuid`) agar hubungan data tetap bisa ditegakkan pada environment UUID-cutover.
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
- Smart planner:
  - `POST /smart-attendance-shifting/generate` — HCM admin only; output `schedule_generation`, `attendance_analysis`, `recommendation`, dan `explanation`
  - `GET/PUT /smart-attendance-shifting/settings` — default rules + transition matrix per-tenant
  - `POST /smart-attendance-shifting/publish-roster` — persist draft ke roster harian bertanggal
  - `GET /schedule-rosters` — baca roster harian bertanggal untuk review/pagination

Source of truth kontrak:
- `docs/api/hcm-shift-schedule-api.md`
- `docs/api/hcm-attendance-api.md`

## Existing Vs Target

- Existing: shift master, schedule timing, dan timesheets admin sudah aktif dengan FE/BE contract numeric identifier yang seragam.
- Existing: tenant guard write schedule aktif, sorting start-time schedule stabil terhadap pagination, dan non-global tidak bisa memutasi template shift global.
- Existing: UI shift master sekarang menegakkan permission write agar user view-only tidak melihat aksi mutasi.
- Existing: smart planner backend sudah tersedia untuk generate draft jadwal + analisis anomali attendance berbasis data tenant.
- Existing: smart planner frontend sudah mendukung batch rolling per minggu sampai akhir tahun dan menggabungkan output ke satu draft agregat untuk review kalender.
- Existing: stage 3 publish workflow tersedia dalam mode aman: apply dominant shift dari draft ke `schedule-timing` per user, dengan preview diff before/after dan conflict gate `force apply` untuk conflict kritikal.
- Existing: stage 4 konfigurabilitas planner aktif dengan UI yang aman:
  - Default rules + transition matrix tersimpan per-tenant (endpoint GET/PUT `/smart-attendance-shifting/settings`).
  - Publish roster harian bertanggal tersedia (endpoint POST `/smart-attendance-shifting/publish-roster`).
  - Roster index siap untuk pagination review (endpoint GET `/schedule-rosters`).
  - Scope planner sekarang memakai entitas yang lebih deterministik untuk HR:
    - `All`: semua employee aktif tenant.
    - `Department`: employee aktif yang punya `departmentId/departmentName` pada employee directory tenant.
    - `Karyawan pilihan (advanced)`: path khusus bila admin memang perlu membatasi draft hanya ke employee tertentu lewat multi-select directory.
  - Free-text `team keyword` tidak lagi dipakai di planner karena tidak ada master scope team khusus di flow ini; sebelumnya field itu hanya melakukan pencarian string pada snapshot employee dan terbukti rancu untuk HR.
  - Planner directory sekarang membaca seluruh page employee directory (`/v1/hcm/employees`) sampai `meta.total` terpenuhi, bukan berhenti diam-diam di page pertama.
  - Persistence settings planner sudah dual-write `company_id` + `company_uuid` dan actor `*_user_id` + `*_user_uuid`, sehingga relasi tidak lagi bergantung pada uniqueness `companies.id`/`users.id`.
  - Panel `Planner Defaults & Transition Rules` tetap punya edit mode UX:
    - **View mode** (default): semua input disabled, hanya tampil konfigurasi yang tersimpan, tombol `Edit` aktif.
    - **Edit mode**: ketika admin klik `Edit`, semua input field & transition checkboxes enabled, tombol `Simpan`, `Cancel`, dan `Reset` tampil. Tombol Generate + Publish disabled untuk mencegah trigger yang tidak disengaja.
    - Detail flow dan arti mode/scope dipindahkan ke tombol `Panduan planner` agar surface utama tidak noisy.
- Existing: label shift pada kalender/draft sudah membaca metadata shift runtime (`/v1/hcm/shifts`) termasuk `shiftType`, bukan hardcode slot 07/15/23.
- Existing: formula planner sudah dituning agar lebih employee-safe:
  - Batas consecutive work day (`max_consecutive_work_days`) ikut divalidasi selain batas total work day mingguan.
  - Illegal transition mengikuti matrix transition (`illegal_transition_rules`) berbasis `shift_type` aktual, bukan hanya heuristik night->morning.
  - Ranking kandidat menurunkan risiko kelelahan dengan mempertimbangkan work streak, short-rest events, dan backward rotation events.
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
