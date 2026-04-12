# Overtime

## Scope

- Master overtime type
- Overtime request (admin + employee scope)
- Overtime policy negative scenario
- Overtime calculator (acuan PP 35/2021)

## UI Pages

- `/overtime-master` - CRUD tipe lembur (`weekday_ot`, `holiday_ot`, dst).
- `/overtime-master` juga menyediakan tombol **Panduan perhitungan** (modal) untuk HR agar cepat melihat rumus dasar + contoh hitung.
- Di modal panduan terdapat **simulasi kalkulator UI-only** (tanpa simpan DB/API) untuk estimasi cepat.
- `/overtime` — **HCM admin**: daftar semua request, kolom karyawan, field policy/status di modal, kalkulator dengan pemilih karyawan (opsional) untuk auto-fill kompensasi.
- `/overtime-employee` — **non-admin**: hanya data sendiri (`scope=me`); tanpa pemilih karyawan di kalkulator; `/overtime` mengarahkan non-admin ke sini (sama pola dengan `/leaves` → `/leaves-employee`).

## API utama (`/v1/hcm`)

- Master type:
  - `GET /overtime-types` (non-admin: hanya aktif)
  - `POST /overtime-types` (admin)
  - `PUT /overtime-types/{id}` (admin)
  - `DELETE /overtime-types/{id}` (admin)
- Request:
  - `GET /overtime-requests`
  - `POST /overtime-requests`
  - `PUT /overtime-requests/{id}`
  - `DELETE /overtime-requests/{id}`
- Calculator:
  - `POST /overtime-requests/calculate`

## Policy negative scenario

- `requestType`:
  - `employee_request`
  - `company_assignment` (lembur dadakan dari perusahaan)
  - `missed_log_correction` (karyawan lupa catat)
- `policyNote`: catatan alasan kebijakan/perbaikan.
- Non-admin hanya boleh `employee_request`.

## Formula lembur (acuan)

- Upah sejam = `(gaji pokok + tunjangan tetap) / 173`
- Hari kerja:
  - jam pertama `1.5x`
  - jam berikutnya `2x`
- Hari libur:
  - mengikuti segment multiplier lebih tinggi (5/6 hari kerja) sesuai matrix di service.

## Data model ringkas

- `hcm_overtime_types`
- `overtime_requests` (opsional FK `hcm_overtime_type_id`, plus policy fields)

## Frontend flow

- `frontend/resources/js/overtime-master-data.js`
- `frontend/resources/js/hcm-extras-data.js` (overtime section)
