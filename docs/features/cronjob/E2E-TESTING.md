# E2E Testing - Cronjob

## Preconditions

- Backend aktif (`http://127.0.0.1:8007`)
- Scheduler worker/cron server tidak wajib running untuk uji UI, tapi disarankan untuk validasi end-to-end runtime
- Siapkan minimal 2 akun:
  - Akun global super admin (type-1)
  - Akun non-global-admin

## Scenario 1 - Akses Halaman (Role Gate)

1. Login sebagai global super admin.
2. Buka `/cronjob`.
3. Pastikan halaman terbuka normal.
4. Logout, login sebagai non-global-admin.
5. Akses `/cronjob`.
6. Pastikan redirect ke `employee-dashboard`.

Expected:

- Hanya global super admin yang bisa membuka halaman cronjob.

## Scenario 2 - Simpan Konfigurasi Job

1. Login sebagai global super admin.
2. Klik tombol **Panduan** dan pastikan panel panduan terbuka.
3. Ubah konfigurasi beberapa job, misal:
	- `payment_reminder` -> enabled + jam baru
	- `wilayah_sync` -> day/time baru
	- `saas_recurring_billing` -> jam baru
4. Klik **Save Configuration**.
5. Reload halaman.

Expected:

- Nilai tetap persist sesuai input terakhir.
- Panel panduan menampilkan arahan penggunaan halaman Cronjob.
- Kolom **Panduan & Tujuan** tampil di setiap row dan menjelaskan frekuensi + tujuan bisnis job.

## Scenario 3 - Runtime Flag Override Visibility

1. Set salah satu flag runtime SaaS ke false (misal `app.saas.auto_suspension_enabled=false`).
2. Pastikan row job terkait di UI tetap bisa diatur.
3. Pastikan row menampilkan warning bahwa runtime override aktif.

Expected:

- Operator melihat warning jelas bahwa job akan di-skip walau enabled di UI.

## Scenario 4 - Konsistensi Runtime Scheduler

1. Jalankan `php artisan schedule:list`.
2. Cocokkan daftar job aktif dengan konfigurasi di `/cronjob`.
3. Pastikan job berikut muncul di scheduler:
	- payment reminder
	- wilayah sync
	- payroll refresh
	- leave maintenance (3 mode)
	- saas convert trials
	- saas terminate expired
	- saas suspend overdue
	- saas check employee limits
	- saas recurring billing

Expected:

- Tidak ada job yang hanya tampil di UI tapi hilang dari runtime schedule.

## Evidence Yang Wajib Dicatat

- Tanggal/jam testing
- User yang dipakai per role
- Screenshot sebelum/sesudah save konfigurasi
- Output `php artisan schedule:list`
- Catatan pass/fail per scenario
