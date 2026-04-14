# Reporting System - E2E Testing

## Setup

1. Jalankan service lokal dari root repo:
   - `./run.sh`
2. Pastikan endpoint tersedia:
   - backend health: `http://127.0.0.1:8007/health`
   - web app: `http://127.0.0.1:5179/login`
3. Akun uji:
   - Admin: `qa.login@example.com / StrongPass1`
   - Non-admin: `demo.owner01@example.com / StrongPass1`

## Scenario 1 - Reports Hub Generate + Export (Admin)

1. Login sebagai admin.
2. Buka halaman Reports Hub.
3. Generate snapshot:
   - reportType: `employee`
   - period: 30 hari terakhir
   - async: `false`
4. Validasi row snapshot muncul status `completed`.
5. Trigger export `csv`, `excel`, `pdf`.
6. Validasi URL export terisi dan file bisa diunduh.

Expected:

- Semua request sukses (`202` untuk generate, `201` untuk export)
- File tersimpan dan dapat diakses di `/storage/report-exports/...`

## Scenario 2 - Employee Report Live vs Archive (Admin)

1. Buka `/employee-report`.
2. Mode `Live Data` -> klik `Load`.
3. Validasi kartu summary dan tabel terisi.
4. Ubah ke `Archive Snapshot`.
5. Isi `Snapshot ID` valid untuk report type `employee`.
6. Klik `Load`.

Expected:

- Badge source berubah mengikuti mode
- Archive berhasil menampilkan summary + baris agregat status
- Jika snapshot ID kosong, tampil pesan validasi

## Scenario 3 - Leave Report Live vs Archive (Admin)

1. Buka `/leave-report`.
2. Mode `Live Data` -> klik `Load`.
3. Validasi summary requests/days/approved/pending dan tabel leave.
4. Ubah ke `Archive Snapshot`.
5. Isi `Snapshot ID` valid untuk report type `leave`.
6. Klik `Load`.

Expected:

- Badge source menampilkan `Archive #<id>`
- Summary archive terisi dari snapshot
- Tabel menampilkan baris agregasi user leave

## Scenario 4 - Non-admin Guard

1. Login non-admin.
2. Coba akses:
   - `/employee-report`
   - `/leave-report`
3. Coba panggil API snapshot via devtools/manual request.

Expected:

- UI diarahkan ke dashboard employee bila route admin-only
- API snapshot mengembalikan `403 AUTH_FORBIDDEN`

## Scenario 5 - Export Error Cases (API)

1. Gunakan snapshot status `processing`, panggil `POST /v1/hcm/reports/snapshots/{id}/export`.
2. Gunakan snapshot ID yang tidak ada.

Expected:

- Status `422` dengan code `SNAPSHOT_NOT_READY`
- Status `404` dengan code `SNAPSHOT_NOT_FOUND`

## Evidence Log Template

- Date:
- Tester:
- Environment:
- Scenario 1: Pass/Fail
- Scenario 2: Pass/Fail
- Scenario 3: Pass/Fail
- Scenario 4: Pass/Fail
- Scenario 5: Pass/Fail
- Notes/Deviation:
