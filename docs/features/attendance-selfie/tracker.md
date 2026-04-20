# Attendance Selfie Tracker

## Snapshot Status

- Tanggal: 2026-04-20
- Status: ready for deployment
- Ringkasan: selfie attendance sudah aktif sebagai bukti visual attendance harian dengan camera modal di halaman employee, private storage, integrity hash, dan admin download yang tetap tenant-scoped.

## Evidence Terbaru

- Runtime host aktif di `backend/resources/views/attendance-employee.blade.php`.
- Bundle selfie tetap terintegrasi di `frontend/resources/js/attendance-data.js`.
- Kontrak API selfie aktif dan terdokumentasi di `docs/api/hcm-attendance-api.md`.
- Backend tenant/success path tervalidasi di `backend/tests/Feature/AttendanceSelfieTest.php`.
- Guard tombol selfie sebelum attendance dimulai tervalidasi di `backend/tests/ui/attendance.wiring.test.js`.
- Checklist manual dan debugging kamera sudah tersedia di `TESTING.md` dan `DEBUG-CAMERA.md`.

## Gap Aktif

1. Belum ada ringkasan evidence manual lintas browser untuk permission camera dan kualitas preview.
2. Troubleshooting kamera masih manual dan belum punya automated browser coverage untuk stream media nyata.
3. Jika nanti ada kewajiban blur/masking image, kontrak dan storage policy harus ditinjau ulang.

## Keputusan Saat Ini

- Selfie attendance dianggap siap dipakai sebagai attachment audit untuk attendance tenant aktif.
- Perubahan berikutnya wajib mempertahankan gating attendance-started, private storage, dan tenant-scoped admin download.
