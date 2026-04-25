# Attendance Selfie Tracker

## Snapshot Status

- Tanggal: 2026-04-25
- Status: needs hardening follow-up
- Ringkasan: flow selfie attendance berfungsi, tetapi audit menemukan gap dokumentasi-vs-runtime, validasi upload yang belum ketat, dan coverage test yang belum cukup kuat untuk mencegah regresi.

## Evidence Terbaru

- Runtime host aktif di `backend/resources/views/attendance-employee.blade.php`.
- Bundle selfie tetap terintegrasi di `frontend/resources/js/attendance-data.js`.
- Kontrak API selfie aktif dan terdokumentasi di `docs/api/hcm-attendance-api.md`.
- Backend tenant/success path tervalidasi di `backend/tests/Feature/AttendanceSelfieTest.php`.
- Guard tombol selfie sebelum attendance dimulai tervalidasi di `backend/tests/ui/attendance.wiring.test.js`.
- Checklist manual dan debugging kamera sudah tersedia di `TESTING.md` dan `DEBUG-CAMERA.md`.

## Audit Anomali (2026-04-25)

1. **Doc/runtime drift frontend host**
	- Dokumen menyatakan runtime selfie dikelola `frontend/resources/js/attendance-data.js`, tetapi implementasi camera lifecycle utama masih inline script di blade (`backend/resources/views/attendance-employee.blade.php`).
2. **Gating backend belum strict ke punch-in**
	- Endpoint upload selfie hanya mengecek attendance record ada, belum memastikan `check_in_at` terisi. Ini bisa membuka celah ketika record dibuat tanpa punch-in (mis. via koreksi/admin path).
3. **Validasi payload selfie belum ketat**
	- Saat ini hanya `base64_decode` + simpan file. Belum ada validasi MIME whitelist, ukuran maksimum, atau verifikasi image payload (`jpeg/png/webp` aman).
4. **Terminologi security berpotensi misleading**
	- Dokumen menyebut “encrypted at storage layer”, sementara disk `private` saat ini adalah local private visibility (bukan encryption-at-rest bawaan aplikasi).
5. **Coverage test selfie masih lemah untuk guard regression**
	- Test feature masih permisif (`status in [200,401,403,422]` / `status != 500`) dan belum mengunci skenario penting (cross-tenant download deny, punch-in required guard, invalid mime/oversize).

## Fix Plan (Proposed)

### Phase 1 - Contract & Security Hardening (High)

1. Perketat upload guard backend:
	- Wajib `attendance` ada **dan** `check_in_at` tidak null sebelum terima selfie.
	- Return `422 ATTENDANCE_NOT_STARTED` untuk record tanpa punch-in.
2. Tambah validasi image payload:
	- Batas ukuran payload (mis. <= 5 MB setelah decode).
	- Validasi MIME whitelist (`image/jpeg`, `image/png`, `image/webp`) via sniffing binary.
	- Tolak payload non-image dengan `422 VALIDATION_ERROR`.
3. Tambah kontrol nama file dan metadata:
	- Pertahankan path tenant-aware.
	- Simpan `uploaded_at` deterministik dari timestamp server (sudah ada), tambahkan audit log error lebih jelas untuk payload reject.

### Phase 2 - Frontend Runtime Consolidation (High)

1. Migrasi logic kamera inline dari blade ke module terstruktur (`attendance-data.js` atau module selfie terpisah yang diimport).
2. Blade tinggal host markup + data attributes; hilangkan business logic JS inline agar testability naik.
3. Satukan source toast/error handling dengan helper frontend yang sudah dipakai modul attendance.

### Phase 3 - Test Strengthening (High)

1. Rewrite/assertion hardening `AttendanceSelfieTest`:
	- Success path harus `200` + `attendance_id/selfie_path/uploaded_at` terisi.
	- Punch-in belum ada -> `422 ATTENDANCE_NOT_STARTED`.
	- Invalid base64/non-image/oversize -> `422 VALIDATION_ERROR`.
2. Tambah tenant-isolation tests:
	- Admin tenant A download selfie tenant B harus `404` atau `403` sesuai kontrak.
3. Tambah UI wiring tests untuk camera state machine:
	- Open -> capture -> retake -> submit; dan deny permission path menampilkan pesan yang jelas.

### Phase 4 - Documentation Sync (Medium)

1. Sinkronkan `README.md`, `IMPLEMENTATION.md`, `TESTING.md`, `DEBUG-CAMERA.md` dengan runtime aktual (termasuk lokasi script/module yang sebenarnya).
2. Perjelas istilah security:
	- Bedakan “private storage visibility” vs “encryption-at-rest”.
3. Jika kontrak error baru ditambah/diubah, update `docs/api/hcm-attendance-api.md` (+ OpenAPI bila diperlukan).

## Acceptance Criteria

1. Upload selfie tidak bisa dilakukan sebelum punch-in terisi (`check_in_at`).
2. Payload non-image/oversize selalu ditolak dengan error terstandar.
3. Test feature + UI untuk selfie menutup success path dan negative path utama.
4. Dokumen feature konsisten dengan runtime aktual (tanpa drift host script).
5. Tenant isolation admin download tervalidasi lewat automated test.

## Gap Aktif

1. Belum ada ringkasan evidence manual lintas browser untuk permission camera dan kualitas preview.
2. Troubleshooting kamera masih manual dan belum punya automated browser coverage untuk stream media nyata.
3. Jika nanti ada kewajiban blur/masking image, kontrak dan storage policy harus ditinjau ulang.
4. Coverage test backend selfie masih belum cukup ketat untuk mencegah regresi guard/security.
5. Drift dokumentasi terhadap lokasi runtime JS selfie perlu diselesaikan.

## Keputusan Saat Ini

- Selfie attendance tetap aktif dipakai, tetapi status quality dinaikkan ke hardening-required sampai Phase 1-3 selesai.
- Perubahan berikutnya wajib mempertahankan gating attendance-started, private storage, dan tenant-scoped admin download.
