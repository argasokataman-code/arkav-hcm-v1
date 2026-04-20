# Payroll Items Tracker

## Snapshot Status

- Tanggal: 2026-04-20
- Status: ready for deployment
- Ringkasan: payroll item runtime dan narasi operasional utamanya sudah selaras; feature siap dipakai sebagai katalog operasional tenant untuk membentuk payroll lines.

## Evidence Terbaru

- README feature sudah menegaskan `/payroll` bukan redirect atau alias dari `/salary-component-master`.
- Halaman `/payroll` kini menampilkan lifecycle note bahwa perubahan item berlaku untuk draft berikutnya atau draft recalculation, bukan untuk run yang sudah paid.
- Evidence backend API tersedia di `backend/tests/Feature/HcmPayrollItemApiTest.php`.
- Evidence assignment tersedia di `backend/tests/Feature/HcmPayrollItemAssignmentApiTest.php`.
- Guard web admin payroll surfaces ikut ter-cover di `backend/tests/Feature/WebHcmRouteGuardTest.php`.
- README feature sekarang memuat decision matrix item kustom vs linked master vs assignment per karyawan.

## Gap Aktif

1. Decision matrix item kustom vs master harus tetap dijaga konsisten saat tenant menambah komponen baru.
2. Jika payroll flow bertambah luas, narasi lintas modul perlu diperbarui agar tetap business-readable.

## Keputusan Saat Ini

- Anggap audit kontrak utama payroll items dan narasi deploy-readiness saat ini sudah tertutup.
- Perlakukan sisa pekerjaan sebagai maintenance dokumentasi operasional, bukan blocker deploy atau regression API.