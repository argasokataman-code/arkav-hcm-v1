# UUID Migration Summary & Integration Report

Tanggal pembaruan: 18 April 2026

## Status saat ini

Migrasi UUID belum final complete.

- Selesai mayoritas: rollout kolom `uuid` dan banyak relasi `*_uuid` sudah dibuat dan dibackfill bertahap.
- Belum selesai: cutover primary key utama dari integer `id` ke UUID belum dieksekusi.
- Bukti utama: migration [backend/database/migrations/2026_04_18_130000_switch_pk_to_uuid_core_tables.php](../../../backend/database/migrations/2026_04_18_130000_switch_pk_to_uuid_core_tables.php) masih no-op (hanya warning, tidak mengubah PK/FK).

## Cakupan target

Target akhir adalah seluruh entitas inti memakai UUID sebagai primary key dan seluruh relasi utama memakai foreign key UUID.

Kondisi aktual per tanggal dokumen:

- Sistem berada pada fase transisi aman (dual key: integer tetap aktif, uuid dipakai untuk adopsi bertahap).
- Belum masuk fase final cutover PK/FK global.

## Ringkasan progres

- Batch UUID rollout sudah berjalan luas lintas domain (core, payroll, leave, asset, performance, reporting, billing support, RBAC).
- Hardening khusus billing telah ditambahkan melalui migration lanjutan untuk menutup mismatch urutan migrasi historis.
- Tracking detail per batch dan status final cutover dipisah ke file tracker: [docs/features/uuid-migration/uuid-migration-table-list.md](uuid-migration-table-list.md).

## Gap yang masih kurang

1. Menjalankan migration final switch PK integer ke UUID untuk tabel inti di environment target.
2. Penyesuaian menyeluruh model, service, dan query raw yang masih asumsikan integer PK/FK sebagai sumber utama.
3. Regression test dan smoke test end-to-end pasca cutover.
4. Verifikasi ulang API contract jika ada perubahan identifier pada payload/route binding.

Catatan implementasi terbaru:

- Migration final sudah ditambahkan di [backend/database/migrations/2026_04_26_150000_finalize_uuid_primary_keys_for_core_tables.php](../../../backend/database/migrations/2026_04_26_150000_finalize_uuid_primary_keys_for_core_tables.php).
- Migration tersebut melakukan PK cutover ke `uuid` untuk tabel inti (`users`, `companies`, `employee_profiles`, `hcm_user_roles`, `company_users`) dengan guard idempotent, backfill UUID, dan menjaga indeks legacy `id` untuk kompatibilitas transisi.

## Risks & safeguards

- Risiko downtime tetap ada pada tahap cutover PK/FK.
- Backup wajib sebelum eksekusi migration final.
- Gunakan pendekatan forward-only dan idempotent guard untuk menghindari drift antar environment.
- Jangan menghapus migration historis yang sudah berpotensi tercatat pada tabel `migrations` di environment lain.

## Definition of done

UUID migration dianggap selesai jika seluruh kondisi ini terpenuhi:

1. PK utama tabel inti sudah UUID (bukan integer auto-increment).
2. FK utama child table sudah mereferensikan kolom UUID parent.
3. Tidak ada query critical path yang mengandalkan integer PK sebagai identifier bisnis.
4. Test migration, integrity check, dan smoke test modul utama lulus.
5. Dokumentasi dan tracker status sinkron.

## Dokumen terkait

- Detail langkah eksekusi: [docs/features/uuid-migration/STEPS.md](STEPS.md)
- Tracker batch dan status per migration: [docs/features/uuid-migration/uuid-migration-table-list.md](uuid-migration-table-list.md)
- Runbook production: [docs/features/uuid-migration/PRODUCTION-RUNBOOK.md](PRODUCTION-RUNBOOK.md)
- SQL integrity checks: [docs/sql/uuid-cutover-integrity-check.sql](../../sql/uuid-cutover-integrity-check.sql)
- Script pre-check: `bash scripts/uuid-pre-migration-check.sh`
