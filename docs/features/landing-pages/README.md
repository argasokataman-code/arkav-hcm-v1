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

## Akses

- Surface ini bersifat publik/guest untuk calon customer.
- Endpoint onboarding public yang akan ditambahkan harus tetap dibatasi oleh rate limit dan ownership rules.

## UI Aktif

- Halaman marketing utama: `/landing` dan guest entry yang diarahkan ke surface marketing publik.
- Runtime aktif memakai React entry `frontend/resources/js/public-landing-react.jsx` dengan komponen utama `frontend/resources/js/components/public-landing-reference-app.jsx`.
- Styling aktif memakai stylesheet khusus `frontend/resources/js/styles/public-landing-reference.css` agar layout mengikuti repo referensi `Pureesocial/modern-parallax-land` tanpa bentrok dengan CSS Blade lama.
- Halaman `/login` sudah memakai auth shell baru yang diselaraskan visualnya dengan landing, tetapi tetap mempertahankan DOM hook login lama (`api-login-form`, `login-email`, `login-password`, mode employee/company, dan company code).
- Route `/register` (dan alias `/register-2`, `/register-3`) tidak lagi merender halaman tersendiri; semuanya redirect ke `/landing?openOnboarding=1&startMode=pending_payment` agar memakai modal onboarding React yang sama dengan landing.
- Route `/trial` juga sekarang hanya redirect ke `/landing?openOnboarding=1` (dengan `package=<uuid>` / `startMode=pending_payment` di-forward bila ada) supaya hanya ada satu form onboarding yang dipelihara: modal React di landing page.

## Flow Bisnis End-to-End

1. Guest membuka landing page.
2. Guest membaca hero, dashboard preview, feature cards, step-by-step setup, dan pricing tier untuk membandingkan paket aktif.
3. Guest memilih CTA yang relevan dari hero, pricing, preview, atau final CTA.
4. Jika guest memulai dari CTA trial/plan di landing, sistem membuka modal onboarding React (di halaman landing itu juga) dengan package pilihan yang sudah diprefill dan `start_mode=trial`.
5. Jika guest memulai dari CTA "Daftarkan company di sini" pada halaman login, sistem masuk ke route `/register` lalu diarahkan ke `/landing?openOnboarding=1&startMode=pending_payment`; modal onboarding React yang sama auto-terbuka dengan paket berbayar dan `start_mode=pending_payment` — tidak ada form Blade terpisah lagi.
6. Guest mengisi data company dan owner.
7. Sistem membuat entity onboarding yang diperlukan.
8. Jika mode `trial`, owner diarahkan ke login untuk masuk ke workspace.
9. Jika mode `pending_payment`, owner diarahkan ke login company dengan tujuan akhir checkout `/subscription`; halaman checkout harus langsung menampilkan invoice pending yang sudah dibuat saat onboarding dan menyediakan aksi bayar yang membuka hosted payment gateway mock/dev.
10. Selama status tenant masih `pending_payment`, halaman `/subscription` menjadi billing-only lock screen: sidebar, header aplikasi, dan menu operasional tidak boleh muncul, dan setiap upaya membuka route HCM lain harus dipaksa kembali ke checkout billing.
11. Jika mode `trial`, owner boleh langsung masuk ke workspace HCM. Header aplikasi harus menampilkan badge trial beserta sisa hari aktif agar status tenant terlihat jelas sejak awal.

## Lifecycle Dan Keputusan Bisnis

- Landing hanya boleh menampilkan data marketing-safe.
- Self-serve onboarding aktif lewat modal publik, tetapi tetap wajib mengikuti kontrak public onboarding yang sudah didokumentasikan dan tidak boleh mem-bypass validasi backend.
- Validasi FE/BE harus tetap parity dengan kontrak identity/company yang aktif.
- Reference visual boleh berubah, tetapi package aktif, billing cycle, start mode, dan owner/company onboarding tetap mengikuti source of truth backend.
- Registrasi resmi dari login tidak boleh jatuh ke paket trial atau copy trial; source of truth-nya adalah `startMode=pending_payment` di controller/view onboarding.
- Runtime yang sehat untuk `pending_payment` adalah: buat company + invoice, login company, masuk ke checkout payment, invoice pending auto-muncul, buka hosted payment gateway, lalu bayar hingga subscription aktif. Redirect ke dashboard HCM atau menampilkan shell/menu aplikasi penuh sebelum payment dianggap bug flow.
- Runtime yang sehat untuk `trial` adalah: owner masuk ke app tanpa blok billing, tetapi badge trial dengan sisa hari harus tetap terlihat agar transisi ke billing berikutnya tidak mengejutkan tenant.

## Integrasi

- Packages: daftar paket dan ringkasan limit diambil dari modul packages. Lihat `docs/features/packages/README.md`.
- Subscriptions dan Purchase Transactions: flow subscribe, invoice, dan payment melanjut ke ekosistem billing aktif. Lihat `docs/features/subscriptions/README.md` dan `docs/features/purchase-transaction/README.md`.
- Identity Auth: register owner dan login company mengikuti kontrak auth yang aktif. Lihat `docs/features/identity-auth/README.md`.
- Domain Management dan Trial/Billing Dashboard: tenant yang selesai onboarding akan masuk ke operasi SaaS lanjutan. Lihat `docs/features/domain-management/README.md` dan `docs/features/trial-billing-dashboard/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

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

## Existing Vs Target

- Existing runtime: landing React publik sudah aktif dengan struktur section yang mengikuti repo referensi `modern-parallax-land`, pricing dinamis dari package aktif backend, dashboard preview visual, dan modal onboarding publik.
- Existing runtime: login publik sudah memakai auth shell baru yang selaras dengan landing, sedangkan `/register` sekarang menjadi redirect ke onboarding resmi `pending_payment`.
- Target lanjutan: fallback Blade lama diganti atau dipensiunkan penuh jika tidak lagi dibutuhkan, serta manual/E2E mobile visual check diselaraskan ke layout baru.

