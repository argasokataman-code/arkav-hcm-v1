# Payroll — Master komponen gaji

## Ringkasan

Data master komponen (**flag peraturan Indonesia**, persen default, dll.) disimpan di **`hcm_salary_components`** dan diakses lewat **`/v1/hcm/salary-components`**. **UI admin** untuk mengelola komponen dalam konteks payroll dipusatkan di **`/payroll`** (payroll items: taut atau kustom). Halaman **`/salary-component-master`** tidak lagi ada di menu; URL tersebut **redirect ke `/payroll`**.

## Akses

- **Web:** route di grup middleware `hcm.web.admin` — non-admin diarahkan ke `/employee-dashboard`.
- **API:** semua verb hanya **HCM Admin** (`EnsuresHcmAdmin`).

## UI

- Blade legacy: `salary-component-master.blade.php` (tanpa entri menu; redirect web ke `/payroll`).
- Partial modal lama: `hcm/partials/salary-component-modals.blade.php` (masih ada untuk referensi; tidak dimuat dari `footer-scripts` secara default).
- Hapus baris master via API tetap memakai pola konfirmasi template di consumer yang memanggil `DELETE /salary-components/{id}`; halaman `/payroll` memakai `ArcavUi.confirmDelete` untuk hapus payroll item.

## Kontrak API

Lihat `docs/api/hcm-salary-components-api.md` dan `docs/api/openapi.yaml` (tag **Payroll**).

## Halaman gaji karyawan

- **`/employee-salary`**: ringkasan **`baseSalary`** + **`fixedAllowance`** per karyawan (sumber `GET /v1/hcm/employees`), penyuntingan via **`PUT /v1/hcm/employees/{id}`**. Web: `hcm.web.admin`; JS: `employee-salary-data.js`. Detail: `docs/features/employee-salary/README.md`.

## Integrasi lembur

- Pengajuan lembur (`overtime_requests`) menyimpan **`hcm_salary_component_id`** ke komponen slip upah lembur (resolver: `code = upah_lembur`, fallback kategori `overtime`).
- Respons **GET** `/v1/hcm/overtime-requests` dan **POST** `/v1/hcm/overtime-requests/calculate` menyertakan tautan ke komponen tersebut agar UI overtime dan master komponen tidak “nyasar”.
- Detail kontrak: `docs/api/hcm-overtime-api.md` (§ Integrasi master komponen gaji).

## Catatan produk

Interpretasi rincian pajak dan JS tetap menjadi tanggung jawab **konsultan/klien**; field `legal_basis` / `legal_notes` membantu dokumentasi internal, bukan opini hukum final.
