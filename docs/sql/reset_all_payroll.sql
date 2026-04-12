SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM hcm_payroll_lines;
DELETE FROM hcm_payroll_runs;
DELETE FROM hcm_payroll_periods;

UPDATE hcm_thr_batches 
SET status = 'draft', 
    hcm_payroll_run_id = NULL,
    hcm_payroll_period_id = NULL,
    assigned_at = NULL,
    assigned_by_user_id = NULL
WHERE calendar_year = 2026;

DELETE FROM hcm_thr_disbursements;

UPDATE hcm_thr_batch_lines 
SET payment_status = 'unpaid',
    slip_storage_path = NULL,
    paid_at = NULL
WHERE hcm_thr_batch_id IN (SELECT id FROM hcm_thr_batches WHERE calendar_year = 2026);

SET FOREIGN_KEY_CHECKS = 1;

-- Pastikan DBeaver auto-commit ON atau tekan Ctrl+Shift+C (commit)
-- Kalau masih tidak jalan, coba jalankan setiap baris DELETE/UPDATE satu per satu