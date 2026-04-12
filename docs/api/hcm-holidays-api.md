# Holidays API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmHolidayController.php`.

## Base path

`/v1/hcm`

## Endpoint

- `GET /holidays` (HCM Admin)
- `POST /holidays` (HCM Admin) — create manual holiday
- `POST /holidays/sync-indonesia` (HCM Admin) — sync baseline Indonesia (libur.deno.dev), `year` opsional
- `PUT /holidays/{id}` (HCM Admin) — update (source diset manual untuk override)
- `DELETE /holidays/{id}` (HCM Admin)

## Field penting (ringkas)

- `source`: `manual|api`
- `lastSyncedAt`: timestamp sync

## Detail kontrak

### GET `/holidays`

RBAC:
- HCM Admin only

Success `200`:
- list diurutkan `holiday_date` desc
- item: `{ id, title, holidayDate, description, isActive, source, lastSyncedAt }`
- `meta.linkage`: monitoring integrasi `holidays` ↔ `holiday_calendars`
  - `holidayRows`, `calendarRows`, `linkedRows`, `unlinkedRows`, `manualRows`, `apiRows`

### POST `/holidays`

RBAC:
- HCM Admin only

Body:
- `title` required string max 200
- `holidayDate` required date
- `description` optional string max 5000
- `isActive` optional boolean

Success `201`:

```json
{ "success": true, "data": { "id": 1 } }
```

### PUT `/holidays/{id}`

RBAC:
- HCM Admin only

Body: sama seperti POST (semua required kecuali description/isActive)

Behavior:
- `source` akan dipaksa `manual` dan `lastSyncedAt=null`

### DELETE `/holidays/{id}`

RBAC:
- HCM Admin only

### POST `/holidays/sync-indonesia`

RBAC:
- HCM Admin only

Body:
- `year` optional int 2000..2100 (default year berjalan)

Upstream:
- primary: `libur.deno.dev` (`GET /api?year=YYYY`)
- fallback otomatis saat primary gagal/invalid: `date.nager.at` (`GET /api/v3/PublicHolidays/{year}/ID`)

Behavior:
- skip jika ada holiday manual dengan `(date,title)` sama
- create/update hanya untuk source `api`
- rekonsiliasi tahunan source `api`: row API tahun target yang sudah tidak ada di payload provider akan dibersihkan (manual tidak disentuh)
- setiap perubahan API/manual otomatis sinkron ke `holiday_calendars` via `holiday_id`, sehingga leaves selalu membaca data holiday terbaru

Errors:
- `502 HOLIDAY_SYNC_UNREACHABLE` (network/timeout)
- `502 HOLIDAY_SYNC_FAILED` (non-200)
- `502 HOLIDAY_SYNC_INVALID_PAYLOAD` (payload bukan array)

Success `200`:

```json
{
  "success": true,
  "data": {
    "year": 2026,
    "created": 10,
    "updated": 5,
    "skippedManual": 2,
    "invalidRows": 0,
    "cleanedStaleApi": 1
  },
  "meta": {
    "linkage": {
      "holidayRows": 20,
      "calendarRows": 20,
      "linkedRows": 20,
      "unlinkedRows": 0,
      "manualRows": 3,
      "apiRows": 17
    }
  }
}
```

