# Landing Pages — E2E TESTING (manual)

Dokumen ini berisi skenario manual untuk memastikan landing page public dan CTA “Mulai trial” berjalan sesuai flow sistem.

## Prasyarat

- Backend berjalan normal.
- Minimal ada 1 package aktif di sistem (agar pricing cards muncul).
- User bisa login via `/login`.

## Skenario 1 — Guest dapat membuka landing page

- Buka `/` di browser mode incognito (tanpa cookie).
- Expected:
  - Halaman tampil (tidak redirect ke `/lock-screen`).
  - Tidak menampilkan sidebar aplikasi HCM (marketing layout).
  - Tombol “Login” terlihat.

## Skenario 2 — Animasi ringan berjalan dan tidak mengganggu

- Scroll dari hero ke section bawah.
- Expected:
  - Section reveal animation halus (fade/slide).
  - Jika OS setting `prefers-reduced-motion` aktif, animasi minimal/disabled.

## Skenario 3 — CTA “Mulai trial” membawa ke login + next redirect

- Dari landing, klik “Mulai trial” pada salah satu paket.
- Expected:
  - Browser ke `/login?next=/subscription?...` (atau bentuk query yang dipakai implementasi).

Lalu:
- Login dengan akun valid.
- Expected:
  - Setelah login sukses, browser redirect ke halaman subscription dengan parameter paket terisi.
  - Flow status awal subscription sesuai skenario `pending_payment`.

## Skenario 4 — Anti open redirect pada parameter `next`

- Buka `/login?next=https://example.com`.
- Login sukses.
- Expected:
  - Tidak redirect ke domain luar.
  - Fallback redirect ke `/index` (atau fallback internal yang disepakati).

## Skenario 5 — Lanjutan flow subscribe → invoice → payment

Tujuan skenario ini: memastikan landing tidak memutus flow SaaS yang sudah ada.

- Setelah redirect ke halaman subscription:
  - Buat subscription (status `pending_payment`) sesuai paket yang dipilih.
- Lanjutkan ke invoice screen (SaaS invoices) dan pastikan invoice terbuat/terkait subscription sesuai desain.
- Lanjutkan payment:
  - Buat payment record lalu verify (admin/operator).
- Expected:
  - Invoice menjadi paid sesuai mekanisme sistem.
  - Subscription menjadi active sesuai lifecycle yang terdokumentasi pada `docs/features/subscriptions/`.

## Skenario 6 — Self-serve onboarding (company + owner) dari landing (target)

Skenario ini berlaku jika endpoint public onboarding sudah diimplementasikan (lihat `IMPLEMENTATION.md`).

- Dari landing, pilih paket lalu lanjut ke form onboarding.
- Isi:
  - Company code (pattern `^[A-Za-z0-9_-]+$`)
  - Company name, timezone, currency, country code
  - Owner name/email/password kuat
- Submit.
- Expected:
  - User ter-login (cookie token ter-set)
  - Company baru tercipta dan menjadi activeCompany
  - Subscription tercipta dengan status `trial` atau `pending_payment`
  - Jika `pending_payment`: invoice tercipta dan user diarahkan ke layar payment

Validasi negatif minimum:
- Company code invalid (spasi / simbol) → 422 + pesan jelas
- Password lemah → 422
- Email duplicate → 409/422 sesuai kontrak

## Skenario 7 — Negative: company code sudah dipakai (duplikat)

- Isi onboarding dengan `company.code` yang sudah ada (mis. `default_company` atau kode lain yang sudah tersimpan).
- Expected:
  - `409` (Conflict) atau `422` (Validation) mengikuti pola implementasi,
  - error envelope berisi pesan “code already exists” yang ditampilkan di field company code,
  - tidak ada company/subscription baru yang tercipta.

## Skenario 8 — Negative: email owner sudah terdaftar (duplikat)

- Isi onboarding dengan email yang sudah ada di tabel `users`.
- Expected:
  - `409` atau `422` (unique constraint),
  - UI menunjukkan pesan yang jelas (mis. “email sudah terdaftar, silakan login”),
  - tidak ada company/subscription baru yang tercipta (no partial provisioning).

## Skenario 9 — Negative: paket tidak aktif / archived

- Pilih paket yang statusnya bukan `active` (atau manipulasi request `package_id` ke id paket nonaktif).
- Expected:
  - `404` (Not found) atau `422` (invalid package) sesuai kontrak,
  - UI menampilkan pesan “Paket tidak tersedia / sudah tidak aktif”.

## Skenario 10 — Negative: billing_cycle invalid

- Kirim `billing_cycle` selain `monthly|yearly` (mis. `weekly`) via request manipulation.
- Expected:
  - `422 VALIDATION_ERROR`,
  - field error untuk billing cycle.

## Skenario 11 — Negative: trial tidak tersedia untuk paket

- Jika bisnis membatasi trial hanya untuk paket tertentu:
  - Pilih paket tanpa trial, tapi paksa `status=trial`/`trialDays` pada payload (jika ada).
- Expected:
  - `422` dengan code domain (mis. `TRIAL_NOT_ALLOWED`) atau pesan yang setara,
  - UI fallback ke `pending_payment` atau meminta user memilih paket lain (sesuai product decision).

## Skenario 12 — Negative: attempt berulang (rate limit)

- Lakukan submit onboarding berkali-kali dari IP/email yang sama dalam waktu singkat.
- Expected:
  - `429` (Too many requests) untuk mencegah spam provisioning,
  - UI menampilkan “coba lagi setelah X detik/menit”.

## Skenario 13 — Negative: double submit (idempotency)

- Klik tombol submit onboarding cepat 2x atau refresh saat request berjalan.
- Expected:
  - hanya 1 company/subscription tercipta,
  - request kedua menghasilkan:
    - response idempotent (mengembalikan result yang sama), atau
    - `409` “already created” dengan pointer ke resource yang sudah dibuat.

## Skenario 14 — Negative: subscription conflict untuk company yang sama

- Jika company sudah punya subscription `active` atau `pending_payment`, coba onboarding lagi untuk company yang sama (atau manipulasi payload agar menarget company existing).
- Expected:
  - `422` dengan code domain (mis. `SUBSCRIPTION_ALREADY_EXISTS` / `SUBSCRIPTION_CONFLICT`),
  - UI mengarahkan user ke halaman subscription/invoice existing, bukan membuat entri baru.

## Skenario 15 — Negative: invoice/payment mismatch

- Buat payment untuk invoice yang:
  - sudah `paid`, atau
  - berbeda `company_id` / `subscription_id`.
- Expected:
  - `422` atau `403` (ownership) sesuai kontrak,
  - tidak ada perubahan status subscription akibat payment yang tidak valid.

