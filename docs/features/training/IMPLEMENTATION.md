# Training — Implementation

Status: Implemented (Training Type + Training CRUD + Trainer + History per User)
Updated: 2026-05-08

## Overview

Modul training mengelola program pelatihan karyawan. Mencakup master tipe training, data pelatih (trainer), record training, dan histori training per karyawan.

## Controller

- `backend/app/Http/Controllers/Api/HcmTrainingController.php`

## Web Surfaces

- `backend/resources/views/training.blade.php` — daftar program training (admin)
- `backend/resources/views/training-type.blade.php` — master tipe training (admin)
- `backend/resources/views/trainers.blade.php` — manajemen trainer (admin)

## Route File

`backend/routes/api/training.php` — prefix `v1/hcm/training`, middleware: `api.token`, `tenant.context`, `hcm.api.feature:training`

## Main API Endpoints

### Training Types
- `GET /v1/hcm/training/types` — daftar tipe training
- `POST /v1/hcm/training/types` — buat tipe
- `PUT /v1/hcm/training/types/{id}` — update tipe
- `DELETE /v1/hcm/training/types/{id}` — hapus tipe

### Trainings
- `GET /v1/hcm/training/trainings` — daftar program training
- `POST /v1/hcm/training/trainings` — buat program training
- `PUT /v1/hcm/training/trainings/{id}` — update program
- `DELETE /v1/hcm/training/trainings/{id}` — hapus program
- `GET /v1/hcm/training/users/{userId}/trainings` — histori training per karyawan

### Trainers
- `GET /v1/hcm/training/trainers` — daftar trainer
- `POST /v1/hcm/training/trainers` — tambah trainer
- `PUT /v1/hcm/training/trainers/{id}` — update trainer
- `DELETE /v1/hcm/training/trainers/{id}` — hapus trainer

## Data Models

- `HcmTraining` — program training (nama, tipe, tanggal, status, trainer, peserta)
- `HcmTrainingType` — master tipe training per tenant
- `HcmTrainer` — data trainer internal/eksternal

## Feature Gate

Endpoint dilindungi `hcm.api.feature:training` — hanya tenant dengan fitur training aktif yang dapat mengakses.

## Tenant Scope

Semua data training dikunci ke `company_id` aktif.
