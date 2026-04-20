---
applyTo: "backend/routes/api.php,backend/app/Http/Controllers/Api/**/*.php,docs/api/**/*.md,docs/api/openapi.yaml,scripts/check-api-docs-sync.sh"
---

## API contract sync (wajib)

Sebelum menutup task yang menyentuh route/controller API:

1. Jadikan runtime sebagai dasar kebenaran:
- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/**/*.php`

2. Sinkronkan dua artefak dokumentasi sekaligus:
- `docs/api/openapi.yaml`
- `docs/api/<feature>-api.md` yang terdampak

3. Jangan ubah kontrak API tanpa alasan kuat:
- hanya jika ada issue API nyata, bug, security gap, atau kebutuhan fitur baru yang disetujui.
- jika tidak ada issue API, pertahankan backward compatibility kontrak.

4. Jika endpoint berada pada masa transisi identifier, tulis eksplisit:
- UUID-only, atau
- numeric legacy, atau
- UUID + numeric fallback.

5. Validasi wajib sebelum selesai:
- jalankan `scripts/check-api-docs-sync.sh`
- pastikan tidak ada mismatch antara route/controller dengan OpenAPI + feature docs.

Rujukan kanonik detail di Cursor rules:
- `.cursor/rules/openapi-collection-sync.mdc`
- `.cursor/rules/api-spec-docs-sync-per-change.mdc`
- `.cursor/rules/development-closure-checklist.mdc`

**Terakhir diselaraskan dengan:** isi `.cursor/rules` pada 2026-04-19 (kontrak API UUID transition + sync guard).