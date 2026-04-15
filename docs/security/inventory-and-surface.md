# Inventaris layanan & audit permukaan (snapshot)

Ringkasan untuk **April 2026** — selaraskan dengan `backend/routes/api.php` dan `backend/routes/web.php` bila route berubah.

## 1. API (prefix sesuai `bootstrap/app.php`, saat ini tanpa prefix tambahan)

| Grup path | Auth | Catatan |
|------------|------|---------|
| `POST /v1/identity/auth/register` | Publik | Rate limit / validasi di controller |
| `POST /v1/identity/auth/login` | Publik | Set cookie HttpOnly + hash token DB |
| `POST /v1/identity/auth/logout`, `GET /v1/identity/auth/me` | **api.token** | Bearer atau cookie |
| `GET|POST|PUT|DELETE /v1/hcm/...` (seluruh tree HCM) | **api.token** | RBAC per controller |
| `GET|POST|PUT|DELETE /v1/hcm/user-management/...` | **api.token** + **hcmAdmin** | Endpoint sensitif role/permission; non-admin harus `403 AUTH_FORBIDDEN` |
| `GET|POST /v1/reconciliation/exports...` | **api.token + tenant.context + hcmAdmin** | Evidence export reconciliation hanya untuk operator/admin; bukan flow customer subscribe |
| `GET /health` | Publik | Health check JSON (pertimbangkan pembatasan IP di reverse proxy produksi) |

**Temuan audit:** Tidak ada endpoint HCM yang terbuka tanpa token. Risiko utama bukan di API, melainkan **halaman web** yang memuat markup + aset bila tidak dilindungi server-side.

## 2. Web (Laravel `web` middleware)

| Jenis | Kebijakan (default produksi) |
|-------|-----------------------------|
| `GET/HEAD` | **Whitelist publik tunggal** (`config/arcav_hcm_web_guard.php`): wajib cookie API valid **atau** `Auth::check()` (sesi legacy), kecuali `public_paths` / `public_prefixes`. Tamu pada path lain → `error-404-guest` (tanpa sidebar). Tidak ada mode legacy / env yang mematikan guard global. |
| `POST` / mutasi form | Tidak dicek oleh guard ini; dilindungi **CSRF** + alur bisnis masing-masing. |

**Path publik default (whitelist):**

- `/` (login utama)
- `login`, `register`, `signout`
- `up` (health Laravel)
- `api-docs`, `api-docs/openapi.yaml`

**Temuan audit (sebelum guard global):** Ratusan route **katalog tema** (`/dashboard`, `/chat`, `/clients`, `/ui-*`, CRM, dll.) dapat di-render tanpa login sehingga membocorkan struktur UI dan skrip. **Telah ditutup** dengan kebijakan whitelist publik (semua GET/HEAD non-publik wajib auth).

**Risiko residual:**

- **OpenAPI di `/api-docs`:** berguna untuk QA; di internet publik bisa membantu penyerang. Pertimbangkan matikan di produksi atau lindungi jaringan / basic auth di proxy.
- **Sesi legacy `custom-login`:** pengguna hanya sesi web tanpa cookie API tetap bisa buka halaman web setelah login — konsisten dengan kompatibilitas; data sensitif tetap harus ditolak di API jika token tidak ada.
- **Berkas export reconciliation di storage private:** pastikan tidak ada direct-public URL; akses file wajib lewat endpoint download yang tenant-scoped.

## 3. Fitur keamanan yang dipasang di kode

| Fitur | Lokasi |
|-------|--------|
| Validasi token API (cookie + Bearer) | `ArcavAccessTokenResolver`, `AuthenticateApiToken` |
| Guard web + 404 tamu | `EnsureHcmWebPagesAuthenticated`, `error-404-guest` |
| Security headers (nosniff, frame, referrer, permissions-policy) | `SecurityHeadersMiddleware` (global) |
| Trace ID request | `TraceIdMiddleware` |

## 4. Layanan di luar ruang lingkup file ini

- File statis di `public/build/*` (tidak melalui guard Blade).
- Proxy Node/Vite jika dipakai di dev — samakan kebijakan auth dengan produksi.

## 5. Matriks HCM vs URL

Detail role per halaman: `docs/planning/active-hcm-templates-and-permissions.md`.
