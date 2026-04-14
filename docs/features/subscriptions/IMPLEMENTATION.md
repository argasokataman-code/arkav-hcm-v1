# Subscriptions Module - Implementation Guide

## Overview

Subscriptions module mengelola hubungan company dengan package SaaS: create langganan, update status, renew periode, dan lifecycle tracking.

## Architecture

Backend:
- Controller: `backend/app/Http/Controllers/Api/SubscriptionController.php`
- Models: `Subscription`, `Package`
- API routes: `backend/routes/api.php` pada prefix `/v1/saas`

Frontend:
- View: `backend/resources/views/saas/subscriptions.blade.php`
- Manager: `frontend/resources/js/subscriptions-management.js`

Web routes:
- `/saas/subscriptions`
- `/subscription`

## API Contract

### 1) List Subscriptions

`GET /v1/saas/subscriptions`

Filters:
- `status`
- `company_id`
- `plan_code`
- `billing_cycle`
- `search` (company/package/plan_code)

Response:
- `success`
- `data[]`
- `pagination`

### 2) Create Subscription (Admin)

`POST /v1/saas/subscriptions`

Validation:
- `company_id` required
- `package_id` required
- `status` required
- `starts_at` required
- `billing_cycle` required (`monthly|yearly`)
- `amount` optional (auto-calc from package jika null)

Behavior:
- `plan_code` didenormalisasi dari package.
- Jika amount kosong, otomatis diisi dari `monthly_price` atau `yearly_price` package.

### 3) Show Subscription

`GET /v1/saas/subscriptions/{subscription}`

Return detail lengkap dalam format `formatSubscription()`.

### 4) Update Subscription (Admin)

`PUT /v1/saas/subscriptions/{subscription}`

Partial update diperbolehkan (`sometimes`) termasuk `status`, `package_id`, `starts_at`, `ends_at`, `billing_cycle`, `auto_renew`, `amount`.

### 5) Delete/Cancel Subscription (Admin)

`DELETE /v1/saas/subscriptions/{subscription}`

Endpoint ini hard delete sesuai implementasi saat ini.

### 6) Renew Subscription (Admin)

`POST /v1/saas/subscriptions/{subscription}/renew`

Input:
- `ends_at` required dan harus date future.

Effect:
- status jadi `active`
- `starts_at` = now
- `ends_at` sesuai request
- `trial_ends_at` null

## Access Control

- Semua mutasi menggunakan check `isHcmAdmin()` di controller.
- Implementasi admin-check sudah distandardkan: `User::isHcmAdmin()`.
- Non-admin akan menerima `403 ADMIN_REQUIRED`.

## Frontend Flow

File: `frontend/resources/js/subscriptions-management.js`

Flow utama:
1. `init()` bind event, load companies, load packages, lalu load subscriptions.
2. `loadSubscriptions()` request list dengan query filter dari UI.
3. `renderSubscriptions()` tampilkan tabel + action buttons.
4. `handleSaveSubscription()` create/update payload dengan field backend.
5. `cancelSubscription()` update status menjadi `cancelled`.
6. `deleteSubscription()` hard delete endpoint.

## Implemented Improvements (2026-04-13)

- Harmonisasi ID form/event handler agar sesuai blade terbaru.
- Mapping payload sudah sinkron ke backend snake_case.
- Filter status/cycle/search aktif dan terkirim ke API.
- Modal add/edit disatukan dan stabil.
- Confirm action memakai Arcav confirm jika tersedia.
- Backend index ditambah filter `billing_cycle` dan `search`.

## Known Gaps

- View details masih menggunakan `window.alert` (belum modal detail khusus).
- Endpoint delete masih hard delete; bila policy perlu soft-cancel, perlu revisi API.

## Verification Checklist

- Open `/saas/subscriptions` dan list tampil.
- Add subscription baru sukses.
- Edit subscription berhasil update.
- Cancel subscription mengubah status ke `cancelled`.
- Delete subscription menghapus record.
- Filter status/cycle/search bekerja sesuai query.
- Non-admin mutasi mendapat `403 ADMIN_REQUIRED`.
