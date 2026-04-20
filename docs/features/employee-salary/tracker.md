# Employee Salary Tracker

## Snapshot Status

- Tanggal: 2026-04-20
- Status: ready for deployment
- Ringkasan: surface kompensasi karyawan aktif sebagai sumber data utama payroll monthly, THR, PKWT, dan kalkulator lembur; assignment payroll item custom per karyawan juga sudah menjadi bagian runtime aktif.

## Evidence Terbaru

- Runtime page aktif di `backend/resources/views/employee-salary.blade.php`.
- Asset route-specific aktif di `build/js/employee-salary-data.js` lewat `footer-scripts.blade.php`.
- Guard web admin untuk `/employee-salary` tervalidasi di `backend/tests/Feature/WebHcmRouteGuardTest.php`.
- Mutasi kompensasi dan employees API tervalidasi di `backend/tests/Feature/HcmEmployeeApiTest.php`.
- README feature sekarang sudah mengikat flow kompensasi ini ke payroll draft, payslip, dan preview PKWT.

## Gap Aktif

1. Evidence manual UI E2E admin vs non-admin masih tersimpan terpisah di `E2E-TESTING.md` dan belum diringkas sebagai log eksekusi terbaru.
2. Jika form kontrak/kompensasi bertambah field baru, tracker ini harus ikut memperbarui dependensi ke PKWT dan payroll draft.

## Keputusan Saat Ini

- Anggap surface `/employee-salary` siap deploy untuk runtime aktif dan integrasi payroll utama.
- Perlakukan sisa pekerjaan sebagai pengayaan evidence QA dan governance data kontrak, bukan blocker deploy.