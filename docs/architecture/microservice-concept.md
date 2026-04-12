# Phase 1 Architecture Baseline (Current Runtime)

## Project context

This repository is an HCM dashboard project that uses an existing template UI and a split runtime between Node frontend proxy and Laravel backend API.

Untuk Phase 1, implementasi final yang dipakai adalah **single Laravel API** (bukan deploy multi-service terpisah) dengan pemisahan domain di level route namespace.

## Goal for Phase 1

- Build a working MVP with current template UI as source of truth.
- Keep backend modular by domain namespace in one Laravel app.
- Ensure existing frontend can consume API without introducing a new frontend app.
- Keep delivery practical and stable for daily development.

## Runtime architecture used now

### 1) Identity domain (inside single Laravel app)

Responsibilities:
- User registration
- Login / logout
- Session/token management
- Role and permission management

API endpoints:
- `POST /v1/identity/auth/register`
- `POST /v1/identity/auth/login`
- `POST /v1/identity/auth/logout`
- `GET /v1/identity/auth/me`

### 2) Core HCM domain (inside single Laravel app)

Responsibilities:
- Employee management
- Department management
- Designation management
- Company structure

API endpoints (inti master data):
- `GET /v1/hcm/employees`
- `GET /v1/hcm/employees/{id}`
- `POST /v1/hcm/employees`
- `GET /v1/hcm/departments`
- `POST /v1/hcm/departments`
- `PUT /v1/hcm/departments/{id}`
- `GET /v1/hcm/designations`
- `POST /v1/hcm/designations`
- `PUT /v1/hcm/designations/{id}`
- `GET/POST/PUT/DELETE /v1/hcm/policies` (company policies)

Endpoint tambahan pada domain yang sama (masih prefix `/v1/hcm`): attendance admin & self-service, timesheets, schedule timing (termasuk pemilihan **shift**), **shifts** CRUD, holidays, leave-requests, leave-settings, overtime-requests. Lihat `backend/routes/api.php` dan `docs/planning/implementation-status.md`.

### 3) Leave & Attendance domain (inside single Laravel app)

Responsibilities:
- Leave request submission
- Leave status tracking
- Attendance recording
- Leave type / policy configuration
- Overtime requests
- Work shifts and per-user schedule (termasuk link ke master shift)

API endpoints (implementasi saat ini — **semua di bawah `/v1/hcm`**, bukan prefix `/v1/leave` terpisah):

- `GET/POST /v1/hcm/leave-requests`, `PUT/DELETE /v1/hcm/leave-requests/{id}`
- `GET/PUT... /v1/hcm/leave-settings` (tipe cuti + custom policies)
- `GET/POST/PUT/DELETE /v1/hcm/holidays`
- `GET/POST/PUT/DELETE /v1/hcm/overtime-requests`
- Attendance & timesheet: `GET/PUT /v1/hcm/attendance/admin`, `GET /v1/hcm/timesheets`, self-service `.../attendance/me/*`, dll.
- `GET /v1/hcm/schedule-timing`, `PUT/DELETE /v1/hcm/schedule-timing/{userId}` (body mendukung `shiftId` opsional)
- `GET/POST /v1/hcm/shifts`, `PUT/DELETE /v1/hcm/shifts/{id}`

Dokumen lawas yang menyebut `/v1/leave/*` menggambarkan **batas domain logis**; path HTTP dapat diselaraskan di rilis berikutnya atau di-proxy dari gateway.

### 4) Frontend gateway (Node server)

Responsibilities:
- Serve the UI
- Route pages and screens
- Call backend APIs
- Manage authentication tokens

Notes:
- Node server handles frontend access and proxies API traffic to Laravel.
- Laravel stays as the single backend API runtime.
- Frontend UI remains based on the existing template structure.

---

## Phase 1 MVP

### MVP 1 - Authentication and access
- Register new user
- Login with email/password
- Logout
- Redirect to dashboard after login
- Protect dashboard pages behind auth

### MVP 2 - Dashboard landing page
- Basic dashboard summary
- Welcome message with user name
- Summary cards for active employees, pending leave, departments
- Navigation links to employee and leave pages

### MVP 3 - Employee management
- Employee list page
- View employee details
- Create employee profile with key fields: name, email, department, designation, status

### MVP 4 - Department and designation management
- List departments
- Add / edit department
- List designations
- Add / edit designation

### MVP 5 - Leave request flow
- Submit leave request
- View pending and approved leave requests
- Simple approval workflow for a manager/admin

### MVP 6 - Basic UI flow and navigation
- Login page
- Register page
- Dashboard page
- Employees page
- Leave page
- Settings / profile page

## Domain boundaries for Phase 1 (logical, not separate deployment)

### Identity boundary
- User credentials
- Access tokens
- Role validation
- Authentication state

### HCM boundary
- Employee records
- Departments
- Designations
- Minimal company metadata

### Leave boundary
- Leave requests
- Leave statuses
- Attendance records

## Non-functional baseline (minimum)

- Health check endpoint in backend (`/health`)
- Standardized error contract (`code`, `message`, optional `details`, `traceId`)
- Structured logging untuk endpoint penting
- Basic auth, request validation, dan role checks

## UI-template lock policy (mandatory)

Semua pengembangan backend harus mengikuti template yang saat ini dipakai. Artinya:

- Tidak menambah flow halaman baru yang tidak ada di template tanpa persetujuan eksplisit.
- Tidak mengubah perilaku utama navigasi template melalui perubahan backend.
- Kontrak data backend harus menyesuaikan kebutuhan komponen template existing.
- Jika kebutuhan bisnis baru muncul, usulkan mapping-nya ke komponen template dulu, baru implementasikan endpoint.
