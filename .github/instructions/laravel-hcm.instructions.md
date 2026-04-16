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
- **Penutupan task substantif:** security + semua `docs/` terdampak + OpenAPI jika API berubah (`development-closure-checklist`).
- **Migrasi:** file di `backend/database/migrations/` + verifikasi; lihat `migration-discipline`.
- **Bugfix:** akar masalah + minimal satu regression test (`bugfix-guardrails`).
- **Web GET/HEAD:** kebijakan whitelist publik + guard; lihat `web-hcm-route-security` + `docs/security/*` bila permukaan berubah.

**Terakhir diselaraskan dengan:** isi `.cursor/rules` pada 2026-04-16 (Context7 fallback note).
