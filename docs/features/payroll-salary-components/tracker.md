# Payroll Salary Components Tracker

## Snapshot Status

- Tanggal: 2026-04-20
- Status: ready for deployment
- Ringkasan: source of truth master komponen payroll sudah siap dipakai runtime; komponen inti, linking, dan batas compliance policy vs implementasi teknis sekarang sudah jelas.

## Evidence Terbaru

- README feature sudah menegaskan `/salary-component-master` tetap menjadi halaman CRUD master aktif.
- Halaman `/salary-component-master` kini menampilkan lifecycle note bahwa koreksi komponen payroll reguler harus memakai void + recalculation selama run belum paid.
- Evidence backend API tersedia di `backend/tests/Feature/HcmSalaryComponentApiTest.php`.
- Guard web admin master component surface ikut ter-cover di `backend/tests/Feature/WebHcmRouteGuardTest.php`.
- README feature sekarang memuat decision matrix komponen yang wajib dimasterkan vs yang boleh tetap sebagai payroll item kustom.
- Compliance boundary kini ditulis eksplisit: sign-off kebijakan payroll berbeda dari readiness runtime master component.

## Gap Aktif

1. Matriks kepatuhan internal yang lebih detail masih bisa ditambahkan bila owner bisnis memerlukannya sebagai artefak governance terpisah.
2. Decision matrix harus tetap dijaga sinkron jika daftar komponen inti payroll bertambah.

## Keputusan Saat Ini

- Anggap audit teknis integrasi utama, pemisahan surface, dan readiness deploy master component sudah tertutup.
- Perlakukan sisa pekerjaan sebagai governance/policy maintenance, bukan blocker deploy atau regression CRUD/API inti.