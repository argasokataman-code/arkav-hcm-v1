# Performance — Implementation

Status: Implemented (Goal Types + Goals + Indicator Templates + Performance Cycles + Reviews)
Updated: 2026-05-08

## Overview

Modul performance mencakup manajemen tujuan (goals), template indikator, siklus review, dan proses appraisal karyawan. Alur review melibatkan self-assessment karyawan, penilaian manager, dan finalisasi admin.

## Controller

- `backend/app/Http/Controllers/Api/HcmPerformanceController.php`

## Web Surfaces

- `backend/resources/views/performance/` — folder views performance (admin)
- `backend/resources/views/performance-appraisal.blade.php` — halaman appraisal
- `backend/resources/views/performance-review.blade.php` — halaman review
- `backend/resources/views/performance-indicator.blade.php` — master indikator
- `backend/resources/views/goal-tracking.blade.php` — tracking goals (admin + employee)
- `backend/resources/views/goal-type.blade.php` — master tipe goal

## Route File

`backend/routes/api/performance.php` — prefix `v1/hcm/performance`, middleware: `api.token`, `tenant.context`, `hcm.api.feature:performance`

## Main API Endpoints

### Goal Types & Goals
- `GET /v1/hcm/performance/goal-types` — daftar tipe tujuan
- `POST /v1/hcm/performance/goal-types` — buat tipe
- `PUT /v1/hcm/performance/goal-types/{id}` — update tipe
- `DELETE /v1/hcm/performance/goal-types/{id}` — hapus tipe
- `GET /v1/hcm/performance/goals` — daftar goals (admin: semua; employee: milik sendiri)
- `POST /v1/hcm/performance/goals` — buat goal
- `PUT /v1/hcm/performance/goals/{id}` — update goal
- `DELETE /v1/hcm/performance/goals/{id}` — hapus goal

### Indicator Templates & Items
- `GET /v1/hcm/performance/indicator-templates` — daftar template indikator
- `POST /v1/hcm/performance/indicator-templates` — buat template
- `PUT /v1/hcm/performance/indicator-templates/{id}` — update template
- `DELETE /v1/hcm/performance/indicator-templates/{id}` — hapus template
- `GET /v1/hcm/performance/indicator-templates/{id}/items` — items dalam template
- `POST /v1/hcm/performance/indicator-templates/{id}/items` — tambah item ke template
- `PUT /v1/hcm/performance/indicator-items/{itemId}` — update item
- `DELETE /v1/hcm/performance/indicator-items/{itemId}` — hapus item

### Performance Cycles
- `GET /v1/hcm/performance/cycles` — daftar siklus review
- `POST /v1/hcm/performance/cycles` — buat siklus baru
- `PUT /v1/hcm/performance/cycles/{id}` — update siklus
- `POST /v1/hcm/performance/cycles/{id}/activate` — aktifkan siklus
- `POST /v1/hcm/performance/cycles/{id}/close` — tutup siklus

### Performance Reviews
- `GET /v1/hcm/performance/reviews` — daftar review (admin: semua; employee: milik sendiri)
- `POST /v1/hcm/performance/reviews` — buat review (admin membuat untuk karyawan)
- `GET /v1/hcm/performance/reviews/{id}` — detail review
- `PUT /v1/hcm/performance/reviews/{id}` — update self-assessment (employee)
- `POST /v1/hcm/performance/reviews/{id}/submit` — submit self-assessment
- `PUT /v1/hcm/performance/reviews/{id}/manager` — update penilaian manager
- `POST /v1/hcm/performance/reviews/{id}/manager-complete` — tandai review manager selesai
- `PUT /v1/hcm/performance/reviews/{id}/final` — update nilai final (admin)
- `POST /v1/hcm/performance/reviews/{id}/finalize` — finalisasi review (admin)

## Data Models

- `PerformanceCycle` — siklus review (status: `draft|active|closed`)
- `PerformanceGoal` — tujuan karyawan dengan tipe dan periode
- `PerformanceGoalType` — master tipe tujuan
- `PerformanceIndicatorTemplate` — template indikator penilaian
- `PerformanceIndicatorItem` — item indikator dalam template
- `PerformanceReview` — proses review per karyawan per siklus
- `PerformanceReviewScore` — nilai tiap indikator dalam review

## Review Lifecycle

`draft` → `self_submitted` → `manager_reviewed` → `finalized`

Admin membuat review, employee mengisi self-assessment dan submit, manager memberikan penilaian, admin finalisasi.

## Integrasi

- **Leave**: cycle review membaca data approved leave untuk kalkulasi absenteeism.
- **Goal Tracking**: web view `/goal-tracking` menampilkan progress goals aktif karyawan.
