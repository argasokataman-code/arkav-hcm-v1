# UUID Migration Production Runbook

Tanggal pembaruan: 18 April 2026

## Tujuan

Dokumen ini adalah prosedur operasional untuk menjalankan cutover UUID PK di environment production dengan risiko minimum.

## Scope cutover

Migration target utama:
- `backend/database/migrations/2026_04_26_150000_finalize_uuid_primary_keys_for_core_tables.php`

Core tables yang di-cutover:
- `users`
- `companies`
- `employee_profiles`
- `hcm_user_roles`
- `company_users`

## 1. Pre-flight wajib

Semua item berikut harus `PASS` sebelum mulai:

1. Freeze deploy aktif (tidak ada deploy schema/app selama window).
2. Backup DB full berhasil dibuat dan sudah diuji restore metadata-nya.
3. Estimasi downtime dan maintenance page sudah disiapkan.
4. Regression tests minimal untuk auth + permission + employee flow lulus di staging clone data terbaru.
5. Tidak ada migration pending selain yang memang direncanakan untuk cutover.

### Command pre-flight lokal

Jalankan dari root repository:

```bash
bash scripts/uuid-pre-migration-check.sh
```

## 2. Backup & safety checkpoint

Contoh (MySQL):

```bash
mysqldump --single-transaction --routines --triggers --events \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
  "$DB_DATABASE" > "backup-before-uuid-cutover-$(date +%Y%m%d-%H%M%S).sql"
```

Simpan checksum:

```bash
shasum -a 256 backup-before-uuid-cutover-*.sql
```

## 3. Execute cutover

Masuk ke folder backend:

```bash
cd backend
```

Opsional: lihat migration status

```bash
php artisan migrate:status | cat
```

Jalankan migration:

```bash
php artisan migrate --force
```

## 4. Integrity verification (wajib)

Jalankan query di file:
- `docs/sql/uuid-cutover-integrity-check.sql`

Kriteria PASS:

1. PK pada 5 core table = `uuid`.
2. Tidak ada `uuid` null pada core table.
3. Tidak ada duplikasi `uuid` pada core table.
4. FK relasi utama ke parent UUID tervalidasi (tidak orphan).

## 5. Smoke test pasca cutover

Minimal jalankan:

```bash
cd backend
php artisan test --filter=UserHcmAdminGateTest
php artisan test --filter=AuthApiTest
```

Lanjutkan smoke test modul utama sesuai checklist project:
- auth/login/logout
- employee management
- leave
- payroll
- billing/reporting yang memakai tenant context

## 6. Rollback trigger

Rollback dipicu jika salah satu kondisi ini terjadi:

1. Migration gagal di tengah dan DB state tidak konsisten.
2. Integrity check gagal (PK/FK mismatch, orphan tidak dapat ditoleransi).
3. Smoke test kritikal gagal pada auth/tenant access.

## 7. Rollback plan

1. Aktifkan maintenance mode penuh.
2. Restore dari backup pre-cutover.
3. Verifikasi service up + auth path kembali normal.
4. Catat root cause dan siapkan rerun plan.

## 8. Exit criteria

Cutover dinyatakan selesai jika semua terpenuhi:

1. Migration sukses tanpa error.
2. Integrity query seluruhnya PASS.
3. Smoke test kritikal lulus.
4. README dan tracker status UUID diperbarui.
