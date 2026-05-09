# Payroll Items — Implementation

Status: Implemented (Payroll Item CRUD + Assignment)
Updated: 2026-05-08

Tracker: [tracker.md](tracker.md)

## Overview

Payroll items adalah komponen tambahan (bonus, insentif, potongan, dll.) yang dapat di-assign ke karyawan dan ikut diperhitungkan dalam kalkulasi draft payroll run. Berbeda dengan salary components (yang berbasis komponen gaji tetap), payroll items bersifat per-assignment dan fleksibel per periode.

## Controllers

- `backend/app/Http/Controllers/Api/HcmPayrollItemController.php`
- `backend/app/Http/Controllers/Api/HcmPayrollItemAssignmentController.php`

## Web Surfaces

- `backend/resources/views/payroll.blade.php` — halaman manajemen payroll items (admin)

## Route File

`backend/routes/api/payroll.php` — prefix `v1/hcm`, middleware: `hcm.api.feature:payroll`

## Main API Endpoints

### Payroll Items (admin only)
- `GET /v1/hcm/payroll-items` — list payroll items tenant
- `GET /v1/hcm/payroll-items/export` — export CSV/Excel
- `POST /v1/hcm/payroll-items` — buat payroll item baru
- `PUT /v1/hcm/payroll-items/{id}` — update payroll item
- `DELETE /v1/hcm/payroll-items/{id}` — hapus payroll item

### Payroll Item Assignments (admin only)
- `GET /v1/hcm/payroll-item-assignments` — list assignment per karyawan
- `POST /v1/hcm/payroll-item-assignments` — assign item ke karyawan
- `PUT /v1/hcm/payroll-item-assignments/{id}` — update assignment
- `DELETE /v1/hcm/payroll-item-assignments/{id}` — hapus assignment

## Data Models

- `HcmPayrollItem` — master item (nama, tipe: `earning|deduction`, nominal/persentase)
- `HcmEmployeePayrollItemAssignment` — penugasan item ke employee dengan periode berlaku

## Integrasi

- Payroll run `calculate-draft` membaca semua item assignment aktif untuk employee eligible dan memasukkannya sebagai baris run.
- Item yang di-assign namun nilainya 0 pada saat kalkulasi tidak otomatis dimasukkan sebagai baris (bergantung rule kalkulasi).
