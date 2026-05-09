# Payroll Salary Components — Implementation

Status: Implemented (Salary Component CRUD + Category + Tax Flags + Employee Profiles)
Updated: 2026-05-08

Tracker: [tracker.md](tracker.md)

## Overview

Salary components adalah katalog komponen gaji struktural per tenant (contoh: Gaji Pokok, Tunjangan Tetap, Tunjangan Makan). Setiap komponen dapat dikategorikan, diberi tax flags, dan di-assign ke karyawan via employee salary profiles. Komponen ini menjadi dasar kalkulasi slip gaji pada payroll run.

## Controller

- `backend/app/Http/Controllers/Api/HcmSalaryComponentController.php`

## Web Surfaces

- `backend/resources/views/employee-salary.blade.php` — halaman manajemen salary components & profiles (admin)

## Route File

`backend/routes/api/salary-component.php` — prefix `v1/hcm`, middleware: `hcm.api.feature:payroll`

## Main API Endpoints

### Salary Components
- `GET /v1/hcm/salary-components` — list semua komponen gaji tenant
- `GET /v1/hcm/salary-components/employee-profiles` — profil gaji per employee (komponen + nilai)
- `GET /v1/hcm/salary-component-categories` — daftar kategori
- `POST /v1/hcm/salary-component-categories` — buat kategori baru
- `PUT /v1/hcm/salary-component-categories/{id}` — update kategori
- `DELETE /v1/hcm/salary-component-categories/{id}` — hapus kategori
- `POST /v1/hcm/salary-components` — buat komponen baru
- `GET /v1/hcm/salary-components/{id}` — detail komponen
- `PUT /v1/hcm/salary-components/{id}` — update komponen
- `PATCH /v1/hcm/salary-components/{id}/tax-flags` — update flag pajak pada komponen
- `DELETE /v1/hcm/salary-components/{id}` — hapus komponen

## Data Models

- `HcmSalaryComponent` — master komponen gaji (nama, tipe, kategori, tax flags, status)
- `EmployeeCompensation` — profil gaji karyawan; relasi ke salary component + nilai per karyawan

## Tax Flags

Komponen salary memiliki flag pajak yang dipakai oleh tax governance (PPh 21) untuk menentukan komponen mana yang masuk objek pajak dan kategori pajaknya.

Endpoint `PATCH /tax-flags` memperbarui flag ini secara terpisah untuk menjaga audit trail perpajakan.

## Integrasi

- Payroll run kalkulasi membaca employee salary profiles (komponen + nilai) untuk membentuk baris upah pokok dan tunjangan tetap.
- Tax governance membaca tax flags komponen untuk SPT Masa PPh 21.
