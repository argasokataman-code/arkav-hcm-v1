# Auxiliary Tables: Wilayah Indonesia (Location Cache)

Data wilayah Indonesia dari `wilayah.id`, disimpan lokal untuk performa.

## `wilayah_provinces`

- `id BIGINT UNSIGNED PK`
- `code VARCHAR(20) NOT NULL UNIQUE` — kode provinsi (contoh: `31` = DKI Jakarta)
- `name VARCHAR(255) NOT NULL`
- `index('name')`
- `created_at`, `updated_at`

---

## `wilayah_regencies`

- `id BIGINT UNSIGNED PK`
- `province_code VARCHAR(20) NOT NULL` (index) — FK soft ke `wilayah_provinces.code`
- `code VARCHAR(20) NOT NULL UNIQUE` — kode kabupaten/kota (contoh: `3101` = Jakarta Pusat)
- `name VARCHAR(255) NOT NULL`
- `created_at`, `updated_at`

---

## `wilayah_districts`

- `id BIGINT UNSIGNED PK`
- `regency_code VARCHAR(20) NOT NULL` (index) — FK soft ke `wilayah_regencies.code`
- `code VARCHAR(20) NOT NULL UNIQUE` — kode kecamatan (contoh: `3101010` = Gambir)
- `name VARCHAR(255) NOT NULL`
- `created_at`, `updated_at`

**Note:** Data wilayah disinkronkan dari `wilayah.id` via command `php artisan wilayah:sync` (scheduler bulanan). Lihat `docs/features/locations/IMPLEMENTATION.md`.

---

## Related Documentation

- **Feature Docs:** `docs/features/locations/`
- **API:** None (read-only internal cache)
