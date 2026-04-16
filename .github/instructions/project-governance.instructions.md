---
applyTo: "**/*"
---

## Aturan proyek (semua path)

**Kanonical detail:** [`.cursor/rules/`](../../.cursor/rules/) (berkas `.mdc`). **Ringkasan agen:** [`AGENTS.md`](../../AGENTS.md).

Sebelum menyelesaikan pekerjaan substantif (fitur, API, migrasi, RBAC, UI HCM ter-wire):

1. **Security** — auth di server, bukan hanya UI; rujuk `.cursor/rules/application-security-baseline.mdc` + `web-hcm-route-security.mdc`.
2. **Dokumentasi** — `docs/` yang terdampak + fitur di `docs/features/<feature>/`; rujuk `.cursor/rules/documentation-sync-after-development.mdc` + `documentation-feature-packaging.mdc`.
3. **OpenAPI** — jika kontrak API berubah, `docs/api/openapi.yaml`; rujuk `.cursor/rules/openapi-collection-sync.mdc`.
4. **HCM role** — matriks di `docs/planning/active-hcm-templates-and-permissions.md` + `.cursor/rules/role-permissions-with-features.mdc`.
5. **Kualitas** — cek anomali singkat: `.cursor/rules/quality-anomaly-pass.mdc`.
6. **Library/framework** — Context7 sebelum mengandalkan sintaks: `.cursor/rules/context7-usage.mdc`.

Konflik instruksi user vs rule proyek: sebutkan konflik dan minta konfirmasi; jangan mengabaikan rule tanpa persetujuan eksplisit.

**Terakhir diselaraskan dengan:** isi `.cursor/rules` pada 2026-04-16 (integrasi GitHub ↔ Cursor).
