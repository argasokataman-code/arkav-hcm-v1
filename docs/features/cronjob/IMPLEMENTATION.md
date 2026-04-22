# Implementation Details

## Arsitektur Runtime

- Source of truth scheduler: `backend/routes/console.php`
- Bootstrap command routing: `backend/bootstrap/app.php` (`withRouting(commands: ...)`)
- `backend/app/Console/Kernel.php` tidak lagi mendefinisikan job scheduler aktif, agar tidak terjadi duplikasi definisi.

## Komponen Utama

- `App\Support\CronjobSettings`
  - Menyimpan daftar definisi job + default config.
  - Menyimpan metadata penjelasan job untuk UI: `frequencyExplanation`, `businessPurpose`, `expectedOutcome`.
  - Membaca/menulis key setting dengan format `cronjob_<job_key>`.
  - Tetap aman saat tabel `settings` belum ada (guard schema + try/catch).

- `App\Http\Controllers\CronjobController`
  - `index()` render tabel konfigurasi cronjob.
  - `update()` normalisasi + persist konfigurasi per job.
  - Menyediakan state runtime-feature-flag untuk warning operasional di UI.
  - Menyediakan daftar timezone valid agar operator tidak terkunci ke dua opsi saja.
  - Guard controller: `isGlobalHcmAdmin()`.

- `backend/resources/views/cronjob.blade.php`
  - Menyediakan tombol collapse **Panduan** untuk membantu admin memahami aturan penggunaan halaman.
  - Menambah kolom **Panduan & Tujuan** per row cronjob agar maksud tiap scheduler eksplisit.
  - Menampilkan warning runtime override jika job di-skip oleh feature flag config.

## Security Model

- Route:
  - `GET /cronjob` -> middleware `hcm.web.global-admin`
  - `POST /cronjob` -> middleware `hcm.web.global-admin`
- Controller tetap memverifikasi global admin sebagai lapisan tambahan.
- Implikasi bisnis: hanya akun global super admin (type-1) yang dapat mengubah orkestrasi scheduler lintas tenant.

## Scheduler Mapping

- `payment_reminder` -> `SendPaymentReminder` (daily)
- `wilayah_sync` -> `wilayah:sync` (monthlyOn day/time)
- `payroll_refresh_open_period` -> closure rebuild draft payroll (daily)
- `leave_monthly_accrual` -> `hcm:leave-maintenance --mode=monthly-accrual`
- `leave_yearly_carry` -> `hcm:leave-maintenance --mode=yearly-carry`
- `leave_daily_expire` -> `hcm:leave-maintenance --mode=daily-expire`
- `saas_convert_ended_trials` -> `ConvertExpiredTrialsToPendingPaymentJob`
- `saas_terminate_expired_subscriptions` -> `TerminateExpiredSubscriptionsJob`
- `saas_suspend_overdue_services` -> `SuspendServicesForOverdueInvoicesJob`
- `saas_check_employee_count_limits` -> `CheckEmployeeCountLimitsJob`
- `saas_recurring_billing` -> `ProcessRecurringSubscriptionBilling`

## Runtime Override (Feature Flags)

Job berikut dapat di-skip walau row UI enabled, jika flag runtime config bernilai false:

- `saas_terminate_expired_subscriptions` -> `app.saas.auto_termination_enabled`
- `saas_suspend_overdue_services` -> `app.saas.auto_suspension_enabled`
- `saas_check_employee_count_limits` -> `app.saas.employee_limit_enforcement_enabled`

UI cronjob menampilkan warning untuk kondisi override ini agar tidak menyesatkan operator.

## Validasi Dan Normalisasi Input

- `time`: regex `HH:MM`; invalid fallback `00:00`
- `timezone`: harus valid IANA timezone; invalid fallback ke default job
- `dayOfMonth` (monthly): dibatasi `1..28`
- `enabled`: checkbox presence-based boolean

## Test Coverage

- `backend/tests/Feature/CronjobSettingsWebTest.php`
  - Global admin dapat menyimpan konfigurasi job termasuk `saas_recurring_billing`
  - Non-global-admin ditolak mengubah konfigurasi
