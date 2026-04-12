-- =============================================================================
-- Reset THR (MySQL) — setara: php artisan hcm:reset-thr-test-data
-- =============================================================================
-- Skrip siap pakai (satu tahun, JOIN aman): **THR_RESET_QUERY.sql** di folder ini.
-- =============================================================================
--
-- DBeaver / klien SQL — kalau UI Laravel tetap sama setelah “run”:
--   1) Pastikan koneksi ke database YANG SAMA dengan backend (.env DB_DATABASE).
--   2) Jalankan SELURUH skrip sampai COMMIT (bukan satu statement saja).
--      - Pilih semua (Ctrl+A) lalu Execute SQL Statement, atau
--      - Klik “Execute SQL Script” / jalankan file utuh agar COMMIT ikut dieksekusi.
--   3) Tanpa COMMIT, perubahan hanya di transaksi session DBeaver — aplikasi
--      (baca koneksi lain) TIDAK melihat update.
--   4) Setelah sukses, cek bagian VERIFIKASI di bawah; lalu hard refresh browser
--      (Ctrl+Shift+R) di halaman /payroll-thr.
--
-- URUTAN WAJIB:
--   1) Lepas dulu FK batch → payroll run/periode (UPDATE hcm_thr_batches).
--      Kalau tidak, DELETE hcm_payroll_runs bisa gagal (masih direferensi batch).
--   2) Baru hapus hcm_payroll_lines lalu hcm_payroll_runs (purpose=thr).
--
-- thr_slip_public_no: JANGAN di-UPDATE ke NULL. NOT NULL + UNIQUE = identitas slip.
--   Nomor baru: php artisan hcm:reset-thr-test-data --fresh-slip-numbers
--
-- Tambahkan WHERE pada UPDATE/DELETE jika tidak ingin menyentuh SEMUA tahun/batch.
-- =============================================================================

START TRANSACTION;

-- 0) (Opsional) batas satu tahun — contoh bind: ganti 2026 sesuai kebutuhan
-- SET @y := 2026;

-- 1) BATCH DULU: lepas run & periode payroll, kembalikan draft
UPDATE hcm_thr_batches
SET
  status = 'draft',
  assigned_at = NULL,
  assigned_by_user_id = NULL,
  hcm_payroll_period_id = NULL,
  hcm_payroll_run_id = NULL;
-- Disarankan untuk produksi/dev multi-tahun:
-- WHERE calendar_year = @y;
-- atau: WHERE calendar_year = 2026;

-- 2) Hapus baris slip payroll yang menempel ke run THR
DELETE FROM hcm_payroll_lines
WHERE hcm_payroll_run_id IN (
  SELECT id FROM (SELECT id FROM hcm_payroll_runs WHERE purpose = 'thr') AS t
);

-- 3) Hapus run payroll THR
DELETE FROM hcm_payroll_runs WHERE purpose = 'thr';

-- 4) Hapus jejak disbursement (hati-hati: tanpa WHERE = semua batch)
DELETE FROM hcm_thr_disbursements;
-- Lebih aman per tahun:
-- DELETE FROM hcm_thr_disbursements
-- WHERE hcm_thr_batch_id IN (SELECT id FROM hcm_thr_batches WHERE calendar_year = 2026);

-- 5) Reset kolom bayar & slip per baris (JANGAN sentuh thr_slip_public_no)
UPDATE hcm_thr_batch_lines
SET
  payment_status = 'unpaid',
  payment_failure_reason = NULL,
  payment_gateway_ref = NULL,
  paid_at = NULL,
  slip_storage_path = NULL,
  slip_generated_at = NULL,
  slip_notify_sent_at = NULL,
  last_disbursement_id = NULL;
-- Disarankan:
-- WHERE hcm_thr_batch_id IN (SELECT id FROM hcm_thr_batches WHERE calendar_year = 2026);

COMMIT;

-- =============================================================================
-- VERIFIKASI (jalankan setelah COMMIT; harusnya terlihat di hasil query)
-- =============================================================================
-- SELECT id, calendar_year, status, hcm_payroll_run_id, hcm_payroll_period_id
-- FROM hcm_thr_batches
-- ORDER BY calendar_year DESC, id DESC;
--
-- SELECT id, hcm_thr_batch_id, payment_status, slip_storage_path IS NOT NULL AS has_slip_path
-- FROM hcm_thr_batch_lines
-- ORDER BY id DESC
-- LIMIT 30;
--
-- SELECT COUNT(*) AS thr_runs_left FROM hcm_payroll_runs WHERE purpose = 'thr';
-- (harusnya 0 setelah reset penuh)

-- Opsional: hapus folder PDF di disk per tahun
--   storage/app/private/thr-slips/{calendar_year}/
