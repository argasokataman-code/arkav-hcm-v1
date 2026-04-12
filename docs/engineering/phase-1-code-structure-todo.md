# Phase 1 Code Structure TODO

Checklist struktur kode agar implementasi Phase 1 tetap rapi dan konsisten dengan arsitektur runtime saat ini.

## 1) Repository and service layout

- [x] Tetapkan pendekatan repo: single repo dengan split runtime `backend/` + `frontend/`
- [x] Struktur target:
  - [x] `backend/` (single Laravel API app)
  - [x] `frontend/` (existing assets + Node proxy server)
  - [x] `docs/` (arsitektur, API, planning, status implementasi)
  - [ ] `shared/contracts/` (optional for generated client/schema)
- [ ] Tambahkan README operasional per runtime (`backend` dan `frontend`) — root `README.md` sudah ada quick start

## 2) Backend layering convention

- [ ] Standarkan layer: routes/controllers/services/repositories/models/validators/middlewares/tests
- [ ] Controller tipis, business logic di service
- [ ] Repository fokus data access
- [ ] Standarkan DTO/resource response
- [ ] Standarkan pagination/filter contract

## 3) Frontend feature-based structure

- [x] Struktur fitur (per halaman template): auth, employees, attendance, leave-settings, shift-master, HCM extras — via `*-data.js` + `api-client.js`
- [ ] Shared layer modern (hooks/types) — tidak wajib; pola saat ini IIFE + axios/fetch
- [x] API calls terpusat di modul JS per fitur (`employees-data.js`, `attendance-data.js`, dll.), bukan inline sembarangan
- [x] Pertahankan UI template existing, tanpa membuat halaman/flow baru di luar template

## 4) API contract and error handling

- [x] Success envelope `{ success, data }` dipakai luas — dokumentasi di `api-spec-phase-1.md`
- [ ] Error response format: `code`, `message`, `details`, `traceId` — **sebagian** endpoint; perlu audit menyeluruh
- [x] Mapping HTTP status umum (401/422/403) pada endpoint utama
- [x] Error catalog dasar di spec — perlu tambah kode domain shift/schedule jika distandarkan

## 5) CI/CD, testing, observability baseline

- [ ] Gate: lint + test + build sebelum merge
- [ ] Unit/integration/smoke test untuk flow utama
- [x] `/health` endpoint backend
- [ ] request logging untuk endpoint critical
- [ ] Correlation ID propagation dasar

## 6) Backend must follow template UI (mandatory gate)

- [ ] Setiap endpoint baru memiliki mapping ke halaman/komponen template existing yang jelas.
- [ ] Tidak ada perubahan backend yang memaksa pembuatan UI baru di luar template.
- [ ] Menu, route, dan state utama (auth, dashboard, employees, leave) tetap sejalan dengan flow template.
- [ ] PR wajib menyertakan bukti test pada halaman template terkait (manual steps atau automated test).
