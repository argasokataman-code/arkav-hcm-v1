# Employee Salary - E2E UI Testing

## Objective

Memastikan flow UI Employee Salary tervalidasi untuk role admin dan non-admin sesuai guard akses.

## Local Execution Setup

1. Jalankan app lokal
   - Backend: `http://127.0.0.1:8007/health`
   - Frontend: `http://127.0.0.1:5179/login`
   - Start service: `./run.sh`
2. Seed data
   - `cd backend && php artisan db:seed`
3. Akun uji
   - Admin: `qa.login@example.com / StrongPass1`
   - Non-admin/company: `demo.owner01@example.com / StrongPass1`

## Playwright Commands

- `cd backend && npm run e2e:employee-salary`
- `cd backend && npm run e2e:employee-salary:mobile`

## Scenarios

1. Admin access page `/employee-salary`
2. Admin search by name/email
3. Admin filter by employment status
4. Admin open edit modal and submit compensation update
5. Admin verify updated value persisted by reopening modal
6. Admin rollback edited value to initial baseline (test-data safe rerun)
7. Admin menambah assignment payroll item custom pada karyawan (nominal > 0)
8. Admin mengubah nominal assignment custom dan toggle aktif/nonaktif
9. Admin menghapus assignment custom karyawan
10. Non-admin (company mode login) redirected to `/employee-dashboard`

## Latest Execution Snapshot

Tanggal eksekusi: 2026-04-13

- Command: `npm run e2e:employee-salary`
- Result: `2 passed`

## Manual UI E2E Execution Log

| Date | Role | Scenario | Result | Notes |
|------|------|----------|--------|-------|
| 2026-04-13 | HCM Admin | Scenario 1-6 | PASS | Search/filter/edit + verify persisted + rollback baseline via Playwright |
| 2026-04-13 | Company/Non-Admin | Scenario 7 | PASS | Login company mode + redirect ke `/employee-dashboard` tervalidasi |