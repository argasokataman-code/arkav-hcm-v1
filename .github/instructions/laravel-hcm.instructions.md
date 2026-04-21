---
applyTo: "backend/**/*.php,backend/routes/**/*.php,backend/resources/views/**/*.blade.php,backend/tests/**/*.php"
---

## Kanonik Cursor (detail)

Aturan lengkap dan prioritas agent: folder **[`.cursor/rules/`](../../.cursor/rules/)** (`.mdc`). Ringkasan navigasi: **[`AGENTS.md`](../../AGENTS.md)**.

Integrasi dua arah GitHub ↔ Cursor: **[`.github/instructions/README.md`](./README.md)**.

Sebelum menulis code yang melibatkan Laravel/PHPUnit/Composer package, fetch docs via Context7:
`mcp_context7_resolve-library-id` → `mcp_context7_query-docs` (rule `context7-usage`). Jangan andalkan training data.

Jika Context7 tidak tersedia di environment (mis. agen GitHub tertentu tidak punya MCP), tulis catatan eksplisit:
- “Context7 tidak tersedia; referensi berbasis pengetahuan internal repo + dokumentasi resmi publik.”
- Pastikan tetap selaras dengan kontrak repo: `docs/api/*`, `docs/api/openapi.yaml`, dan aturan `.cursor/rules/*`.

## Pola backend HCM (ringkas, selaras `.cursor/rules`)

- API: envelope `{ success, data?, error? }`; RBAC/ownership di **server** (`EnsuresHcmAdmin`, tenant context, dll.) — jangan mengandalkan sembunyikan tombol saja.
- Validasi: **server-side** wajib; selaraskan dengan `docs/api/*` + `docs/api/openapi.yaml` bila kontrak berubah (`openapi-collection-sync`, `api-spec-docs-sync-per-change`).
- API contract freeze: jangan ubah kontrak endpoint aktif kecuali ada issue API nyata (bug/security/regression) atau kebutuhan fitur baru yang disetujui.
- Jika API berubah: update **keduanya** (`docs/api/openapi.yaml` + `docs/api/<feature>-api.md`) agar Swagger-style docs dan OpenAPI tetap satu fakta.
- **Penutupan task substantif:** security + semua `docs/` terdampak + OpenAPI jika API berubah (`development-closure-checklist`).
- **Migrasi:** file di `backend/database/migrations/` + verifikasi; lihat `migration-discipline`.
- **Bugfix:** akar masalah + minimal satu regression test (`bugfix-guardrails`).
- **Web GET/HEAD:** kebijakan whitelist publik + guard; lihat `web-hcm-route-security` + `docs/security/*` bila permukaan berubah.

## Wajib setelah fixing

- Jalankan migrasi dulu sebelum validasi akhir:
	- `cd backend && php artisan migrate --force`
- Lalu test ulang minimal suite terdampak; untuk fixing atau fitur baru, `php artisan test <suite-terdampak>` wajib disebut eksplisit dalam evidence.
- Jika perubahan juga menyentuh Blade ter-wire ke JS/TS atau asset frontend yang mempengaruhi runtime, jalankan juga `cd backend && npm run test -- <scope>` atau `cd backend && npx vitest run <scope>`.
- Untuk area tenant/RBAC/API kritikal atau perubahan lintas FE+BE, perluas ke suite lintas modul terkait dan jalankan PHPUnit + Vitest bila relevan.
- Bukti completion harus menyebut hasil migrate (`Nothing to migrate` atau migration applied) dan ringkasan hasil PHPUnit/Vitest yang relevan.

**Terakhir diselaraskan dengan:** isi `.cursor/rules` pada 2026-04-21 (mandatory PHPUnit + Vitest gate for fixes/features).
