# Payslip (My Payslip)

## Ringkasan

Halaman `/payslip` adalah surface **My Payslip** untuk karyawan membaca slip gaji pribadinya yang sudah final. UI ini tidak membentuk payroll baru; tugasnya membaca hasil final dari run payroll yang sudah diposting dan menampilkannya sebagai ringkasan pendapatan, potongan, total bersih, lalu menyediakan unduhan PDF.

Halaman ini memakai fallback ke periode final terbaru agar employee tidak berhenti di layar kosong saat bulan yang dipilih belum punya slip. Status dokumentasi dan evidence terbaru dicatat di `tracker.md`.

## Terminologi UI (Anti-Ambigu)

- **My Payslip**: slip gaji milik user yang sedang login (self-service employee).
- **Payslip Report (All Employees)**: daftar slip lintas karyawan untuk kebutuhan admin/reporting.
- **Payroll Run History**: riwayat eksekusi payroll run (bukan detail slip pribadi).

## Akses

- Employee: mengakses `/payslip` untuk **My Payslip** (slip miliknya sendiri).
- HCM Admin dengan permission `payroll.view` akan diarahkan ke `/payslip-report` (**Payslip Report (All Employees)**) karena audience `/payslip` memang self-service.
- API self-service hanya mengembalikan data user yang sedang login.

## UI Aktif

- Web page: `/payslip`.
- Blade: `backend/resources/views/payslip.blade.php`.
- JS: `frontend/resources/js/payslip-data.js`, dimuat lewat `footer-scripts.blade.php` saat route `payslip` aktif.
- PDF bulanan: `backend/resources/views/pdf/monthly-payslip.blade.php`.
- Untuk audience admin, route laporan terpisah ada di `/payslip-report`.

## Flow Bisnis End-to-End

1. Employee membuka `/payslip`.
2. Frontend memeriksa audience lewat `GET /v1/identity/auth/me`; jika user adalah admin payroll, flow dialihkan ke halaman laporan admin.
3. Frontend mencoba memuat slip bulan yang sedang dipilih memakai `GET /v1/hcm/payroll/my-slip`.
4. Jika periode itu belum punya run final, frontend memakai `GET /v1/hcm/payroll/my-slip-latest-period` untuk pindah ke periode final terbaru yang memang punya slip untuk user tersebut.
5. Setelah data ada, layar menampilkan ringkasan employee, daftar earning, daftar deduction, net pay, dan tautan unduh PDF.
6. Jika belum ada slip final sama sekali, halaman tetap hidup dengan state kosong yang jelas, bukan error fatal.

## Lifecycle Dan Keputusan Bisnis

- Payslip hanya membaca hasil payroll yang sudah final; sumber kebenaran tetap run payroll di backend.
- Agregasi slip bulanan dapat menggabungkan run `monthly`, `thr`, dan `pkwt_compensation` pada bulan kalender yang sama.
- PDF tidak boleh dianggap tersedia sebelum run final ada untuk employee dan periode tersebut.
- Halaman ini tidak membuka aksi mutasi payroll; koreksi dilakukan dari flow admin di payroll run/THR/PKWT, lalu hasilnya tercermin kembali di slip employee.

## Integrasi

- Payroll Runs: hasil final `monthly` menjadi komponen utama slip bulanan. Lihat `../payroll-runs/README.md`.
- Payroll THR: run `thr` pada bulan yang sama ikut muncul dalam agregasi slip. Lihat `../payroll-thr/README.md`.
- Payroll PKWT Compensation: run `pkwt_compensation` pada bulan yang sama juga ikut tampil. Lihat `../payroll-pkwt-compensation/README.md`.
- Employee Salary dan Payroll Items: perubahan kompensasi dan assignment item memengaruhi isi payroll run yang kemudian terlihat di slip final.

## Kontrak API

- `GET /v1/hcm/payroll/my-slip?periodYear=&periodMonth=` untuk data slip agregat.
- `GET /v1/hcm/payroll/my-slip-latest-period` untuk fallback UX ke periode final terbaru.
- `GET /v1/hcm/payroll/my-slip-pdf?periodYear=&periodMonth=` untuk unduhan PDF.
- Referensi kontrak utama: `docs/api/hcm-payroll-api.md` dan `docs/api/openapi.yaml`.

## Existing Vs Target

- Existing: `/payslip` web aktif, memakai API self-service yang hidup, punya fallback periode final terbaru, dan menyediakan PDF slip bulanan.
- Existing: agregasi run `monthly`, `thr`, dan `pkwt_compensation` sudah menjadi bagian dari surface slip karyawan.
- Target: penguatan berikutnya lebih ke hardening operasional, misalnya bukti manual E2E per role dan review UX empty-state lintas tenant jika ada perubahan aturan payroll di masa depan.
