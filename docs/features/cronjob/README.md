# Cronjob Scheduler Configuration

## Ringkasan

Fitur ini adalah pusat kontrol jadwal otomatis sistem melalui halaman `/cronjob`.
Admin yang berwenang dapat mengatur kapan job berjalan, timezone, dan status aktif/non-aktif per job. Semua konfigurasi disimpan ke tabel `settings` (group `cronjob`) lalu dipakai langsung oleh scheduler runtime.

## Akses Dan Otorisasi

- Halaman aktif: `/cronjob`.
- Hanya **global super admin (type-1 / `users.is_super_admin=1`)** yang boleh mengakses.
- Route GET dan POST sama-sama dijaga middleware global-admin.
- Controller juga menerapkan guard global-admin sebagai defense-in-depth.
- User non-global-admin diarahkan ke `employee-dashboard`.

## Flow Bisnis End-to-End

1. Global super admin membuka halaman `/cronjob`.
2. Admin dapat membuka tombol **Panduan** untuk membaca arti frekuensi scheduler dan maksud bisnis tiap job.
3. Admin memilih job yang ingin diaktifkan/nonaktifkan, mengisi waktu, timezone, dan `dayOfMonth` untuk job bulanan.
4. Admin membaca kolom **Panduan & Tujuan** pada setiap baris untuk memahami apa yang dicek/diproses job tersebut.
5. Sistem menyimpan payload ke `settings` dengan key `cronjob_<job_key>`.
6. Scheduler runtime membaca konfigurasi tersebut dari source of truth tunggal (`routes/console.php`).
7. Saat scheduler server (`php artisan schedule:run`) berjalan, job due akan dieksekusi sesuai setting.

## Lifecycle Dan Keputusan Bisnis

- Job yang disabled tidak dihapus; scheduler akan skip job tersebut.
- Bila data setting belum ada, sistem fallback ke default di `App\Support\CronjobSettings`.
- Beberapa job SaaS punya runtime feature flag tambahan dari config aplikasi; meskipun row UI enabled, job tetap bisa di-skip jika flag runtime mati.
- Halaman ini bukan eksekusi manual job, melainkan pengaturan orkestrasi jadwal.
- Kolom deskripsi detail per job menjelaskan tiga hal: arti frekuensi, tujuan bisnis, dan output yang diharapkan.

## Managed Jobs (Current)

- Payment Reminder (daily)
- Wilayah Sync (monthly)
- Payroll Refresh Open Period (daily)
- Leave Monthly Accrual (daily)
- Leave Yearly Carry (daily)
- Leave Daily Expire (daily)
- SaaS Convert Ended Trials (daily)
- SaaS Terminate Expired Subscriptions (daily)
- SaaS Suspend Overdue Services (daily)
- SaaS Check Employee Limits (daily)
- SaaS Recurring Billing (daily)

## Data Konfigurasi Per Job

- `enabled` (boolean)
- `time` (`HH:MM`)
- `timezone` (IANA timezone)
- `dayOfMonth` (`1..28`, khusus schedule bulanan)

## Integrasi Modul

- Locations (`wilayah:sync`): lihat `docs/features/locations/README.md`
- Leave & Holidays: lihat `docs/features/leave-and-holidays/README.md`
- Payroll Runs: lihat `docs/features/payroll-runs/README.md`
- Trial/Billing/Subscription automation: lihat `docs/features/trial-billing-dashboard/README.md` dan `docs/features/subscriptions/README.md`
- Peta lintas fitur: `docs/features/INTEGRATION-MAP.md`

## Existing Vs Target

- Existing: konfigurasi cronjob sudah persisted dan dipakai scheduler runtime.
- Existing: scheduler source sekarang tunggal di `routes/console.php` agar tidak split-brain.
- Target: menambah observability operasional (run history + success/failure trend) sebagai backlog terpisah.
