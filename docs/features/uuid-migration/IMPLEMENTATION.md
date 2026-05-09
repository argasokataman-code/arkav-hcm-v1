# UUID Migration — Implementation

Status: Ongoing (UUID pada identifier API aktif; full PK cutover masih dalam transisi)
Updated: 2026-05-08

Runbook: [PRODUCTION-RUNBOOK.md](PRODUCTION-RUNBOOK.md)
Steps: [STEPS.md](STEPS.md)
DB Tracker: [runtime-db-table-tracker.md](runtime-db-table-tracker.md)

## Overview

UUID migration bukan fitur UI — ini adalah program teknis untuk memindahkan semua identifier publik dari integer legacy ke UUID v4. Tujuannya adalah keamanan API (tidak ada ID enumerable), konsistensi kontrak, dan audit trail yang lebih kuat.

## Scope Migrasi

### Batch 1 — Users & Core
- Migration: `2026_04_17_200000_add_uuid_to_users_table.php`
- Migration: `2026_04_17_210000_add_uuid_to_batch1_tables.php`
- Migration: `2026_04_17_220000_add_uuid_foreign_keys_for_batch1_tables.php`

### Batch 2 — Billing & Packages
- Migration: `2026_04_17_229000_add_uuid_to_packages_table.php`
- Migration: `2026_04_17_230000_add_uuid_fields_for_billing_batch2_tables.php`
- Migration: `2026_04_17_231000_add_uuid_to_packages_table.php`

### Batch 3 — Employee History
- Migration: `2026_04_17_240000_add_uuid_to_employee_history_batch1_tables.php`
- Migration: `2026_04_17_250000_add_uuid_to_employee_history_batch2_tables.php`

### Batch 4 — RBAC & Ticketing
- Migration: `2026_04_17_260000_add_uuid_to_hcm_role_permission_batch1_tables.php`
- Migration: `2026_04_18_000100_add_uuid_to_ticketing_batch_tables.php`

## Status Runtime

Lihat [runtime-db-table-tracker.md](runtime-db-table-tracker.md) untuk status kolom UUID per tabel.

### Fase yang sudah selesai:
- UUID pada semua route API publik utama (security/API UUID done)
- Guard route validasi UUID (`whereUuid('uuid')`) aktif di endpoint kritis

### Masih dalam transisi (hybrid):
- Beberapa domain masih mendukung numeric ID legacy sebagai fallback untuk kompatibilitas
- Full PK cutover (penghapusan kolom integer legacy) belum dilakukan

## Pola Route UUID

Endpoint yang sudah migrasi ke UUID menggunakan:
```php
->where('id', '[0-9a-fA-F\-]+')  // atau
->whereUuid('uuid')
```

Endpoint dengan identifier numeric (`->whereNumber('id')`) berarti masih menggunakan integer PK.

## Backward Compatibility

Domain hybrid mempertahankan integer ID di DB, tetapi API publik menggunakan UUID. Resolusi internal terjadi di controller/service layer.

## Deployment Notes

Sebelum menjalankan migration UUID batch baru:
1. Backup database
2. Jalankan pre-migration check: `bash scripts/uuid-pre-migration-check.sh`
3. Apply migration: `php artisan migrate --force`
4. Verifikasi: jalankan suite test terdampak
