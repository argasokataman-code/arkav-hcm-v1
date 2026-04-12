# Payroll Items (`/payroll`)

## Ringkasan

Menu **Payroll Items** adalah **satu layar admin** untuk katalog `hcm_payroll_items`:

- **Kustom** — tanpa FK ke `hcm_salary_components`.
- **Taut ke master** — menyalin nama/kode/jenis/kategori dari baris seed `hcm_salary_components` (satu master hanya satu payroll item).

Entri menu **Master komponen gaji** dihapus; URL lama **`/salary-component-master`** mengarah ke **`/payroll`**. API **`/v1/hcm/salary-components`** tetap dipakai untuk dropdown taut dan integrasi mesin.

## Akses

- Web: **`hcm.web.admin`**
- API: **`GET/POST/PUT/DELETE /v1/hcm/payroll-items`** — **HCM Admin** saja.

## Export & filter master aktif

- Tombol export di halaman payroll items sekarang terhubung ke **`GET /v1/hcm/payroll-items/export`** dengan format `csv`/`xlsx` dan mengikuti filter `kind` (`addition`/`deduction`).
- Dropdown taut master pada modal hanya memuat komponen gaji aktif via **`GET /v1/hcm/salary-components?isActive=1`** untuk mencegah linking ke master non-aktif.

## File

- Blade: `payroll.blade.php`
- Partial modal: `hcm/partials/payroll-item-modals.blade.php`
- JS: `frontend/resources/js/payroll-items-data.js` + salinan `backend/public/build/js/`
- Muat skrip: `footer-scripts.blade.php` jika `Route::is(['payroll'])`

## Dokumentasi API

`docs/api/hcm-payroll-items-api.md`
