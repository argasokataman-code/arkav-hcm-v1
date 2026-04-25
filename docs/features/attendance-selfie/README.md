# Attendance Selfie

## Ringkasan

Feature ini mendokumentasikan menu selfie attendance yang menjadi lampiran bukti visual untuk record absensi harian. Selfie tidak berdiri sendiri: flow-nya selalu mengikuti attendance employee, hanya aktif setelah punch in, disimpan ke storage privat, dan bisa diunduh admin untuk audit record yang relevan.

Dokumen teknis pendamping:
- `IMPLEMENTATION.md` untuk route, controller, storage, dan test surface.
- `tracker.md` untuk snapshot readiness dan evidence aktif.
- `TESTING.md` untuk checklist UI/API manual.
- `DEBUG-CAMERA.md` untuk troubleshooting kamera browser.

## Akses

- Employee: mengambil selfie dari `/attendance-employee` melalui `POST /v1/hcm/attendance/me/selfie` dan mengecek status via `GET /v1/hcm/attendance/me/selfie/status`.
- HCM Admin: mengunduh selfie pada record attendance tenant aktif melalui `GET /v1/hcm/attendance/admin/records/{id}/selfie/download`.
- Non-admin tidak memiliki akses download selfie record orang lain.

## UI Aktif

- Tombol `Ambil Selfie` berada di halaman `/attendance-employee`.
- Modal selfie memuat video stream, tombol `Ambil Foto`, `Ulangi`, `Simpan Selfie`, dan badge bahwa file diproses secara aman.
- Tidak ada menu terpisah untuk selfie; seluruh flow dirender sebagai bagian dari bundle `frontend/resources/js/attendance-data.js`.

## Flow Bisnis End-to-End

1. Employee membuka `/attendance-employee` dan sistem memuat status attendance hari ini.
2. Tombol selfie tetap nonaktif sampai employee berhasil punch in untuk hari tersebut.
3. Saat tombol dibuka, browser meminta izin kamera dan frontend memulai stream `facingMode: user`.
4. Employee mengambil foto, meninjau preview, lalu mengirim base64 image ke `POST /v1/hcm/attendance/me/selfie`.
5. Backend mencari attendance record hari ini pada tenant aktif, memvalidasi bahwa attendance sudah dimulai (`check_in_at` sudah ada), lalu menyimpan file ke storage privat.
6. Backend menghitung hash SHA256 atas payload gambar untuk integrity check dan mengembalikan path file + waktu upload.
7. Admin dapat mengunduh selfie dari record attendance tertentu jika record itu masih berada dalam tenant aktif.

## Lifecycle Dan Keputusan Bisnis

- Selfie hanya valid setelah attendance hari itu dimulai; sebelum punch in request harus gagal `ATTENDANCE_NOT_STARTED`.
- Selfie dianggap bukti lampiran attendance, bukan pengganti GPS atau timestamp punch.
- Storage file harus privat; yang dibuka ke admin adalah response download terotorisasi, bukan URL publik.
- Hash SHA256 disimpan untuk audit integrity, bukan untuk akses publik.
- Download selfie wajib ikut tenant scope record attendance; admin tenant A tidak boleh membaca file tenant B.
- Payload selfie hanya menerima gambar valid (`jpeg/png/webp`) dengan batas ukuran maksimum 5MB setelah decode.

## Integrasi

- Attendance core: gating tombol dan refresh status mengikuti attendance employee. Lihat `../attendance/README.md`.
- Security/storage: file disimpan privat dan hanya dibuka melalui controller yang memeriksa auth + tenant.
- Reporting/audit: admin attendance dapat memakai selfie sebagai bukti saat memeriksa anomali punch atau correction.

## Kontrak API

Base path: `/v1/hcm`

- `POST /attendance/me/selfie`
- `GET /attendance/me/selfie/status`
- `GET /attendance/admin/records/{id}/selfie/download`

Source of truth kontrak: `docs/api/hcm-attendance-api.md`.

## Existing Vs Target

- Existing: kamera modal, upload selfie, hash integrity, private storage, dan admin download tenant-scoped sudah aktif.
- Existing: frontend sudah memblok tombol selfie sebelum attendance dimulai.
- Existing: backend menolak upload selfie jika attendance belum punch-in dan menolak payload non-image/oversize.
- Target: evidence manual lintas browser dan fallback UX untuk error perangkat kamera masih bisa diperkaya tanpa mengubah kontrak inti.
