# Landing Pages — IMPLEMENTATION (rencana)

Dokumen ini menjelaskan rencana implementasi landing page public, tetap mengikuti aturan template HCM/SaaS di repo ini.

## Scope (updated)

- Landing page public (guest) untuk promosi produk + paket.
- Landing menyediakan onboarding self-serve: **pilih paket → registrasi company + owner → subscription → invoice/payment**.
- Tetap mengikuti kontrak validasi (regex) dan keamanan (rate limit, ownership, anti open-redirect).

## Route & Web guard

### Perubahan route

- Ubah `/` agar merender landing (bukan redirect ke `/login`).
- Opsional: tambah `/landing` agar QA mudah mengakses URL eksplisit.

### Web guard whitelist

Guard: `backend/app/Http/Middleware/EnsureHcmWebPagesAuthenticated.php` menggunakan whitelist dari `backend/config/arcav_hcm_web_guard.php`.

- Root (`''`) sudah termasuk `public_paths`, sehingga landing di `/` bisa public.
- Jika memakai `/landing`, tambahkan `landing` ke `public_paths`.

## Layout dan wiring template

### Kenapa tidak pakai `layout.mainlayout`

`layout.mainlayout` adalah shell aplikasi (header + sidebar) untuk halaman authenticated. Jika dipakai di halaman public, guest akan melihat navigasi internal (dan halaman bisa memuat komponen yang asumsi auth context).

### Layout public

Buat layout khusus public (contoh): `backend/resources/views/layout/publiclayout.blade.php`

- Memakai aset template yang sama (CSS/JS vendor).
- Tidak include `layout.partials.header`, `layout.partials.sidebar`, dan modal admin.
- Punya header marketing sederhana (logo + “Login” + CTA).
- Punya footer marketing (terms/privacy/contact).

## Data paket (source of truth)

Landing butuh menampilkan paket aktif dan highlight fitur. Ada dua opsi:

### Opsi A — SSR dari DB (recommended untuk marketing list)

- Query `Package` status `active` + relasi `features`.
- Render pricing cards server-side (Blade).
- Kelebihan: tidak perlu membuka endpoint API baru untuk guest.
- Kekurangan: butuh desain Blade yang rapi (tetap bagus untuk marketing).

### Opsi B — Endpoint public read-only (opsional)

- Tambah endpoint khusus marketing (mis. `/v1/public/packages`) yang hanya expose field aman.
- Kelebihan: landing bisa SPA/JS-driven.
- Kekurangan: perlu perubahan keamanan + update OpenAPI + test tambahan.

Keputusan opsi akan ditetapkan saat implementasi; default yang disarankan: **Opsi A**.

## Copywriting dan konsistensi istilah

Sumber istilah & definisi fitur:

- Feature limits: `package_features.limit` (`null` = unlimited, `0` = not included, `>0` = capped).
- Flow subscription: `docs/features/subscriptions/SCENARIOS.md` (status `pending_payment` → invoice paid → active).
- Deskripsi modul/fitur: `frontend/resources/js/packages-management.js` (`FEATURE_LIBRARY`) sebagai referensi copy internal.

Aturan:

- Copy landing tidak boleh membuat klaim yang bertentangan dengan feature flags dan limits.
- CTA tidak boleh mengesankan “langsung aktif” bila flow masih `pending_payment`.

## Onboarding API (public) — desain yang dibutuhkan

Saat ini kontrak API yang ada tidak cukup untuk onboarding self-serve karena:

- `POST /v1/company` adalah **admin-only** (lihat `docs/api/company-api.md`).
- `POST /v1/saas/subscriptions` adalah **admin-only** (lihat OpenAPI).
- `GET /v1/saas/packages` saat ini berada di middleware `api.token` (bukan public).

### Endpoint yang direkomendasikan

Tambahkan satu endpoint onboarding atomik:

- `POST /v1/public/onboarding`

Payload (contoh; final harus diselaraskan dengan OpenAPI dan controller validation):

- `package_id` (atau `plan_code`)
- `billing_cycle` (`monthly|yearly`)
- `company`:
  - `code` (pattern `^[A-Za-z0-9_-]+$`, max 100)
  - `name` (max 255)
  - `legal_name` (optional)
  - `timezone` (max 100)
  - `currency` (max 10)
  - `country_code` (max 10)
- `owner`:
  - `name` (regex sesuai `RegisterRequest.name`)
  - `email` (email)
  - `password` (regex kuat)
  - `confirmPassword` (same)

Response sukses:

- set cookie `arcav_access_token` (seperti login/register),
- mengembalikan:
  - `company` (id, code, name),
  - `subscription` (id, status `pending_payment` atau `trial`),
  - jika `pending_payment`: `invoice` (id, amount_due, due_date) + next step URL untuk payment UI.

### Security & business rules

- Rate limit per IP/email.
- `company.code` unique global (409/422 sesuai pola).
- Owner otomatis menjadi `owner` pada `company_users`.
- Subscription awal:
  - `trial` bila paket mengizinkan trial (jika rule ada),
  - selain itu `pending_payment` + invoice dibuat.

## Mapping status → badge → next action (UI)

Landing onboarding + post-onboarding screen harus konsisten menampilkan status berikut:

| State | Badge | Kondisi sumber kebenaran | Next action utama |
|------|-------|--------------------------|-------------------|
| Trial | `TRIAL` | `subscription.status=trial` dan `trial_ends_at` terisi | Arahkan user ke login/app; tampilkan countdown trial |
| Pending payment | `PENDING_PAYMENT` | `subscription.status=pending_payment` + invoice unpaid | CTA “Bayar sekarang” → detail invoice/payment |
| Active (paid) | `ACTIVE` | `subscription.status=active` (invoice paid / activation) | CTA “Masuk ke workspace” |
| Suspended/expired | `SUSPENDED` / `EXPIRED` | status sesuai lifecycle subscription | CTA “Hubungi admin / perpanjang” (ke billing) |

Catatan: “Subscribed tab” di dashboard admin akan menampilkan `ACTIVE` dan `PENDING_PAYMENT` dengan badge berbeda.

## Error codes (selaras dokumentasi API) + HTTP status

Gunakan envelope error standar `{ success:false, error:{ code, message, details?, traceId } }` (lihat `docs/api/api-spec-phase-1.md`).

Prinsip: untuk kontrak onboarding public, **jangan membuat error.code baru** jika sudah ada padanan global yang dipakai modul lain. Default mapping yang konsisten di repo:

- `401` → `AUTH_UNAUTHORIZED`
- `403` → `AUTH_FORBIDDEN` atau `TENANT_FORBIDDEN` (untuk konteks tenant)
- `409` → `CONFLICT`
- `422` → `VALIDATION_ERROR`
- `429` → `AUTH_TOO_MANY_ATTEMPTS` (jika memakai throttle sejenis identity) atau tetap `TOO_MANY_REQUESTS` bila disepakati sebagai global

Tabel skenario onboarding (UI landing) dengan code yang konsisten:

| Scenario | HTTP (disarankan) | `error.code` (canonical) | Catatan UI |
|----------|--------------------|--------------------------|------------|
| Email owner sudah terdaftar | `409` atau `422` | `CONFLICT` atau `VALIDATION_ERROR` | UI arahkan ke `/login` |
| Company code sudah dipakai | `409` atau `422` | `CONFLICT` atau `VALIDATION_ERROR` | Fokus ke input `company.code` |
| Paket tidak aktif / tidak ditemukan | `404` atau `422` | `NOT_FOUND` atau `VALIDATION_ERROR` | “Paket tidak tersedia” |
| Billing cycle invalid | `422` | `VALIDATION_ERROR` | Field error |
| Trial tidak tersedia untuk paket | `422` | `VALIDATION_ERROR` | Pesan domain di `error.message` |
| Rate limit onboarding | `429` | `AUTH_TOO_MANY_ATTEMPTS` | Tampilkan retry-after jika ada |
| Double submit / idempotency | `409` | `CONFLICT` | Return pointer resource yang sudah dibuat (di `error.message` atau `data` jika tim memilih idempotent response) |
| Subscription conflict (company sudah ada subscription aktif/pending) | `422` | `VALIDATION_ERROR` | UI arahkan ke subscription/invoice existing |
| Unauthorized (akses endpoint protected) | `401` | `AUTH_UNAUTHORIZED` | Redirect login |

Catatan: jika tim ingin `NOT_FOUND` / `CONFLICT` sebagai code eksplisit, pastikan itu juga digunakan konsisten di dokumen API lain (bukan hanya di landing).

### Validasi parity FE/BE

FE landing wajib menerapkan constraint yang sama dengan OpenAPI:

- `RegisterRequest` untuk owner.
- `companyCode` pattern untuk `company.code`.

Jika kontrak onboarding memperkenalkan regex baru (mis. untuk `company.name`), harus ditambahkan ke:

- `docs/api/api-spec-phase-1.md` (shared regex),
- `docs/api/openapi.yaml` (schema),
- validasi backend + validasi frontend.

## CTA dan redirect setelah login (anti open-redirect)

Target behavior (jika onboarding memerlukan login step):

- Landing membentuk URL `GET /login?next=/subscription?packageId=<id>&status=pending_payment`.
- Setelah login sukses, user diarahkan ke `next` (bukan selalu ke `/index`).

Keamanan:

- Parameter `next` harus dibatasi ke **relative path** yang diawali `/` dan tidak mengandung skema (`http://`).
- Jika invalid, fallback ke `/index`.

Implementasi rencana:

- Update `frontend/resources/js/auth-login.js` agar membaca `next` query param dan melakukan redirect yang aman.

## Animasi ringan (tanpa library baru)

Rencana animasi:

- CSS keyframes untuk hero background (gradient/shape).
- Reveal animation per section menggunakan `IntersectionObserver` (fade-up/slide-up).
- Hormati preferensi user: jika `prefers-reduced-motion`, nonaktifkan animasi.

## Asset pipeline (template lock)

Jika menambah file JS/CSS:

- Sumber di `frontend/resources/js/landing-page.js` (atau sesuai konvensi).
- Pastikan output build tersalin ke `backend/public/build/js/`.
- Script inclusion mengikuti pola yang ada (conditional include di `backend/resources/views/layout/partials/footer-scripts.blade.php` atau include langsung di landing view).

## Test plan (automation + manual)

- Tambah regression test: guest bisa akses `/` landing tanpa redirect `lock-screen`.
- Tambah regression test: login dengan `next` valid redirect sesuai, `next` invalid tidak bisa open redirect.
- Manual: lihat `E2E-TESTING.md`.

