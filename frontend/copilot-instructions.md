# GitHub Copilot Instructions — Frontend

## Snapshot
- Area ini berisi JS/Vite untuk halaman Blade HCM.
- Fokus pada reuse pola yang sudah ada; jangan menambah UI pattern baru tanpa alasan kuat.

## Wajib diikuti
- Gunakan Bootstrap/card/table/modal/toast yang sudah ada di repo.
- Gunakan `window.ArcavUi.confirmDelete`; jangan pakai `alert/confirm/prompt` native.
- Gunakan `ApiClient` / pola request yang sudah ada.
- Jangan hardcode dummy data di halaman aktif; ambil dari API/DB atau kosongkan placeholder.
- UI boleh menyembunyikan aksi admin, tapi backend tetap wajib enforce `403`.

## Area kerja utama
- Scripts: `resources/js/`
- Server/dev config: `server.js`
- Blade yang memakai asset ini ada di `../backend/resources/views/`
- Asset hasil build dipakai dari `../backend/public/build/js/`
- Loader script utama: `../backend/resources/views/layout/partials/footer-scripts.blade.php`

## Setelah ubah JS
- Jalankan build yang relevan
- Pastikan asset terbaru tersinkron ke `../backend/public/build/js/`
- Cek route Blade yang memuat script masih benar

## Quick commands
```bash
npm install
npm run build
npm run dev
```

## Hindari
- Dummy rows/card statis untuk data HCM aktif
- Custom dialog/drawer yang menyimpang dari template
- Styling besar baru jika Bootstrap yang ada sudah cukup
- Menaruh logika izin hanya di frontend

## Jika butuh detail
Lihat `.cursor/rules/backend-template-lock.mdc` dan `.cursor/rules/no-hardcoded-dummy-template-data.mdc`.

