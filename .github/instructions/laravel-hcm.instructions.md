---
applyTo: "backend/**/*.php,backend/routes/**/*.php,backend/resources/views/**/*.blade.php"
---

Sebelum menulis code yang melibatkan Laravel/PHPUnit/Composer package, fetch docs via Context7:
`mcp_context7_resolve-library-id` → `mcp_context7_query-docs`. Jangan andalkan training data.

Gunakan pola Laravel 11 yang sudah ada di project ini:
- API response harus konsisten: `{ success, data?, error? }`.
- Validasi selalu server-side (FormRequest/validator eksplisit).
- Endpoint sensitif wajib auth + permission check di server (bukan hanya UI).
- Untuk bugfix, tambahkan regression test (minimal 1) pada area `backend/tests/Feature` atau `backend/tests/Unit`.
- Jika kontrak API berubah, sinkronkan `docs/api/openapi.yaml` dan dokumen API fitur terkait.
