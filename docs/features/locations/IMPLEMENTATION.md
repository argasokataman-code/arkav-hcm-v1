## Sync Progress Monitoring

Manual sync Data Alamat sekarang expose progress status agar terlihat apakah request ke API third-party (`wilayah.id`) berjalan di setiap level data.

### Endpoint status

- `GET /locations/sync-status`
- Response envelope: `{ success, data }`

Contoh payload `data`:

```json
{
	"running": true,
	"progress": 67,
	"stage": "districts",
	"message": "Sync districts dari seluruh regencies.",
	"processed": 340,
	"total": 514,
	"error": null,
	"summary": {
		"provinces": 38,
		"regencies": 514,
		"districts": 341,
		"villages": 0,
		"source": "wilayah.id",
		"syncedAt": "2026-04-15 06:30:00"
	},
	"startedAt": "2026-04-15T06:28:00+07:00",
	"updatedAt": "2026-04-15T06:31:12+07:00",
	"finishedAt": null
}
```

### Stage sync yang dipantau

- `provinces`
- `regencies`
- `districts`
- `villages`
- terminal state: `completed` atau `failed`

UI halaman Locations melakukan polling endpoint ini untuk menampilkan progress bar persentase dan status per tahap.

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