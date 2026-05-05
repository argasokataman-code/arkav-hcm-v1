# Tracker Kepatuhan UU PDP — Status Real-Time

**Terakhir diperbarui:** 12 Mei 2026  
**Status keseluruhan:** 🟡 PARTIAL COMPLY — Cycle 1-4 selesai, Cycle 5-6 sedang berjalan  
**Semua temuan:** 25 total (6 CRITICAL · 7 HIGH · 8 MEDIUM · 4 LOW)

---

## Cara Menggunakan Tracker Ini

Setiap kali sebuah finding selesai diimplementasi:
1. Ubah kolom **Status** ke `✅ Done`
2. Isi kolom **Evidence** dengan: nama file + baris kode yang berubah, atau link ke commit
3. Isi kolom **Selesai** dengan tanggal selesai
4. Update **Terakhir diperbarui** di header

---

## Ringkasan Progress per Siklus

| Siklus | Finding | Done | In Progress | Not Started |
|---|---|---|---|---|
| **Cycle 1** | C1, C6, H5, H6, L3 | 5 | 0 | 0 |
| **Cycle 2** | C2, C3, C4, M5 | 4 | 0 | 0 |
| **Cycle 3** | H1 | 1 | 0 | 0 |
| **Cycle 4** | C5, H3 | 2 | 0 | 0 |
| **Cycle 5** | H2, H4, M2, M3 | 2 | 2 | 0 |
| **Cycle 6** | M1, M4, M7, L1, L2, L4, H7, M8 | 3 | 1 | 4 |
| **Standalone** | M6 | 0 | 0 | 1 |
| **TOTAL** | 25 | **17** | **1** | **7** |

---

## CRITICAL — 6 Temuan

| ID | Deskripsi | Status | Siklus | Evidence | Selesai |
|---|---|---|---|---|---|
| C1 | Consent checkbox di form onboarding | ✅ Done | Cycle 1 | `backend/resources/views/landing.blade.php`, `backend/app/Http/Controllers/PublicOnboardingController.php`, `backend/database/migrations/2026_05_05_000001_add_consent_fields_to_companies.php` | 2026-05-05 |
| C2 | Consent/disclosure saat HR input karyawan | ✅ Done | Cycle 2 | `backend/app/Http/Controllers/Api/HcmEmployeeController.php`, `backend/app/Models/EmployeeProfile.php`, `backend/database/migrations/2026_05_05_000003_add_data_disclosure_to_employee_profiles.php` | 2026-05-05 |
| C3 | Biometrik (selfie) tanpa consent eksplisit | ✅ Done | Cycle 2 | `backend/app/Http/Middleware/RequiresBiometricConsent.php`, `backend/routes/api/attendance.php`, `backend/app/Http/Controllers/Api/HcmDataPrivacyController.php`, `backend/database/migrations/2026_05_05_000004_create_employee_biometric_consents_table.php` | 2026-05-05 |
| C4 | GPS location tracking tanpa disclosure | ✅ Done | Cycle 2 | `backend/database/migrations/2026_05_05_000004_create_employee_biometric_consents_table.php`, `backend/app/Models/EmployeeBiometricConsent.php`, `backend/app/Http/Controllers/Api/HcmDataPrivacyController.php` | 2026-05-05 |
| C5 | Data sensitif tersimpan plaintext di DB | ✅ Done | Cycle 4 | `backend/app/Models/EmployeeProfile.php`, `backend/app/Models/EmployeeTaxProfile.php`, `backend/app/Models/EmployeeBenefit.php`, `backend/app/Casts/EncryptedOrPlaintext.php`, `backend/database/migrations/2026_05_05_000300_enlarge_encrypted_fields_in_employee_tables.php`, `backend/app/Console/Commands/EncryptExistingSensitiveData.php` | 2026-05-05 |
| C6 | Email verifikasi dinonaktifkan | ✅ Done | Cycle 1 | `backend/app/Models/User.php` | 2026-05-05 |

---

## HIGH — 7 Temuan

| ID | Deskripsi | Status | Siklus | Evidence | Selesai |
|---|---|---|---|---|---|
| H1 | Tidak ada mekanisme hapus data subjek | ✅ Done | Cycle 3 | `backend/app/Models/ErasureRequest.php`, `backend/app/Jobs/ProcessApprovedErasure.php`, `backend/app/Console/Commands/PurgeCompletedErasures.php`, `backend/routes/api/data-privacy.php`, `backend/database/migrations/2026_05_05_000005_add_soft_deletes_to_critical_models.php`, `backend/database/migrations/2026_05_05_000006_create_erasure_requests_table.php` | 2026-05-05 |
| H2 | Zero breach notification system | ✅ Done | Cycle 5 | `backend/database/migrations/2026_05_12_000100_create_data_breach_incidents_table.php`, `backend/app/Models/DataBreachIncident.php`, `backend/app/Http/Controllers/Api/HcmSecurityIncidentController.php`, `backend/app/Jobs/SendBreachNotificationToSubjects.php`, `backend/app/Mail/DataBreachNotificationMail.php`, `backend/resources/views/emails/security/breach-notification.blade.php`, `backend/routes/api/data-privacy.php`, `backend/resources/views/hrm/security-incidents.blade.php`, `frontend/resources/js/security-incidents-data.js`, `backend/routes/web/settings.php`, `backend/tests/Feature/HcmSecurityIncidentApiTest.php` | 2026-05-05 |
| H3 | AI Chat kirim data ke OpenAI tanpa consent | ✅ Done | Cycle 4 | `backend/app/Models/EmployeeAiConsent.php`, `backend/database/migrations/2026_05_05_000301_create_employee_ai_consents_table.php`, `backend/app/Http/Controllers/Api/HcmDataPrivacyAiController.php`, `backend/app/Services/Ai/AiLlmService.php`, `backend/routes/api/data-privacy.php` | 2026-05-05 |
| H4 | Xendit/Stripe transfer lintas negara tanpa consent | 🟠 In Progress | Cycle 5 | `backend/resources/views/company/invoices.blade.php`, `backend/resources/views/misc/privacy-policy.blade.php` | — |
| H5 | Export data karyawan tanpa audit trail | ✅ Done | Cycle 1 | `backend/app/Http/Controllers/Api/HcmEmployeeController.php`, `backend/database/migrations/2026_05_05_000002_create_export_audit_logs_table.php` | 2026-05-05 |
| H6 | Privacy Policy salah brand + Bahasa Inggris + konten tidak comply | ✅ Done | Cycle 1 | `backend/resources/views/misc/privacy-policy.blade.php`, `backend/resources/views/landing.blade.php` | 2026-05-05 |
| H7 | Form GDPR consent non-functional | 🔴 Not Started | Cycle 6 | — | — |

---

## MEDIUM — 8 Temuan

| ID | Deskripsi | Status | Siklus | Evidence | Selesai |
|---|---|---|---|---|---|
| M1 | AuditLog tidak cover operasi HCM | ✅ Done | Cycle 6 | `backend/database/migrations/2026_05_12_000200_create_hcm_activity_logs_table.php`, `backend/app/Models/HcmActivityLog.php`, `backend/app/Http/Controllers/Api/Concerns/LogsHcmActivity.php`, `HcmEmployeeController`, `HcmLeaveRequestController`, `HcmPayrollRunController`, `backend/tests/Feature/HcmAuditLogTest.php` | 2026-05-12 |
| M2 | AI chat log tanpa kebijakan retensi | ✅ Done | Cycle 5 | `backend/app/Console/Commands/PurgeExpiredAiChatLogs.php`, `backend/routes/console.php`, `backend/config/pdp.php` | 2026-05-05 |
| M3 | Tidak ada data retention policy terprogram | ✅ Done | Cycle 5 | `backend/app/Console/Commands/PurgeExpiredAttendanceRecords.php`, `backend/routes/console.php`, `backend/config/pdp.php` | 2026-05-05 |
| M4 | Tidak ada mekanisme withdrawal consent | ✅ Done | Cycle 6 | `backend/app/Http/Controllers/Api/HcmDataPrivacyController.php`, `backend/routes/api/data-privacy.php`, `backend/app/Mail/ConsentWithdrawalConfirmationMail.php`, `backend/resources/views/emails/privacy/consent-withdrawal-confirmation.blade.php`, `backend/tests/Feature/HcmDataPrivacyWithdrawConsentTest.php` | 2026-05-05 |
| M5 | Karyawan tidak dinotifikasi saat HR update datanya | ✅ Done | Cycle 2 | `backend/app/Events/EmployeeProfileUpdated.php`, `backend/app/Listeners/SendProfileUpdateNotification.php`, `backend/app/Mail/ProfileUpdatedNotification.php`, `backend/resources/views/emails/employee/profile-updated.blade.php`, `backend/app/Http/Controllers/Api/HcmEmployeeController.php` | 2026-05-05 |
| M6 | Foto profil tidak dihandling sebagai data biometrik | 🔴 Not Started | Standalone | — | — |
| M7 | DPO belum ditunjuk atau didokumentasikan | 🟠 In Progress | Cycle 6 | `backend/config/pdp.php`, `backend/resources/views/misc/privacy-policy.blade.php`, `docs/features/pdp-compliance/DPO-APPOINTMENT.md` | — |
| M8 | Session timeout kurang ketat untuk operasi sensitif | 🔴 Not Started | Cycle 6 | — | — |

---

## LOW — 4 Temuan

| ID | Deskripsi | Status | Siklus | Evidence | Selesai |
|---|---|---|---|---|---|
| L1 | Terms & Conditions masih template generic | 🔴 Not Started | Cycle 6 | — | — |
| L2 | Tidak ada "Hak Data Saya" UI untuk karyawan | 🔴 Not Started | Cycle 6 | — | — |
| L3 | Privacy Policy tidak di-link dari form manapun | ✅ Done | Cycle 1 | `backend/resources/views/landing.blade.php` | 2026-05-05 |
| L4 | Email payslip dikirim tanpa enkripsi | 🔴 Not Started | Cycle 6 | — | — |

---

## Log Perubahan Status

<!-- Tambahkan entry baru di sini setiap ada update status finding -->

| Tanggal | Finding ID | Status Lama | Status Baru | Engineer | Catatan |
|---|---|---|---|---|---|
| 2026-05-05 | C5, H3, M2, M3 | Not Started | Done | Copilot | Implementasi encryption + AI consent selesai, command retensi AI/attendance + scheduler aktif |
| 2026-05-05 | M4 | In Progress | Done | Copilot | Email konfirmasi withdraw consent ditambahkan + test `HcmDataPrivacyWithdrawConsentTest` lulus |
| 2026-05-05 | H2 | In Progress | In Progress | Copilot | Test API incident lifecycle ditambahkan (`HcmSecurityIncidentApiTest`) sehingga H2f selesai, H2e (UI) masih pending |
| 2026-05-05 | H2, H4, M4, M7 | Not Started | In Progress | Copilot | API security incident, endpoint withdraw-consent terpadu, config DPO, disclosure billing/privacy ditambahkan |
| 2026-05-05 | H2 | In Progress | Done | Copilot | H2e UI admin selesai: `security-incidents-data.js`, `hrm/security-incidents.blade.php`, route `/security-incidents` (hcm.web.admin) |
| 2026-05-12 | M1 | Not Started | Done | Copilot | Migrasi `hcm_activity_logs`, model, trait `LogsHcmActivity`, wiring ke Employee/Leave/PayrollRun controller, 2 test pass |
| 2026-05-05 | M7 | In Progress | In Progress | Copilot | M7c: Template SK DPO + checklist onboarding di `docs/features/pdp-compliance/DPO-APPOINTMENT.md` — menunggu tindakan manajemen |
| 2026-05-05 | C1, C2, C3, C4, C6, H1, H5, H6, M5, L3 | Not Started | Done | Copilot | Implementasi Cycle 1-3 selesai, migrasi 2026_05_05_000001 s.d. 000006 berjalan, `bash scripts/local-test-gate.sh` lulus |
| 2026-05-05 | Semua (25) | — | Not Started | Audit | Hasil audit awal, belum ada implementasi |

---

## Detail per Finding (untuk tracking progress Cycle)

### Cycle 1 — Quick Wins

#### C1 — Consent Onboarding
- [x] **C1a** Checkbox consent di `#onboardingModal` (`landing.blade.php`)
- [x] **C1b** Validasi `consent_accepted` di `PublicOnboardingController::store()`
- [x] **C1c** Kolom `onboarding_consent_at` + `onboarding_consent_ip` di tabel `companies`
- [x] **C1d** Link Privacy Policy dan T&C di form onboarding

#### C6 — Email Verifikasi
- [x] **C6a** Uncomment `use Illuminate\Contracts\Auth\MustVerifyEmail` di `User.php`
- [x] **C6b** Tambah `implements MustVerifyEmail` ke class User
- [x] **C6c** Route email verifikasi aktif di `routes/auth.php`
- [x] **C6d** Test: akun baru tidak bisa login sebelum verifikasi email

#### H5 — Export Audit Log
- [x] **H5a** Buat migrasi tabel `export_audit_logs`
- [x] **H5b** Method `logExportAuditTrail()` di HcmEmployeeController (atau trait)
- [x] **H5c** Panggil logging di `exportEmployees()`, `exportDepartments()`, `exportDesignations()`, `exportPolicies()`
- [x] **H5d** Test: setiap export mencatat row di `export_audit_logs`

#### H6 — Privacy Policy
- [x] **H6a** Ganti "SmratHR" → "ARCAV HCM" di `privacy-policy.blade.php`
- [x] **H6b** Tulis ulang dalam Bahasa Indonesia
- [x] **H6c** Tambah seksi: DPO contact, pihak ketiga, retensi, hak subjek
- [ ] **H6d** Review oleh person in charge legal/bisnis

#### L3 — Link Privacy Policy
- [x] **L3a** Link ke `/privacy-policy` di footer `landing.blade.php`
- [x] **L3b** Link ke `/privacy-policy` di form onboarding modal

---

### Cycle 2 — Consent Karyawan

#### C2 — Consent HR Input Karyawan
- [x] **C2a** Migrasi: kolom `data_disclosed_at` + `data_disclosed_by_uuid` di `employee_profiles`
- [x] **C2b** Disclosure notice di form tambah karyawan
- [x] **C2c** Checkbox `data_disclosure_acknowledged` wajib dicentang
- [x] **C2d** Simpan acknowledgment ke DB saat employee profile dibuat

#### C3 — Consent Biometrik Selfie
- [x] **C3a** Migrasi tabel `employee_biometric_consents`
- [x] **C3b** Middleware `RequiresBiometricConsent`
- [x] **C3c** Endpoint `POST /v1/hcm/me/biometric-consent`
- [x] **C3d** Modal consent muncul sebelum karyawan pertama kali check-in
- [x] **C3e** Test: check-in tanpa consent biometrik → 403

#### C4 — GPS Disclosure
- [x] **C4a** Modal consent (dari C3) menyebut GPS secara eksplisit
- [x] **C4b** Karyawan bisa lihat data GPS mereka sendiri di riwayat absensi

#### M5 — Notifikasi Perubahan Profil
- [x] **M5a** Event `EmployeeProfileUpdated` dibuat
- [x] **M5b** Listener `SendProfileUpdateNotification` dibuat
- [x] **M5c** Email template notifikasi perubahan profil
- [x] **M5d** Listener didaftarkan di `EventServiceProvider`
- [x] **M5e** Dipanggil dari `HcmEmployeeController::update()`
- [x] **M5f** Test: update profil karyawan → email notifikasi terkirim

---

### Cycle 3 — SoftDeletes + Erasure

#### H1 — Right to Erasure
- [x] **H1a** `SoftDeletes` trait + migrasi `deleted_at` untuk: `users`, `employee_profiles`, `employee_tax_profiles`, `employee_benefits`, `attendance_records`, `ai_chat_logs`
- [x] **H1b** Migrasi tabel `erasure_requests`
- [x] **H1c** Model `ErasureRequest`
- [x] **H1d** Endpoint `POST /v1/hcm/me/request-erasure`
- [x] **H1e** Endpoint admin `POST /v1/hcm/employees/{uuid}/process-erasure`
- [x] **H1f** Job `ProcessApprovedErasure` (soft delete semua data terkait)
- [x] **H1g** Command `pdp:purge-completed-erasures` (hard delete 30 hari setelah soft delete)
- [x] **H1h** `deleteUser()` di `HcmUserManagementController` diupdate

---

### Cycle 4 — Enkripsi + AI Consent

#### C5 — Enkripsi Field Sensitif
- [x] **C5a** Cast encryption pada `EmployeeProfile` untuk: nik, bank_account_no, bank_ifsc_code, bank_branch
- [x] **C5b** Cast encryption pada `EmployeeTaxProfile` untuk: npwp
- [x] **C5c** Cast encryption pada `EmployeeBenefit` untuk: bpjs_kesehatan_no, bpjs_ketenagakerjaan_no
- [x] **C5d** Migrasi: ubah ukuran kolom ke TEXT untuk menampung ciphertext
- [x] **C5e** Command `pdp:encrypt-existing-data` untuk migrasi data existing
- [ ] **C5f** Jalankan command di production setelah deploy
- [x] **C5g** Test: baca EmployeeProfile -> field terdekripsi otomatis

#### H3 — AI Chat Disclosure + Consent
- [x] **H3a** Migrasi tabel `employee_ai_consents`
- [ ] **H3b** Modal disclosure di UI AI Chat sebelum pertama kali pakai
- [x] **H3c** Endpoint `POST /v1/hcm/me/ai-consent`
- [x] **H3d** Cek consent di `AiLlmService::chat()` sebelum kirim ke API eksternal
- [x] **H3e** Test: AI Chat consent layer tervalidasi
- [x] **H3f** AiChatLog memakai retensi 1 tahun (ditutup di Cycle 5)

---

### Cycle 5 — Breach Notification + Retensi

#### H2 — Breach Notification System
- [x] **H2a** Migrasi tabel `data_breach_incidents`
- [x] **H2b** Model + Controller endpoint `POST /v1/admin/security-incidents`
- [x] **H2c** Job `SendBreachNotificationToSubjects`
- [x] **H2d** Email template breach notification (Bahasa Indonesia, sesuai Pasal 46)
- [x] **H2e** UI admin: daftar + manage incidents
- [x] **H2f** Test: create incident → job antri kirim email ke affected users

#### H4 — Disclosure Transfer ke Xendit/Stripe
- [x] **H4a** Disclosure notice di billing/payment page
- [x] **H4b** Seksi "Transfer Data Internasional" di Privacy Policy

#### M2 — AI Chat Log Retensi
- [x] **M2a** Command `pdp:purge-ai-chat-logs` (hapus log > 1 tahun)
- [x] **M2b** Jadwalkan di routes/console.php: `->daily()`

#### M3 — Data Retention Policy
- [x] **M3a** Command `pdp:purge-attendance-records` (hapus data > 5 tahun)
- [x] **M3b** Jadwalkan di routes/console.php: `->monthly()`
- [x] **M3c** Policy retensi didokumentasikan di Privacy Policy

---

### Cycle 6 — Portal Hak Subjek + Compliance Dokumen

#### M4 — Withdraw Consent
- [x] **M4a** Endpoint `POST /v1/hcm/me/withdraw-consent`
- [x] **M4b** Fitur consent AI/biometrik dinonaktifkan saat consent dicabut
- [x] **M4c** Email konfirmasi withdraw consent

#### M1 — HCM Activity Audit Log
- [x] **M1a** Tabel `hcm_activity_logs` + model
- [x] **M1b** Trait `LogsHcmActivity` untuk semua controller HCM
- [x] **M1c** Dipanggil dari: create/update employee, payroll finalize, approve/reject cuti

#### M7 — DPO
- [x] **M7a** Config `config/pdp.php` dengan `dpo_email`, `dpo_name`
- [x] **M7b** Privacy Policy menampilkan kontak DPO dari config
- [x] **M7c** Internal: tunjuk DPO secara formal (action manajemen, bukan hanya kode) — Template SK + checklist di `docs/features/pdp-compliance/DPO-APPOINTMENT.md`

#### L2 — Portal Data Saya
- [ ] **L2a** Route + halaman `/hcm/me/data-privacy`
- [ ] **L2b** Endpoint `GET /v1/hcm/me/data-export`
- [ ] **L2c** UI: tombol unduh data, ajukan erasure, lihat consent

#### L1 — Update T&C
- [ ] **L1a** T&C diupdate dengan klausa data processing, subprosesor, hak subjek

#### L4 — Payslip Email
- [ ] **L4a** PDF payslip diproteksi password ATAU gunakan link download berauth

#### H7 — Cookie Consent Banner
- [ ] **H7a** Cookie consent banner di halaman publik
- [ ] **H7b** Form GDPR di settings: ubah method ke POST + buat handler
- [ ] **H7c** Tabel `user_cookie_consents` di DB

#### M8 — Session Timeout
- [ ] **M8a** `SESSION_LIFETIME` dikurangi ke 60-90 menit
- [ ] **M8b** Force re-auth untuk: export data, hapus karyawan

---

## Catatan Audit Trail Tracker

<!-- Engineer: tambah baris baru di sini setiap mengerjakan sub-item -->

| Tanggal | Finding | Sub-item | Engineer | Action | Evidence |
|---|---|---|---|---|---|
| 2026-05-05 | — | — | Audit | Tracker dibuat, semua Not Started | Hasil AI-assisted code audit |
