# Policies

## Ringkasan

Feature ini mengelola kebijakan perusahaan di modul HCM, termasuk relasi opsional ke department dan attachment yang mengikuti media service. Policies dipakai sebagai referensi operasional internal, sehingga akses, tenant scope, dan konsistensi dengan struktur organisasi harus jelas.

## Akses

- Halaman aktif: `/policy` via middleware `hcm.web.admin`.
- Permission gate UI: `policy.manage` sebelum list/form CRUD dirender.
- Backend tetap menjadi sumber kebenaran authorization.

## UI Aktif

- Web page aktif: `/policy`.
- Frontend aktif: `frontend/resources/js/hcm-pages-data.js`.

## Flow Bisnis End-to-End

1. Admin membuka halaman policies.
2. Sistem memuat daftar policy sesuai company aktif.
3. Admin membuat atau memperbarui kebijakan perusahaan, dengan department opsional bila policy bersifat spesifik unit.
4. Perubahan policy tersimpan dan dapat dipakai sebagai referensi di modul organisasi/knowledgebase.

## Lifecycle Dan Keputusan Bisnis

- Policy adalah referensi operasional internal, bukan konten publik.
- Relasi department bersifat opsional agar kebijakan bisa berlaku umum maupun spesifik unit.
- Attachment/storage mengikuti media service yang sama dengan surface admin lain.

## Integrasi

- Employees Organization: department relation dan struktur organisasi menjadi konteks utama kebijakan internal. Lihat `docs/features/employees-organization/README.md`.
- Knowledgebase: SOP dan artikel bantuan dapat merujuk policy company sebagai rujukan operasional. Lihat `docs/features/knowledgebase/README.md`.
- User Management: permission `policy.manage` dan akses admin mengikuti fondasi RBAC HCM. Lihat `docs/features/user-management/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Scope

- Company policies CRUD di modul HCM
- Relasi opsional ke department
- Catatan attachment/storage mengikuti service media

## Akses aktif

- Halaman aktif: `GET /policy` via middleware `hcm.web.admin`
- Frontend aktif: `frontend/resources/js/hcm-pages-data.js`
- Permission gate: `policy.manage` sebelum list/form CRUD dirender

## API utama (`/v1/hcm`)

- `GET /policies`
- `POST /policies`
- `PUT /policies/{id}`
- `DELETE /policies/{id}`

## Data model ringkas

- `policies`:
  - `department_id` (nullable)
  - `name`
  - `description`
  - `effective_date`

## Catatan implementasi

- Controller policy berada di `HcmEmployeeController` (belum dipisah ke controller khusus).
- Shared HCM page helper sekarang mengirim auth + tenant context header ke `/v1/identity/auth/me`, `/v1/hcm/departments`, dan `/v1/hcm/policies`.
- Endpoint update saat ini menerima variasi `PUT` dan fallback `POST` pada path yang sama untuk kompatibilitas template/form.

## Validasi

- Vitest wiring: `backend/tests/ui/policy.wiring.test.js`

## Existing Vs Target

- Existing: CRUD policy aktif di modul HCM, tenant context sudah dikirim helper HCM page, dan update masih menerima variasi `PUT`/fallback `POST` untuk kompatibilitas template.
- Target: pemisahan controller khusus policy dan dokumentasi flow bisnis lintas modul yang lebih lengkap.
