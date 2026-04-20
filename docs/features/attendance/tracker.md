# Attendance Tracker

## Snapshot Status

- Tanggal: 2026-04-20
- Status: ready for deployment
- Ringkasan: menu attendance employee, attendance admin, dan attendance report sudah aktif dengan tenant scope server-side, guard archive snapshot di UI, serta lokasi attendance yang terbaca konsisten lintas halaman.

## Evidence Terbaru

- Runtime page aktif di `backend/resources/views/attendance-employee.blade.php`, `attendance-admin.blade.php`, dan `attendance-report.blade.php`.
- Bundle aktif tetap terpusat di `frontend/resources/js/attendance-data.js`.
- Kontrak admin write dan timesheet negative path tervalidasi di `backend/tests/Feature/AttendanceApiTest.php`.
- Tenant scope admin attendance tervalidasi di `backend/tests/Feature/AttendanceAdminTenantScopeTest.php`.
- Guard archive report attendance dan timesheet invalid range tervalidasi di `backend/tests/ui/attendance.wiring.test.js`.
- Manual snapshot selector reporting juga tervalidasi di `backend/tests/ui/report-snapshot-selector.wiring.test.js` untuk konsistensi archive mode lintas report pages.

## Gap Aktif

1. Evidence manual UI E2E untuk employee/admin/report attendance belum diringkas sebagai log eksekusi terbaru.
2. Correction request masih belum punya approval chain berlapis di dokumentasi maupun runtime.
3. Bundle JS attendance masih memegang banyak menu sekaligus sehingga regression surface tetap luas setiap kali file ini disentuh.

## Keputusan Saat Ini

- Perlakukan attendance core sebagai surface runtime aktif yang siap dipertahankan sebagai source of truth presensi tenant.
- Perubahan berikutnya di area attendance harus ikut mengecek tenant scope, archive/live consistency, dan negative scenario date/filter sebelum dianggap selesai.
