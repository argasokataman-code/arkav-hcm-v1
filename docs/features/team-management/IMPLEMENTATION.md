# Team Management — Implementation

Status: Implemented (Team CRUD + Member Reassignment + Backfill)
Updated: 2026-05-08

Tracker: [tracker.md](tracker.md)
Impact Analysis: [IMPACT-ANALYSIS.md](IMPACT-ANALYSIS.md)
Use Cases: [USE-CASES.md](USE-CASES.md)
Backfill Runbook: [BACKFILL-RUNBOOK.md](BACKFILL-RUNBOOK.md)

## Overview

Team management mengelola pengelompokan karyawan ke dalam tim. Tim digunakan oleh reporting, performance cycle, dan assignment kerja. Admin dapat membuat tim, mengelola anggota, dan memindahkan anggota antar tim.

## Controller

- `backend/app/Http/Controllers/Api/HcmTeamController.php`

## Web Surfaces

- `backend/resources/views/teams.blade.php` — daftar dan manajemen tim (admin)
- `backend/resources/views/team-members.blade.php` — anggota tim

## Route File

`backend/routes/api/employee.php` — routes team berada dalam prefix `v1/hcm`, middleware: `api.token`, `tenant.context`

## Main API Endpoints

- `GET /v1/hcm/teams` — daftar tim tenant
- `POST /v1/hcm/teams` — buat tim baru
- `GET /v1/hcm/teams/{id}` — detail tim
- `GET /v1/hcm/teams/{id}/members` — daftar anggota tim
- `PUT /v1/hcm/teams/{id}` — update nama/info tim
- `DELETE /v1/hcm/teams/{id}` — hapus tim
- `POST /v1/hcm/teams/reassign-members` — pindahkan anggota ke tim lain (bulk)

## Data Model

- `Team` — tim karyawan
  - `id` bigint PK
  - `company_id` — tenant scope
  - `name` — nama tim
  - `description` — deskripsi
  - `manager_id` / `lead_id` — user penanggung jawab tim (nullable)
  - `members` — relasi ke `company_users`

## Backfill

Tim lama (sebelum implementasi feature ini) tidak memiliki relasi formal. Lihat [BACKFILL-RUNBOOK.md](BACKFILL-RUNBOOK.md) untuk prosedur migrasi data eksisting ke struktur tim baru.

## Tenant Scope

Semua tim dikunci ke `company_id` aktif. `reassign-members` hanya boleh memindahkan user yang berada dalam tenant yang sama.

## Integrasi

- **Performance**: review cycle dapat di-assign ke tim atau dibuat per anggota tim.
- **Reporting**: laporan kehadiran dan produktivitas dapat difilter per tim.
- **Attendance shift**: shift assignment dapat dilakukan per tim.
