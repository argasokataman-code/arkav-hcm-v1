# Guard halaman web HCM (cookie API + sesi + 404 tamu)

## Tujuan

- Mencegah pengunjung tanpa identitas menerima **HTML halaman aplikasi** (termasuk katalog tema) kecuali path yang **secara eksplisit** dibuka publik.
- Tanpa auth → **HTTP 404** + view **`error-404-guest`** (layout minimal, **tanpa** sidebar/header).
- Header **`Cache-Control: no-store`** pada respons 404 guard.

## Satu mode kebijakan (tanpa legacy)

**Aturan tunggal:** setiap `GET`/`HEAD` web **wajib** auth (**cookie API valid** atau **`Auth::check()`** sesi legacy), **kecuali** path cocok **`public_paths`** atau **`public_prefixes`** di `config/arcav_hcm_web_guard.php`.

- Tidak ada lagi daftar `protected_paths` atau env yang mematikan pelindungan global.
- Untuk preview tema tanpa login di dev: tambahkan sementara prefix/path ke **`public_prefixes`** / **`public_paths`** (jangan commit ke produksi tanpa review).

## Path publik default (`public_paths`)

- `''` → `/`
- `up` → health Laravel
- `login`, `register`, `signout`
- `api-docs`, `api-docs/openapi.yaml`

Tambah path tema (mis. `login-2`) hanya jika produk benar-benar membutuhkan halaman itu tanpa login.

## Implementasi kode

| Komponen | Lokasi |
|----------|--------|
| Middleware | `app/Http/Middleware/EnsureHcmWebPagesAuthenticated.php` |
| Middleware admin-only (halaman tertentu) | `app/Http/Middleware/EnsureHcmWebAdminPage.php` (alias `hcm.web.admin`) |
| Konfigurasi | `config/arcav_hcm_web_guard.php` |
| Resolver token | `app/Support/ArcavAccessTokenResolver.php` |
| View tamu | `resources/views/error-404-guest.blade.php`, `layout/guest-fullscreen-minimal.blade.php` |
| Registrasi `web` | `bootstrap/app.php` |

### Halaman web HCM Admin saja (middleware `hcm.web.admin`)

Route **`GET /promotion`**, **`/resignation`**, **`/termination`**, **`/salary-component-master`**, **`/employee-salary`**, **`/payroll`**, **`/payroll-overtime`**, **`/payroll-deduction`**, **`/payroll-run`**, **`/payroll-run-history`**, **`/payroll-thr`** memakai middleware **`hcm.web.admin`** **setelah** guard auth umum. Pengguna terautentikasi yang **bukan** `User::isHcmAdmin()` mendapat **redirect 302** ke **`/employee-dashboard`** (sumber kebenaran sama dengan heuristik admin di API). Ini melengkapi redirect di JS; **tanpa** mengganti RBAC di endpoint `/v1/hcm/*`.

## Prinsip keamanan (ringkas)

1. **Server-side dulu** — jangan mengandalkan redirect JS saja.
2. **Satu sumber token API** — validasi `AuthToken` selaras `AuthenticateApiToken`.
3. **RBAC data** tetap di **endpoint** — guard web hanya lapisan presentasi.
4. **404 tamu** tidak menggantikan **403** di API untuk role salah.

## Tes

`tests/Feature/WebHcmRouteGuardTest.php` — **`test_all_web_guarded_get_routes_public_or_guest_404`** mengiterasi **setiap** route `GET` yang memakai middleware grup `web` (ratusan path): tamu harus 404 + `no-store` kecuali path whitelist config; plus tes cookie API, sesi web, `HEAD`, `/up`, sampel halaman dengan auth, dan **admin vs non-admin** untuk `/promotion`, `/resignation`, `/termination`, `/salary-component-master`, `/employee-salary`, `/payroll`, `/payroll-overtime`, `/payroll-deduction`, `/payroll-run`, `/payroll-run-history`, `/payroll-thr`.

## Troubleshooting: halaman masih terbuka tanpa login

1. **Bersihkan config cache:** `php artisan config:clear` lalu restart `php artisan serve`.
2. **Uji tanpa cookie:** jendela private / perangkat lain, atau `curl -I http://127.0.0.1:PORT/employees?view=list` — harus **404** (bukan 200 + HTML halaman).
3. Pastikan kode middleware terbaru ter-deploy (satu mode whitelist; tidak ada env lama `ARCAV_WEB_GUARD_DENY_BY_DEFAULT`).
