# Identity & Auth — Implementation

Status: Implemented (Auth v1 + Profile + Cookie HttpOnly)
Updated: 2026-05-08

## Overview

Modul identity adalah pintu masuk seluruh sistem. Mengelola login (employee mode + company mode), logout, sesi aktif via cookie HttpOnly, profil user, ganti password, dan registrasi yang diarahkan ke onboarding resmi. Konteks tenant aktif (`activeCompany`) dan hint permission (`hcmAdmin`, `hcmGlobalAdmin`, `permissionCodes`) dikembalikan dari endpoint `me` dan dipakai hampir semua modul lain.

## Controller

- `backend/app/Http/Controllers/Api/AuthController.php`

## Web Surfaces

- `backend/resources/views/login.blade.php` — halaman login utama
- `backend/resources/views/forgot-password.blade.php` — forgot password
- `backend/resources/views/reset-password.blade.php` — reset password
- `backend/resources/views/public/trial.blade.php` — onboarding (mode `trial` + `pending_payment`)

## Route File

`backend/routes/api/auth.php` — prefix `v1/identity`

## Main API Endpoints

- `POST /v1/identity/auth/register` — registrasi (tidak butuh token, redirect ke onboarding)
- `POST /v1/identity/auth/login` — login (employee mode atau company mode)
- `POST /v1/identity/auth/logout` — logout, hapus cookie (butuh `api.token`)
- `GET /v1/identity/auth/me` — user aktif + tenant context + permission hints (butuh `api.token` + `tenant.context`)
- `PUT /v1/identity/auth/profile` — update profil user/company (butuh `api.token` + `tenant.context`)
- `POST /v1/identity/auth/change-password` — ganti password (butuh `api.token` + `tenant.context`)

## Data Models

- `User` — user utama, field `is_super_admin` untuk Global Super Admin flag
- `AuthToken` — token sesi HttpOnly
- `CompanyUser` — membership user ke company tenant, field `role`
- `HcmUserRole` / `HcmUserRoleAudit` — role granular + audit log

## Auth Cookie

- Nama cookie: `arcav_access_token`
- Type: HttpOnly, tidak boleh diakses JS langsung
- Dikirim di setiap request protected sebagai bearer

## Tenant Context

- Backend menentukan `activeCompany` dari token + `X-Company-Id` header
- FE tidak boleh manipulasi tenant context secara mandiri; selalu percaya nilai dari backend `me` response
- Login company mode wajib sertakan `companyCode`; tanpa itu backend return `AUTH_COMPANY_MODE_REQUIRED`

## Middleware Chain

- `api.token` — validasi cookie/token
- `tenant.context` — resolve company aktif dari token + header
- `hcm.api.feature:*` — feature gate per fitur HCM

## Frontend JS

- `frontend/resources/js/api-client.js` — HTTP client utama, sertakan cookie di semua request
- `frontend/resources/js/auth-login.js` — form login handler
- `frontend/resources/js/auth-guard.js` — redirect guard saat sesi invalid
- `frontend/resources/js/auth-logout.js` — logout flow
