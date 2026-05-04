# Payroll PKWT Compensation

## Ringkasan

Halaman `/payroll-pkwt-compensation` dipakai HCM Admin untuk melihat daftar karyawan PKWT yang kontraknya berakhir pada bulan tertentu, menghitung estimasi kompensasi proporsional, membangun draft payroll purpose `pkwt_compensation`, lalu meneruskan pembayaran lewat flow payroll run.

Fitur ini menutup jarak antara data kontrak di profil karyawan dan kebutuhan pembayaran kompensasi kontrak yang terpisah dari payroll monthly biasa. Status dokumentasi dan evidence terbaru dicatat di `tracker.md`.

## Akses

- Web: `/payroll-pkwt-compensation` hanya tersedia untuk admin payroll.
- API preview dan post-payroll PKWT adalah HCM Admin only.
- Hasil final untuk employee muncul kembali melalui slip bulanan bila run `pkwt_compensation` pada bulan tersebut sudah final.

## UI Aktif

- Web page: `/payroll-pkwt-compensation`.
- Blade: `backend/resources/views/payroll-pkwt-compensation.blade.php`.
- JS: asset `pkwt-compensation-data.js`, dimuat saat route `payroll-pkwt-compensation` aktif.
- Halaman ini juga menyediakan quick calculator dan status run periode yang sedang dipreview.

## Flow Bisnis End-to-End

1. HCM Admin memilih tahun dan bulan untuk melihat kontrak PKWT yang berakhir pada periode itu.
2. Sistem menampilkan preview employee eligible, masa kerja, upah acuan berbasis gaji pokok aktif, dan nominal kompensasi.
3. Admin dapat menjalankan quick calculator untuk satu kontrak sebagai verifikasi cepat.
4. Jika data sudah benar, admin menjalankan `Generate draft payroll` untuk membuat atau membangun ulang run purpose `pkwt_compensation` pada periode tersebut.
5. Pembayaran aktual dilakukan lewat flow payroll run/disburse untuk run yang sudah terbentuk.
6. Setelah run final, hasil kompensasi ikut masuk ke slip employee pada bulan kalender yang sama.

## Lifecycle Dan Keputusan Bisnis

- PKWT compensation diperlakukan sebagai payroll purpose terpisah agar jejak pembayaran kontrak tidak tercampur dengan draft monthly biasa.
- Hanya kontrak yang benar-benar berakhir pada bulan yang dipilih yang boleh masuk preview eligible.
- Post-payroll ditolak bila tidak ada employee eligible atau komponen master `kompensasi_pkwt` belum tersedia.
- Enforcement export reconciliation dapat memblokir post-payroll bila kebijakan evidence diaktifkan.
- Data kontrak yang disunting dari `employee-salary` menjadi sumber utama preview bulanannya.

## Integrasi

- Employee Salary: metadata `pkwt` / tanggal kontrak dari profil karyawan menjadi input utama preview. Lihat `../employee-salary/README.md`.
- Payroll Runs: run purpose `pkwt_compensation` dibayar dan dihistori lewat flow payroll run biasa. Lihat `../payroll-runs/README.md`.
- Payslip: hasil final ikut muncul di `/payslip` pada bulan yang sama. Lihat `../payslip/README.md`.
- Export Reconciliation: post-payroll dapat digate oleh evidence export. Lihat `../export-reconciliation/README.md`.

## Kontrak API

- `GET /v1/hcm/payroll/pkwt-compensations?periodYear=&periodMonth=`
- `POST /v1/hcm/payroll/pkwt-compensations/post-payroll`
- `POST /v1/hcm/payroll/pkwt-calculate`

Referensi kontrak utama: `docs/api/hcm-payroll-api.md` dan `docs/api/openapi.yaml`.

## Existing Vs Target

- Existing: preview employee eligible, quick calculator, generate standalone payroll, status run aktif, dan integrasi ke slip employee sudah aktif.
- Existing: halaman sudah memuat indicator evidence/export untuk flow yang butuh rekonsiliasi.
- Target: penguatan selanjutnya terutama berupa evidence manual payment flow dan review kebijakan saat aturan kompensasi kontrak berubah, bukan pembangunan runtime dasarnya.
