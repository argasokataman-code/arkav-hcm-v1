# Arkav HCM

Single-repository Human Capital Management project with split folders:

- `backend/` for Laravel API app.
- `frontend/` for existing frontend assets (no separate frontend app bootstrap).

## Current architecture

- Backend uses one Laravel application as centralized API for all endpoints.
- Frontend assets are built through backend Vite config by sourcing files from `frontend/resources`.
- API namespaces:
  - `/v1/identity/*`
  - `/v1/hcm/*` (termasuk leave, attendance, holidays, overtime, leave settings, shifts)
- Dokumentasi detail: folder `docs/` — snapshot fitur: `docs/planning/implementation-status.md`

## Quick start

Database **bersama / produksi** memakai **MySQL** (bukan SQLite). Salin template env, lalu sesuaikan `DB_*` di `backend/.env` ke instance MySQL kamu.

```bash
cd backend
composer install --ignore-platform-req=php
npm install
cp env.txt .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

SQLite di `env.txt` hanya opsi komentar untuk uji cepat lokal; untuk tim/global gunakan MySQL.

## Development

```bash
cd backend
composer run dev
```

This starts Laravel server, queue listener, logs tail, and Vite.

## Simple run command

From repository root, use:

```bash
./run.sh
```

This runs:

- backend: `php artisan serve`
- frontend node server: `node frontend/server.js` (reverse proxy to backend)

Both run in foreground. Press `Ctrl+C` to stop all services.

## Login test quick guide

1. Start services from root:

```bash
./run.sh
```

2. Seed reusable QA login user (MySQL):

```bash
cd backend
php artisan db:seed --force
```

Seeded credentials:
- email: `qa.login@example.com`
- password: `StrongPass1`

3. Manual API checks:

```bash
# Login via frontend proxy (5179)
curl -X POST http://127.0.0.1:5179/v1/identity/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"qa.login@example.com","password":"StrongPass1"}'

# Health check backend
curl http://127.0.0.1:8007/health
```

4. Automated auth tests:

```bash
cd backend
php artisan test --filter=AuthApiTest
```

## Cursor / AI agents

Project rules live in `.cursor/rules/` (`*.mdc`). For agent behavior, **`alwaysApply: true`** on those files plus optional **User rules** (Cursor **Settings → Rules**) is the closest Cursor gets to “read project rules first” every time. See **`.cursor/rules/AGENTS.md`** for a short pointer.

## Format jawaban wajib

Untuk seluruh panduan internal (agent maupun non-agent), format rekomendasi harus mengikuti standar berikut:

1. Jika memberi next step atau saran, tampilkan seluruh langkah relevan sekaligus dalam satu list lengkap.
2. Jangan beri rekomendasi bertahap satu-per-satu jika konteksnya dapat dijelaskan sekaligus.
3. Urutkan dari mandatory ke opsional agar prioritas reviewer/operator jelas.
4. Saat konteks deploy, jangan dorong auto-push; status harus berhenti di ready to push sampai ada konfirmasi operator.

Lint helper untuk membantu review cepat ada di `scripts/lint-next-step-format.sh`.

## Documentation

Project planning and API docs are centralized in `docs/`.
Feature-level flow docs for team handoff live in `docs/features/` (start from `docs/features/README.md`). Role/permission per halaman HCM aktif (menu ter-wire): `docs/planning/active-hcm-templates-and-permissions.md`.

Deployment references:
- Docker/VPS flow: `PRODUCTION-SETUP.md`
- Shared hosting artifact flow (local build, server no Composer/npm): `docs/engineering/shared-hosting-setup.md`


untuk performaa https://github.com/upstash/context7


Username: qa.hcm@example.com
Password: StrongPass1
Untuk akun QA dev:
Username: qa.login@example.com
Password: StrongPass1