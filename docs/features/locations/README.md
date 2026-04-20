# Locations / Wilayah Sync

## Ringkasan

Feature ini mengganti data dummy pada menu Locations dengan data wilayah Indonesia yang disinkronkan dari `wilayah.id` dan disimpan di database lokal.

## Akses

- Halaman locations mengikuti route existing untuk admin/operator.
- Sync manual tersedia dari UI web untuk admin yang berwenang.

## UI Aktif

- Halaman aktif: `countries`, `states`, `cities`, dan `villages`.
- Setiap halaman memakai pagination server-side, search, dan pilihan jumlah baris per halaman.

## Flow Bisnis End-to-End

1. Admin membuka halaman locations.
2. Sistem memuat data wilayah lokal hasil sinkronisasi.
3. Jika data perlu diperbarui, admin menjalankan `Sync Data Wilayah` dari UI atau scheduler menjalankan sync otomatis.
4. Data wilayah bertingkat kemudian dipakai ulang oleh modul yang memerlukan referensi alamat/lokasi.

## Lifecycle Dan Keputusan Bisnis

- Route lama dipertahankan agar navigasi existing tidak putus.
- Data disimpan lokal agar pencarian dan pagination tidak bergantung langsung ke API publik setiap kali dibuka.
- Sync otomatis dan manual sama-sama dipertahankan untuk kebutuhan operasional.

## Integrasi

- Cronjob: sinkronisasi otomatis wilayah dijalankan sebagai managed job scheduler. Lihat `docs/features/cronjob/README.md`.
- Employees Organization dan Identity/Auth/company profile flows: data wilayah menjadi referensi alamat, company locale, dan data administratif lain. Lihat `docs/features/employees-organization/README.md` dan `docs/features/identity-auth/README.md`.
- Knowledgebase: dokumentasi penggunaan menu wilayah dan SOP sync dapat dirujuk dari knowledgebase. Lihat `docs/features/knowledgebase/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

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

## Existing Vs Target

- Existing: data wilayah Indonesia sudah disinkronkan ke DB lokal, halaman memakai pagination server-side, dan sync manual/otomatis tersedia.
- Target: pemanfaatan data villages lebih luas di UI dan dokumentasi kontrak API/internal yang lebih lengkap.