# Overtime

## Ringkasan

Fitur overtime mengelola master tipe lembur, pengajuan lembur oleh employee atau admin, serta kalkulator estimasi lembur berdasarkan acuan PP 35/2021. Modul ini menjadi jembatan antara data presensi, kompensasi karyawan, dan payroll karena hasil perhitungan lembur memerlukan basis gaji serta komponen slip yang konsisten.

## Akses

- Employee: mengakses `/overtime-employee` dan hanya melihat request sendiri.
- HCM Admin: mengakses `/overtime`, `/overtime-master`, dan semua mutasi tipe/request lembur.
- Tenant owner/admin dengan jalur legacy membership tetap dapat melihat daftar lembur company selama berada pada konteks tenant aktif yang benar.

## UI Aktif

- `/overtime-master` - CRUD tipe lembur (`weekday_ot`, `holiday_ot`, dst).
- `/overtime-master` juga menyediakan tombol **Panduan perhitungan** (modal) untuk HR agar cepat melihat rumus dasar + contoh hitung.
- Di modal panduan terdapat **simulasi kalkulator UI-only** (tanpa simpan DB/API) untuk estimasi cepat.
- `/overtime` — **HCM admin**: daftar semua request, kolom karyawan, field policy/status di modal, kalkulator dengan pemilih karyawan (opsional) untuk auto-fill kompensasi.
- `/overtime-employee` — **non-admin**: hanya data sendiri (`scope=me`); tanpa pemilih karyawan di kalkulator; `/overtime` mengarahkan non-admin ke sini (sama pola dengan `/leaves` → `/leaves-employee`).

## Flow Bisnis End-to-End

1. HCM Admin mengatur master overtime type di `/overtime-master`.
2. Employee atau admin membuat overtime request melalui halaman yang sesuai scope-nya.
3. Kalkulator overtime menghitung estimasi upah lembur berdasarkan kompensasi karyawan dan tipe/hari kerja.
4. Backend memvalidasi konflik kebijakan, termasuk request type dan benturan dengan cuti yang sudah approved.
5. Hasil lembur yang lolos approval menjadi kandidat input ke payroll period/run berikutnya.

## Lifecycle Dan Keputusan Bisnis

- Request type: `employee_request`, `company_assignment`, dan `missed_log_correction` memisahkan asal kebutuhan lembur.
- Non-admin hanya boleh membuat `employee_request`.
- Team scope untuk admin mengikuti permission RBAC dan kompatibilitas legacy owner/admin tenant.
- Konflik cuti: lembur tidak boleh lolos bila ada approved leave pada tanggal yang sama untuk user dan tenant yang sama.
- Admin form lembur aktif mengirim numeric `users.id` untuk target employee; backend tetap menerima UUID sebagai fallback legacy, tetapi target user wajib anggota tenant aktif.

## Integrasi

- Employee Salary: kalkulator memakai `baseSalary + fixedAllowance` sebagai dasar upah sejam. Lihat `docs/features/employee-salary/README.md`.
- Payroll Salary Components: request lembur dapat menunjuk `hcm_salary_component_id` ke komponen `upah_lembur` atau kategori `overtime`. Lihat `docs/features/payroll-salary-components/README.md`.
- Payroll Runs: lembur approved yang masuk scope payroll sudah diagregasi ke payroll draft per periode melalui builder payroll, selama komponen dan kompensasi dasarnya valid. Lihat `docs/features/payroll-runs/README.md`.
- Leave & Holidays: overtime request diblok jika bertabrakan dengan leave approved pada tenant yang sama. Lihat `docs/features/leave-and-holidays/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## API utama (`/v1/hcm`)

- Master type:
  - `GET /overtime-types` (non-admin: hanya aktif)
  - `POST /overtime-types` (admin)
  - `PUT /overtime-types/{id}` (admin)
  - `DELETE /overtime-types/{id}` (admin)
- Request:
  - `GET /overtime-requests`
  - `POST /overtime-requests` — `userId` admin mengikuti kontrak aktif numeric `users.id` dengan fallback UUID legacy
  - `PUT /overtime-requests/{id}`
  - `DELETE /overtime-requests/{id}`
- Calculator:
  - `POST /overtime-requests/calculate`

### Otorisasi tenant (update 19 April 2026)

- Konteks tenant aktif (`X-Company-Id`/activeCompanyId) tetap wajib untuk endpoint overtime list.
- Team scope pada `/overtime` mengakui dua jalur admin:
  - Permission RBAC granular (`overtime.view`, `overtime.approve`, `attendance.admin`).
  - Kompatibilitas legacy admin/owner berbasis membership tenant (`company_users.role`) melalui helper `isHcmAdminForCompany`.
- Dengan ini, owner/admin tenant tetap bisa melihat daftar lembur company secara benar tanpa membuka akses lintas tenant.

## Existing Vs Target

- Existing: master overtime type, request admin/employee, calculator, dan negative policy scenario sudah aktif.
- Existing: agregasi lembur approved ke payroll draft payroll sudah aktif; area lanjutan yang tersisa lebih ke penguatan evidence operasional, review policy approval, dan verifikasi lintas modul payroll saat aturan bisnis berubah.

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
