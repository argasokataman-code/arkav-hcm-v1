---
applyTo: "frontend/resources/js/**/*.js,backend/resources/views/**/*.blade.php"
---

Sebelum menulis code yang melibatkan Vite/Bootstrap/library JS apapun, fetch docs via Context7:
`mcp_context7_resolve-library-id` → `mcp_context7_query-docs`. Jangan andalkan training data.

Gunakan pola frontend HCM yang sudah ada:
- Reuse Bootstrap/modal/toast yang ada; hindari pattern UI baru tanpa kebutuhan kuat.
- Gunakan `window.ArcavUi.confirmDelete`, jangan pakai dialog native browser.
- Jangan hardcode dummy data untuk halaman aktif.
- Untuk perubahan yang memengaruhi role/action, pastikan backend tetap enforce permission.
- Setelah ubah JS frontend, pastikan proses build asset dijalankan agar sinkron ke `backend/public/build/js`.
