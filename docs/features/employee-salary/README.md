# Payroll — Gaji karyawan (kompensasi bulanan)

## Ringkasan

Halaman **`/employee-salary`** memberi HCM Admin tampilan terpusat untuk **gaji pokok** (`baseSalary`) dan **tunjangan tetap** (`fixedAllowance`) yang disimpan di `employee_profiles`, konsisten dengan **`GET/PUT /v1/hcm/employees`** dan halaman detail karyawan.

Mulai update April 2026, halaman ini juga memfasilitasi **assignment payroll item custom per karyawan** (tunjangan/potongan) tanpa mengubah katalog global payroll item.

## Dokumentasi QA

- E2E browser flow: `docs/features/employee-salary/E2E-TESTING.md`

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
- Kolom **Dasar / bln** = `baseSalary + fixedAllowance` (sama dengan pembilang ÷173 pada kalkulator lembur).
- **Set kompensasi:** modal dengan dropdown karyawan (agregasi halaman API hingga batas aman).
- **Edit** baris: modal yang sama, tanpa dropdown.
- **Custom payroll item per karyawan:** di modal edit tersedia section assignment dengan flow tambah, ubah nominal, aktif/nonaktif, dan hapus assignment.
- Metadata kontrak yang disunting di sini sudah distandardkan ke **`pkwt` / `pkwtt`** dan ikut mengalir ke preview kompensasi PKWT serta payroll draft bulanan.
- **Slip:** halaman **`/payslip`** masih placeholder; API self-service **`GET /v1/hcm/payroll/my-slip-lines`** tersedia setelah admin **finalize** run (`docs/api/hcm-payroll-api.md`).

## Integrasi

- **Lembur:** `POST /v1/hcm/overtime-requests/calculate` memakai gaji pokok + tunjangan tetap dari profil; pastikan nilai di halaman ini selaras dengan yang dipakai kalkulator.
- **Payroll items:** tautan ke `/payroll` (komponen slip / katalog, termasuk konteks upah lembur).
- **Payroll draft:** assignment custom aktif akan ikut terbentuk sebagai payroll line saat admin menghitung draft periode (`POST /v1/hcm/payroll-periods/{id}/calculate-draft`).
- **Directory / detail:** tautan ke `/employees` dan `/employee-details?id=…`.

## Kontrak API

- List & field baris: `docs/api/hcm-employees-api.md` (termasuk `phone` pada list).
- OpenAPI: `docs/api/openapi.yaml` — skema `EmployeeDirectoryRow`.

## Tes

- `WebHcmRouteGuardTest` — akses `/employee-salary` untuk admin vs non-admin (cookie API + sesi web).
- `HcmEmployeeApiTest` — perilaku umum employees API (kompensasi diuji lewat create/update profil).
