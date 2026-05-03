# Leave and Holidays

## Ringkasan

Fitur ini mencakup master hari libur, pengajuan cuti, katalog tipe cuti, dan custom leave policy per tenant. Modul leave menjadi sumber kebenaran status cuti yang kemudian memengaruhi attendance, metrik performa, dan beberapa pembatasan proses seperti overtime di tanggal yang sama.

## Akses

- Employee: dapat melihat dan mengelola cuti sendiri melalui `scope=me`, selama status request masih `pending` untuk edit/delete.
- HCM Admin: dapat melihat seluruh cuti tenant, membuat cuti atas nama karyawan lain yang masih berada di tenant aktif, mengubah status approve/decline, serta mengelola holiday dan leave settings.
- Web admin dan employee dipisahkan lewat halaman `/leaves` dan `/leaves-employee`.
- `/leave-report`, `/leave-settings`, dan `/holidays` adalah halaman admin-only; non-admin diarahkan ke `/employee-dashboard`.

## UI Aktif

- `/leaves` untuk admin.
- `/leaves-employee` untuk employee.
- `/leave-type`, `/leave-settings`, dan `/holidays` untuk katalog/settings admin.
- `/leave-report` untuk ringkasan cuti admin dengan mode `live` dan `archive`.
- JS aktif: `frontend/resources/js/hcm-extras-data.js` dan `frontend/resources/js/leave-settings-data.js`.

## Flow Bisnis End-to-End

1. Admin mengelola hari libur, tipe cuti, dan custom policy tenant.
2. Employee mengajukan cuti dari `/leaves-employee` atau admin membuat cuti atas nama karyawan dari `/leaves`.
3. Backend menghitung hari kerja efektif, mengecek leave balance bila tipe cuti memotong saldo, lalu menyimpan request.
4. HCM Admin meng-approve atau menolak request.
5. Saat status menjadi `approved`, attendance pada tenant yang sama diubah menjadi `on_leave` untuk hari kerja yang relevan.

## Lifecycle Dan Keputusan Bisnis

- Pending: employee masih boleh edit/delete request sendiri.
- Approved/declined: mutasi status untuk request orang lain hanya boleh oleh HCM Admin.
- Balance-aware leave: tipe cuti dengan `deduct_from_balance=true` wajib lolos cek saldo.
- Tenant scope: perubahan attendance dan leave balance hanya boleh menyentuh data company yang sama.

## Integrasi

- Attendance & Shift: approve leave mengubah attendance menjadi `on_leave`, reversal mengembalikan record tenant yang sama. Lihat `docs/features/attendance-shift-schedule/README.md`.
- Overtime: overtime diblok jika ada approved leave pada tanggal yang sama dengan tenant dan user yang sama. Lihat `docs/features/overtime/README.md`.
- Performance: review cycle menghitung leave frequency dan absenteeism dari leave approved tenant-scoped. Lihat `docs/features/performance/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## API utama (`/v1/hcm`)

- Holiday (semua endpoint **hcmAdmin** saja — `HcmHolidayController` + trait `EnsuresHcmAdmin`):
  - `GET/POST /holidays`
  - `PUT/DELETE /holidays/{id}`
  - `POST /holidays/sync-indonesia` — sinkron baseline hari libur nasional dari libur.deno.dev (`year` opsional; default tahun berjalan).
- Leave requests:
  - `GET /leave-type-options` — daftar tipe cuti **aktif** (`is_enabled`) untuk dropdown form; **semua user terautentikasi** (bukan admin settings penuh di `/leave-settings`).
  - `GET /leave-requests` — query opsional `scope=me` (hanya baris milik user). Tanpa `scope=me`, non-**hcmAdmin** (`User::isHcmAdmin()`, sama dengan `GET /auth/me` → `hcmAdmin`) tetap hanya melihat cuti sendiri.
  - `GET /employee-leave-balance` — kartu saldo pada modal leave; admin hanya boleh membaca saldo user lain yang masih berada di tenant aktif.
  - `POST /leave-requests` — field opsional `userId` hanya untuk **hcmAdmin** (buat cuti atas nama karyawan lain di tenant aktif). UI admin aktif mengirim `users.id` numeric; backend masih menerima UUID sebagai fallback legacy. Validasi balance: jika tipe cuti `deduct_from_balance=true` dan ada record balance, cek saldo cukup.
  - `PUT /leave-requests/{id}` — pemilik hanya boleh mengubah field cuti sendiri selagi `status=pending`; **hcmAdmin** boleh mengubah `status`/`notes` untuk pengajuan milik orang lain (approve/decline).
  - `DELETE /leave-requests/{id}` — hanya pemilik, hanya `pending`.
- Leave type catalog:
  - `GET /leave-types` — katalog admin per-company (`company_id` scope) dari `hcm_leave_type_settings`; permission `leave.type`; dipakai juga oleh dropdown `GET /leave-type-options`.
  - `POST /leave-types` / `PUT /leave-types/{id}` / `DELETE /leave-types/{id}` — CRUD admin per-company dari halaman Blade `/leave-type`; permission `leave.type` semua verb.
- Leave settings (semua endpoint **hcmAdmin** saja — `HcmLeaveSettingController` + `EnsuresHcmAdmin`):
  - `GET /leave-settings`
  - `PUT /leave-settings/types/{code}`
  - `POST /leave-settings/custom-policies`
  - `PUT/DELETE /leave-settings/custom-policies/{id}`

## Existing Vs Target

- Existing: holiday sync nasional, leave CRUD tenant-scoped, leave type catalog, dan custom policy admin sudah aktif.
- Existing: integrasi attendance, overtime conflict, dan performance metrics sudah berjalan di backend.
- Existing: `/leave-report` sudah bisa menampilkan agregat live dengan menarik seluruh halaman `/v1/hcm/leave-requests` dan dapat membaca archive snapshot yang statusnya `completed`.
- Target: belum ada endpoint laporan leave khusus; mode live masih menghitung agregat di client dari endpoint list yang sudah ada.

## Data model ringkas

- `holidays`
- `leave_requests`
- `hcm_leave_type_settings`
- `hcm_leave_custom_policies`

## Frontend flow

- `frontend/resources/js/hcm-extras-data.js` (holiday + leave request) — salin ke `backend/public/build/js/` jika diubah.
- `frontend/resources/js/leave-settings-data.js` — idem.

Halaman Blade:

- `/leaves` — daftar admin (kolom karyawan); setelah `GET /auth/me`, pengguna tanpa `hcmAdmin` diarahkan ke `/leaves-employee` agar layout tabel selaras dengan data (scope `me`). Dropdown karyawan di modal Add Leave memuat `GET /v1/hcm/employees` dengan **`perPage` maks. 100** (sesuai validasi API); jika karyawan >100, JS mengambil halaman berikutnya sampai habis.
- `/leaves-employee` — pengajuan cuti sendiri; `leave-modals` dengan `arcavLeaveAdmin` false. Jenis cuti: `<select>` diisi dari `GET /leave-type-options` (nilai = **nama** tipe, selaras kolom `leave_requests.leave_type`).
- `/leave-type` — halaman admin katalog leave type yang membaca `hcm_leave_type_settings`, menampilkan data real, dan menjalankan CRUD via `/v1/hcm/leave-types`.
- `/leave-settings` — tipe + custom policies (API di atas).
- `/holidays` — master libur; non-admin diarahkan ke `/employee-dashboard` (selaras API).
- Halaman `/holidays` menyediakan input tahun + tombol **Sync ID** untuk menarik data nasional ke tabel lokal.
- Kolom tabel holidays menampilkan `source` (`manual` / `api`) dan `lastSyncedAt`; edit manual akan mengembalikan source ke `manual`.
- `/leave-settings` — non-admin diarahkan ke `/employee-dashboard` sebelum memuat data.

## Cross-module integration

- **Leave approval → Attendance**: when leave status changes to `approved`, the system marks attendance records for the same `company_id` and `user_id` as `on_leave` for each working day in the leave range. Reversal of approved leave restores `on_leave` records to `absent` only for the same tenant. Leave approval now strictly operates within the leave request's tenant scope and preserves attendance records belonging to other companies.
- **Overtime conflict**: overtime requests are blocked if an approved leave exists on the same date for the same `company_id` and `user_id`. Error code returned is `OT_ON_LEAVE_CONFLICT`. Admin overtime form currently sends numeric `users.id`; backend accepts numeric ids and still keeps UUID fallback for legacy callers, but target user must remain inside the active tenant.
- **Performance review metrics**: `HcmPerformanceController` calculates leave frequency and absenteeism for approved leaves inside a review cycle, scoped to `user_id` and tenant.
- **Role/permission guidebook**: leave-related endpoints and UI flows respect tenant membership and HCM role context. `userId` on `POST /leave-requests` dan `GET /employee-leave-balance` hanya diterima untuk user yang masih berada di tenant aktif; non-admin users always see only their own leave data.

## Tenant safety and guidebook isolation

- All leave and attendance flows are scoped by `company_id`.
- No tenant/customer guidebook details are exposed across tenants.
- The active feature guide should remain tenant-specific: admin-only flows and user-only workflows are separated in UI and API documentation.
- `GET /leave-requests?scope=me` returns only the current employee's own requests, while admin endpoints remain tenant-scoped and do not expose data from other companies.

## Halaman terkait cuti tanpa API khusus (gap)

- `/leave-report` — belum punya endpoint laporan agregat khusus; mode live menghitung ringkasan dari paginated `/leave-requests`, sedangkan mode archive hanya menerima snapshot completed yang tipenya sesuai. Halaman ini sekarang dikunci `hcm.web.admin` agar konsisten dengan matriks role aktif.

## Catatan

- Dokumen legacy masih ada yang menyebut prefix `/v1/leave/*`; implementasi runtime saat ini dominan di `/v1/hcm/*`.
