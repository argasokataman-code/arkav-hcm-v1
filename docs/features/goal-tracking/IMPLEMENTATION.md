# Goal Tracking — Implementation

Status: Implemented (Web-only View — Goals dari Performance Module)
Updated: 2026-05-08

## Overview

Goal tracking bukan modul API tersendiri — ini adalah web surface yang menampilkan goal progress karyawan berdasarkan data dari modul Performance. Halaman `/goal-tracking` menampilkan goals aktif yang di-set dalam performance cycle yang aktif.

## Controller

- Tidak ada API controller khusus. Data diambil dari endpoint performance:
  - `GET /v1/hcm/performance/goals`
  - `GET /v1/hcm/performance/cycles`

## Web Surfaces

- `backend/resources/views/goal-tracking.blade.php` — halaman tracking goals
- `backend/resources/views/goal-type.blade.php` — master tipe goal

## Web Route

`backend/routes/web/performance.php`

```php
Route::get('/goal-tracking', fn() => view('goal-tracking'))->name('goal-tracking');
```

## API Endpoints (dari Performance module)

- `GET /v1/hcm/performance/goals` — daftar goals (admin: semua; employee: milik sendiri)
- `POST /v1/hcm/performance/goals` — buat goal
- `PUT /v1/hcm/performance/goals/{id}` — update progress goal
- `DELETE /v1/hcm/performance/goals/{id}` — hapus goal
- `GET /v1/hcm/performance/goal-types` — daftar tipe goal

## Data Models

- `PerformanceGoal` — goal karyawan (judul, target, progress, status, periode)
- `PerformanceGoalType` — kategori tipe goal

## Ketergantungan

- Fitur ini sepenuhnya bergantung pada modul Performance (feature gate: `performance`).
- Perubahan pada `PerformanceGoal` atau route performance akan berdampak pada halaman ini.
- Dokumentasi API lengkap: `docs/features/performance/IMPLEMENTATION.md`
