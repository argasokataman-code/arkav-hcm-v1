# GitHub Copilot Instructions — ARCAV HCM

## Struktur repo
- `backend/` — Laravel 11, API + Blade views, PHPUnit tests
- `frontend/` — Vite + TypeScript/JS, assets di-build ke `backend/public/build/`
- `docs/` — arsitektur, API contract, feature docs, planning
- `.github/instructions/` — instruction files per domain (lihat bawah)

## Tech stack
- **Backend**: Laravel 11, PHP 8.2+, MySQL, multi-tenant via `X-Company-Id`
- **Frontend**: Vite, Bootstrap 5, TypeScript (`frontend/resources/ts/`), plain JS (`frontend/resources/js/`)
- **Tests**: PHPUnit (backend), Vitest (frontend UI wiring)
- **Deploy**: shared-hosting artifact via `scripts/shared-hosting-package-local.sh`

## Non-negotiable rules
1. **Auth di server** — jangan rely on UI-only guard; wajib `403` di controller
2. **API prefix** `/v1/hcm/*`; response shape `{ success, data?, error? }`
3. **API contract berubah** → sinkronkan `docs/api/openapi.yaml` + `docs/api/<feature>-api.md`
4. **RBAC berubah** → update `docs/planning/active-hcm-templates-and-permissions.md`
5. **Local test gate wajib** sebelum push ke `main`:
   ```bash
   bash scripts/local-test-gate.sh
   ```
6. **Jangan push otomatis** — saat "prepare deploy", stop di "ready to push", tunggu konfirmasi operator
7. **Context7 wajib** sebelum menulis sintaks library/framework (resolve → query docs)
8. Jika ada konflik instruksi user vs rule proyek, sebutkan dan minta konfirmasi

## File kunci
| Kebutuhan | Lokasi |
|---|---|
| Routes API | `backend/routes/api/` |
| Routes Web | `backend/routes/web/` |
| Controllers | `backend/app/Http/Controllers/` |
| Models | `backend/app/Models/` |
| Blade views | `backend/resources/views/` |
| Frontend JS/TS | `frontend/resources/js/`, `frontend/resources/ts/` |
| Built assets | `backend/public/build/js/` |
| Web guard config | `backend/config/arcav_hcm_web_guard.php` |
| OpenAPI | `docs/api/openapi.yaml` |
| Role/permission matrix | `docs/planning/active-hcm-templates-and-permissions.md` |

## Detail per domain → `.github/instructions/`
- **Laravel/backend**: `.github/instructions/laravel-hcm.instructions.md`
- **Frontend/Blade**: `.github/instructions/frontend-hcm.instructions.md`
- **API contract sync**: `.github/instructions/api-contract-sync.instructions.md`
- **Project governance**: `.github/instructions/project-governance.instructions.md`

## Definition of done
- Pola repo konsisten; tidak ada dummy data hardcoded di halaman HCM aktif
- Tes relevan sudah dijalankan (happy path + 401/403/422)
- Docs terdampak ikut diupdate
- OpenAPI disinkronkan jika kontrak API berubah
- Tidak ada secret atau gap RBAC yang dibiarkan terbuka
