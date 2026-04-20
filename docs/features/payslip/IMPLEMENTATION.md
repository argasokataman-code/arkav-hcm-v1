# Payslip Implementation

## Runtime Surface

- Web route memakai `backend/resources/views/payslip.blade.php`.
- Frontend runtime ada di `frontend/resources/js/payslip-data.js`.
- PDF renderer ada di `backend/resources/views/pdf/monthly-payslip.blade.php`.
- Admin audience dipisah ke `backend/resources/views/payslip-report.blade.php`.

## API Yang Dipakai

- `GET /v1/hcm/payroll/my-slip`
- `GET /v1/hcm/payroll/my-slip-latest-period`
- `GET /v1/hcm/payroll/my-slip-pdf`
- `GET /v1/identity/auth/me` untuk audience check di frontend

## Perilaku Penting

- Frontend melakukan redirect ke `/payslip-report` bila payload identity menunjukkan permission `payroll.view`.
- Bila periode yang dipilih belum punya slip final, frontend meminta periode final terbaru dan memuat ulang layar dengan periode tersebut.
- Download button hanya aktif saat `downloadUrl` dikembalikan backend.
- Empty state dan error state dipisah agar karyawan bisa membedakan antara "belum ada slip" dan "request gagal".

## Dependensi Data

- Data utama berasal dari run payroll `finalized` untuk user yang sedang login.
- Agregasi backend dapat menggabungkan line dari purpose `monthly`, `thr`, dan `pkwt_compensation` pada bulan kalender yang sama.
- Nomor slip display memakai format `SLIP-YYYY-MM-USERID` pada response self-service.

## Test Dan Evidence

- `backend/tests/Feature/HcmPayrollRunApiTest.php` mencakup self-service payslip, latest finalized period, dan agregasi penerima special run pada periode yang sama.
- Runtime frontend sudah ter-wire di footer script untuk route `payslip`.
- Evidence status dan snapshot terbaru diringkas di `tracker.md`.

## Risiko Yang Masih Harus Dijaga

- Jika kontrak agregasi payroll berubah, `/payslip` harus diuji ulang karena employee tidak punya kontrol korektif dari layar ini.
- Redirect audience admin vs employee harus tetap sinkron dengan permission server agar tidak terjadi kebocoran context.
