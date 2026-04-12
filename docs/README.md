# Arcav HCM Documentation

Folder `docs/` adalah sumber kebenaran (single source of truth) untuk arah arsitektur, standar engineering, kontrak API, dan batas implementasi UI.

## Prinsip utama project

1. **Arsitektur runtime tetap**:
   - `frontend/` = existing Node server + existing template assets.
   - `backend/` = satu aplikasi Laravel untuk semua endpoint API.
2. **Tidak membuat UI baru di luar template aktif**:
   - Semua kebutuhan BE harus mengikuti struktur halaman, komponen, dan flow navigasi template saat ini.
3. **Domain API dipisah secara namespace (di Laravel yang sama)**:
   - `/v1/identity/*`
   - `/v1/hcm/*` — saat ini juga mencakup sebagian besar endpoint leave, attendance, holidays, overtime, leave settings, dan **shifts** (lihat `planning/implementation-status.md` untuk snapshot path aktual).
   - `/v1/leave/*` — masih dipakai di beberapa dokumen lama sebagai referensi domain; belum tentu tersedia sebagai prefix terpisah di route sampai diselaraskan dengan OpenAPI.
4. **Data source produksi**:
   - MySQL (bukan SQLite) untuk environment development dan runtime aplikasi.

## Struktur dokumentasi

- `architecture/` -> baseline arsitektur final dan flow feature
- `planning/` -> urutan kerja phase, prioritas implementasi, **snapshot status kode** (`implementation-status.md`), dan **matriks template HCM aktif + target role/permission** (`active-hcm-templates-and-permissions.md`)
- `engineering/` -> standar coding, struktur kode, dan delivery gate
- `frontend/` -> panduan komponen UI template existing
- `database/` -> spesifikasi MySQL dan model data
- `api/` -> kontrak endpoint, format request/response, error contract
- `security/` -> inventaris permukaan serangan, kebijakan web guard, header keamanan, rekomendasi hardening ([`security/README.md`](./security/README.md))
- `features/` -> dokumentasi **per fitur** (flow UI, endpoint, rule bisnis, edge case)

## Peta dokumen per fitur

- `features/README.md` (index utama dokumen fitur)
- `features/identity-auth/README.md`
- `features/employees-organization/README.md`
- `features/employees-organization/USE-CASES.md` (hak akses & alur employee)
- `features/attendance-shift-schedule/README.md`
- `features/overtime/README.md`
- `features/leave-and-holidays/README.md`
- `features/policies/README.md`

## Alur baca yang disarankan untuk tim

1. Buka `features/README.md`, pilih fitur yang sedang dikerjakan.
2. Baca dokumen fitur terkait (flow UI + API + edge case).
3. Cocokkan dengan `planning/implementation-status.md` untuk status runtime terkini.
4. Jika ada gap kontrak, sinkronkan `api/api-spec-phase-1.md` dan dokumen fitur bersamaan.

## Snapshot progress terkini

- `planning/implementation-status.md` — ringkasan fitur, route, tabel, dan wiring frontend yang sudah ada di repo (perbarui bersamaan dengan rilis besar).

## Dokumen wajib dibaca sebelum coding

1. `architecture/microservice-concept.md`  
   Menjelaskan arsitektur final yang dipakai saat ini (single Laravel API + Node frontend proxy).
2. `api/api-spec-phase-1.md`  
   Kontrak API yang harus diikuti backend.
3. `engineering/frameworks.md`  
   Mapping stack yang disetujui project.
4. `engineering/phase-1-code-structure-todo.md`  
   Standar struktur dan delivery checklist implementasi phase 1.
5. `engineering/backend-template-enforcement.md`  
   Aturan wajib bahwa development BE harus mengikuti template UI saat ini.
6. `frontend/css-components-cheat-sheet.md`  
   Referensi komponen visual template yang wajib dipertahankan.

## Aturan integrasi backend terhadap template UI

- Backend **wajib** menyesuaikan payload dan validasi berdasarkan kebutuhan komponen pada template yang sudah ada.
- Backend **dilarang** mengubah flow navigasi UI dengan pendekatan server-side baru yang memutus perilaku template existing.
- Jika ada kebutuhan field/endpoint baru, dokumentasi API harus diperbarui dulu lalu implementasi backend mengikuti kontrak itu.
- Perubahan yang membuat UI tidak sesuai template (layout, nama menu, interaksi utama) tidak boleh di-merge.

## Definisi selesai (Definition of Done)

Sebuah task dianggap selesai jika:

- endpoint backend berjalan sesuai contract;
- halaman template terkait tetap berfungsi tanpa UI baru di luar template;
- login/session/logout dan auth guard tetap konsisten;
- dokumentasi pada `docs/` terbarui jika ada perubahan behavior;
- smoke test minimal (manual atau automated) untuk flow yang disentuh sudah lewat.
