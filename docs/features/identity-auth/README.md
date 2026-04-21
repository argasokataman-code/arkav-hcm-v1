# Identity & Auth

## Ringkasan

Fitur ini menjadi pintu masuk seluruh sistem untuk login, logout, registrasi, pengecekan sesi aktif, dan profil pengguna. Runtime auth aktif memakai endpoint `/v1/identity/auth/*`, cookie HttpOnly `arcav_access_token`, dan halaman guard yang memutus akses ke seluruh route protected saat token hilang, invalid, atau tenant context tidak valid.

Selain autentikasi dasar, modul ini juga menentukan konteks tenant aktif (`activeCompany`) dan hint permission (`hcmAdmin`, `hcmGlobalAdmin`, `permissionCodes`) yang dipakai hampir semua modul lain untuk memutuskan redirect, visibilitas menu, dan header tenant di request berikutnya.

## Akses

- Guest: bisa mengakses `/login`, `/register`, `/trial`, dan endpoint login/register/reset password.
- Authenticated user: bisa mengakses `GET /v1/identity/auth/me`, `POST /v1/identity/auth/logout`, dan `PUT /v1/identity/auth/profile` sesuai sesi aktif.
- Company-mode login: owner/admin tenant harus login dengan `companyCode`; tanpa itu backend mengembalikan `AUTH_COMPANY_MODE_REQUIRED`.

## UI Aktif

- Login page: `backend/resources/views/login.blade.php`.
- Route `/register`: redirect ke onboarding resmi `/trial?startMode=pending_payment`; gate Blade lama bukan lagi surface aktif utama.
- Onboarding public: `backend/resources/views/public/trial.blade.php` dengan mode `trial` dan `pending_payment`.
- Forgot/reset password: `backend/resources/views/forgot-password.blade.php` dan `backend/resources/views/reset-password.blade.php`.
- JS aktif: `frontend/resources/js/api-client.js`, `auth-login.js`, `auth-guard.js`, dan `auth-logout.js`.

## Flow Bisnis End-to-End

1. Guest membuka `/login` atau masuk dari landing/onboarding.
2. FE mengirim `POST /v1/identity/auth/login` dengan mode employee atau company.
3. Backend memvalidasi credential, menetapkan cookie `arcav_access_token`, dan mengembalikan data user + `activeCompany`.
4. FE membersihkan tenant context lama, menyimpan konteks tenant valid dari backend, lalu redirect ke halaman protected.
5. Halaman protected memanggil `GET /v1/identity/auth/me`; jika token invalid atau context tenant rusak, user dipaksa kembali ke `/login`.
6. Jika guest belum punya akun company, CTA registrasi dari login diarahkan ke `/register` lalu langsung masuk ke onboarding resmi `pending_payment` tanpa melewati flow trial.

## Lifecycle Dan Keputusan Bisnis

- Employee mode vs company mode: memisahkan login user biasa dan login tenant owner/admin.
- Tenant context: FE hanya boleh percaya `activeCompany` dari backend, bukan nilai input mentah user.
- Unauthorized handling: helper auth wajib menghapus context lokal dan mengarahkan ulang user saat sesi tidak lagi valid.
- Register gate: form signup lama ditutup; route `/register` sekarang menjadi redirect tipis ke onboarding resmi company pada mode `pending_payment` agar billing flow tetap konsisten.
- Onboarding public memakai satu view dengan dua mode bisnis: `trial` untuk CTA landing dan `pending_payment` untuk registrasi resmi dari login.

## Integrasi

- Landing pages: CTA dari `/landing` mengarah ke onboarding public trial, sedangkan CTA registrasi dari `/login` mengarah ke `/register` lalu redirect ke onboarding resmi `/trial?startMode=pending_payment`. Lihat `docs/features/landing-pages/README.md`.
- User management dan seluruh modul HCM: `GET /v1/identity/auth/me` menyuplai `hcmAdmin`, `hcmGlobalAdmin`, `permissions`, dan `activeCompany` yang dipakai guard modul lain. Lihat `docs/features/user-management/README.md`.
- Subscriptions dan SaaS billing: company-mode login bergantung pada tenant yang aktif agar halaman seperti `/subscription` dan `/company/invoices` bekerja pada company yang benar. Lihat `docs/features/subscriptions/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

- Endpoint utama: `POST /v1/identity/auth/register`, `POST /v1/identity/auth/login`, `POST /v1/identity/auth/logout`, `GET /v1/identity/auth/me`, `PUT /v1/identity/auth/profile`.
- Format error standar: `{ success:false, error:{ code, message, ... } }`.
- Kontrak penting pada `GET /auth/me`: `hcmAdmin`, `hcmGlobalAdmin`, `permissionCodes`, dan `activeCompany`.

## Existing Vs Target

- Existing: auth cookie-first, company-mode login, rate limit, dan reset password web flow sudah aktif.
- Existing: login page sudah diselaraskan visualnya dengan landing, dan register flow lama sudah ditutup lalu diganti redirect ke onboarding resmi `pending_payment`.
- Target: hardening lanjutan seperti device session management dan token rotation periodik masih backlog.

## Respons penting

- `GET /auth/me` mengembalikan:
  - `id`, `name`, `email`
  - `profile` (`firstName`, `lastName`, `phone`, `address`, `addressDetail`, `designation`, `team`, `profilePhotoUrl`)
  - `roles` (transitional)
  - `hcmAdmin` (boolean hint untuk UI HCM admin-only)
  - `hcmGlobalAdmin`, `permissions`, `permissionCodes`
  - `activeCompany` (`id`, `uuid`, `code`, `name`, `role`)

## Frontend flow

- `frontend/resources/js/api-client.js` - request helper cookie-first (`withCredentials` / `credentials:same-origin`) + auth failure handling.
- `frontend/resources/js/auth-login.js` - submit login form + kirim `rememberMe` (tanpa simpan token lokal).
- `frontend/resources/js/auth-guard.js` - jaga halaman protected.
- `frontend/resources/js/auth-logout.js` - revoke token + redirect login.
- Forgot/reset password web flow memakai Blade form server-rendered: `backend/resources/views/forgot-password.blade.php` -> `POST /forgot-password` -> email reset -> `backend/resources/views/reset-password.blade.php` -> `POST /reset-password`.
- Login page: `backend/resources/views/login.blade.php`.
- Route register resmi: redirect `/register` -> `/trial?startMode=pending_payment`.
- Dashboard guard page: `backend/resources/views/index.blade.php` (tanpa axios CDN eksternal).

Hardening FE hasil audit:
- sebelum submit login, FE membersihkan tenant context lama agar header tenant stale tidak terbawa ke sesi berikutnya
- login company hanya menyimpan tenant context dari `activeCompany` hasil backend, bukan dari input mentah user
- jika login company sukses tetapi payload tenant tidak valid, FE membatalkan redirect dan menampilkan error
- CTA `Create Account` dari login tidak lagi membuka form signup lama; guest diarahkan ke route resmi `/register` yang langsung masuk ke onboarding `pending_payment`
- CTA pricing di landing membuka flow onboarding trial sesuai package terpilih
- halaman `/trial` menegaskan paket pilihan dari landing dibawa ke form onboarding, dan mode `pending_payment` mengganti copy + default package agar registrasi resmi tidak masuk ke trial

## UX / regex alignment

- Login memakai input native `type="email"` dan `type="password"`, jadi validasi format dasar sudah mengikuti browser tanpa regex custom yang bertabrakan dengan backend.
- Mode `Login Employee` dan `Login Company` disinkronkan ke BE: company mode mewajibkan `companyCode`, lalu FE menyimpan tenant context agar request berikutnya membawa header company yang benar.
- Pesan error login menampilkan payload API apa adanya untuk kasus credential salah, invalid payload, dan rate limit.
- Login Company tanpa `companyCode` dihentikan di FE sebelum request dikirim, tetapi validasi server tetap menjadi sumber kebenaran.

## Flow ringkas

1. User submit email/password dari `/login`.
2. FE memanggil `POST /v1/identity/auth/login`.
3. Jika sukses, backend set cookie HttpOnly `arcav_access_token` lalu FE redirect `/index`.
4. Halaman protected memanggil `GET /v1/identity/auth/me` via guard.
5. Jika token invalid/expired, helper auth redirect ke `/login`.

Flow guest registration yang aktif:

1. Guest membuka landing page atau gate `/register`.
2. Jika guest memulai dari landing, guest memilih plan dari `landing#pricing` lalu membuka onboarding trial dengan package terpilih otomatis diprefill.
3. Jika guest memulai dari `/login`, CTA registrasi membawa guest ke `/register` lalu redirect ke `/trial?startMode=pending_payment` untuk onboarding resmi.
4. Form signup web lama `register-2` dan `register-3` tidak lagi dipakai; keduanya redirect ke route resmi `/register` yang berujung ke onboarding `pending_payment`.

## Skenario negatif yang harus lolos

- **Credential salah** -> `AUTH_INVALID_CREDENTIALS` (401), tampilkan pesan error di form.
- **Payload invalid** -> `VALIDATION_ERROR` (422), FE menampilkan pesan API.
- **Brute-force login** -> setelah 5 percobaan gagal per kombinasi email+IP dalam 60 detik, endpoint return `AUTH_TOO_MANY_ATTEMPTS` (429).
- **Owner/admin tenant login tanpa company mode** -> `AUTH_COMPANY_MODE_REQUIRED` (422).
- **Login company sukses tetapi payload tenant aktif tidak valid** -> FE tidak redirect dan menampilkan error.
- **Token hilang di halaman protected** -> redirect langsung ke `/login`.
- **Token revoked/expired** -> auto cleanup token lokal lalu redirect `/login`.

## Data & kontrak penting

- Cookie auth FE: `arcav_access_token` (HttpOnly, SameSite=Lax, Secure by env).
- `GET /auth/me` memuat `hcmAdmin` untuk gating fitur admin HCM.
- Format error standar: `{ success:false, error:{ code, message, ... } }`.
- Login menerima `rememberMe` (boolean): menentukan TTL token/cookie pendek vs panjang.

## Test coverage saat ini

- `backend/tests/Feature/AuthApiTest.php`
  - register/login/me/logout happy path
  - validation error login
  - remember-me expiry, rate limit, tenant context on me, and profile update coverage
  - login as company success/forbidden dan owner-company-mode requirement
- `backend/tests/Feature/PasswordResetWebFlowTest.php`
  - request reset link dari `/forgot-password`
  - submit password baru valid ke `/reset-password`
- `backend/tests/ui/auth-api.wiring.test.js`
  - FE auth client wiring, tenant headers, and unauthorized payload handling
- `backend/tests/ui/auth-login.wiring.test.js`
  - regular login membersihkan tenant context stale
  - company mode mewajibkan `companyCode`
  - tenant context diambil dari `activeCompany` backend
  - malformed tenant payload diblok di FE

## Known gaps / next

- Pertimbangkan rotasi token berkala + session management (list/force logout device) untuk hardening tahap lanjutan.
