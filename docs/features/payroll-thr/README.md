# Payroll THR

## Ringkasan

Halaman `/payroll-thr` dipakai HCM Admin untuk mengelola siklus THR per tahun kalender: menyimpan tanggal referensi hari raya, cut-off pro rata, menghitung estimasi THR, membuat batch karyawan eligible, mengeksekusi pembayaran, lalu mem-posting hasilnya ke payroll purpose `thr`.

Flow ini terpisah dari payroll bulanan karena tanggal bayar THR sering tidak sama dengan tanggal payroll rutin. Status dokumentasi dan evidence terbaru dicatat di `tracker.md`.

## Akses

- Web: `/payroll-thr` hanya tersedia lewat guard admin payroll.
- API: seluruh endpoint `thr-calculate`, `thr-settings`, `thr-batch`, `thr-batch/disburse`, dan `thr-batch/post-payroll` adalah HCM Admin only.
- Self-service employee untuk hasil akhirnya memakai `GET /v1/hcm/payroll/my-thr-slip` atau PDF slip THR miliknya sendiri.

## UI Aktif

- Web page: `/payroll-thr`.
- Blade: `backend/resources/views/payroll-thr.blade.php`.
- JS: asset `thr-payroll-batch.js`, dimuat saat route `payroll-thr` aktif.
- PDF slip THR: `backend/resources/views/pdf/thr-slip.blade.php`.

## Flow Bisnis End-to-End

1. HCM Admin menyimpan pengaturan tahunan THR: tanggal hari raya, tanggal pembayaran, cut-off pro rata, dan catatan internal.
2. Admin bisa menjalankan kalkulator cepat untuk satu karyawan/kontrak sebagai sanity check.
3. Setelah cut-off tersedia, admin menjalankan generate batch untuk membentuk daftar karyawan eligible pada tahun tersebut.
4. Admin meninjau nominal per karyawan, lalu menjalankan export reconciliation bila enforcement aktif.
5. Admin membayar line THR yang terpilih lewat flow disburse batch.
6. Setelah seluruh line payable berstatus `paid`, admin mem-posting batch menjadi payroll purpose `thr`.
7. Hasil final ikut muncul di slip employee dan tersedia sebagai PDF slip THR per line.

## Lifecycle Dan Keputusan Bisnis

- THR diperlakukan sebagai siklus tahunan yang berdiri sendiri, bukan sekadar tambahan di payroll monthly.
- Batch hanya bisa diposting ke payroll bila semua line yang harus dibayar sudah `paid`.
- Tahun yang sudah `assigned` tidak boleh di-generate ulang lewat endpoint draft biasa.
- Employee yang resign/terminate approved harus keluar dari batch eligible; pending resignation tidak otomatis memblokir.
- Slip THR untuk employee tetap terpisah dari halaman web khusus; JSON self-service dan PDF menjadi surface utama hasil akhir.

## Integrasi

- Payroll Runs: hasil post-payroll membuat run purpose `thr` yang ikut terbaca di histori payroll dan agregasi payslip. Lihat `../payroll-runs/README.md`.
- Payslip: periode yang sama akan menampilkan hasil THR di `/payslip`. Lihat `../payslip/README.md`.
- Employee Salary: dasar pro rata memakai gaji pokok pada implementasi saat ini. Lihat `../employee-salary/README.md`.
- Export Reconciliation: disburse dan post-payroll bisa digate oleh evidence export. Lihat `../export-reconciliation/README.md`.

## Kontrak API

- `POST /v1/hcm/payroll/thr-calculate`
- `GET /v1/hcm/payroll/thr-settings`
- `PUT /v1/hcm/payroll/thr-settings/{calendarYear}`
- `GET /v1/hcm/payroll/thr-batch?calendarYear=`
- `POST /v1/hcm/payroll/thr-batch/generate`
- `POST /v1/hcm/payroll/thr-batch/disburse`
- `POST /v1/hcm/payroll/thr-batch/post-payroll`
- `GET /v1/hcm/payroll/thr-batch/lines/{line}/slip`
- `GET /v1/hcm/payroll/my-thr-slip`

Referensi kontrak utama: `docs/api/hcm-payroll-api.md` dan `docs/api/openapi.yaml`.

## Existing Vs Target

- Existing: UI pengaturan tahunan, kalkulator THR, batch generate, disburse, post-payroll, slip PDF, dan evidence indicator sudah aktif.
- Existing: batch sudah menghormati filter employee aktif/probation dan mengecualikan resignation/termination approved.
- Target: penguatan selanjutnya lebih ke bukti operasional dan manual E2E admin, bukan lagi kekosongan runtime dasar.
