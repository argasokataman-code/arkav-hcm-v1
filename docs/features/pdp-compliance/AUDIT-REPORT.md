# Laporan Audit Kepatuhan UU PDP — ARCAV HCM

**Tanggal audit:** 5 Mei 2026  
**Versi kode diperiksa:** HEAD (branch main)  
**Auditor:** Internal AI-assisted audit (GitHub Copilot + manual code review)  
**Status:** 25 temuan (6 CRITICAL · 7 HIGH · 8 MEDIUM · 4 LOW)  
**Deadline hukum yang terlewat:** Oktober 2024 (Pasal 74 UU No. 27/2022)

---

## Metodologi Audit

Audit dilakukan dengan cara:
1. Tracing semua entry point pengumpulan data pribadi (form, API endpoint)
2. Memeriksa model-model DB yang menyimpan data sensitif
3. Memeriksa layanan pihak ketiga yang menerima data
4. Memeriksa ada/tidaknya mekanisme hak subjek (hapus, akses, withdraw consent)
5. Memeriksa audit log dan notifikasi
6. Memeriksa konten legal (Privacy Policy, T&C)

**File yang diperiksa (40+ file):**
- `backend/resources/views/public/landing.blade.php`
- `backend/app/Http/Controllers/Api/PublicOnboardingController.php`
- `backend/app/Http/Controllers/Api/HcmEmployeeController.php`
- `backend/app/Http/Controllers/Api/HcmUserManagementController.php`
- `backend/app/Http/Controllers/Api/HcmAiChatController.php`
- `backend/app/Models/User.php`
- `backend/app/Models/EmployeeProfile.php`
- `backend/app/Models/EmployeeTaxProfile.php`
- `backend/app/Models/EmployeeBenefit.php`
- `backend/app/Models/AttendanceRecord.php`
- `backend/app/Models/AuditLog.php`
- `backend/app/Models/AiChatLog.php`
- `backend/app/Services/Ai/AiLlmService.php`
- `backend/app/Services/XenditService.php`
- `backend/app/Services/StripeService.php`
- `backend/resources/views/misc/privacy-policy.blade.php`
- `backend/resources/views/settings/gdpr.blade.php`
- `backend/app/Mail/MonthlyPayslipMail.php`
- Dan 20+ file lain (controllers, migrations, routes)

---

## CRITICAL — 6 Temuan

### C1: Tidak Ada Consent di Form Onboarding Perusahaan

**Pasal dilanggar:** Pasal 20 + 21 UU PDP (dasar pemrosesan data = persetujuan)  
**Risiko:** Sanksi langsung; setiap pendaftar = 1 data subjek tanpa consent

**Evidence:**
```
File: backend/resources/views/public/landing.blade.php
Masalah: Form #onboardingModal mengumpulkan nama, email, telepon, alamat, password
         — TIDAK ADA checkbox consent, TIDAK ADA link Privacy Policy
         — TIDAK ADA disclosure tujuan penggunaan data

File: backend/app/Http/Controllers/Api/PublicOnboardingController.php
Method: store()
Masalah: Tidak ada validasi field 'consent_accepted'
         Tidak ada penyimpanan 'consent_timestamp'
```

**Dampak bisnis:** Setiap registrasi tenant baru saat ini tidak sah secara hukum.

---

### C2: Tidak Ada Consent saat HR Input Data Karyawan

**Pasal dilanggar:** Pasal 20-21 + Pasal 4 ayat (2) (data spesifik)  
**Risiko:** NIK, agama, status perkawinan, rekening bank dikumpulkan tanpa izin karyawan

**Evidence:**
```
File: backend/app/Http/Controllers/Api/HcmEmployeeController.php
Method: store() — sekitar baris 496
Masalah: HR dapat langsung input NIK, date_of_birth, gender, marital_status,
         religion, bank_account_no, bpjs_kesehatan_no, bpjs_ketenagakerjaan_no
         — Tidak ada konfirmasi consent dari karyawan
         — Tidak ada disclosure dasar hukum pemrosesan

File: backend/app/Models/EmployeeProfile.php
$fillable: 'nik', 'date_of_birth', 'place_of_birth', 'gender', 'marital_status',
           'religion', 'nationality', 'bank_name', 'bank_account_no',
           'bank_ifsc_code', 'bank_branch', 'emergency_contacts',
           'education_items', 'experience_items', 'profile_photo_path'
Masalah: Semua tersimpan tanpa consent record
```

**Dampak bisnis:** Karyawan dapat menuntut perusahaan tenant + ARCAV atas pemrosesan data tanpa izin.

---

### C3: Biometrik (Selfie) Dikumpulkan Tanpa Consent Khusus

**Pasal dilanggar:** Pasal 4 ayat (2) huruf b — Data Biometrik = Data Pribadi Spesifik  
**Risiko:** Data biometrik memerlukan consent eksplisit terpisah dari consent umum (Pasal 21)

**Evidence:**
```
File: backend/app/Models/AttendanceRecord.php
$fillable: 'selfie_path', 'selfie_encrypted_hash'
Masalah: Foto wajah karyawan disimpan tanpa consent biometrik khusus
         'selfie_path' = path ke file gambar mentah
         'selfie_encrypted_hash' = hash, bukan enkripsi proper

File: backend/app/Http/Controllers/Api/AttendanceController.php (atau serupa)
Masalah: Tidak ada pengecekan apakah karyawan sudah berikan consent biometrik
```

**Dampak bisnis:** Biometrik = kategori data paling sensitif, sanksi lebih berat.

---

### C4: GPS Location Tracking Tanpa Disclosure ke Karyawan

**Pasal dilanggar:** Pasal 4 (data pribadi spesifik — data lokasi), Pasal 20-21  
**Risiko:** Karyawan tidak tahu lokasi mereka dilacak dan disimpan

**Evidence:**
```
File: backend/app/Models/AttendanceRecord.php
$fillable: 'check_in_latitude', 'check_in_longitude',
           'check_out_latitude', 'check_out_longitude'
Masalah: Koordinat GPS disimpan permanen
         Tidak ada disclosure/consent untuk pelacakan lokasi
         Tidak ada kebijakan retensi data lokasi
```

**Dampak bisnis:** Berpotensi melanggar aturan ketenagakerjaan + privasi lokasi.

---

### C5: Data Sensitif Karyawan Tersimpan Plaintext di Database

**Pasal dilanggar:** Pasal 35 UU PDP — Kewajiban keamanan teknis (enkripsi)  
**Risiko:** Jika DB bocor, semua data sensitif langsung terbaca

**Evidence:**
```
File: backend/app/Models/EmployeeProfile.php
$casts: [] — TIDAK ADA 'encrypted' cast
Fields terdampak: nik, bank_account_no, bank_ifsc_code

File: backend/app/Models/EmployeeTaxProfile.php
Fields terdampak: npwp (Nomor Pokok Wajib Pajak) — plaintext

File: backend/app/Models/EmployeeBenefit.php
Fields terdampak: bpjs_kesehatan_no, bpjs_ketenagakerjaan_no — plaintext

Catatan: Laravel 9+ mendukung 'encrypted' cast native.
Semua field di atas seharusnya menggunakan: 'field' => 'encrypted'
```

**Dampak bisnis:** Kebocoran DB = eksposur data PII + data keuangan jutaan karyawan.

---

### C6: Verifikasi Email Dinonaktifkan

**Pasal dilanggar:** Pasal 35 + Pasal 36 — Kewajiban autentikasi yang andal  
**Risiko:** Akun dapat dibuat dengan email palsu; tidak ada verifikasi identitas

**Evidence:**
```
File: backend/app/Models/User.php
Line 5: // use Illuminate\Contracts\Auth\MustVerifyEmail;
Status: DIKOMENTARI — email verifikasi TIDAK AKTIF

File: backend/routes/auth.php (atau web.php)
Status: Route verifikasi email mungkin tidak terdaftar
```

**Dampak bisnis:** Akun fiktif dapat mengakses sistem; tidak ada bukti kepemilikan email.

---

## HIGH — 7 Temuan

### H1: Tidak Ada Mekanisme Hapus Data Subjek (Right to Erasure)

**Pasal dilanggar:** Pasal 8 + Pasal 43-44 UU PDP  
**Risiko:** Jika subjek meminta hapus data, sistem tidak bisa memenuhi

**Evidence:**
```
File: backend/app/Http/Controllers/Api/HcmUserManagementController.php
Method: deleteUser() — sekitar baris 462
Masalah: Hanya menghapus record CompanyUser (keanggotaan tenant)
         User record tetap ada di tabel 'users'
         EmployeeProfile tetap ada di DB
         AttendanceRecord, AiChatLog, dll tidak dihapus

File: backend/app/Models/User.php
Masalah: TIDAK ADA 'use SoftDeletes' trait
         Hard delete pun tidak diimplementasi properly

File: backend/app/Models/EmployeeProfile.php
Masalah: TIDAK ADA 'use SoftDeletes' trait
```

**Dampak bisnis:** Tidak bisa comply dengan permintaan hapus data dari karyawan atau regulator.

---

### H2: Tidak Ada Sistem Notifikasi Breach (Data Breach Notification)

**Pasal dilanggar:** Pasal 46 UU PDP — wajib notifikasi dalam 3×24 jam  
**Risiko:** Jika ada insiden, ARCAV tidak punya mekanisme wajib notif

**Evidence:**
```
Hasil pencarian di seluruh codebase:
- grep 'breach' = 0 hasil
- grep 'incident' = 0 hasil  
- grep 'security_incident' = 0 hasil
- grep 'data_breach' = 0 hasil
- Tidak ada tabel/model untuk incident tracking
- Tidak ada email template untuk breach notification
- Tidak ada cronjob atau event listener untuk notifikasi
```

**Dampak bisnis:** Pelanggaran Pasal 46 = sanksi otomatis tanpa perlu ada breach; cukup tidak punya sistem notifikasi.

---

### H3: AI Chat Mengirim Data Karyawan ke Server OpenAI Eksternal Tanpa Disclosure

**Pasal dilanggar:** Pasal 48-51 (transfer data ke pihak ketiga/luar negeri), Pasal 20  
**Risiko:** Data karyawan dikirim ke OpenAI (US) tanpa consent atau disclosure

**Evidence:**
```
File: backend/app/Services/Ai/AiLlmService.php
Method: chat()
Kode kunci:
  Http::withToken($this->apiKey)
      ->post("{$this->baseUrl}/chat/completions", [
          'messages' => $messages,  // BERISI data karyawan
          ...
      ])

File: backend/app/Http/Controllers/Api/HcmAiChatController.php
Masalah: Endpoint menerima query tentang payslip, absensi, cuti karyawan
         Data ini dikirim ke API eksternal
         Tidak ada disclosure di UI: "Data Anda akan diproses oleh AI pihak ketiga"
         Tidak ada consent khusus untuk transfer data ke AI

File: backend/app/Models/AiChatLog.php
$fillable: 'user_message', 'ai_reply' (pesan verbatim tersimpan)
Masalah: Percakapan karyawan disimpan tanpa batas waktu
```

**Dampak bisnis:** Transfer data lintas negara ke OpenAI (US) tanpa basis hukum yang jelas.

---

### H4: Transfer Data ke Xendit (Singapura) dan Stripe (AS) Tanpa Consent

**Pasal dilanggar:** Pasal 48-51 — Transfer data lintas negara harus ada dasar hukum  
**Risiko:** Nama + email pelanggan dikirim ke gateway asing tanpa disclosure

**Evidence:**
```
File: backend/app/Services/XenditService.php
Method: createInvoice()
Data yang dikirim ke https://api.xendit.co:
  'customer_name' => $company->name
  'customer_email' => $company->email
  'amount' => ...
Catatan: Xendit beroperasi di Singapura

File: backend/app/Services/StripeService.php
Data yang dikirim ke Stripe (AS):
  customer name, email, amount
Catatan: Stripe beroperasi di Amerika Serikat

Tidak ada:
  - Disclosure di form pembayaran bahwa data dikirim ke Xendit/Stripe
  - Consent untuk transfer data ke luar negeri
  - Klausa transfer lintas negara di Privacy Policy
```

**Dampak bisnis:** Setiap transaksi pembayaran berpotensi melanggar Pasal 48-51.

---

### H5: Export Data Karyawan Tanpa Audit Trail

**Pasal dilanggar:** Pasal 31 — Wajib rekam kegiatan pemrosesan  
**Risiko:** Tidak ada catatan siapa yang mendownload data apa, kapan

**Evidence:**
```
File: backend/app/Http/Controllers/Api/HcmEmployeeController.php
Method: exportEmployees()  — baris ~662
Masalah: Langsung return StreamedResponse CSV/Excel/PDF
         Tidak ada log: user UUID, IP, timestamp, format yang didownload

Method: exportDepartments() — masalah serupa
Method: exportDesignations() — masalah serupa  
Method: exportPolicies() — masalah serupa

File: backend/app/Models/AuditLog.php
Method: recordAuditLog()
Masalah: Hanya dipanggil dari SuperAdminDashboardController
         HCM operations (HR admin export, payroll run, dll) TIDAK tercatat
```

**Dampak bisnis:** Tidak bisa audit siapa yang mengambil data karyawan; risiko insider threat tanpa trace.

---

### H6: Privacy Policy Bermasalah (Salah Brand, Bahasa, Konten Tidak Comply)

**Pasal dilanggar:** Pasal 22 ayat (4) huruf c — Kewajiban informasi dalam Bahasa Indonesia  
**Risiko:** Privacy Policy tidak valid secara hukum

**Evidence:**
```
File: backend/resources/views/misc/privacy-policy.blade.php
Masalah 1: Nama brand salah — tertulis "SmratHR" (bukan ARCAV HCM)
Masalah 2: Ditulis seluruhnya dalam Bahasa Inggris
           UU PDP mensyaratkan dokumen kebijakan dalam Bahasa Indonesia
Masalah 3: Tidak menyebutkan:
           - Kontak DPO (Data Protection Officer)
           - Periode retensi data
           - Mekanisme tarik persetujuan
           - Hak subjek data (Pasal 5-13)
           - Informasi transfer ke pihak ketiga (Xendit/Stripe/OpenAI)
Masalah 4: Tidak ada link ke Privacy Policy dari form onboarding/registrasi
```

**Dampak bisnis:** Privacy Policy yang tidak valid = seperti tidak punya Privacy Policy = sanksi.

---

### H7: Form Consent GDPR Tidak Berfungsi (Non-Functional)

**Pasal dilanggar:** Pasal 20-21 — Mekanisme consent harus benar-benar berfungsi  
**Risiko:** Form consent yang ada adalah facade tanpa backend

**Evidence:**
```
File: backend/resources/views/settings/gdpr.blade.php
Masalah: <form action="{{ url('gdpr') }}" method="GET">
         Method GET untuk form consent adalah salah (tidak ada body/payload)
         
Hasil cek route: POST /gdpr TIDAK ADA di route files
                 GET /gdpr mungkin ada tapi tidak memproses consent

Tidak ada:
  - Cookie consent banner di halaman publik
  - Mekanisme opt-out aktif
  - Record consent yang tersimpan di DB
```

**Dampak bisnis:** Klaim "kami sudah implementasi consent" tidak bisa dibuktikan.

---

## MEDIUM — 8 Temuan

### M1: AuditLog Hanya Cover Super Admin, Tidak Cover Operasi HCM

**Pasal dilanggar:** Pasal 31 — Wajib rekam kegiatan pemrosesan  
**Evidence:**
```
File: backend/app/Models/AuditLog.php
File: backend/app/Http/Controllers/SuperAdminDashboardController.php
Method: recordAuditLog() — dipanggil HANYA dari SuperAdmin actions

Operasi yang TIDAK tercatat:
  - HR Admin buat/edit/hapus profil karyawan
  - HR Admin jalankan payroll
  - HR Admin approve/reject cuti
  - HR Admin export data
  - Karyawan update profil sendiri
```

---

### M2: AI Chat Log Menyimpan Pesan Verbatim Tanpa Kebijakan Retensi

**Pasal dilanggar:** Pasal 26 — Pemrosesan minimal, Pasal 31  
**Evidence:**
```
File: backend/app/Models/AiChatLog.php
$fillable: 'user_message', 'ai_reply'
Masalah: Semua percakapan tersimpan full verbatim tanpa batas waktu
         Tidak ada 'deleted_at' (tidak SoftDeletable)
         Tidak ada scheduled job untuk hapus log lama
         Percakapan mungkin berisi pertanyaan sensitif tentang gaji, cuti, dll
```

---

### M3: Tidak Ada Data Retention Policy yang Terprogram

**Pasal dilanggar:** Pasal 26 ayat (1) — Data tidak boleh disimpan melebihi tujuan  
**Evidence:**
```
Hasil pencarian di seluruh codebase:
  - grep 'retention' = 0 hasil relevan
  - grep 'scheduled_deletion' = 0 hasil
  - Tidak ada model dengan 'expires_at' untuk data sensitif
  - Tidak ada job/command untuk hapus data kadaluarsa
  - Tidak ada config ttl untuk jenis data tertentu
```

---

### M4: Tidak Ada Mekanisme Withdrawal Consent

**Pasal dilanggar:** Pasal 9 — Hak tarik persetujuan  
**Evidence:**
```
Hasil pencarian route:
  - POST /v1/hcm/me/withdraw-consent = TIDAK ADA
  - Tidak ada endpoint untuk tarik consent
  - Tidak ada UI untuk karyawan minta cabut consent
  - Tidak ada proses yang dipicu saat consent dicabut
```

---

### M5: Karyawan Tidak Dinotifikasi saat HR Mengubah Datanya

**Pasal dilanggar:** Pasal 30 ayat (2) — Notifikasi ke subjek saat data diperbarui  
**Evidence:**
```
File: backend/app/Http/Controllers/Api/HcmEmployeeController.php
Method: update()
Masalah: Data karyawan diupdate langsung tanpa notifikasi ke karyawan
         Karyawan tidak tahu HR mengubah: salary, jabatan, status, bank account
         Tidak ada email/notifikasi otomatis ke karyawan
```

---

### M6: Foto Profil Karyawan Tidak Dihandling sebagai Data Biometrik Potensial

**Pasal dilanggar:** Pasal 4 ayat (2) — Foto profil bisa termasuk data biometrik  
**Evidence:**
```
File: backend/app/Models/EmployeeProfile.php
$fillable: 'profile_photo_path'
Masalah: Foto profil disimpan tanpa perlakuan khusus
         Tidak ada enkripsi storage untuk foto
         Tidak ada retention policy untuk foto
         Tidak ada consent khusus untuk penggunaan foto profil
```

---

### M7: DPO (Data Protection Officer) Belum Ditunjuk

**Pasal dilanggar:** Pasal 53 UU PDP — Pengendali wajib menunjuk DPO  
**Evidence:**
```
Hasil pencarian:
  - grep 'dpo' di config/ = 0 hasil
  - grep 'data_protection_officer' = 0 hasil
  - Privacy Policy tidak menyebut DPO atau kontak privasi
  - Tidak ada config 'dpo_email' atau 'privacy_contact'
  - Tidak ada halaman/alamat kontak khusus untuk hak subjek data
```

---

### M8: Sesi Login Tidak Memiliki Timeout yang Ketat

**Pasal dilanggar:** Pasal 35 — Kewajiban keamanan  
**Evidence:**
```
File: backend/config/session.php
'lifetime' => env('SESSION_LIFETIME', 120), // 2 jam default
Masalah: 2 jam sesi tanpa aktivitas = cukup lama untuk workstation yang ditinggal
         Tidak ada force re-auth untuk operasi sensitif (export data, hapus karyawan)
         Tidak ada session invalidation saat password berubah
```

---

## LOW — 4 Temuan

### L1: Terms & Conditions Masih Template Generic

**Evidence:**
```
File: backend/resources/views/misc/terms-condition.blade.php
Masalah: Konten masih menggunakan template generic
         Tidak ada klausa spesifik tentang pemrosesan data HCM
         Tidak ada klausa subprosesor (Xendit/Stripe/OpenAI)
```

---

### L2: Tidak Ada "Hak Data Saya" UI untuk Karyawan

**Evidence:**
```
Tidak ada halaman/section di portal karyawan untuk:
  - Lihat data apa saja yang disimpan tentang mereka
  - Download salinan data (data portability)
  - Ajukan permintaan koreksi/hapus
  - Lihat riwayat consent yang pernah diberikan
```

---

### L3: Privacy Policy Tidak Terhubung dari Form Manapun

**Evidence:**
```
File: backend/resources/views/public/landing.blade.php
File: backend/resources/views/auth/register.blade.php (jika ada)
Masalah: Tidak ada link "Kebijakan Privasi" di form onboarding
         Tidak ada link di footer halaman utama yang menuju Privacy Policy
         Route /privacy-policy mungkin ada tapi tidak di-link dari user journey utama
```

---

### L4: Email Payslip Tidak Terenkripsi (Plain Email)

**Evidence:**
```
File: backend/app/Mail/MonthlyPayslipMail.php
Masalah: Slip gaji dikirim via email biasa tanpa enkripsi end-to-end
         Email berisi data gaji = data keuangan sensitif
         Jika email server dikompromis, payslip dapat dibaca
         Pertimbangkan: password-protected PDF atau link download dengan auth
```

---

## Ringkasan Temuan

| ID | Kategori | Pasal UU PDP | File Utama | Severity |
|---|---|---|---|---|
| C1 | Consent onboarding | Pasal 20-21 | `landing.blade.php`, `PublicOnboardingController` | CRITICAL |
| C2 | Consent input karyawan | Pasal 20-21, 4(2) | `HcmEmployeeController`, `EmployeeProfile` | CRITICAL |
| C3 | Biometrik tanpa consent | Pasal 4(2)b, 21 | `AttendanceRecord`, `AttendanceController` | CRITICAL |
| C4 | GPS tanpa disclosure | Pasal 4, 20-21 | `AttendanceRecord` | CRITICAL |
| C5 | Data sensitif plaintext | Pasal 35 | `EmployeeProfile`, `EmployeeTaxProfile`, `EmployeeBenefit` | CRITICAL |
| C6 | Email verif dinonaktifkan | Pasal 35-36 | `User.php` line 5 | CRITICAL |
| H1 | Tidak bisa hapus data | Pasal 8, 43-44 | `HcmUserManagementController`, `User`, `EmployeeProfile` | HIGH |
| H2 | Zero breach notification | Pasal 46 | Seluruh codebase — tidak ada | HIGH |
| H3 | AI kirim data ke OpenAI | Pasal 48-51, 20 | `AiLlmService`, `HcmAiChatController` | HIGH |
| H4 | Xendit/Stripe transfer lintas negara | Pasal 48-51 | `XenditService`, `StripeService` | HIGH |
| H5 | Export tanpa audit trail | Pasal 31 | `HcmEmployeeController::exportEmployees()` | HIGH |
| H6 | Privacy Policy invalid | Pasal 22(4)c | `privacy-policy.blade.php` | HIGH |
| H7 | GDPR form non-functional | Pasal 20-21 | `settings/gdpr.blade.php` | HIGH |
| M1 | AuditLog tidak cover HCM ops | Pasal 31 | `AuditLog`, `SuperAdminDashboardController` | MEDIUM |
| M2 | AI chat log tanpa retensi | Pasal 26, 31 | `AiChatLog` | MEDIUM |
| M3 | Tidak ada data retention | Pasal 26(1) | Seluruh codebase | MEDIUM |
| M4 | Tidak ada withdraw consent | Pasal 9 | Tidak ada endpoint | MEDIUM |
| M5 | Karyawan tidak dinotif saat data berubah | Pasal 30(2) | `HcmEmployeeController::update()` | MEDIUM |
| M6 | Foto profil tidak dihandling khusus | Pasal 4(2) | `EmployeeProfile` | MEDIUM |
| M7 | DPO belum ditunjuk | Pasal 53 | Config + Privacy Policy | MEDIUM |
| M8 | Session timeout kurang ketat | Pasal 35 | `session.php` | MEDIUM |
| L1 | T&C template generic | - | `terms-condition.blade.php` | LOW |
| L2 | Tidak ada UI hak subjek | Pasal 5-13 | Portal karyawan | LOW |
| L3 | Privacy Policy tidak di-link | Pasal 22 | `landing.blade.php` | LOW |
| L4 | Payslip email tidak terenkripsi | Pasal 35 | `MonthlyPayslipMail` | LOW |
