# Overtime — Implementation

Status: Implemented (Overtime Type + Request CRUD + Calculation)
Updated: 2026-05-08

## Overview

Modul overtime mengelola master tipe overtime per tenant dan request lembur karyawan. Setiap request overtime melewati kalkulasi jam dan nominal berdasarkan profil gaji karyawan dan tipe overtime. Request diblok jika karyawan memiliki approved leave pada tanggal yang sama.

## Controllers

- `backend/app/Http/Controllers/Api/HcmOvertimeTypeController.php`
- `backend/app/Http/Controllers/Api/HcmOvertimeRequestController.php`

## Web Surfaces

- `backend/resources/views/overtime-master.blade.php` — master tipe overtime (admin)
- `backend/resources/views/overtime.blade.php` — request lembur (admin + employee)
- `backend/resources/views/payroll-overtime.blade.php` — integrasi overtime ke payroll

## Route File

`backend/routes/api/overtime.php` — prefix `v1/hcm`, middleware: `api.token`, `tenant.context` (tanpa feature gate tambahan)

## Main API Endpoints

### Overtime Types (admin only)
- `GET /v1/hcm/overtime-types` — daftar tipe overtime tenant
- `POST /v1/hcm/overtime-types` — buat tipe overtime baru
- `PUT /v1/hcm/overtime-types/{id}` — update tipe
- `DELETE /v1/hcm/overtime-types/{id}` — hapus tipe

### Overtime Requests
- `GET /v1/hcm/overtime-requests` — daftar request (admin: semua; employee: milik sendiri)
- `POST /v1/hcm/overtime-requests` — ajukan request overtime
- `POST /v1/hcm/overtime-requests/calculate` — kalkulasi estimasi nominal overtime
- `PUT /v1/hcm/overtime-requests/{id}` — update/approve/decline request
- `DELETE /v1/hcm/overtime-requests/{id}` — hapus request (jika masih pending)

## Data Models

- `HcmOvertimeType` — master tipe overtime (nama, multiplier, aturan pembulatan jam)
- `OvertimeRequest` — request lembur (user, tanggal, jam mulai/selesai, tipe, status, nominal)

## Business Rules

- Request overtime diblok (`LEAVE_CONFLICT`) jika ada approved leave request pada tanggal yang sama untuk user dan tenant yang sama.
- Kalkulasi menggunakan formula yang bergantung tipe overtime (multiplier terhadap upah pokok per jam).

## Integrasi

- **Leave**: cek konflik approved leave sebelum simpan request overtime.
- **Payroll**: nominal overtime yang approved dapat dimasukkan sebagai payroll item ke run bulan terkait.
