# Locations / Wilayah Sync - E2E Testing

## Smoke test checklist

1. Jalankan `php artisan wilayah:sync`.
2. Buka `/countries` dan pastikan daftar provinces muncul.
3. Buka `/states` dan pastikan regencies/cities muncul.
4. Buka `/cities` dan pastikan districts muncul.
5. Buka `/villages` dan pastikan villages muncul.
6. Klik tombol `Sync Data Wilayah` di masing-masing halaman (`/countries`, `/states`, `/cities`, `/villages`).
7. Pastikan muncul status bahwa sync dimulai/dieksekusi setelah submit tombol.
8. Tunggu beberapa saat lalu refresh halaman untuk melihat data terbaru hasil sync background.
9. Pastikan tidak ada row dummy hardcoded seperti United States, Canada, California, atau Los Angeles.
10. Uji filter pencarian (`q`) di tiap halaman dan pastikan hasil tabel terfilter sesuai keyword.
11. Ubah `perPage` (25/50/100) dan pastikan pagination berubah tanpa error.
12. Uji keyword 1 karakter dan pastikan sistem tetap responsif (filter belum diaktifkan sampai minimal 2 karakter).
13. Pindah halaman pagination dan pastikan waktu respon stabil (tidak ada gejala query count berat).

## Akun / role

- Gunakan user authenticated biasa atau HCM admin sesuai guard web yang aktif di environment.

## Catatan hasil

- Tulis hasil pass/fail, tanggal, dan anomali tampilan bila ada.