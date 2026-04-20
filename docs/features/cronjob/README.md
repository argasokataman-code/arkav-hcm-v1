# Cronjob Scheduler Configuration

## Ringkasan

Feature ini mengelola konfigurasi scheduler internal melalui halaman `/cronjob`, dengan persistence ke `settings` table dan konsumsi runtime oleh kernel scheduler. Cronjob menjadi titik kontrol operasional untuk job lintas modul seperti payment reminder, wilayah sync, payroll refresh, dan leave accrual.

## Akses

- Halaman aktif: `/cronjob`.
- Hanya HCM Admin yang boleh mengakses; non-admin diarahkan ke `lock-screen`.

## UI Aktif

- Halaman `/cronjob` untuk mengelola scheduler settings per job key.
- Perubahan disimpan di `settings` table (`group = cronjob`).

## Flow Bisnis End-to-End

1. Admin membuka halaman cronjob.
2. Admin mengatur apakah job aktif, waktu eksekusi, timezone, dan hari bulanan bila relevan.
3. Setting disimpan ke database.
4. Kernel scheduler dan consumer runtime membaca konfigurasi itu saat menjadwalkan job.

## Lifecycle Dan Keputusan Bisnis

- Disabled jobs tidak dihapus dinamis; runtime hanya melewati job tersebut.
- Fallback default tetap tersedia agar scheduler tidak kosong saat setting DB belum ada.
- Halaman ini adalah kontrol operasional, bukan executor manual job business flow.

## Integrasi

- Locations: job `Wilayah Sync` dijadwalkan dari modul cronjob. Lihat `docs/features/locations/README.md`.
- Leave And Holidays: leave monthly accrual, yearly carry, dan daily expire bergantung pada scheduler ini. Lihat `docs/features/leave-and-holidays/README.md`.
- Payroll dan billing reminders: payroll refresh open period dan payment reminder juga dikelola dari sini. Lihat `docs/features/payroll-runs/README.md`, `docs/features/trial-billing-dashboard/README.md`, dan `docs/features/purchase-transaction/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Scope
- Web page: `/cronjob`
- Persist scheduler settings via `settings` table (`group = cronjob`).
- Scheduler consumer:
  - `app/Console/Kernel.php`
  - `routes/console.php`

## Access Policy
- **HCM Admin only**.
- Non-admin is redirected to `lock-screen`.

## Managed Jobs
- Payment Reminder (daily)
- Wilayah Sync (monthly day + time)
- Payroll Refresh Open Period (daily)
- Leave Monthly Accrual (daily)
- Leave Yearly Carry (daily)
- Leave Daily Expire (daily)

## Data Contract (stored per job key)
- `enabled` (bool)
- `time` (`HH:MM`)
- `timezone` (IANA timezone)
- `dayOfMonth` (1-28, monthly jobs only)

## Notes
- Fallback defaults are hardcoded in `App\Support\CronjobSettings` and used when DB setting is missing.
- Scheduler jobs are not deleted dynamically; disabled jobs are skipped in runtime.

## Existing Vs Target

- Existing: scheduler setting sudah persisted di DB dan dibaca consumer runtime utama.
- Target: dokumentasi job-level ownership dan evidence operasional per schedule bisa dibuat lebih rinci.
