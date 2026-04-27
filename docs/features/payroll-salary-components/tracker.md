# Payroll Salary Components Tracker

## Snapshot 2026-04-27

- Status: salary component dan category master sekarang sudah murni CRUD; lock delete legacy/hardcoded dihapus dari runtime.

### Evidence Runtime

- `HcmSalaryComponent::categoriesForKind()` sekarang membaca master kategori dinamis apa adanya saat tabel `hcm_salary_component_categories` tersedia, tanpa merge paksa daftar default.
- `DELETE /v1/hcm/salary-components/{id}` sekarang mengizinkan hapus semua komponen, termasuk seed/system dan komponen dengan integrasi legacy.
- `DELETE /v1/hcm/salary-component-categories/{id}` sekarang menghapus kategori beserta seluruh komponen di kategori itu agar tidak menyisakan referensi kategori yatim.
- UI `salary-component-master-data.js` sekarang menampilkan aksi hapus untuk semua row kategori/komponen, dengan konfirmasi eksplisit untuk delete kategori yang bersifat cascading.

### Evidence Test

- `php artisan test tests/Feature/HcmSalaryComponentApiTest.php`
- Suite ini sekarang mencakup delete komponen integrated/system dan delete kategori dengan efek cascade ke komponennya.

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