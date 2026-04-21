---
applyTo: "frontend/resources/js/**/*.js,frontend/resources/ts/**/*.ts,backend/resources/views/**/*.blade.php"
---

## Kanonik Cursor (detail)

Aturan lengkap: **[`.cursor/rules/`](../../.cursor/rules/)** — utamakan `backend-template-lock.mdc`, `no-hardcoded-dummy-template-data.mdc`, `documentation-sync-after-development.mdc`, `role-permissions-with-features.mdc`. Navigasi: **[`AGENTS.md`](../../AGENTS.md)**.

Integrasi dua arah GitHub ↔ Cursor: **[`.github/instructions/README.md`](./README.md)**.

Sebelum menulis code yang melibatkan Vite/Bootstrap/library JS/TS, fetch docs via Context7:
`mcp_context7_resolve-library-id` → `mcp_context7_query-docs` (rule `context7-usage`). Jangan andalkan training data.

Jika Context7 tidak tersedia di environment (mis. agen GitHub tertentu tidak punya MCP), tulis catatan eksplisit:
- “Context7 tidak tersedia; referensi berbasis pengetahuan internal repo + dokumentasi resmi publik.”
- Tetap patuhi kontrak UI↔API yang sudah terdokumentasi (`docs/api/*`, `docs/api/openapi.yaml`) dan pola template (`backend-template-lock`).

## Pola frontend HCM (ringkas)

- **Template:** reuse Bootstrap/modal/toast; hindari pola UI baru tanpa kebutuhan kuat (`backend-template-lock`).
- **Konfirmasi hapus:** `window.ArcavUi.confirmDelete` — bukan `alert` / `confirm` native.
- **Data:** jangan dummy bisnis hardcode di halaman aktif yang sudah ter-wire API (`no-hardcoded-dummy-template-data`).
- **Role:** UI boleh menyembunyikan aksi; **otorisasi tetap di backend** — selaraskan dengan matriks HCM (`role-permissions-with-features` + `docs/planning/active-hcm-templates-and-permissions.md`).
- **Build:** setelah ubah sumber di `frontend/resources/js` atau TS yang di-bundle ke halaman Blade, jalankan build Vite di `backend/` agar aset di `backend/public/build/js` terbaru (lihat `backend-template-lock`).
- **Test wajib:** untuk fixing atau fitur baru yang menyentuh JS/TS/Blade ter-wire, jalankan `cd backend && npm run test -- <scope>` atau `cd backend && npx vitest run <scope>`; jika perubahan lintas FE+BE, jalankan juga `cd backend && php artisan test <suite-terdampak>`.
- **Evidence:** jangan klaim selesai tanpa ringkasan hasil build Vite bila relevan, hasil Vitest, dan hasil PHPUnit yang relevan untuk kontrak runtime yang ikut berubah.

**Terakhir diselaraskan dengan:** isi `.cursor/rules` pada 2026-04-21 (mandatory PHPUnit + Vitest gate for fixes/features).
