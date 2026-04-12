# Leave and Holidays

## Scope

- Holiday master
- Leave request CRUD
- Leave settings (types + custom policies)

## API utama (`/v1/hcm`)

- Holiday (semua endpoint **hcmAdmin** saja — `HcmHolidayController` + trait `EnsuresHcmAdmin`):
  - `GET/POST /holidays`
  - `PUT/DELETE /holidays/{id}`
  - `POST /holidays/sync-indonesia` — sinkron baseline hari libur nasional dari libur.deno.dev (`year` opsional; default tahun berjalan).
- Leave requests:
  - `GET /leave-type-options` — daftar tipe cuti **aktif** (`is_enabled`) untuk dropdown form; **semua user terautentikasi** (bukan admin settings penuh di `/leave-settings`).
  - `GET /leave-requests` — query opsional `scope=me` (hanya baris milik user). Tanpa `scope=me`, non-**hcmAdmin** (`User::isHcmAdmin()`, sama dengan `GET /auth/me` → `hcmAdmin`) tetap hanya melihat cuti sendiri.
  - `POST /leave-requests` — field opsional `userId` hanya untuk **hcmAdmin** (buat cuti atas nama karyawan lain).
  - `PUT /leave-requests/{id}` — pemilik hanya boleh mengubah field cuti sendiri selagi `status=pending`; **hcmAdmin** boleh mengubah `status`/`notes` untuk pengajuan milik orang lain (approve/decline).
  - `DELETE /leave-requests/{id}` — hanya pemilik, hanya `pending`.
- Leave settings (semua endpoint **hcmAdmin** saja — `HcmLeaveSettingController` + `EnsuresHcmAdmin`):
  - `GET /leave-settings`
  - `PUT /leave-settings/types/{code}`
  - `POST /leave-settings/custom-policies`
  - `PUT/DELETE /leave-settings/custom-policies/{id}`

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
- `/leave-settings` — tipe + custom policies (API di atas).
- `/holidays` — master libur; non-admin diarahkan ke `/employee-dashboard` (selaras API).
- Halaman `/holidays` menyediakan input tahun + tombol **Sync ID** untuk menarik data nasional ke tabel lokal.
- Kolom tabel holidays menampilkan `source` (`manual` / `api`) dan `lastSyncedAt`; edit manual akan mengembalikan source ke `manual`.
- `/leave-settings` — non-admin diarahkan ke `/employee-dashboard` sebelum memuat data.

## Halaman terkait cuti tanpa API khusus (gap)

- `/leave-report` — placeholder: belum ada endpoint laporan agregat; tabel kosong sengaja.
- `/leave-type` — masih halaman settings theme generik; **bukan** mirror `/leave-settings`; tidak memuat `leave-settings-data.js`.

## Catatan

- Dokumen legacy masih ada yang menyebut prefix `/v1/leave/*`; implementasi runtime saat ini dominan di `/v1/hcm/*`.
