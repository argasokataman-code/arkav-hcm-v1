# UUID Migration Production Runbook

Tanggal pembaruan: 2 Mei 2026
Owner: Backend/Platform Team

## Tujuan
Runbook ini dipakai saat rollout migration UUID ke environment production agar aman, terukur, dan bisa rollback cepat jika ada insiden.

## Scope
- Cutover migration UUID dan guard compatibility terkait.
- Validasi integritas UUID sesudah migration.
- Smoke test endpoint kritikal setelah deploy.

## Prasyarat Wajib
1. Branch release sudah hijau:
- PHPUnit full pass
- Vitest full pass

2. Guard script wajib pass:
- `bash scripts/check-deploy-runtime-guard.sh`
- `bash scripts/check-api-docs-sync.sh`
- `bash scripts/check-shared-hosting-artifact-sync.sh`
- `bash scripts/uuid-pre-migration-check.sh`

3. Backup dan maintenance:
- Full database backup sudah dibuat dan tervalidasi restore-nya.
- Maintenance window disetujui operator.
- Operator deploy dan DB operator standby.

## Pre-Deploy Checklist (Production)
1. Konfirmasi target host dan env:
- `APP_ENV=production`
- Credential DB mengarah ke production (bukan staging).

2. Simpan metadata deploy:
- Commit hash release
- Timestamp mulai deploy
- Nama operator

3. Aktifkan maintenance mode (jika dibutuhkan):
- `php artisan down --render="errors::503"`

## Langkah Deploy
1. Deploy artifact release ke host production.
2. Jalankan migration production:
- `php artisan migrate --force`

3. Jalankan integritas UUID SQL dari:
- `docs/sql/uuid-cutover-integrity-check.sql`

4. Verifikasi hasil integritas:
- Tidak ada row dengan UUID null pada tabel target.
- Tidak ada duplicate UUID pada tabel target.
- FK UUID kritikal valid.

## Post-Deploy Verification
1. Jalankan smoke test API kritikal:
- Auth/login
- Endpoint HCM core
- Billing invoice/subscription path

2. Jalankan smoke test web kritikal:
- Landing page
- Login page
- Dashboard after login

3. Validasi background job/queue:
- Queue worker aktif
- Tidak ada spike gagal job karena mismatch identifier

4. Matikan maintenance mode:
- `php artisan up`

## Rollback Plan
Gunakan rollback jika terjadi salah satu kondisi:
- Migrate gagal dan tidak bisa diperbaiki cepat.
- Integritas UUID gagal (null/duplicate/FK break) pada tabel kritikal.
- Endpoint kritikal mengalami error massal setelah deploy.

Langkah rollback:
1. Kembalikan code ke release stabil terakhir.
2. Restore DB dari backup snapshot sebelum deploy.
3. Jalankan smoke test minimal untuk verifikasi recovery.
4. Catat RCA awal dan hold deploy lanjutan sampai fix tervalidasi.

## Evidence Wajib Disimpan
- Output command migrate (`php artisan migrate --force`).
- Output integritas UUID SQL.
- Output smoke test kritikal.
- Timestamp down/up maintenance.
- Commit hash final yang aktif di production.

## Catatan Operasional
- Jangan jalankan migration production dari mesin/dev env yang credential-nya tidak tervalidasi.
- Jangan lanjut deploy jika salah satu guard script gagal.
- Untuk push ke main, tetap ikuti aturan repo: stop di status ready to push dan tunggu konfirmasi operator.
