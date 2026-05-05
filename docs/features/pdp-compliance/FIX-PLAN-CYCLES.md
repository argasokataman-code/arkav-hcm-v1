# Fix Plan — Siklus Remediasi UU PDP

**Total siklus:** 6  
**Urutan:** Kerjakan berurutan; Cycle 1 dan 2 adalah WAJIB SEGERA karena risiko hukum tertinggi.

---

## Cycle 1 — Quick Wins: Consent Onboarding, Email Verifikasi, Privacy Policy, Export Log

**Tujuan:** Tutup celah CRITICAL yang paling mudah dan HIGH yang paling visible  
**Temuan tercakup:** C1, C6, H5, H6, L3  
**Estimasi:** 1-2 minggu  
**Priority:** 🔴 WAJIB SEGERA

### Exit Criteria Cycle 1

- [ ] **C1a** — Form onboarding `#onboardingModal` memiliki checkbox consent dengan teks: _"Saya menyetujui [Kebijakan Privasi] dan [Syarat & Ketentuan] ARCAV HCM. Saya memahami data saya akan digunakan untuk keperluan layanan HR."_
- [ ] **C1b** — Field `consent_accepted` (boolean) divalidasi `required|accepted` di `PublicOnboardingController::store()`
- [ ] **C1c** — Field `consent_timestamp` (datetime) disimpan di tabel `companies` atau `company_onboarding_consents`
- [ ] **C1d** — Link Privacy Policy dan T&C ada di form (href ke `/privacy-policy` dan `/terms-condition`)
- [ ] **C6a** — `MustVerifyEmail` di-uncomment di `User.php`
- [ ] **C6b** — Route email verifikasi aktif dan berfungsi
- [ ] **C6c** — Alur registrasi: setelah daftar → harus verifikasi email dulu sebelum bisa login
- [ ] **H5a** — Export karyawan (CSV/Excel/PDF) mencatat audit log: `user_uuid`, `company_id`, `action='export_employees'`, `ip_address`, `exported_at`, `format`
- [ ] **H5b** — Metode `recordExportAuditLog()` dipanggil dari semua export method di `HcmEmployeeController`
- [ ] **H6a** — Nama brand di `privacy-policy.blade.php` diubah dari "SmratHR" → "ARCAV HCM"
- [ ] **H6b** — Privacy Policy dibuat ulang dalam Bahasa Indonesia
- [ ] **H6c** — Konten Privacy Policy mencakup: jenis data, tujuan pemrosesan, pihak ketiga (Xendit/Stripe/OpenAI), hak subjek, kontak DPO sementara, periode retensi
- [ ] **L3a** — Link `/privacy-policy` ada di footer landing page dan di dalam form onboarding

**Test yang harus PASS:**
```bash
php artisan test --filter=OnboardingConsentTest
php artisan test --filter=AuthEmailVerificationTest
php artisan test --filter=EmployeeExportAuditTest
```

---

## Cycle 2 — Consent Karyawan, Biometrik/GPS, Notifikasi Perubahan Data

**Tujuan:** Tutup celah consent di alur HCM inti  
**Temuan tercakup:** C2, C3, C4, M5  
**Estimasi:** 2-3 minggu  
**Priority:** 🔴 WAJIB SEGERA

### Exit Criteria Cycle 2

- [ ] **C2a** — Saat HR tambah karyawan, sistem menampilkan disclosure: "Data karyawan dikumpulkan berdasarkan hubungan kerja (Pasal 20 huruf c UU PDP). Data yang dikumpulkan mencakup: [list field sensitif]."
- [ ] **C2b** — Field `data_disclosure_acknowledged` (boolean) di form tambah karyawan; HR wajib centang sebelum submit
- [ ] **C2c** — Timestamp acknowledgment disimpan di `employee_profiles.data_disclosed_at` atau tabel consent terpisah
- [ ] **C3a** — Pertama kali karyawan akses fitur absensi → muncul modal: "Fitur absensi menggunakan foto wajah (biometrik) dan lokasi GPS. Data ini digunakan hanya untuk verifikasi kehadiran."
- [ ] **C3b** — Record consent biometrik tersimpan: `employee_biometric_consents(employee_uuid, consent_given_at, ip_address)`
- [ ] **C3c** — Jika karyawan belum consent biometrik → tidak bisa check-in dengan selfie
- [ ] **C4a** — Modal consent absensi (dari C3a) juga menyebut GPS secara eksplisit
- [ ] **C4b** — Karyawan dapat melihat bahwa GPS mereka direkam di riwayat absensi mereka sendiri
- [ ] **M5a** — Saat HR update profil karyawan → sistem kirim email notifikasi ke karyawan: "Profil Anda telah diperbarui oleh HR pada [tanggal]. Field yang berubah: [list perubahan]."
- [ ] **M5b** — Notifikasi menggunakan event `EmployeeProfileUpdated` + `SendProfileUpdateNotification` listener

**Test yang harus PASS:**
```bash
php artisan test --filter=EmployeeConsentTest
php artisan test --filter=BiometricConsentTest
php artisan test --filter=AttendanceGpsConsentTest
php artisan test --filter=EmployeeProfileUpdateNotificationTest
```

---

## Cycle 3 — SoftDeletes dan Right to Erasure

**Tujuan:** Implementasi kemampuan hapus data subjek sesuai Pasal 8 + 43-44  
**Temuan tercakup:** H1  
**Estimasi:** 2-3 minggu  
**Priority:** 🟠 HIGH

### Exit Criteria Cycle 3

- [ ] **H1a** — `SoftDeletes` trait ditambahkan ke model: `User`, `EmployeeProfile`, `EmployeeTaxProfile`, `EmployeeBenefit`, `AttendanceRecord`, `AiChatLog`
- [ ] **H1b** — Migrasi: tambah kolom `deleted_at` ke tabel: `users`, `employee_profiles`, `employee_tax_profiles`, `employee_benefits`, `attendance_records`, `ai_chat_logs`
- [ ] **H1c** — Endpoint baru: `POST /v1/hcm/me/request-erasure` untuk karyawan mengajukan permintaan hapus
- [ ] **H1d** — Endpoint baru: `POST /v1/hcm/employees/{uuid}/process-erasure` untuk admin approve/reject
- [ ] **H1e** — Saat erasure diapprove: `User::delete()`, `EmployeeProfile::delete()`, `AttendanceRecord::where(...)->delete()`, `AiChatLog::where(...)->delete()` (soft delete semua)
- [ ] **H1f** — 30 hari setelah soft delete → scheduled job `ProcessPendingErasures` jalankan hard delete
- [ ] **H1g** — Email konfirmasi dikirim ke subjek data saat erasure diproses
- [ ] **H1h** — `deleteUser()` di `HcmUserManagementController` diupdate: menghapus User + semua data terkait, bukan hanya CompanyUser

**Test yang harus PASS:**
```bash
php artisan test --filter=DataErasureTest
php artisan test --filter=SoftDeleteTest
php artisan test --filter=ErasureRequestWorkflowTest
```

**Migrasi yang dibutuhkan:**
```
database/migrations/xxxx_add_soft_deletes_to_users.php
database/migrations/xxxx_add_soft_deletes_to_employee_profiles.php
database/migrations/xxxx_add_soft_deletes_to_attendance_records.php
database/migrations/xxxx_add_soft_deletes_to_ai_chat_logs.php
database/migrations/xxxx_create_erasure_requests_table.php
```

---

## Cycle 4 — Enkripsi Data Sensitif at-Rest, AI Chat Disclosure

**Tujuan:** Enkripsi field PII sensitif, tambah disclosure untuk fitur AI  
**Temuan tercakup:** C5, H3  
**Estimasi:** 3-4 minggu  
**Priority:** 🟠 HIGH

### Exit Criteria Cycle 4

- [ ] **C5a** — Di `EmployeeProfile.php`, tambah cast `'encrypted'` untuk: `nik`, `bank_account_no`, `bank_ifsc_code`, `bank_branch`
- [ ] **C5b** — Di `EmployeeTaxProfile.php`, tambah cast `'encrypted'` untuk: `npwp`
- [ ] **C5c** — Di `EmployeeBenefit.php`, tambah cast `'encrypted'` untuk: `bpjs_kesehatan_no`, `bpjs_ketenagakerjaan_no`
- [ ] **C5d** — `APP_KEY` di `.env` minimal 256-bit (sudah ada di Laravel default)
- [ ] **C5e** — Migrasi data: field yang sudah ada di DB dienkripsi (script one-time migration)
- [ ] **C5f** — Semua test yang membaca field ini tetap PASS setelah enkripsi
- [ ] **H3a** — Di halaman/UI AI Chat, tambahkan notice: "Fitur AI ini menggunakan layanan AI pihak ketiga. Pertanyaan Anda dan data terkait akan diproses oleh server AI eksternal."
- [ ] **H3b** — Sebelum karyawan pertama kali pakai AI Chat → muncul modal consent: "Saya memahami bahwa data saya akan dikirim ke layanan AI pihak ketiga untuk diproses."
- [ ] **H3c** — Record consent AI tersimpan: `employee_ai_consents(employee_uuid, consent_given_at)`
- [ ] **H3d** — `AiLlmService::chat()` mengecek apakah user sudah consent sebelum kirim data
- [ ] **H3e** — `AiChatLog` ditambah `deleted_at` (dari Cycle 3) dan kebijakan retensi 1 tahun

**Test yang harus PASS:**
```bash
php artisan test --filter=EmployeeProfileEncryptionTest
php artisan test --filter=AiChatConsentTest
php artisan test --filter=AiChatLogRetentionTest
```

**Catatan penting untuk C5:**
```php
// Di model, ubah dari:
protected $fillable = ['nik', 'bank_account_no', ...];

// Menjadi tambahan di $casts:
protected $casts = [
    'nik'             => 'encrypted',
    'bank_account_no' => 'encrypted',
    'bank_ifsc_code'  => 'encrypted',
    'bank_branch'     => 'encrypted',
];
// Laravel akan auto-encrypt saat set, auto-decrypt saat get
// Data di DB akan berupa string encrypted, bukan plaintext
```

**Catatan penting: data migration untuk C5e**
```php
// Command artisan one-time:
// php artisan pdp:encrypt-existing-sensitive-data
// Baca semua record, re-save dengan cast 'encrypted' aktif
// Ini WAJIB dijalankan setelah deploy, sebelum aplikasi dipakai
```

---

## Cycle 5 — Breach Notification, Transfer Pihak Ketiga, Retensi Data

**Tujuan:** Sistem notifikasi insiden, disclosure transfer, kebijakan retensi otomatis  
**Temuan tercakup:** H2, H4, M2, M3  
**Estimasi:** 3-4 minggu  
**Priority:** 🟡 MEDIUM

### Exit Criteria Cycle 5

- [ ] **H2a** — Tabel `data_breach_incidents` dibuat: `id, title, description, affected_data_types, affected_subjects_count, detected_at, reported_to_bssn_at, notifications_sent_at, created_by, status`
- [ ] **H2b** — Endpoint admin: `POST /v1/admin/security-incidents` untuk catat insiden
- [ ] **H2c** — Job `SendBreachNotificationToSubjects` — kirim email ke semua user terdampak
- [ ] **H2d** — UI admin untuk manage incident lifecycle (detected → notified → resolved)
- [ ] **H2e** — Template email notifikasi breach sesuai Pasal 46: berisi apa yang bocor, langkah mitigasi, kontak DPO
- [ ] **H4a** — Di checkout/billing page, tambah disclosure: "Informasi pembayaran Anda diproses oleh [Xendit/Stripe], layanan pembayaran pihak ketiga yang beroperasi di [Singapura/Amerika Serikat]."
- [ ] **H4b** — Di Privacy Policy (dari Cycle 1), tambah seksi "Transfer Data Internasional" yang menyebut Xendit, Stripe, OpenAI
- [ ] **H4c** — Basis hukum transfer: "Transfer ke Xendit/Stripe dilakukan berdasarkan pelaksanaan kontrak (Pasal 49 huruf b UU PDP)"
- [ ] **M2a** — `AiChatLog` ditambah config retensi: 1 tahun (12 bulan)
- [ ] **M2b** — Scheduled command `PurgeExpiredAiChatLogs` berjalan setiap hari, hapus log `created_at < now() - 1 year`
- [ ] **M3a** — `AttendanceRecord` ditambah config retensi: 5 tahun (sesuai ketentuan ketenagakerjaan)
- [ ] **M3b** — Scheduled command `PurgeExpiredAttendanceRecords` berjalan setiap bulan
- [ ] **M3c** — Data pribadi dari tenant yang tidak aktif (subscription expired + 90 hari grace) dijadwalkan hapus

**Test yang harus PASS:**
```bash
php artisan test --filter=BreachNotificationTest
php artisan test --filter=DataRetentionJobTest
php artisan test --filter=AiChatLogPurgeTest
```

---

## Cycle 6 — Portal Hak Subjek, Withdraw Consent, DPIA, DPO

**Tujuan:** Self-service rights portal, dokumentasi kepatuhan penuh  
**Temuan tercakup:** M1, M4, M7, L1, L2, L4, H7 (cookie consent)  
**Estimasi:** 4-6 minggu  
**Priority:** 🟡 MEDIUM

### Exit Criteria Cycle 6

- [ ] **M4a** — Endpoint: `POST /v1/hcm/me/withdraw-consent` — karyawan dapat cabut consent
- [ ] **M4b** — Saat consent dicabut: fitur yang bergantung consent dinonaktifkan (AI Chat, biometrik) sampai consent diberikan kembali
- [ ] **M4c** — Email konfirmasi withdraw consent
- [ ] **M1a** — `AuditLog::recordAuditLog()` atau helper baru dipanggil dari semua operasi kritis HCM: create/update/delete employee, payroll run, approve/reject cuti, data export
- [ ] **M1b** — Format log: `entity_type`, `entity_uuid`, `action`, `performed_by_uuid`, `company_id`, `ip_address`, `timestamp`, `changed_fields` (diff sebelum/sesudah)
- [ ] **M7a** — Tambah config: `config/pdp.php` berisi `dpo_name`, `dpo_email`, `privacy_contact_url`
- [ ] **M7b** — Privacy Policy menampilkan kontak DPO dari config
- [ ] **L2a** — Halaman "Data Saya" (`/hcm/me/data-privacy`) untuk karyawan: lihat data apa yang disimpan, riwayat consent, riwayat perubahan profil
- [ ] **L2b** — Tombol "Unduh Salinan Data Saya" (data portability) — export data karyawan sendiri ke JSON/PDF
- [ ] **L2c** — Tombol "Ajukan Permintaan Hapus Data"
- [ ] **L1a** — T&C diupdate dengan klausa: pemrosesan data HCM, subprosesor (Xendit/Stripe/OpenAI), hak subjek, cara mengajukan keluhan
- [ ] **L4a** — Email payslip menggunakan PDF terproteksi password ATAU link download dengan autentikasi (tidak attach plaintext payslip)
- [ ] **H7a** — Cookie consent banner di semua halaman publik
- [ ] **H7b** — Form GDPR di settings diubah ke method POST dengan handler yang benar
- [ ] **H7c** — Preference cookie tersimpan di DB: `user_cookie_consents(user_uuid, analytics, marketing, essential, updated_at)`
- [ ] **DPIA** — Dokumen Penilaian Dampak Pelindungan Data (DPIA) dibuat untuk: fitur biometrik, AI Chat, payroll processing
- [ ] **M8a** — Session lifetime dikurangi ke 60 menit atau 90 menit untuk operasi normal
- [ ] **M8b** — Force re-auth (password konfirmasi) untuk operasi sensitif: export data, hapus karyawan

**Test yang harus PASS:**
```bash
php artisan test --filter=WithdrawConsentTest
php artisan test --filter=HcmAuditLogTest
php artisan test --filter=DataPrivacyPortalTest
php artisan test --filter=CookieConsentTest
```

---

## Checklist Akhir Kepatuhan (Setelah Semua Siklus)

Sebelum klaim "ARCAV HCM sudah comply UU PDP", semua item ini harus terpenuhi:

### Consent & Persetujuan
- [ ] Semua form pengumpulan data punya consent checkbox aktif
- [ ] Consent timestamp tersimpan di DB
- [ ] Mekanisme withdraw consent berfungsi
- [ ] Consent biometrik (selfie) terpisah dan eksplisit

### Informasi ke Subjek
- [ ] Privacy Policy dalam Bahasa Indonesia, up-to-date, brand benar
- [ ] Privacy Policy mencakup semua pihak ketiga (Xendit, Stripe, OpenAI)
- [ ] DPO / kontak privasi terdaftar dan aktif
- [ ] Link ke Privacy Policy ada di semua form utama

### Keamanan Data
- [ ] NIK, NPWP, nomor rekening bank tersimpan terenkripsi
- [ ] Email verifikasi aktif untuk semua akun baru
- [ ] Session timeout sesuai
- [ ] Export data memerlukan re-auth

### Hak Subjek Data
- [ ] Right to access: karyawan bisa lihat datanya sendiri
- [ ] Right to correction: karyawan bisa minta koreksi
- [ ] Right to erasure: endpoint + workflow hapus data berfungsi
- [ ] Right to data portability: bisa download salinan data
- [ ] Right to withdraw consent: endpoint aktif

### Transfer ke Pihak Ketiga
- [ ] Disclosure Xendit/Stripe di halaman payment
- [ ] Disclosure OpenAI di fitur AI Chat
- [ ] Semua pihak ketiga tercantum di Privacy Policy

### Audit & Retensi
- [ ] Semua operasi HCM tercatat di audit log
- [ ] Export data tercatat siapa/kapan/format
- [ ] Data retention policy terprogram (AI Chat: 1 tahun, Attendance: 5 tahun)
- [ ] Model-model kritis menggunakan SoftDeletes

### Insiden
- [ ] Sistem notifikasi breach ada dan teruji
- [ ] Template email breach sesuai Pasal 46
- [ ] Prosedur internal breach response terdokumentasi

### Dokumentasi Internal
- [ ] DPIA untuk biometrik, AI, payroll
- [ ] DPO ditunjuk dengan kontak resmi
- [ ] T&C diupdate dengan klausa data
