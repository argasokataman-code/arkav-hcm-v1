# GitHub Copilot Instructions — Backend

## Snapshot
- Area ini adalah Laravel 11 + MySQL + Blade.
- Jaga solusi tetap kecil, konsisten, dan sesuai pola HCM yang sudah ada.

## Wajib diikuti
- Route API sensitif wajib memakai `api.token`.
- Admin-only wajib pakai `EnsuresHcmAdmin` atau ownership/scope check di server.
- Bentuk response tetap: `{ success, data?, error? }`.
- Validasi wajib server-side (`FormRequest` / request validator eksplisit).
- Hindari mass assignment dari raw request; gunakan `$fillable` / DTO yang jelas.
- Untuk route web sensitif, patuhi guard dan whitelist publik di `config/arcav_hcm_web_guard.php`.

## Area kerja utama
- Routes: `routes/api.php`, `routes/web.php`
- HTTP layer: `app/Http/Controllers/`, `app/Http/Requests/`, `app/Http/Middleware/`
- Domain: `app/Models/`, `app/Services/`
- Views: `resources/views/`
- Tests: `tests/Feature/`, `tests/Unit/`

## Setiap perubahan sebaiknya
- Tambah/ubah tes untuk happy path + `401/403/422` bila relevan
- Update `docs/api/<feature>-api.md` untuk perubahan endpoint
- Update `../docs/planning/active-hcm-templates-and-permissions.md` bila RBAC/path berubah
- Update `../docs/database/mysql-database-specification.md` bila schema berubah
- Update `../docs/api/openapi.yaml` bila kontrak API berubah

## Quick commands
```bash
composer install
php artisan test
composer audit
```

## Hindari
- Cek izin hanya di UI
- Response shape yang beda-beda antar endpoint
- Secret di repo
- Dummy data hardcoded di Blade aktif

## Jika butuh detail
Lihat `.cursor/rules/backend-template-lock.mdc` dan `.cursor/rules/application-security-baseline.mdc`.

