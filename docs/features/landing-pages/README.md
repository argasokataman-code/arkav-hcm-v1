# Landing Pages (Marketing)

## Dokumentasi

- **README.md** (ini) — gambaran & flow end-to-end
- **IMPLEMENTATION.md** — rencana teknis (route/guard/layout/assets/copy/SEO)
- **E2E-TESTING.md** — skenario manual untuk validasi UX end-to-end

## Ringkasan

Fitur **Landing Pages** adalah halaman marketing yang bisa diakses publik (guest) untuk:

- menampilkan **daftar package/paket** yang sudah ada di sistem (pricing + ringkasan fitur/limit),
- memungkinkan calon customer **memilih paket**,
- melakukan **registrasi company (tenant)** + user owner,
- melanjutkan ke flow **subscribe → invoice → payment** sesuai kontrak SaaS yang sudah ada.

## Target flow (public → onboarding → payment)

Alur yang diinginkan:

1. Guest buka landing (`/`).
2. Guest pilih paket → klik CTA “Mulai Trial / Mulai Berlangganan”.
3. Guest isi form onboarding:
   - data company (kode, nama, timezone, currency, country)
   - data owner (nama, email, password kuat)
4. Sistem membuat:
   - user owner,
   - company baru,
   - subscription awal (status `pending_payment` atau `trial` sesuai rules),
   - invoice (bila `pending_payment`),
   - lalu mengarahkan user ke layar payment.

Catatan: flow di atas butuh endpoint onboarding public yang **belum menjadi kontrak final** di repo saat ini (lihat “Status kontrak” di bawah).

## Status kontrak (existing vs perlu ditambah)

### Sudah ada (existing)

- **Register user**: `POST /v1/identity/auth/register` (regex name/email/password)  
  Dokumen: `docs/api/identity-api.md`, OpenAPI: `docs/api/openapi.yaml` (`RegisterRequest`)
- **Company CRUD (admin-only)**: `POST /v1/company` (admin-only)  
  Dokumen: `docs/api/company-api.md`
- **List packages**: `GET /v1/saas/packages` (saat ini protected `api.token`)  
  Dokumen: `docs/api/packages-api.md`, OpenAPI: `docs/api/openapi.yaml`
- **Create subscription (admin-only)**: `POST /v1/saas/subscriptions` (admin-only)  
  Dokumen: `docs/api/subscriptions-api.md`, OpenAPI: `docs/api/openapi.yaml`
- **Invoice & Payment**: kontrak billing ada di `docs/api/subscriptions-api.md` + `docs/api/purchase-transaction-api.md` dan OpenAPI bagian invoices/payments.

### Perlu ditambah (untuk self-serve landing onboarding)

Agar landing bisa benar-benar self-serve “buat company + subscribe + payment” tanpa akses admin:

- Endpoint public **onboarding** (recommended): contoh desain
  - `POST /v1/public/onboarding` → membuat company + owner + subscription + invoice (jika perlu)
- atau minimum:
  - `POST /v1/public/companies` (create company sebagai owner yang baru register)
  - `POST /v1/public/subscriptions` (create subscription untuk company milik sendiri)

Dokumen rencana endpoint ini akan ditaruh di `docs/features/landing-pages/IMPLEMENTATION.md` dan harus diselaraskan ke `docs/api/openapi.yaml` saat implementasi dimulai.

## Validasi & regex (parity FE/BE)

Landing onboarding wajib mengikuti validasi yang sudah disepakati di spek:

- **User register** (`RegisterRequest`):
  - `name`: \(min 2, max 150\), regex `^[A-Za-z][A-Za-z\s'.-]{1,149}$`
  - `email`: format email + regex dasar
  - `password`: kuat \(8–64\), regex `^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$`
- **Company code** (dipakai juga untuk login mode “Login as Company”):
  - pattern `^[A-Za-z0-9_-]+$` (lihat `LoginRequest.companyCode` di OpenAPI)

Frontend (form landing) harus memasang constraint yang sama: `pattern`, `minlength`, `maxlength`, dan error message yang konsisten dengan envelope API.

## Keamanan (wajib)

- Landing public hanya menampilkan data “marketing-safe” (package aktif + fitur/limit). Tidak ada data tenant internal.
- Endpoint onboarding public harus:
  - rate limit,
  - mencegah open redirect,
  - mencegah pembuatan company massal tanpa verifikasi (opsional: email verification),
  - memastikan ownership: user hanya bisa membuat subscription untuk company miliknya.

## Skenario negatif (wajib di-cover)

Daftar ini melengkapi `E2E-TESTING.md` dan harus jadi acuan saat implementasi endpoint onboarding:

- **Duplikasi**:
  - `company.code` sudah dipakai → `409`/`422`
  - `owner.email` sudah terdaftar → `409`/`422` (UI: arahkan ke login)
- **Data tidak valid**:
  - `billing_cycle` bukan `monthly|yearly` → `422`
  - password tidak memenuhi regex kuat → `422`
  - `company.code` tidak match pattern → `422`
- **Produk/plan edge-case**:
  - paket nonaktif/archived tidak boleh dipilih → `404`/`422`
  - trial tidak tersedia untuk paket → `422` (domain code)
- **Abuse & reliability**:
  - rate limit onboarding → `429`
  - double submit / idempotency: tidak boleh membuat company/subscription duplikat
- **Conflict lifecycle**:
  - company sudah punya subscription `active/pending_payment` → `422` + arahkan ke resource yang ada
- **Billing safety**:
  - payment untuk invoice yang sudah paid / beda company/subscription → `422/403` (tidak boleh memicu aktivasi salah)

## Link terkait

- Flow SaaS existing: `docs/features/subscriptions/` dan `docs/features/packages/`
- Guard web (public whitelist): `docs/security/hcm-web-route-guard.md`

