# Employees & Organization — Implementation

Status: Implemented (Employee CRUD + Department + Position + Team + Bulk Import)
Updated: 2026-05-08

## Overview

Surface employee & organization runtime mencakup CRUD karyawan, upload foto profil, manajemen departemen/posisi/jabatan, tim, dan integrasi data wilayah Indonesia. Employee record menjadi sumber kebenaran identitas yang dipakai payroll, attendance, leave, dan modul HR lainnya.

## Controllers

- `backend/app/Http/Controllers/Api/HcmEmployeeController.php`
- `backend/app/Http/Controllers/Api/HcmTeamController.php`
- `backend/app/Http/Controllers/Api/WilayahLookupController.php`

## Web Surfaces

- `backend/resources/views/employees.blade.php` — daftar karyawan (admin)
- `backend/resources/views/employees-grid.blade.php` — tampilan grid karyawan (admin)
- `backend/resources/views/employee-details.blade.php` — detail profil karyawan
- `backend/resources/views/employee-dashboard.blade.php` — dashboard karyawan self-service
- `backend/resources/views/teams.blade.php` — manajemen tim (admin)
- `backend/resources/views/team-members.blade.php` — anggota tim

## Route File

`backend/routes/api/employee.php` — prefix `v1/hcm`

- Employee CRUD: middleware `hcm.api.feature:employee_management`
- Team & Wilayah: middleware `api.token`, `tenant.context` (tanpa feature gate)

## Main API Endpoints

### Employees
- `GET /v1/hcm/employees` — daftar karyawan tenant
- `GET /v1/hcm/employees/export` — export data karyawan
- `POST /v1/hcm/employees` — tambah karyawan baru
- `GET /v1/hcm/employees/bulk-template` — template bulk import
- `POST /v1/hcm/employees/bulk-upload` — bulk import karyawan
- `GET /v1/hcm/employees/{id}` — detail karyawan
- `PUT /v1/hcm/employees/{id}` — update data karyawan
- `POST /v1/hcm/employees/{id}/profile-photo` — upload foto profil
- `DELETE /v1/hcm/employees/{id}/profile-photo` — hapus foto profil

### Departments & Positions
- `GET /v1/hcm/departments` — daftar departemen
- `GET /v1/hcm/departments/export` — export departemen
- `POST /v1/hcm/departments` — buat departemen
- `PUT /v1/hcm/departments/{id}` — update departemen
- `DELETE /v1/hcm/departments/{id}` — hapus departemen
- `GET /v1/hcm/positions` — daftar posisi/jabatan
- `POST /v1/hcm/positions` — buat posisi
- `PUT /v1/hcm/positions/{id}` — update posisi
- `DELETE /v1/hcm/positions/{id}` — hapus posisi

### Teams
- `GET /v1/hcm/teams` — daftar tim tenant
- `POST /v1/hcm/teams` — buat tim baru
- `GET /v1/hcm/teams/{id}` — detail tim
- `GET /v1/hcm/teams/{id}/members` — anggota tim
- `PUT /v1/hcm/teams/{id}` — update tim
- `DELETE /v1/hcm/teams/{id}` — hapus tim
- `POST /v1/hcm/teams/reassign-members` — pindah anggota ke tim lain

### Wilayah (Indonesia)
- `GET /v1/hcm/wilayah/provinces` — daftar provinsi
- `GET /v1/hcm/wilayah/regencies` — daftar kab/kota
- `GET /v1/hcm/wilayah/districts` — daftar kecamatan
- `GET /v1/hcm/wilayah/villages` — daftar kelurahan/desa

## Data Models

- `EmployeeProfile` — data personal, kontak, alamat karyawan
- `EmployeeContract` — kontrak kerja (tipe, tanggal mulai/selesai, status)
- `EmployeeAssignment` — penugasan posisi/departemen/lokasi
- `EmployeeBankAccount` — rekening bank untuk payroll disburse
- `EmployeeEducation` / `EmployeeExperience` / `EmployeeEmploymentHistory` — riwayat
- `EmployeeEmergencyContact` — kontak darurat
- `EmployeeBenefit` — benefit tambahan
- `CompanyUser` — relasi user ke company dengan role
- `Team` — grup tim karyawan

## Tenant Scope

Semua query employee dikunci ke `company_id` dari tenant context aktif. Admin tidak dapat melihat atau memodifikasi karyawan di tenant lain.

## Integrasi

- Payroll run kalkulasi hanya memproses karyawan dengan status kerja `active` atau `probation` di tenant aktif.
- Attendance, leave, overtime, dan semua modul HCM menggunakan `user_id` dari record karyawan ini.
- Team membership dipakai oleh reporting dan performance cycle.
