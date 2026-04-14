# Locations / Wilayah Sync

Feature ini mengganti data dummy pada menu Locations dengan data wilayah Indonesia yang disinkronkan dari `wilayah.id` dan disimpan di database lokal.

## Scope

- Halaman web `countries`, `states`, dan `cities` menampilkan data lokal yang sudah di-sync.
- Halaman web `villages` menampilkan data desa/kelurahan yang sudah di-sync.
- Setiap halaman locations menggunakan pagination server-side (default 25 per page) untuk menghindari full table render.
- Tersedia filter pencarian (`q`) dan pilihan jumlah baris per halaman (`perPage`: 25/50/100).
- Data disimpan bertingkat: provinces, regencies, districts, dan villages.
- Sync otomatis dijalankan lewat artisan command `wilayah:sync`.
- Setiap halaman locations menyediakan tombol **Sync Data Wilayah** untuk trigger manual sync dari UI web.

## Sumber data

- `https://wilayah.id/api/provinces.json`
- `https://wilayah.id/api/regencies/{province}.json`
- `https://wilayah.id/api/districts/{regency}.json`
- `https://wilayah.id/api/villages/{district}.json`

## Catatan

- Halaman menu tetap memakai route lama agar tidak memutus navigasi existing.
- Data desa/kelurahan juga ikut disimpan walau belum ditampilkan di menu.