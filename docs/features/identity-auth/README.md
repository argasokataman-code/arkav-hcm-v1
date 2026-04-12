# Identity & Auth

## Scope

- Endpoint identity: `/v1/identity/auth/*`
- Login/register/logout/me
- Cookie HttpOnly `arcav_access_token` + redirect unauthorized
- Guard akses halaman berbasis token (`auth-guard.js`)

## API utama

- `POST /v1/identity/auth/register`
- `POST /v1/identity/auth/login`
- `POST /v1/identity/auth/logout`
- `GET /v1/identity/auth/me`

## Respons penting

- `GET /auth/me` mengembalikan:
  - `id`, `name`, `email`
  - `roles` (transitional)
  - `hcmAdmin` (boolean hint untuk UI HCM admin-only)

## Frontend flow

- `frontend/resources/js/api-client.js` - request helper cookie-first (`withCredentials` / `credentials:same-origin`) + auth failure handling.
- `frontend/resources/js/auth-login.js` - submit login form + kirim `rememberMe` (tanpa simpan token lokal).
- `frontend/resources/js/auth-guard.js` - jaga halaman protected.
- `frontend/resources/js/auth-logout.js` - revoke token + redirect login.
- Login page: `backend/resources/views/login.blade.php`.
- Dashboard guard page: `backend/resources/views/index.blade.php` (tanpa axios CDN eksternal).

## Flow ringkas

1. User submit email/password dari `/login`.
2. FE memanggil `POST /v1/identity/auth/login`.
3. Jika sukses, backend set cookie HttpOnly `arcav_access_token` lalu FE redirect `/index`.
4. Halaman protected memanggil `GET /v1/identity/auth/me` via guard.
5. Jika token invalid/expired, helper auth redirect ke `/login`.

## Skenario negatif yang harus lolos

- **Credential salah** -> `AUTH_INVALID_CREDENTIALS` (401), tampilkan pesan error di form.
- **Payload invalid** -> `VALIDATION_ERROR` (422), FE menampilkan pesan API.
- **Brute-force login** -> setelah 5 percobaan gagal per kombinasi email+IP dalam 60 detik, endpoint return `AUTH_TOO_MANY_ATTEMPTS` (429).
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

## Known gaps / next

- Forgot password UI masih template-level; flow reset password backend belum jadi kontrak final.
- Pertimbangkan rotasi token berkala + session management (list/force logout device) untuk hardening tahap lanjutan.
