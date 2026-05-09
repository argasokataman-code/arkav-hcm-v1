# AI Assistant — Implementation

Status: Implemented (Chat + Intent Resolver + Consent Management)
Updated: 2026-05-08

Intent Catalog: [INTENT-CATALOG.md](INTENT-CATALOG.md)
RBAC Policy: [RBAC-POLICY.md](RBAC-POLICY.md)

## Overview

AI Assistant adalah fitur natural language interface untuk karyawan dan admin. User dapat bertanya tentang data HCM mereka (saldo cuti, slip gaji, jadwal shift, dll.) dan assistant merespons berdasarkan data aktual dari API internal. Setiap interaksi melewati intent resolver yang memetakan pertanyaan ke endpoint internal.

## Controllers

- `backend/app/Http/Controllers/Api/HcmAiChatController.php`
- `backend/app/Http/Controllers/Api/HcmDataPrivacyAiController.php`

## Web Surfaces

- AI chat terintegrasi sebagai floating widget atau section di halaman dashboard HCM.

## Route File

`backend/routes/api/dashboard.php` — prefix `v1/hcm`, middleware: `api.token`, `tenant.context`
`backend/routes/api/data-privacy.php` — consent management

## Main API Endpoints

### Chat
- `POST /v1/hcm/ai/chat` — kirim pertanyaan ke AI assistant (throttle: 30 req/menit)
- `GET /v1/hcm/ai/intents` — daftar intent yang tersedia untuk user aktif

### AI Consent (Data Privacy)
- `POST /v1/hcm/me/ai-consent` — beri consent untuk penggunaan AI
- `DELETE /v1/hcm/me/ai-consent` — tarik consent (opt-out)
- `GET /v1/hcm/me/ai-consent-status` — cek status consent aktif

## Data Models

- `AiChatLog` — log semua interaksi chat (user, pesan, intent terdeteksi, response, timestamp)
- `EmployeeAiConsent` — status consent AI per karyawan (opt-in/opt-out)

## Intent Resolver

AI Assistant tidak menggunakan model LLM eksternal untuk query data — ia memakai intent resolver internal yang memetakan natural language ke endpoint internal berdasarkan INTENT-CATALOG.md.

Intent status:
- `implemented` — sudah live
- `ready` — endpoint ada, belum diimplementasikan di resolver
- `planned` — endpoint belum ada
- `deferred` — v2+

## Rate Limiting

- `/ai/chat`: throttle 30 req/menit (mencegah abuse query berlebihan)
- `/ai/intents`: tanpa throttle khusus (read-only, ringan)

## Consent Gate

Sebelum AI dapat mengakses data user, sistem memeriksa `EmployeeAiConsent`. User yang belum memberikan consent atau sudah menarik consent tidak dapat menggunakan fitur AI chat. Ini adalah bagian dari kepatuhan data privacy (PDP).

## Tenant Scope

Semua log dan consent dikunci ke `company_id` + `user_id` aktif.

## Integrasi

- Membaca data dari hampir semua modul HCM via intent resolver internal.
- Setiap akses data oleh AI melewati gate yang sama seperti user manusia (auth + tenant context).
- Log interaksi tersedia untuk audit dan kepatuhan PDP.
