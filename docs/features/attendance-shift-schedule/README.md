# Attendance, Shift, and Schedule

## Scope

- Attendance admin dan employee
- Shift master
- Schedule timing per user
- Timesheets

## API utama (`/v1/hcm`)

- Attendance admin:
  - `GET /attendance/admin`
  - `PUT /attendance/admin/record`
- Attendance employee:
  - `GET /attendance/me/today`
  - `GET /attendance/me/history`
  - `GET /attendance/me/stats`
  - `POST /attendance/me/punch` — **wajib** JSON `latitude` (-90…90) dan `longitude` (-180…180) (GPS perangkat). Disimpan ke `attendance_records` (`check_in_*` pada Punch In, `check_out_*` pada Punch Out).
  - `POST /attendance/me/break`
  - `POST /attendance/me/correction-request`
- Shift master:
  - `GET/POST /shifts`
  - `PUT/DELETE /shifts/{id}`
- Schedule timing:
  - `GET /schedule-timing`
  - `PUT/DELETE /schedule-timing/{userId}`

## Data model ringkas

- `attendance_records` (termasuk `check_in_latitude` / `check_in_longitude` / `check_out_latitude` / `check_out_longitude`, nullable `decimal(10,7)`)
- `hcm_shifts`
- `hcm_schedule_timings` (FK opsional `hcm_shift_id`)

## Frontend flow

- `frontend/resources/js/attendance-data.js`
- `frontend/resources/js/shift-master-data.js`
- Halaman **`/attendance-employee`**: sebelum Punch In/Out, browser meminta **Geolocation** (`enableHighAccuracy`); koordinat ditampilkan di peta **Leaflet 1.9** dengan tile **OpenStreetMap** (gratis). CSS/JS Leaflet dimuat dari **unpkg** hanya pada route tersebut (`footer-scripts.blade.php`). Respons `GET /attendance/me/today` menyertakan `checkInLatitude` / `checkInLongitude` / `checkOutLatitude` / `checkOutLongitude` untuk menampilkan lokasi tersimpan.
- **Catatan:** geolokasi umumnya membutuhkan **HTTPS** (atau `localhost`). Input admin manual (`PUT /attendance/admin/record`) tidak mengisi kolom GPS.

## Aturan bisnis penting

- `schedule-timing` dapat override manual jam atau refer ke `shiftId`.
- Aksi admin-sensitive mengikuti guard HCM admin (`EnsuresHcmAdmin`).
