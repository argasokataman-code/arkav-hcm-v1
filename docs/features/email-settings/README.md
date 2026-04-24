# Email Settings & Templates

## Ringkasan

Fitur ini adalah area pengaturan kanal email di menu System Settings (`/email-settings`, `/email-template`).
Secara bisnis, halaman ini ditujukan untuk memastikan sistem bisa mengirim email transaksional (contoh: invoice, reminder, reset password) dan memberi visibilitas kesehatan koneksi provider.

Secara dokumentasi, ini paling aman diposisikan sebagai **satu feature package** dengan dua lapisan yang berbeda tetapi masih satu keluarga fitur:

- **Lapisan admin/configuration**: halaman template aktif `/email-settings` dan `/email-template`.
- **Lapisan runtime delivery**: proses kirim email nyata yang dipakai job/notification lintas modul.

Jadi, **bukan dua fitur yang benar-benar terpisah**, tetapi juga **bukan satu layar tunggal**. Pola dokumentasinya:

- `docs/features/email-settings/` menjadi rumah utama untuk surface UI aktif, policy akses, konfigurasi provider, template, dan readiness arsitektur email.
- Runtime email delivery tetap dijelaskan di sini sebagai bagian dari flow end-to-end karena itu hasil bisnis dari settings ini.
- Jika runtime email menjadi bagian dari orkestrasi notifikasi lintas channel (in-app + email + observability), dokumen ini harus cross-link ke feature notifikasi, bukan menduplikasi semua detailnya.

Audit runtime saat ini menunjukkan bahwa fitur masih berada pada fase **baseline observability + UI template**:

- Status koneksi Mailtrap sudah real-time via endpoint API.
- Endpoint test connection untuk SMTP dan Mailtrap sudah tersedia secara temporary/ephemeral (tanpa auto-save credential).
- Snapshot hasil test koneksi terakhir sudah disimpan untuk kebutuhan operasional dasar, tetapi belum ditampilkan sebagai panel audit khusus di UI.
- Form SMTP/PHP Mailer dan Email Template masih bersifat template UI, belum tersambung ke persistence atau API save runtime.
- Compose runtime via API sudah bisa kirim email nyata dan log outbound akan muncul di folder Sent pada halaman `/email`.
- Inbound balasan email sekarang bisa masuk ke Inbox runtime via webhook `POST /webhooks/email-inbound` (token-protected) dengan fallback polling IMAP opsional.
- Pengiriman email bisnis aktif tetap berjalan dari konfigurasi environment Laravel (`config/mail.php`, `config/services.php`) dan service/job backend.

## Akses

- Akses halaman web:
  - `GET /email-settings` -> middleware `hcm.web.global-admin`.
  - `GET /email-template` -> middleware `hcm.web.global-admin`.
- Akses API health Mailtrap:
  - `GET /v1/hcm/email-settings/mailtrap-status` (group `v1/hcm` + middleware `api.token`, `tenant.context`).
  - Guard controller menggunakan `EnsuresHcmAdmin`.

Catatan penting akses:

- Halaman web dibatasi global admin.
- Endpoint API saat ini lolos untuk `isHcmAdmin()` (tenant admin/global admin sesuai konteks), bukan khusus global admin.
- Ini menimbulkan mismatch policy web vs API yang perlu diputuskan eksplisit (apakah endpoint memang untuk semua HCM admin atau harus global-only).

## UI Aktif

### `/email-settings`

- Card **Mailtrap API Status** sudah runtime:
  - Memanggil API `GET /v1/hcm/email-settings/mailtrap-status`.
  - Menampilkan status connected/not connected + pesan error.
  - Tersedia tombol refresh.
- Card **PHP Mailer** dan **SMTP**:
  - Menampilkan modal input.
  - Belum ada binding ke API/backend save.
  - Submit form masih mengarah ke route halaman yang sama (placeholder UI template).

### `/email-template`

- Menampilkan katalog template email statis (contoh: Welcome Email, Password Reset, Leave Request).
- Modal add/edit/delete template masih template UI.
- Belum ada model/database/controller API khusus email template.

## Flow Bisnis End-to-End

### Flow existing (yang benar-benar aktif)

1. Global admin membuka `/email-settings` untuk mengecek status Mailtrap.
2. UI mengambil token auth dari storage/meta, lalu memanggil endpoint status.
3. Backend membaca konfigurasi `MAILTRAP_API_TOKEN` + `MAILTRAP_ACCOUNT_ID` dari environment.
4. Jika konfigurasi lengkap, backend query Mailtrap Account API untuk daftar API token visible.
5. UI menampilkan hasil koneksi (connected atau error message).
6. Pengiriman email operasional sistem (invoice/reminder/reset password) tetap dieksekusi oleh job/notification backend memakai mailer Laravel yang aktif.

### Flow target (provider-agnostic, belum aktif)

1. Admin memilih provider (SMTP generic, Mailtrap, SES, Postmark, Resend, dsb).
2. Admin mengisi credential via UI settings.
3. Sistem menyimpan credential terenkripsi per scope (tenant/platform) dengan audit trail.
4. Sistem menjalankan test connection terkontrol sebelum aktivasi.
5. Template email dikelola lewat CRUD + preview + variable catalog.
6. Semua producer email menggunakan abstraction service internal, bukan coupling langsung ke satu provider.

## Lifecycle Dan Keputusan Bisnis

- Current lifecycle:
  - `UI visibility` -> tersedia.
  - `Connection observability` -> tersedia untuk Mailtrap.
  - `Provider configuration management` -> belum tersedia.
  - `Template management runtime` -> belum tersedia.
  - `Outbound compose + sent mailbox logging` -> tersedia baseline (hanya outbound, belum inbox sync).
  - `Inbound reply ingestion (webhook + IMAP fallback)` -> tersedia baseline untuk mailbox runtime `/email`.
- Keputusan yang sudah implicit di runtime:
  - Source of truth pengiriman email produksi masih environment/config deploy, bukan setting panel.
  - Mailtrap dipakai terutama sebagai verifikasi koneksi/integrasi, bukan satu-satunya transport wajib.
- Keputusan yang perlu difinalkan:
  - Scope konfigurasi: global per platform vs tenant-specific.
  - Kebijakan rotasi secret dan audit perubahan credential.
  - Batasan provider yang didukung di fase awal.

## Integrasi

- Backend API status:
  - `App\Http\Controllers\Api\HcmEmailSettingsController`
  - `App\Services\MailtrapAccountApiService`
- Konfigurasi mail Laravel:
  - `config/mail.php`
  - `config/services.php` (mailtrap section)
- Producer email aktif (contoh):
  - `App\Services\InvoiceService`
  - `App\Jobs\SendInvoiceEmailJob`
  - `App\Jobs\SendPaymentReminder`
  - `App\Notifications\PasswordResetLinkNotification`
- UI blade/template:
  - `resources/views/email-settings.blade.php`
  - `resources/views/email-template.blade.php`
  - `resources/views/components/modal-popup.blade.php` (modal SMTP/PHP Mailer/template)

## Boundary Fitur

Supaya dokumentasi tidak rancu, pembagiannya sebaiknya seperti ini:

### Masuk feature `email-settings`

- Halaman aktif `/email-settings` dan `/email-template`.
- Policy akses web dan API untuk area settings email.
- Konfigurasi provider (Mailtrap, SMTP, provider lain ke depan).
- Template email administratif.
- Health check, test connection, masking secret, audit perubahan setting.
- Readiness arsitektur untuk mendukung provider email platform mana pun.

### Tidak berdiri sebagai feature terpisah, tetapi menjadi runtime layer dari feature ini

- Pengiriman email nyata dari invoice, reminder, password reset, approval, dan event lain.
- Pemakaian mailer Laravel oleh service/job/notification backend.
- Dampak setting provider terhadap keberhasilan delivery email.

### Masuk feature lain sebagai domain owner, lalu cross-link ke sini

- Notifikasi lintas channel dan observability delivery global -> lihat `docs/features/notifications/README.md`.
- Billing invoice/reminder sebagai proses bisnis -> tetap documented di feature billing/subscription terkait, lalu refer ke feature email-settings untuk control-plane email.

Aturan praktisnya:

- Jika yang dibahas adalah **panel admin email bawaan template aktif**, dokumentasi utama taruh di feature ini.
- Jika yang dibahas adalah **event notifikasi lintas channel**, dokumentasi utama taruh di feature notifikasi lalu refer ke feature ini untuk aspek provider/settings.
- Jika yang dibahas adalah **proses bisnis pemilik event** (misal invoice reminder), dokumentasi utama tetap di feature bisnisnya, lalu refer ke email settings sebagai kanal delivery.

## Kontrak API

Runtime API yang terkonfirmasi saat ini:

- `GET /v1/hcm/email-settings/mailtrap-status`
  - Auth: bearer token (`api.token`).
  - Guard: `EnsuresHcmAdmin`.
  - Response success envelope:
    - `success: true`
    - `data.provider`
    - `data.accountId`
    - `data.tokenConfigured`
    - `data.tokenLast4`
    - `data.connected`
    - `data.visibleTokenCount`
    - `data.visibleTokens[]`
    - `data.error`
- `POST /v1/hcm/email-settings/test-connection`
  - Auth: bearer token (`api.token`).
  - Guard: global HCM admin only.
  - Payload temporary: tidak otomatis menulis ke tabel `settings`.
  - Snapshot hasil test terakhir ditulis ke settings metadata untuk observability dasar.
  - Mendukung `provider=smtp` dan `provider=mailtrap`.
  - Normalized error codes saat ini: `TIMEOUT`, `DNS_ERROR`, `CONNECTION_REFUSED`, `TLS_ERROR`, `AUTH_FAILED`, `CONNECTION_FAILED`, `CONFIGURATION_ERROR`.

Catatan:

- Endpoint save settings profile sudah ada, tetapi wiring UI form belum selesai.
- Belum ada endpoint CRUD untuk email templates.
- Endpoint runtime sudah terdokumentasi di `docs/api/openapi.yaml` dan `docs/api/email-settings-api.md`.

## Existing Vs Target

### Existing

- Kesehatan koneksi Mailtrap dapat dipantau dari UI.
- Pengiriman email aplikasi masih tergantung env deploy (`MAIL_MAILER`, `MAIL_HOST`, dsb).
- UI SMTP/PHP Mailer/Template belum menyimpan perubahan ke backend.

### Target

- Pengaturan provider email multi-platform dari UI (tanpa edit env manual untuk operasi harian).
- Abstraction provider yang memungkinkan berpindah provider tanpa ubah logic bisnis.
- CRUD template email terstruktur (versioning, preview, variable validation, active/inactive).
- Auditability penuh: siapa ubah setting, kapan, dan dampaknya ke delivery.

## Status

Module version: `0.1 (audit baseline)`
Status: `In Progress (settings runtime + connection probe baseline active)`
Last updated: `2026-04-24`

Lihat backlog pengerjaan granular pada `tracker.md`.
