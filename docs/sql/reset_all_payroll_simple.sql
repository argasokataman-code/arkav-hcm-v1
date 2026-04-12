-- RESET SEMUA PAYROLL (Monthly + THR) - Jalankan di DBeaver
SET SQL_SAFE_UPDATES = 0;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Hapus payroll lines
DELETE FROM hcm_payroll_lines WHERE 1=1;

-- 2) Hapus payroll runs
DELETE FROM hcm_payroll_runs WHERE 1=1;

-- 3) Hapus payroll periods
DELETE FROM hcm_payroll_periods WHERE 1=1;

-- 4) Reset THR batches
UPDATE hcm_thr_batches
SET status = 'draft',
    hcm_payroll_run_id = NULL,
    hcm_payroll_period_id = NULL,
    assigned_at = NULL,
    assigned_by_user_id = NULL
WHERE calendar_year = 2026;

-- 5) Hapus THR disbursements
DELETE FROM hcm_thr_disbursements WHERE 1=1;

-- 6) Reset THR batch lines
UPDATE hcm_thr_batch_lines
SET payment_status = 'unpaid',
    slip_storage_path = NULL,
    paid_at = NULL,
    payment_gateway_ref = NULL,
    payment_failure_reason = NULL
WHERE hcm_thr_batch_id IN (SELECT id FROM hcm_thr_batches WHERE calendar_year = 2026);

SET FOREIGN_KEY_CHECKS = 1;
SET SQL_SAFE_UPDATES = 1;

COMMIT;

-- Verify hasil:
SELECT CONCAT('Periods: ', COUNT(*)) as result FROM hcm_payroll_periods
UNION ALL
SELECT CONCAT('Runs: ', COUNT(*)) FROM hcm_payroll_runs
UNION ALL
SELECT CONCAT('Lines: ', COUNT(*)) FROM hcm_payroll_lines
UNION ALL
SELECT CONCAT('THR status: ', status) FROM hcm_thr_batches WHERE calendar_year = 2026;