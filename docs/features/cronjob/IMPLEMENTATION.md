# Implementation Details

## Backend Components
- `App\Support\CronjobSettings`
  - Defines job metadata + defaults.
  - Reads/writes `settings` key format: `cronjob_<job_key>`.
  - Safe during bootstrap/migration gap (guards missing `settings` table).
- `App\Http\Controllers\CronjobController`
  - `index()` renders cronjob settings table.
  - `update()` normalizes and persists payload per job.
  - Security gate: `isHcmAdmin()` required.

## Routes
- `GET /cronjob` -> `CronjobController@index`
- `POST /cronjob` -> `CronjobController@update`

## Scheduler Wiring
- `app/Console/Kernel.php`
  - Payment reminder + wilayah sync read from settings.
- `routes/console.php`
  - Payroll + leave maintenance schedules read from settings.
  - Disabled jobs are skipped with `->skip(fn () => true)`.

## Validation / Normalization
- Time: regex normalized to `HH:MM`, fallback `00:00`.
- Timezone: validated against `timezone_identifiers_list()`, fallback default.
- Monthly day: clamped to range `1..28`.

## Regression Test
- `tests/Feature/CronjobSettingsWebTest.php`
  - Admin can save config and values persist.
  - Non-admin cannot mutate config.
