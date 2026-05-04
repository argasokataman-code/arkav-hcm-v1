# Payroll — Gaji karyawan (kompensasi bulanan)

## Ringkasan

Halaman **`/employee-salary`** memberi HCM Admin tampilan terpusat untuk **gaji pokok** (`baseSalary`) dan assignment payroll item per karyawan, konsisten dengan **`GET/PUT /v1/hcm/employees`** dan halaman detail karyawan.

Mulai update April 2026, halaman ini juga memfasilitasi **assignment payroll item custom per karyawan** (tunjangan/potongan) tanpa mengubah katalog global payroll item.

## Dokumentasi QA

- E2E browser flow: `docs/features/employee-salary/E2E-TESTING.md`
- Status snapshot & evidence formal: `docs/features/employee-salary/tracker.md`

## Akses

- **Web:** route di grup middleware **`hcm.web.admin`** — pengguna terautentikasi yang bukan `User::isHcmAdmin()` diarahkan ke `/employee-dashboard` (sama seperti `/payroll`).
- **API:** list employees dan mutasi kompensasi hanya **HCM Admin** (`EnsuresHcmAdmin` pada `HcmEmployeeController`).

## UI

- Blade: `employee-salary.blade.php`.
- Partial modal: `hcm/partials/employee-salary-compensation-modal.blade.php`.
- JS: `employee-salary-data.js` (sumber `frontend/resources/js/`, salinan di `backend/public/build/js/`).
- Skrip dimuat dari `footer-scripts.blade.php` jika `Route::is(['employee-salary'])`.
- Non-admin: redirect ke `/employee-dashboard` setelah `GET /v1/identity/auth/me` (lapisan ganda dengan middleware web).

## Perilaku

- Tabel: data dari **`GET /v1/hcm/employees`** (paginasi, `search`, `status`), termasuk **`departmentName`** (FK ke master departemen).
- Kolom **Dasar / bln** = `baseSalary`; tunjangan tetap operasional tidak lagi disimpan di salary profile dan harus diatur lewat allowance governance/payroll item assignment.
- **Set kompensasi:** modal dengan dropdown karyawan (agregasi halaman API hingga batas aman).
- **Edit** baris: modal yang sama, tanpa dropdown.
- **Custom payroll item per karyawan:** di modal edit tersedia section assignment dengan flow tambah, ubah nominal, aktif/nonaktif, dan hapus assignment.
- Metadata kontrak yang disunting di sini sudah distandardkan ke **`pkwt` / `pkwtt`** dan ikut mengalir ke preview kompensasi PKWT serta payroll draft bulanan.
- **Slip:** halaman **`/payslip`** sudah aktif untuk audience employee dan memakai `GET /v1/hcm/payroll/my-slip`, `GET /v1/hcm/payroll/my-slip-latest-period`, serta `GET /v1/hcm/payroll/my-slip-pdf` sebagai surface self-service sesudah ada run final.

## Integrasi

- **Lembur:** halaman ini tetap menjadi sumber gaji pokok, tetapi tunjangan tetap legacy tidak lagi berasal dari profil salary karyawan.
- **Payroll items:** tautan ke `/payroll` (komponen slip / katalog, termasuk konteks upah lembur).
- **Payroll draft:** assignment custom aktif akan ikut terbentuk sebagai payroll line saat admin menghitung draft periode (`POST /v1/hcm/payroll-periods/{id}/calculate-draft`).
- **Payslip:** perubahan kompensasi utama dan assignment custom di sini menjadi salah satu input utama yang akhirnya muncul di `/payslip` saat payroll monthly/THR/PKWT pada bulan tersebut sudah final.
- **Directory / detail:** tautan ke `/employees` dan `/employee-details?id=…`.

## Kontrak API

- List & field baris: `docs/api/hcm-employees-api.md` (termasuk `phone` pada list).
- OpenAPI: `docs/api/openapi.yaml` — skema `EmployeeDirectoryRow`.

## Tes

- `WebHcmRouteGuardTest` — akses `/employee-salary` untuk admin vs non-admin (cookie API + sesi web).
- `HcmEmployeeApiTest` — perilaku umum employees API (kompensasi diuji lewat create/update profil).

## Existing Vs Target

- Existing: halaman admin, modal kompensasi, custom payroll item assignment per karyawan, dan integrasi ke overtime/payroll draft sudah aktif.
- Existing: kompensasi utama yang dikelola di halaman ini adalah gaji pokok; tunjangan tetap operasional dipindahkan ke allowance governance/payroll item assignment.
- Target: penyatuan dokumentasi business flow dan lifecycle kompensasi dengan modul payroll terkait bisa dibuat lebih detail bila area ini diaudit lebih dalam lagi.
