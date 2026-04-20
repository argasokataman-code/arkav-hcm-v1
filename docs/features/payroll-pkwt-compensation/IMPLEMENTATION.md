# Payroll PKWT Compensation Implementation

## Runtime Surface

- Blade utama: `backend/resources/views/payroll-pkwt-compensation.blade.php`.
- Asset frontend: `build/js/pkwt-compensation-data.js`, dimuat dari `footer-scripts.blade.php` untuk route `payroll-pkwt-compensation`.
- Flow pembayaran lanjut memakai endpoint payroll run/disburse setelah run PKWT terbentuk.

## API Yang Dipakai

- Preview bulanan: `GET /v1/hcm/payroll/pkwt-compensations?periodYear=&periodMonth=`
- Generate standalone payroll: `POST /v1/hcm/payroll/pkwt-compensations/post-payroll`
- Kalkulator cepat: `POST /v1/hcm/payroll/pkwt-calculate`

## Perilaku Penting

- Preview hanya memuat employee PKWT yang `contract_end_date` jatuh pada bulan yang dipilih.
- Post-payroll membuat atau membangun ulang draft payroll purpose `pkwt_compensation` untuk periode yang dipilih.
- Bila enforcement export reconciliation aktif, post-payroll akan ditolak sebelum evidence tersedia.
- Hasil final dari run PKWT kemudian ikut terlihat di `my-slip-lines` dan agregasi `/payslip`.

## Dependensi Data

- Metadata kontrak, base salary, dan fixed allowance berasal dari profil employee.
- Komponen master `kompensasi_pkwt` harus aktif agar payroll line bisa dibentuk.
- Flow pembayaran aktual tetap bergantung pada lifecycle payroll run setelah draft PKWT berhasil dibangun.

## Test Dan Evidence

- `backend/tests/Feature/HcmPayrollPkwtApiTest.php` mencakup preview eligible, post-payroll ke standalone run, slip line integration, dan reconciliation enforcement.
- `backend/tests/Feature/HcmPayrollRunApiTest.php` juga memverifikasi penerima run purpose `pkwt_compensation` pada periode yang sama.
- Snapshot status terbaru disimpan di `tracker.md`.

## Risiko Yang Masih Harus Dijaga

- Karena fitur ini bergantung pada kualitas metadata kontrak employee, drift data kontrak di profil akan langsung memengaruhi preview dan nominal kompensasi.
- Jika formula kompensasi PKWT berubah, test numerik dan README harus diperbarui bersamaan agar tidak mengulang drift dokumentasi.
