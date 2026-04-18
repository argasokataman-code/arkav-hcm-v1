# UUID Migration Steps & Integration Notes

## Status singkat

Cutover UUID core tables sudah selesai di database lokal.

- Selesai mayoritas: penambahan/backfill kolom UUID dan relasi UUID tambahan.
- Selesai final: switch PK utama integer ke UUID di tabel inti.

Referensi bukti: [backend/database/migrations/2026_04_18_130000_switch_pk_to_uuid_core_tables.php](../../../backend/database/migrations/2026_04_18_130000_switch_pk_to_uuid_core_tables.php).

## Checklist status aktual

| Area | Status | Keterangan |
|------|--------|------------|
| UUID column rollout | Done (majority) | Batch lintas domain sudah tersedia |
| UUID FK rollout tambahan | Done (majority) | Hardening/catch-up sudah ditambahkan |
| PK integer -> UUID cutover | Done | Migration final sudah dijalankan di database lokal |
| Full UUID migration complete | In progress | Menunggu regression test / audit aplikasi bila diperlukan |

## Langkah eksekusi

### Fase 0 - Persiapan

1. Pastikan backup database terbaru siap restore.
2. Freeze deployment perubahan schema lain selama window cutover.
3. Tetapkan daftar tabel inti parent-child yang akan dipotong pada satu gelombang.
4. Jalankan pre-check lokal: `bash scripts/uuid-pre-migration-check.sh`.
5. Ikuti runbook production terstruktur: [docs/features/uuid-migration/PRODUCTION-RUNBOOK.md](PRODUCTION-RUNBOOK.md).

### Fase 1 - Validasi transisi yang sudah ada

1. Pastikan semua tabel target memiliki kolom `uuid` yang terisi.
2. Pastikan kolom `*_uuid` yang sudah ditambahkan memiliki data hasil backfill.
3. Tandai temuan di tracker: [docs/features/uuid-migration/uuid-migration-table-list.md](uuid-migration-table-list.md).

### Fase 2 - Final PK/FK cutover

1. Lepas FK lama berbasis integer secara terkontrol.
2. Ubah PK utama tabel inti ke UUID.
3. Rebind FK child ke parent UUID.
4. Rebuild index utama yang terdampak performa query.

Catatan: migration final yang mengeksekusi fase ini sudah tersedia dan sudah dijalankan:
- [backend/database/migrations/2026_04_26_170000_finalize_uuid_full_cutover_core_tables.php](../../../backend/database/migrations/2026_04_26_170000_finalize_uuid_full_cutover_core_tables.php)
- [backend/database/migrations/2026_04_26_180000_add_uuid_primary_keys_to_company_users_and_hcm_user_roles.php](../../../backend/database/migrations/2026_04_26_180000_add_uuid_primary_keys_to_company_users_and_hcm_user_roles.php)

### Fase 3 - Sinkronisasi aplikasi

1. Update model agar key type dan relasi konsisten dengan UUID.
2. Audit service/controller/query raw agar tidak mengunci integer PK sebagai identifier utama.
3. Pastikan seeder/factory menggunakan UUID.

### Fase 4 - Testing dan verifikasi

1. Jalankan migration pada staging clone data terbaru.
2. Cek integritas dengan query: [docs/sql/uuid-cutover-integrity-check.sql](../../sql/uuid-cutover-integrity-check.sql).
3. Smoke test modul utama (auth, employee, leave, payroll, billing, reporting).
4. Verifikasi respon API tetap memakai envelope standar:
   - sukses: `{ success: true, data: ... }`
   - gagal: `{ success: false, error: { code, message } }`

### Fase 5 - Dokumentasi penutupan

1. Sinkronkan status di [docs/features/uuid-migration/README.md](README.md).
2. Sinkronkan tracker batch di [docs/features/uuid-migration/uuid-migration-table-list.md](uuid-migration-table-list.md).
3. Tambahkan catatan anomali jika ada overlap migration historis atau catch-up baru.

## Catatan integrasi

- Pertahankan pendekatan forward-only dan idempotent guard untuk stabilitas lintas environment.
- Jangan rewrite histori migration lama yang mungkin sudah dieksekusi di environment lain.
- Jika ada migration UUID yang dieksekusi lebih awal dari migration create-table domain terkait, tutup gap dengan migration catch-up terpisah.

## Referensi

- Laravel UUID key docs: https://laravel.com/docs/10.x/eloquent#uuid-and-ulid-keys