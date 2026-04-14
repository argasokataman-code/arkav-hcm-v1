# Cronjob Scheduler Configuration

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
