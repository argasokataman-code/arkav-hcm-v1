# Locations / Wilayah Sync - Implementation

## Komponen backend

- Migrations: `wilayah_provinces`, `wilayah_regencies`, `wilayah_districts`, `wilayah_villages`.
- Models: `WilayahProvince`, `WilayahRegency`, `WilayahDistrict`, `WilayahVillage`.
- Service: `App\Services\Wilayah\WilayahSyncService`.
- Command: `wilayah:sync`.
- Scheduler: monthly on day 1 at `01:00` WIB (`Asia/Jakarta`).

## Alur sync

1. Ambil daftar province dari `wilayah.id`.
2. Upsert province lokal dan prune province yang sudah tidak ada.
3. Untuk setiap province, ambil regencies secara paralel per batch.
4. Untuk setiap regency, ambil districts dan prune data lama yang tidak lagi ada.
5. Untuk setiap district, ambil villages dan prune data lama yang tidak lagi ada.

## UI

- `/countries` menampilkan provinces.
- `/states` menampilkan regencies/cities.
- `/cities` menampilkan districts/subdistricts.
- `/villages` menampilkan villages/subvillages.
- Semua halaman list memakai pagination server-side dan filter query (`q`, `perPage`) untuk menjaga performa saat data besar.
- Listing menggunakan `simplePaginate` (tanpa query `count(*)` per request) untuk menurunkan latensi pada tabel besar.
- Counter total tiap level diambil via cache TTL 5 menit dan di-flush setelah sync selesai.
- Filter `q` hanya aktif untuk keyword minimal 2 karakter untuk menekan full scan yang tidak perlu.
- Tombol `Sync Data Wilayah` tersedia di semua halaman di atas dan mengarah ke `POST /locations/sync`.
- Endpoint manual sync mengeksekusi command `wilayah:sync` di background process agar request web tidak timeout.
- Command dijalankan dengan mode isolated untuk mencegah overlap saat tombol sync diklik berulang.

## Prinsip

- Tidak ada dummy HTML statis lagi pada pages locations.
- Data source of truth tetap `wilayah.id`; database lokal hanya cache operasional.