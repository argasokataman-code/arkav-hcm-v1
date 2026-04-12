# GitHub Copilot Instructions — ARCAV HCM

## Snapshot
- Repo ini terdiri dari `backend/` (Laravel 11), `frontend/` (JS/Vite), dan `docs/`.
- Gunakan instruksi terdekat: root ini + file di `backend/` atau `frontend/`.
- Hemat token: buka file referensi seperlunya, jangan muat dokumentasi panjang bila tidak relevan.

## Context7 — wajib dipakai
Sebelum menulis code yang melibatkan library/framework (Laravel, Livewire, Vite, PHPUnit, dll):
1. Resolve library ID: `mcp_context7_resolve-library-id({ libraryName: "..." })`
2. Ambil docs relevan: `mcp_context7_query-docs({ context7CompatibleLibraryId: "...", topic: "..." })`
Jangan andalkan training data untuk API syntax, config, atau versi — selalu fetch dari Context7 dulu.

## Non-negotiable rules
- Patuhi `.cursor/rules/*.mdc`; jika request bentrok, sebutkan dan minta konfirmasi.
- Ikuti pola UI yang sudah ada di Blade/Bootstrap; jangan bikin design system/pola baru tanpa alasan kuat.
- Jangan sisakan data dummy hardcoded di halaman HCM aktif.
- API sensitif wajib di bawah `api.token`; admin-only wajib ditegakkan di server, bukan UI saja.
- Jika API/schema/RBAC/route berubah, sinkronkan docs pada perubahan yang sama.
- Sebelum menyatakan task selesai: cek **security + docs terdampak + `docs/api/openapi.yaml`** bila kontrak API berubah.

## Default kerja

### Backend
- Prefix API: `/v1/hcm/*`
- Bentuk response: `{ success, data?, error? }`
- Validasi wajib server-side
- Tambahkan tes yang relevan: happy path + `401/403/422`
- Update `docs/api/<feature>-api.md` dan `docs/planning/active-hcm-templates-and-permissions.md` bila perlu

### Frontend
- Gunakan `@extends('layout.mainlayout')`, partial existing, dan Bootstrap modal/toast
- Gunakan `window.ArcavUi.confirmDelete`; jangan pakai `alert/confirm/prompt`
- JS utama ada di `frontend/resources/js/` dan harus tersinkron ke `backend/public/build/js/`
- Sembunyikan aksi admin di UI hanya untuk UX; backend tetap harus `403`

## Bugfix guardrails (anti bolak-balik)
- Untuk setiap bugfix, sertakan minimal 1 test regresi yang mereproduksi bug sebelum perbaikan.
- Prioritaskan perbaikan di akar masalah (root cause), bukan patch di UI saja.
- Jika menyentuh validasi/permission/format response API: wajib cek ulang 401/403/422 dan konsistensi response shape.
- Jika perubahan menyentuh query atau schema, verifikasi dampak ke endpoint/listing yang terkait.
- Setelah perubahan lintas layer (backend+frontend), tulis ringkasan verifikasi singkat: endpoint yang diuji, role yang diuji, dan hasil.

## JIT index
- Backend routes: `backend/routes/api.php`, `backend/routes/web.php`
- Controller / middleware / request: `backend/app/Http/`
- Models / services: `backend/app/Models/`, `backend/app/Services/`
- Blade & partials: `backend/resources/views/`
- Frontend scripts: `frontend/resources/js/`
- Built assets untuk Blade: `backend/public/build/js/`
- Web guard config: `backend/config/arcav_hcm_web_guard.php`
- Docs: `docs/api/`, `docs/features/`, `docs/planning/`, `docs/security/`

## Quick commands
```bash
cd backend && php artisan test
cd backend && composer audit
cd frontend && npm run build
```

## Definition of done
- Pola repo tetap konsisten
- Tes / verifikasi yang relevan sudah dijalankan
- Docs terdampak ikut di-update
- OpenAPI ikut di-update jika API berubah
- Tidak ada secret, dummy data, atau gap RBAC yang dibiarkan

## Jika butuh detail
Buka file rule sumber di `.cursor/rules/`, terutama:
- `development-closure-checklist.mdc`
- `backend-template-lock.mdc`
- `application-security-baseline.mdc`
- `documentation-sync-after-development.mdc`
- `role-permissions-with-features.mdc`

