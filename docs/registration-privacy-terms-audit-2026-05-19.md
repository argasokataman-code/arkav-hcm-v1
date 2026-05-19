# Audit: Registration Flow + Kebijakan Privasi + Syarat & Ketentuan

> **Validation update (2026-05-19):** temuan `Terms & Conditions masih template SmartHR` dan `Privacy & Terms masih memakai shell admin/sidebar` sudah diperbaiki pada runtime. Temuan flow `/register` dan consent landing di dokumen ini juga sudah obsolete karena route register kini redirect langsung ke onboarding landing, consent checkbox sudah wajib, dan consent disimpan di backend.

> **Date:** 2026-05-19  
> **Scope:** `auth/register*.blade.php`, `misc/privacy-policy.blade.php`, `misc/terms-condition.blade.php`, `auth/login.blade.php`, `public/landing.blade.php`

---

## Executive Summary

Ada **3 layer masalah** di halaman registrasi company:

| Layer | Severity | Issue |
|-------|----------|-------|
| **UX/Flow** | 🔴 HIGH | Registration page bilang "Gate Closed" tapi login linknya "Daftarkan company di sini" — kontradiktif |
| **Legal Docs** | 🔴 HIGH | Terms & Conditions masih template "SmartHR" — bukan ARCAV, gak sesuai UU PDP |
| **Styling** | 🟡 MEDIUM | Privacy & Terms pake `mainlayout` dengan sidebar — seharusnya guest layout |
| **Dead Code** | 🟡 MEDIUM | `register-2`, `register-3` masih template demo Smarthr yang gak terpakai |

---

## 1. Halaman Register Utama (`auth/register.blade.php`)

### Masalah

| # | Baris | Masalah | Severity |
|---|-------|---------|----------|
| 1 | L15 | `"Pendaftaran akun kini diarahkan ke onboarding company."` — Campur bahasa Indo-Inggris | 🟡 |
| 2 | L32 | Badge `"Registration Gate Closed"` — aneh, masa halaman registrasi ngomong "gate closed"? UX confusing | 🔴 |
| 3 | L33 | `"Daftarkan company Anda dari landing page"` — Campur bahasa | 🟡 |
| 4 | L48 | Button label: `"Daftarkan your company here"` — Campur bahasa | 🟡 |
| 5 | L49 | `"Langsung ke form onboarding company"` — Vague, user gak tau "onboarding company" itu apa | 🟡 |
| 6 | L57 | **Copyright 2025 - Arkav** — tahun salah, brand name masih "Arkav" bukan "ARCAV" | 🔴 |
| 7 | L28 | Logo hardcoded `build/img/image111.png` — gak pakai dynamic branding dari `WebsiteSettings` | 🟡 |
| 8 | - | Layout pake `mainlayout` — ini halaman public, tapi render dengan sidebar + header navigasi admin | 🔴 |

### Flow Contradiction

```
login.blade.php:     "Daftarkan company di sini" → route('register')
                       ↓
register.blade.php:  "Registration Gate Closed" + "Daftarkan company Anda dari landing page"
```

**User bingung:** "Masa disuruh daftar, tapi pas diklik malah bilang gate closed?"

### Root Cause
Ini adalah **halaman 'gate' sementara** yang dibuat pas migrasi alur register. Masalahnya:
- Link dari login belum diubah ke landing page langsung
- Halaman ini jadi intermediary yang gak jelas
- User harus klik 2x lagi (ke landing → pilih plan → baru onboarding)

---

## 2. Halaman Register-2 & Register-3 (`auth/register-2.blade.php`, `auth/register-3.blade.php`)

### Ini Template Demo Sisa Smarthr — Bukan Produk

| File | Baris | Masalah |
|------|-------|---------|
| `register-2` | L17 | `form action="{{url('login-2')}}"` — Submit ke login-2, bukan register API |
| `register-2` | L102 | **Copyright &copy; 2024 - Smarthr** — Brand lama, tahun 2024 |
| `register-2` | L60-65 | Checkbox `"Agree to Terms & Privacy"` — cocoklogi, tapi gak link ke halaman terms/privacy |
| `register-2` | L78-99 | Social login Facebook/Google/Apple — **semua dummy** `javascript:void(0)` |
| `register-3` | L59 | **Button "Sign In"** — padahal ini halaman register, harusnya "Sign Up" |
| `register-3` | L93 | **Copyright &copy; 2024 - Smarthr** |
| `register-3` | L53 | Checkbox `"Agree to Terms & Privacy"` — no actual links |
| Both | - | Gak ada validasi, gak ada error handling, gak wiring ke API |

**Verdict:** Ini adalah **dead template code** dari theme Smarthr. Tidak dipakai di alur produk. Tapi masih bisa diakses via URL dan muncul di sidebar menu → membingungkan user.

---

## 3. Kebijakan Privasi (`misc/privacy-policy.blade.php`)

### Yang SUDAH BAGUS ✅
- [x] Isi sudah comply UU PDP (9 sections lengkap)
- [x] DPO contact sudah pakai config (`config('pdp.dpo_*')`)
- [x] Tabel pihak ketiga dengan lokasi server
- [x] Hak subjek data sesuai Pasal 5-13
- [x] Retensi data (5 tahun, 10 tahun payroll, 1 tahun log)
- [x] Notifikasi insiden 3×24 jam (Pasal 46)

### Yang PERLU DIPERBAIKI

| # | Baris | Masalah | Severity |
|---|-------|---------|----------|
| 1 | - | **Layout pake `mainlayout`** — privacy policy halaman public, tapi render dengan sidebar + header admin | 🟡 |
| 2 | L44 | `ARCAV HCM ("kami", "Layanan")` — seharusnya "Layanan" = nama produk, konsisten | 🟢 |
| 3 | - | **Tidak ada link/checkbox** dari registration flow → privacy policy | 🟡 |
| 4 | - | Hanya bisa diakses dari sidebar menu — user baru yang mau registrasi gak bakal nemu ini | 🟡 |

---

## 4. Syarat & Ketentuan (`misc/terms-condition.blade.php`) ⛔

### MASALAH BESAR — Masih Template!

```
Line 37:  "Welcome to the Smart HR Admin platform..."
Line 91:  "owned by SmartHR."
Line 110: "In no event shall SmartHR be liable..."
```

| # | Masalah | Detail |
|---|---------|--------|
| 🔴 | **Brand salah** | Masih "SmartHR" bukan "ARCAV HCM" |
| 🔴 | **Gak referensi UU PDP / UU ITE** | Terms ini kayak template generic, bukan legal document Indonesia |
| 🔴 | **Gak spesifik** | "HR Admin platform" — gak menyebut ARCAV, gak menyebut fitur spesifik |
| 🟡 | **Bahasa Inggris semua** | Harusnya bilingual (Indonesia + Inggris) atau minimal Indonesia untuk kepastian hukum |
| 🟡 | **Layout pake mainlayout** | Sama, ini halaman public |
| 🟡 | **Gak ada pasal tentang subscription/billing** | Terms gak cover: refund policy, cancellation, auto-renewal, dll |

### Checklist Terms yang WAJIB Ada untuk SaaS Indonesia

| Pasal | Ada? | Keterangan |
|-------|------|-----------|
| Definisi & Interpretasi | ❌ | |
| Eligibility (18+, badan hukum) | ❌ | |
| Akun & Keamanan Login | ⚠️ | Ada "User Responsibilities" tapi generik |
| Subscription & Pembayaran | ❌ | **KRITIS** — gak ada refund, cancel, auto-renewal |
| SLA & Availability | ❌ | |
| Data Protection & PDP | ❌ | **KRITIS** — terms harus refer ke privacy policy |
| Pembatasan Tanggung Jawab | ⚠️ | Ada tapi copy-paste generic |
| Hukum yang Berlaku | ❌ | Gak mention Indonesia |
| Penyelesaian Sengketa | ❌ | |
| Pengakhiran Layanan | ❌ | |

---

## 5. Landing Page (`public/landing.blade.php`)

### Checkbox Consent

```
Line:  "Data disimpan secara aman sesuai Kebijakan Privasi
        dan Syarat & Ketentuan.
        Saya dapat mengajukan penghapusan data kapan saja melalui pengaturan akun."
```

| # | Masalah |
|---|---------|
| 1 | Ini cuma TEXT, bukan checkbox wajib yang harus di-klik user |
| 2 | Tidak ada `required` validation sebelum submit |
| 3 | Tidak ada record consent (log waktu user menyetujui) — padahal UU PDP Pasal 20 a mensyaratkan **bukti persetujuan** |

---

## 6. Ringkasan Semua Masalah

### 🔴 HIGH PRIORITY (Fix Segera)

| No | Masalah | File | Fix |
|----|---------|------|-----|
| 1 | **Terms & Conditions masih template "SmartHR"** | `misc/terms-condition.blade.php` | Rewrite total dengan legal terms proper untuk ARCAV HCM SaaS, referensi UU PDP |
| 2 | **Registration page bilang "Gate Closed"** | `auth/register.blade.php` | Redirect `route('register')` langsung ke `/landing#pricing`, hapus halaman perantara |
| 3 | **Gak ada consent record** | `public/landing.blade.php` | Ubah dari text biasa jadi checkbox wajib + simpan `accepted_privacy_at` dan `accepted_terms_at` di DB |
| 4 | **Copyright masih "Arkav" / "Smarthr"** | Multiple files | Fix ke "ARCAV HCM" tahun 2026 |

### 🟡 MEDIUM PRIORITY

| No | Masalah | File | Fix |
|----|---------|------|-----|
| 5 | Privacy & Terms pake `mainlayout` (ada sidebar) | `misc/privacy-policy.blade.php`, `misc/terms-condition.blade.php` | Buat guest-only layout tanpa sidebar |
| 6 | `register-2`, `register-3` masih template Smarthr | `auth/register-2.blade.php`, `auth/register-3.blade.php` | Hapus atau redirect ke register utama |
| 7 | Campur bahasa Indo-Inggris di flow register | `auth/register.blade.php` | Konsisten pake Bahasa Indonesia |
| 8 | Logo hardcoded | `auth/register.blade.php` | Pakai `WebsiteSettings::brandingUrl()` |

### 🟢 LOW PRIORITY

| No | Masalah | File |
|----|---------|------|
| 9 | Belum ada halaman privacy/terms di flow guest | All |
| 10 | Social login buttons dummy di register-2/3 | `auth/register-2/3.blade.php` |
| 11 | `form action` register-2 submit ke login-2 | `auth/register-2.blade.php` |

---

## 7. Rekomendasi Fix

### Step 1: 🔴 Terms & Conditions — Rewrite Total

Buat `misc/terms-condition.blade.php` baru dengan konten proper:

```markdown
# Syarat & Ketentuan — ARCAV HCM

## 1. Definisi
## 2. Penerimaan & Perubahan
## 3. Pendaftaran & Akun
## 4. Subscription & Pembayaran (krusial!)
## 5. Kewajiban Pengguna
## 6. Data Pribadi (refer ke Kebijakan Privasi)
## 7. Hak Kekayaan Intelektual
## 8. SLA & Ketersediaan
## 9. Pembatasan Tanggung Jawab
## 10. Hukum yang Berlaku (Indonesia)
## 11. Penyelesaian Sengketa
## 12. Pengakhiran
## 13. Hubungi Kami
```

### Step 2: 🔴 Redirect Register → Landing

```php
// routes/web.php
Route::get('/register', function () {
    return redirect('/landing#pricing');
})->name('register');
```

Hapus `auth/register.blade.php` atau jadikan 404.

### Step 3: 🟡 Guest-Only Layout

Buat `layout/guestlayout.blade.php`:
- Tanpa sidebar
- Tanpa header navigasi admin
- Paling-paling header minimal (logo + link login)

Privacy Policy dan Terms pake layout ini.

### Step 4: 🟡 Consent Checkbox + Logging

Di form landing page:
```html
<div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="consent_terms" id="consent_terms" required>
    <label class="form-check-label" for="consent_terms">
        Saya telah membaca dan menyetujui 
        <a href="/privacy-policy" target="_blank">Kebijakan Privasi</a> dan 
        <a href="/terms-condition" target="_blank">Syarat & Ketentuan</a> ARCAV HCM.
    </label>
</div>
```

Simpan consent di DB:
```
users: accepted_privacy_at, accepted_terms_at, accepted_version
```

---

## 8. Final Verdict

| Area | Nilai | Keterangan |
|------|-------|-----------|
| **Privacy Policy** | ✅ **85% proper** | Isi sudah comply UU PDP, tapi layout salah dan gak terintegrasi dengan flow registrasi |
| **Terms & Conditions** | ❌ **10% — REWRITE** | Masih template "SmartHR" — ini embarrassment buat produk serius |
| **Register Page** | ⚠️ **30%** | Halaman perantara yang kontradiktif, campur bahasa, brand salah |
| **Register-2/3** | ❌ **0% — dead code** | Template demo Smarthr yang gak dipake, bikin bingung |
| **Landing Consent** | ⚠️ **40%** | Text biasa tanpa checkbox, tanpa bukti consent |
| **Overall UX Flow** | ⚠️ **35%** | Login → "Daftar" → "Gate Closed" → "Ke Landing" → Pilih Plan → Onboarding. Terlalu banyak klik. |

> **"Terms & Conditions yang masih pakai brand SmartHR itu masalah paling genting. Privacy Policy udah bagus, tapi percuma kalo terms-nya masih template dan registration flow-nya contradictory."**