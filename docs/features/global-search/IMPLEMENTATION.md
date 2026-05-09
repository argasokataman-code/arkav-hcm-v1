# Global Search — Implementation

Status: Implemented (Cross-module Search API dengan Rate Limiting)
Updated: 2026-05-08

## Overview

Global search menyediakan satu endpoint pencarian lintas modul HCM. User dapat mencari karyawan, dokumen, tiket, dan entitas lain dalam satu query. Endpoint dilindungi rate limiting (120 request/menit) untuk mencegah abuse.

## Controller

- `backend/app/Http/Controllers/Api/HcmGlobalSearchController.php`

## Web Surfaces

- Search bar terintegrasi di header/nav halaman HCM, bukan halaman tersendiri.

## Route File

`backend/routes/api/dashboard.php` — prefix `v1/hcm`, middleware: `api.token`, `tenant.context`

## Main API Endpoint

- `GET /v1/hcm/search?q=...` — pencarian global (throttle: 120 req/menit)

## Request

| Parameter | Type | Required | Keterangan |
|-----------|------|----------|-----------|
| `q` | string | Yes | Query pencarian (min. karakter tertentu) |
| `modules` | array | No | Filter modul yang dicari (default: semua) |
| `limit` | integer | No | Jumlah hasil per modul |

## Response Shape

```json
{
  "success": true,
  "data": {
    "employees": [...],
    "tickets": [...],
    "documents": [...],
    "...": [...]
  }
}
```

## Cakupan Pencarian

Mencakup entitas utama yang relevan di tenant aktif: karyawan, tiket, dokumen, FAQ, dan entitas lain yang dikonfigurasi. Semua hasil dikunci ke `company_id` aktif — tidak ada hasil lintas tenant.

## Rate Limiting

Endpoint menggunakan middleware `throttle:120,1` (120 request per 1 menit per user). Melampaui limit akan mendapat response `429 Too Many Requests`.

## Tenant Scope

Semua pencarian dikunci ke `company_id` aktif dari tenant context.
