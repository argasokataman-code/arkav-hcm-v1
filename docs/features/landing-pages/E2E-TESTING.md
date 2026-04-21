# Landing Pages — E2E TESTING (manual)

Dokumen ini berisi skenario manual untuk memastikan landing page public, login shell baru, dan flow onboarding public berjalan sesuai implementasi aktif.

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

## Skenario 3 — CTA landing membuka onboarding public yang sesuai paket pilihan

- Dari landing, klik “Mulai trial” pada salah satu paket.
- Expected:
  - Muncul modal onboarding public di halaman yang sama.
  - Package yang dipilih di pricing card sudah terpilih di form.
  - Jika package trial dipilih, billing cycle terkunci ke `monthly` dan mode awal mengikuti trial.

Lalu submit data company + owner yang valid.
- Expected:
  - Request terkirim ke `POST /v1/public/onboarding`.
  - Jika sukses, flow lanjut ke hasil onboarding aktif sistem (login owner atau billing flow sesuai mode yang dipakai).

## Skenario 4 — CTA registrasi resmi dari login tidak masuk trial

- Buka `/login` sebagai guest.
- Klik “Daftarkan company di sini”.
- Expected:
  - Browser tidak berhenti di gate informatif lama.
  - Browser diarahkan ke `/register`, lalu redirect ke `/trial?startMode=pending_payment`.
  - Copy halaman onboarding berubah ke registrasi resmi company, bukan copy trial.
  - Trial package tidak tampil sebagai pilihan default untuk mode ini.

## Skenario 5 — Lanjutan flow registrasi resmi → invoice → payment

Tujuan skenario ini: memastikan registrasi resmi dari login tidak memutus flow SaaS yang sudah ada.

- Dari `/trial?startMode=pending_payment`, isi form dengan data valid.
- Expected:
  - Subscription awal berstatus `pending_payment`.
  - Invoice draft tercipta dan mengikuti flow billing aktif.
  - Setelah payment diverifikasi, subscription menjadi `active` sesuai lifecycle pada `docs/features/subscriptions/`.

## Skenario 6 — Self-serve onboarding trial dari landing

- Dari landing, pilih paket lalu lanjut ke form onboarding.
- Isi:
  - Company name, timezone, currency, country code
  - Owner name/email/password kuat
- Submit.
- Expected:
  - Company baru tercipta.
  - Subscription tercipta dengan status `trial` jika package trial dipilih.
  - Untuk package trial, tidak ada copy registrasi resmi dan billing cycle trial tetap terkunci sesuai aturan UI.

Validasi negatif minimum:
- Password lemah → 422
- Email duplicate → 409/422 sesuai kontrak

## Skenario 7 — Negative: email owner sudah terdaftar (duplikat)

- Isi onboarding dengan email yang sudah ada di tabel `users`.
- Expected:
  - `409` atau `422` (unique constraint),
  - UI menunjukkan pesan yang jelas (mis. “email sudah terdaftar, silakan login”),
  - tidak ada company/subscription baru yang tercipta (no partial provisioning).

## Skenario 8 — Negative: paket tidak aktif / archived

- Pilih paket yang statusnya bukan `active` (atau manipulasi request `package_id` ke id paket nonaktif).
- Expected:
  - `404` (Not found) atau `422` (invalid package) sesuai kontrak,
  - UI menampilkan pesan “Paket tidak tersedia / sudah tidak aktif”.

## Skenario 9 — Negative: billing_cycle invalid

- Kirim `billing_cycle` selain `monthly|yearly` (mis. `weekly`) via request manipulation.
- Expected:
  - `422 VALIDATION_ERROR`,
  - field error untuk billing cycle.

## Skenario 10 — Negative: trial tidak tersedia untuk paket

- Jika bisnis membatasi trial hanya untuk paket tertentu:
  - Pilih paket tanpa trial, tapi paksa `status=trial`/`trialDays` pada payload (jika ada).
- Expected:
  - `422` dengan code domain (mis. `TRIAL_NOT_ALLOWED`) atau pesan yang setara,
  - UI fallback ke `pending_payment` atau meminta user memilih paket lain (sesuai product decision).

## Skenario 11 — Negative: attempt berulang (rate limit)

- Lakukan submit onboarding berkali-kali dari IP/email yang sama dalam waktu singkat.
- Expected:
  - `429` (Too many requests) untuk mencegah spam provisioning,
  - UI menampilkan “coba lagi setelah X detik/menit”.

## Skenario 12 — Negative: double submit (idempotency)

- Klik tombol submit onboarding cepat 2x atau refresh saat request berjalan.
- Expected:
  - hanya 1 company/subscription tercipta,
  - request kedua menghasilkan:
    - response idempotent (mengembalikan result yang sama), atau
    - `409` “already created” dengan pointer ke resource yang sudah dibuat.

## Skenario 13 — Negative: subscription conflict untuk company yang sama

- Jika company sudah punya subscription `active` atau `pending_payment`, coba onboarding lagi untuk company yang sama (atau manipulasi payload agar menarget company existing).
- Expected:
  - `422` dengan code domain (mis. `SUBSCRIPTION_ALREADY_EXISTS` / `SUBSCRIPTION_CONFLICT`),
  - UI mengarahkan user ke halaman subscription/invoice existing, bukan membuat entri baru.

## Skenario 14 — Negative: invoice/payment mismatch

- Buat payment untuk invoice yang:
  - sudah `paid`, atau
  - berbeda `company_id` / `subscription_id`.
- Expected:
  - `422` atau `403` (ownership) sesuai kontrak,
  - tidak ada perubahan status subscription akibat payment yang tidak valid.

