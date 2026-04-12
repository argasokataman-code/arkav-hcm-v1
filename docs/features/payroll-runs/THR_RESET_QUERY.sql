-- =============================================================================
-- Reset jejak pembayaran & posting THR — siap dijalankan di MySQL / DBeaver
-- =============================================================================
-- Setelah sukses: batch kembali draft, baris THR unpaid, slip DB dikosongkan,
-- run payroll purpose=thr + disbursement untuk tahun itu bersih.
-- thr_slip_public_no TIDAK diubah (nomor slip resmi di DB).
--
-- Cara pakai:
--   1) Ubah @thr_year di bawah (tahun kalender batch THR, contoh 2026).
--   2) Pilih SEMUA isi file → jalankan sekaligus (termasuk COMMIT).
--   3) Cek hasil dengan query di bagian bawah.
-- =============================================================================

START TRANSACTION;

-- Tahun kalender batch THR yang mau dibersihkan (WAJIB sesuaikan)
SET @thr_year := 2026;

-- 1) Hapus baris slip payroll untuk run THR yang masih terikat batch tahun ini
DELETE pl
FROM hcm_payroll_lines AS pl
INNER JOIN hcm_thr_batches AS b
    ON b.hcm_payroll_run_id = pl.hcm_payroll_run_id
    AND b.calendar_year = @thr_year
INNER JOIN hcm_payroll_runs AS r
    ON r.id = pl.hcm_payroll_run_id
    AND r.purpose = 'thr';

-- 2) Hapus run payroll THR yang terikat batch tahun ini
DELETE r
FROM hcm_payroll_runs AS r
INNER JOIN hcm_thr_batches AS b
    ON b.hcm_payroll_run_id = r.id
    AND b.calendar_year = @thr_year
WHERE r.purpose = 'thr';

-- 3) Batch: draft + lepas periode & run payroll
UPDATE hcm_thr_batches
SET
    status = 'draft',
    assigned_at = NULL,
    assigned_by_user_id = NULL,
    hcm_payroll_period_id = NULL,
    hcm_payroll_run_id = NULL
WHERE calendar_year = @thr_year;

-- 4) Hapus disbursement hanya untuk batch tahun ini
DELETE d
FROM hcm_thr_disbursements AS d
INNER JOIN hcm_thr_batches AS b ON b.id = d.hcm_thr_batch_id AND b.calendar_year = @thr_year;

-- 5) Reset kolom bayar & slip pada baris batch tahun ini (tanpa thr_slip_public_no)
UPDATE hcm_thr_batch_lines AS l
INNER JOIN hcm_thr_batches AS b ON b.id = l.hcm_thr_batch_id AND b.calendar_year = @thr_year
SET
    l.payment_status = 'unpaid',
    l.payment_failure_reason = NULL,
    l.payment_gateway_ref = NULL,
    l.paid_at = NULL,
    l.slip_storage_path = NULL,
    l.slip_generated_at = NULL,
    l.slip_notify_sent_at = NULL,
    l.last_disbursement_id = NULL;

COMMIT;

-- =============================================================================
-- VERIFIKASI (jalan terpisah setelah COMMIT)
-- =============================================================================
-- SET @thr_year := 2026;
--
-- SELECT id, calendar_year, status, hcm_payroll_run_id, hcm_payroll_period_id
-- FROM hcm_thr_batches
-- WHERE calendar_year = @thr_year;
--
-- SELECT l.id, l.payment_status, l.slip_storage_path
-- FROM hcm_thr_batch_lines l
-- INNER JOIN hcm_thr_batches b ON b.id = l.hcm_thr_batch_id AND b.calendar_year = @thr_year
-- LIMIT 50;

-- =============================================================================
-- OPSI: bersihkan SEMUA tahun (berbahaya di produksi) — jangan pakai sembarangan
-- =============================================================================
-- START TRANSACTION;
-- DELETE pl FROM hcm_payroll_lines pl
-- INNER JOIN hcm_payroll_runs r ON r.id = pl.hcm_payroll_run_id AND r.purpose = 'thr';
-- DELETE FROM hcm_payroll_runs WHERE purpose = 'thr';
-- UPDATE hcm_thr_batches SET status = 'draft', assigned_at = NULL, assigned_by_user_id = NULL,
--   hcm_payroll_period_id = NULL, hcm_payroll_run_id = NULL;
-- DELETE FROM hcm_thr_disbursements;
-- UPDATE hcm_thr_batch_lines SET payment_status = 'unpaid', payment_failure_reason = NULL,
--   payment_gateway_ref = NULL, paid_at = NULL, slip_storage_path = NULL,
--   slip_generated_at = NULL, slip_notify_sent_at = NULL, last_disbursement_id = NULL;
-- COMMIT;
