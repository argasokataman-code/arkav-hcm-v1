# Attendance Selfie - Implementation

## Overview

Attendance selfie diimplementasikan sebagai ekstensi dari attendance employee, bukan modul berdiri sendiri. Runtime aktifnya tersebar di:

- API/controller: `backend/app/Http/Controllers/Api/AttendanceController.php`
- View host: `backend/resources/views/attendance-employee.blade.php`
- Frontend runtime: `frontend/resources/js/attendance-data.js`
- Storage private attendance files: disk Laravel privat untuk selfie image

## API Endpoints

- `POST /v1/hcm/attendance/me/selfie`
- `GET /v1/hcm/attendance/me/selfie/status`
- `GET /v1/hcm/attendance/admin/records/{id}/selfie/download`

Input utama upload:
- `selfie_base64`
- `timestamp`

Output utama:
- `attendance_id`
- `selfie_path`
- `uploaded_at`

## Runtime Rules

- Backend mencari attendance record hari ini berdasarkan user auth dan company aktif.
- Jika attendance hari itu belum dimulai, upload gagal dengan `422 ATTENDANCE_NOT_STARTED`.
- File selfie disimpan pada path tenant-aware seperti `selfie/{companyId}/...`.
- Hash SHA256 atas binary image disimpan ke `attendance_records.selfie_encrypted_hash` untuk audit.
- Endpoint admin download memverifikasi bahwa record attendance berada pada tenant aktif sebelum file dibuka.

## Frontend Flow

`attendance-data.js` mengelola:
- lookup tombol selfie dan modal element;
- start/stop kamera via `navigator.mediaDevices.getUserMedia()`;
- capture frame video ke canvas;
- retake flow;
- submit base64 image ke backend;
- refresh attendance page setelah upload berhasil.

Guard UI penting:
- tombol selfie disabled sampai `punchState` menandakan attendance sudah dimulai;
- submit tanpa capture harus ditolak sebelum request dikirim;
- permission denied dari browser harus menghasilkan pesan yang jelas.

## Data Model

Kolom attendance record yang relevan:
- `selfie_path`
- `selfie_encrypted_hash`
- `updated_at`

Selfie melekat pada record attendance harian yang sama, sehingga tidak memerlukan tabel terpisah untuk versi runtime saat ini.

## Security Notes

- File tidak diekspos sebagai public URL tetap.
- Integrity check menggunakan hash SHA256 dari payload gambar.
- Download admin memerlukan auth dan tenant scope yang sama dengan record attendance.
- Cross-tenant download attempt harus gagal `404` atau `403` tanpa membocorkan keberadaan file.

## Tests

Backend:
- `backend/tests/Feature/AttendanceSelfieTest.php`
- `backend/tests/Feature/AttendanceApiTest.php`

Frontend/Vitest:
- `backend/tests/ui/attendance.wiring.test.js`

Dokumen manual:
- `TESTING.md`
- `DEBUG-CAMERA.md`

Coverage aktif:
- upload selfie valid
- status selfie setelah upload
- gagal upload saat attendance belum dimulai
- admin download tenant-scoped
- tombol selfie tidak aktif sebelum punch in

## Known Limits

- Belum ada kompresi atau multiple-size derivative; file asli disimpan sebagai payload audit.
- Fallback UX untuk perangkat tanpa kamera masih berbasis pesan error browser dan belum memakai alternate upload flow.
- Evidence manual Safari/Firefox belum diringkas di tracker.
