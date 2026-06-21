# Agent Instructions — ARCAV HCM

Satu sumber kebenaran rule repo. Dibaca otomatis oleh opencode.

## 1. Development Protocol (6 Fase Wajib)

Jalankan **berurutan** setiap task substantif. Jangan loncat.

### Fase 1: STOP & Pahami
Output: `Task: [ringkasan] Domain: [feature] Jenis: bugfix|new-feature|refactor|migration|docs-only Role: admin|self|team|public`

- Jika ambiguitas → tanya user, jangan tebak.
- Jika task bentrok rule → sebut konflik, minta konfirmasi.

### Fase 2: Source Discovery
Cari informasi dari sumber-sumber berikut, prioritaskan dari yang paling atas:

1.  **Repository Map (`REPO_MAP.md`, `docs/maps/*.md`):** Periksa direktori `docs/maps/` untuk file pemetaan modul/fitur (misal: `feature-payroll-map.md`, `feature-employee-map.md`, `backend-map.md`, `frontend-map.md`). Gunakan ini untuk memahami struktur utama dan entry point fitur yang relevan.
2.  **Feature-Specific Docs:** Jika map tidak cukup detail atau fitur tidak terpetakan, cari dokumentasi spesifik fitur di `docs/features/<feature>/` (README.md, IMPLEMENTATION.md, API.md).
3.  **API Routes:** Cek `backend/routes/api.php` dan `backend/routes/web.php` untuk route aktual.
4.  **Migrations:** Periksa `backend/database/migrations/` untuk skema database.
5.  **Controllers & Services:** Lokasi utama implementasi logic.

Cari secukupnya, berhenti saat konteks yang dibutuhkan sudah cukup jelas.

### Fase 3: Impact Analysis
Jawab sebelum coding:
- **Data/Schema:** model/table berubah? FK cascade? Backfill?
- **API Contract:** response shape berubah? RBAC berubah?
- **UI/Frontend:** Blade mana render? JS mana consume? Sync `frontend/resources/js/` → `backend/public/build/js/`?
- **Cross-feature:** fitur lain depend? Cek `docs/features/INTEGRATION-MAP.md`.

Output:
```
In scope: [file1, file2]
NOT in scope: [file3]
Downstream effects: [...]
Docs to update: [...]
```

### Fase 4: Implementation
- Hanya ubah file "In scope".
- Temuan di luar scope: kecil (1-3 baris) → boleh, sebut di laporan. Besar → catat, tanya user.
- Patuhi: `backend-template-lock` (UI konsisten), `no-hardcoded-dummy-data`, `migration-discipline`, `security-baseline`.

### Fase 5: Verification
```
cd backend && php artisan migrate --force
cd backend && php artisan test <suite-terdampak>
cd backend && npx vitest run <scope>   # jika JS/Blade berubah
```
Closure checklist (lihat §2). Anti-hallucination: ada file luar scope yg berubah? Disengaja?

### Fase 6: Reporting
```
## ✅ Task Complete
### Summary [2-3 kalimat]
### Files Changed
### Impact Confirmation
### Test Results
### Docs Updated
### Caveats / Next Steps
1. [temuan/saran]
```

---

## 2. Task Closure Checklist

Wajib sebelum tutup task substantif (fitur baru, endpoint baru, RBAC, migrasi, halaman HCM).

### Security
| Jika menyentuh | Wajib |
|---|---|
| **Route API** baru / RBAC | Middleware `api.token`; admin/self/ownership di controller; 401/403 konsisten. |
| **Route web** baru | Bukan sensitif tanpa auth; tamu non-whitelist → 404 guest. Admin-only → middleware `hcm.web.admin` setelah auth web. |
| **Whitelist publik** | Update `docs/security/hcm-web-route-guard.md` + `docs/security/inventory-and-surface.md`. |
| **Input user / upload** | Validasi server-side, IDOR, jangan bocorkan stack trace. |

### Documentation (semua terdampak, jangan cherry-pick)
- `docs/planning/implementation-status.md` + `phase-1-todo-plan.md`
- Matriks HCM: `docs/planning/active-hcm-templates-and-permissions.md`
- Kontrak API: `docs/api/<feature>-api.md` + `docs/api/openapi.yaml`
- Feature: `docs/features/<feature>/README.md`
- DB: `docs/database/mysql-database-specification.md`
- Security: `docs/security/*`

### OpenAPI
- Sumber: `docs/api/openapi.yaml` (dimuat Swagger UI `/api-docs/openapi.yaml`).
- Path/method baru atau body/query/response/RBAC berubah → patch **bersamaan** kode.
- Minimum: `tags`, `summary`, `security`, request schema, response 200/201, error 401/403/404/422.

### Checklist Singkat
- [ ] RBAC/guard/tes regresi route baru selaras?
- [ ] Semua `docs/` terdampak sudah update?
- [ ] `docs/api/openapi.yaml` selaras? (atau tdk ada perubahan API)
- [ ] `php artisan test <suite>` + `npx vitest run <scope>` sudah jalan?

### Gate Berurutan (halaman HCM aktif)
1. **Role & use case** — cocokkan matriks, enforce server-side, regresi tes.
2. **UIUX lintas role** — minimal HCM Admin + Karyawan. Cek visibilitas, state kosong/error, redirect.
3. **Manual UI E2E** — jalankan skenario dari `docs/features/<feature>/E2E-TESTING.md` di browser.

---

## 3. Security Baseline

### AuthN / AuthZ
- API: endpoint sensitif di bawah `api.token`.
- RBAC: **server-side** (`EnsuresHcmAdmin`, ownership, scope `me`/`team`/`all`). UI hanya pelengkap.
- Web GET/HEAD: hanya whitelist `public_paths` / `public_prefixes` yg bebas. Sisanya wajib auth (`EnsureHcmWebPagesAuthenticated`). Tamu non-publik → `error-404-guest`.

### Input & Data
- Validasi server-side wajib selaras spec (`docs/api/*`).
- IDOR: cek pemilik/role sebelum baca/ubah resource per-user.
- Upload: batasi tipe & ukuran; jangan simpan path executable.
- Mass assignment: `$fillable` / DTO eksplisit.

### Rahasia
- Jangan commit **password, token, kunci API, `.env`**.
- `composer audit` setelah tambah dependency.
- Cookie: HttpOnly, Secure (HTTPS), SameSite sesuai konfigurasi auth.

### Template Keamanan HCM
- Konfirmasi destruktif: `ArcavUi.confirmDelete` + modal template. Bukan `alert`/`confirm` native.
- Error API: `ApiErrorHelper` / toast, tanpa stack trace ke klien.

### Fitur Keamanan Existing (jangan regresi)
- `SecurityHeadersMiddleware` (global)
- `EnsureHcmWebPagesAuthenticated` + `error-404-guest`
- `ArcavAccessTokenResolver` + `AuthenticateApiToken`
- `WebHcmRouteGuardTest` untuk kebijakan publik/tamu

---

## 4. Backend & API

### Template UI (Blade/Bootstrap)
- Template = acuan: card, table, breadcrumb, dropdown, badge, modal, `btn-*`, `form-*`. Hindari pola UI baru.
- Halaman HCM: `@extends('layout.mainlayout')`, `@section('content')`.
- Script per halaman: `footer-scripts.blade.php` via `@if (Route::is([...]))`.
- Modal destruktif: `hcm/partials/hcm-confirm-delete-modal.blade.php` + `ArcavUi.confirmDelete`.
- JS source berubah → `public/build/js` wajib sync.

### API Contract
- Prefix: `/v1/hcm/...`
- Response: `{ success, data?, error? }`
- RBAC/ownership di server (`EnsuresHcmAdmin`, `isHcmAdmin()`).
- 401 (unauthenticated), 403 (forbidden), 422 (validation), 404 (not found).
- Jangan ubah kontrak API aktif tanpa alasan kuat (bug/security/feature request).

### Validasi Parity (FE/BE)
- Server-side = source of truth.
- Frontend wajib ikut constraint spec: `pattern`, `maxlength`, regex, enum.
- Konflik implementasi vs spec → update spec dalam patch sama.

### Migration
- Perubahan schema → migration file di `backend/database/migrations/`.
- `cd backend && php artisan migrate --force` (non-interactive).
- Verifikasi: hit endpoint + test suite.
- UUID-first: jangan ganti UUID canonical ke integer `id`. `id` boleh sebagai legacy surrogate.
- Update `docs/database/mysql-database-specification.md` bila substantif.

---

## 5. Documentation Rules

### Struktur Wajib
```
docs/features/<feature>/
├── README.md          (overview + nav + flow bisnis)
├── IMPLEMENTATION.md  (arsitektur, API, DB, config)
└── [E2E-TESTING.md, SETUP.md, SCHEMA.md, API.md]
```

### README Feature — Wajib Business-Readable
- Ringkasan bisnis, aktor & role, flow end-to-end, decision tree.
- Status/lifecycle (artti bisnis tiap status).
- Role & permission cross-check (halaman + API).
- Kondisi existing vs target, gap, keputusan kompromi.
- `docs/features/README.md` (main index) update tiap feature baru.

### Dilarang
- ❌ Docs di root / random folder (`/README.md` feature, `/PAYMENT-SETUP.md`, dll).
- ❌ Feature "selesai" tanpa folder `docs/features/<feature>/`.
- ❌ Skip IMPLEMENTATION.md.
- ❌ Lupa update `docs/features/README.md`.

### Cleanup Root
```
/PAYMENT-SETUP.md     → /docs/features/payments/SETUP.md
/PAYROLL-E2E-GUIDE.md → /docs/features/payroll-runs/E2E-TESTING.md
```

---

## 6. RBAC & Multi-Tenant

### Model Role
| Role | Arti |
|---|---|
| Anonim | Belum login |
| Authenticated | Cookie/token valid |
| Karyawan | Authenticated & `hcmAdmin !== true` |
| HCM Admin | `hcmAdmin === true` |

### Multi-Tenant Isolation (STRICT)
- Setiap query WAJIB `company_id` di WHERE untuk data tenant-scoped.
- Roles: company-scoped (`company_id`). Platform roles (super_admin): tanpa `company_id`.
- Permissions: global (tanpa `company_id`), direlasikan ke role via `hcm_role_permissions` + `company_id`.
- Super-admin-only: role/permission creation/update/delete.

### Matriks Halaman Aktif
Sumber kebenaran: `docs/planning/active-hcm-templates-and-permissions.md`.
Cerminan di rule ini: lihat baris matriks di file tsb. Jika mengubah akses, update kedua file.

### Checklist PR (HCM)
- [ ] Baris matriks di-update jika path/izin berubah.
- [ ] Endpoint berisiko punya 403 untuk non-admin.
- [ ] UI admin tidak menggantikan cek server.
- [ ] Test: happy path + forbidden.

---

## 7. Bugfix Process

1. **Root cause** — cari penyebab sesungguhnya, bukan patch UI.
2. **Minimal 1 regression test** — backend → PHPUnit Unit/Feature. Frontend → Vitest.
3. **Eksekusi test** — `php artisan test <suite>` + `npx vitest run <scope>` jika lintas FE+BE.
4. **Cek API/permission** — 401/403/422 masih benar? Envelope konsisten?
5. **Cek lintas query/schema** — endpoint lain pakai data sama tidak rusak?
6. **Override sementara** — jika darurat, tulis `REGRESSION-TODO` di code.

---

## 8. Quality Gate

Jalankan cek anomali untuk perubahan substantif. Pilih sesuai jenis:

### API
- Happy path: Create → List → Update → Delete.
- Auth: 401 (no token), 403 (wrong role).
- Validasi: 422 + error code.
- Duplikasi → 409/422 sesuai pola fitur.
- Response envelope konsisten.

### UI Blade/JS
- Asset & wiring: route terdaftar, JS termuat via `footer-scripts.blade.php`.
- Feedback: toast/flash untuk sukses/error. Jangan `window.alert/confirm/prompt`.
- State: loading & empty state rapi. Button disabled saat submit.

### Migrasi
- FK onDelete sesuai (cascade/null/restrict).
- Unique index cegah duplikasi.
- Kolom baru required → default/backfill.
- Hindari N+1 (eager load).

### RBAC
- Update matriks HCM + docs planning.
- Backend enforce (bukan hanya hide button).

---

## 9. Deployment Runtime Guard

Berlaku saat menyentuh: `.github/workflows/*.yml`, `Dockerfile`, `run.sh`, `PRODUCTION-SETUP.md`, `scripts/*.sh`.

### Persistent Storage
Mount `backend/storage` wajib punya subdirectory sebelum cache Laravel:
```
storage/logs
storage/framework/cache/data
storage/framework/sessions
storage/framework/views
storage/app/public
storage/app/private
bootstrap/cache
```

### Urutan Cache
1. `mkdir -p ...runtime dirs...`
2. `chmod -R ug+rwX storage bootstrap/cache`
3. `php artisan config:clear`
4. `php artisan view:clear`
5. `php artisan config:cache`
6. `php artisan route:cache`
7. `php artisan view:cache`
8. `php artisan migrate --force`

### Validasi
- `bash scripts/check-deploy-runtime-guard.sh`
- `bash -n run.sh` jika disentuh

---

## 10. Context7 Usage

Wajib fetch docs via Context7 sebelum tulis code library/framework: Laravel, PHPUnit/Pest, Vite, Bootstrap, Composer/npm package.

```
mcp_context7_resolve-library_id({ libraryName: "laravel" })
→ /laravel/laravel
mcp_context7_query-docs({ context7CompatibleLibraryId: "/laravel/laravel", topic: "..." })
```

Tidak perlu: logika bisnis murni, script shell/SQL sederhana, rename variabel.

Jika Context7 tidak tersedia → catat eksplisit ke user, lanjut dengan training data + disclaimer.

---

## 11. Shared Hosting Deploy (Lokal-First)

### Workflow
1. `bash scripts/local-test-gate.sh --quick` (opsional — cukup `composer install` + `npm ci` + syntax check; full test di CI)
2. Commit code/docs (tanpa artifact)
3. `bash scripts/shared-hosting-package-local.sh` (build artifact)
4. `bash scripts/check-shared-hosting-artifact-sync.sh` (wajib PASS)
5. Commit `release/shared-hosting/`
6. Push ke main **hanya setelah konfirmasi operator**
7. GitHub Actions: SCP + SSH extract + `shared-hosting-deploy-easy.sh` + **auto-run test suite**

### Mandatory Safety
- "prepare deploy" → stop di "ready to push", jangan push otomatis.
- Artifact dibangun **setelah** commit code/docs (biar `RELEASE-METADATA git_head` tidak stale).
- Gunakan `bash scripts/prepare-main-push.sh --message "<msg>"` untuk alur aman.
- Jangan deploy manual per-file ke staging kecuali emergency approved.
- Emergency hotfix → follow-up commit + rebuild + deploy normal.

---

## 12. Communication Discipline

1. "Next step" / saran: tampilkan **semua langkah relevan** dalam **satu list lengkap**.
2. Urutkan dari paling wajib ke opsional.
3. Dilarang saran bertahap satu-per-satu.
4. Konflik instruksi user vs rule repo → sebutkan konflik, minta konfirmasi.

---

## 13. Local Test Gate (Opsional — Fast Path)

**Full test dijalankan otomatis oleh GitHub Actions CI setiap push/PR/cron.**  
Local test cukup untuk quick validation sebelum commit.

```bash
# Quick local validation (syntax + build — cukup)
composer install --working-dir=backend && npm ci && npm run build

# Full test ada di CI → https://github.com/argasokataman-code/arkav-hcm-v1/actions
```

Opsi local (jika mau):
- `npm run build` — cek asset build ok
- `composer install` — cek dependency ok
- `php -l file.php` — syntax check file tertentu

| Dulu (mandatory local) | Sekarang (CI-first) |
|------------------------|---------------------|
| `php artisan migrate:fresh` | ❌ Skip — CI handle |
| `php artisan test` | ❌ Skip — CI handle |
| `npx vitest run` | ❌ Skip — CI handle |
| `composer install` | ✅ Tetap (cek dependency) |
| `npm ci && npm run build` | ✅ Tetap (cek build) |

> **Catatan:** Jika laptop kuat, bisa jalankan `bash scripts/local-test-gate.sh` untuk validasi penuh sebelum push.

## 14. Database Query Tools

Gunakan **MCP MySQL tools** (`mysql_connect_db`, `mysql_query`, `mysql_execute`) untuk query database, **bukan** `php artisan tinker` via bash.

### Koneksi (lokal)
```
mysql_connect_db(url: "mysql://root:@127.0.0.1:3306/arcav_hcm")
```

### Contoh Query
```
mysql_query(sql: "SELECT code, name, monthly_price FROM packages WHERE status='active'")
mysql_execute(sql: "UPDATE subscriptions SET status='active' WHERE id=1")
```

### Kenapa
- Output rapi (JSON array), langsung bisa dibaca
- Gak perlu `tinker` heredoc rentan error
- Lebih cepet (langsung SQL, tanpa Lapisan ORM)

---

## 15. Repository Protection (OpenCode Lock)

Agent **DILARANG** mengubah, menghapus, atau menambah file/direktori berikut tanpa izin **eksplisit** dari user:

### 🔒 Locked Files & Directories

| Path | Alasan Lock |
|---|---|
| `AGENTS.md` | Single source of truth repo rules. Perubahan sembarangan = chaos. |
| `docs/maps/*.md` | GPS navigasi agent. Struktur map sudah stabil. |
| `.github/workflows/*.yml` | CI/CD pipeline. Salah edit = deploy broken. |
| `Dockerfile` | Container config. |
| `run.sh` | Entry point runtime. |
| `docker-compose.yml` | Orchestration config. |
| `scripts/*.sh` | Deployment & maintenance scripts. |
| `backend/config/*.php` | App configuration. Ubah = seluruh app affected. |
| `backend/app/Providers/*.php` | Service container bindings. |
| `backend/app/Http/Middleware/*.php` | Security & auth layer. |
| `backend/bootstrap/*.php` | Framework bootstrap. |
| `backend/app/Support/ArcavAccessTokenResolver.php` | Token resolution core. |
| `backend/app/Support/TenantContextResolver.php` | Tenant context core. |
| `backend/composer.json` | Dependency manifest. |
| `backend/composer.lock` | Dependency lock. |
| `frontend/package.json` | FE dependency manifest. |
| `frontend/package-lock.json` | FE dependency lock. |
| `backend/vite.config.js` | Build configuration. |
| `backend/vitest.config.js` | Test configuration. |
| `backend/phpunit.xml` | PHPUnit config. |
| `backend/phpstan.neon` | Static analysis config. |

### 🔓 Unlock Conditions

File di atas **boleh** diubah hanya jika:
1. User **secara eksplisit** bilang: "edit file X" atau "unlock file X".
2. Task yang diberikan **memang membutuhkan** perubahan di file tersebut (misal: "tambah middleware baru" → boleh edit `Middleware/` setelah konfirmasi).
3. Agent harus **sebutkan file yang ingin di-edit** dan **alasan** sebelum menyentuh, lalu tunggu approval.

### ⚠️ Protected Patterns (JANGAN UBAH tanpa alasan kuat)

- **UUID-first pattern** — Jangan downgrade UUID ke integer ID.
- **Snapshot pattern** (`currentXxxSnapshot()`) di `EmployeeProfile` — Jangan flatten ke single column.
- **EncryptedOrPlaintext cast** (UU PDP) — Jangan hapus encryption.
- **Multi-tenant `company_id` scope** — Jangan hapus tenant isolation.
- **Concerns/traits split** di controller — Jangan merge jadi 1 file raksasa.
- **`lockForUpdate()`** di transaction — Jangan hapus, ini race condition guard.
- **`api.token` middleware** di route API — Jangan bypass.
- **Response envelope** `{ success, data?, error? }` — Jangan ubah format.
