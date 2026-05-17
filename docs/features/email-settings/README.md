# Email Settings

## Ringkasan

Fitur ini adalah area pengaturan kanal email di menu System Settings (`/email-settings`).
Secara bisnis, area ini menjaga visibilitas konfigurasi pengiriman email sistem, profile settings email yang disimpan di backend, dan kesehatan koneksi provider untuk kebutuhan operasional/admin.

Secara dokumentasi, ini paling aman diposisikan sebagai **satu feature package** dengan dua lapisan yang berbeda tetapi masih satu keluarga fitur:

- **Lapisan admin/configuration**: halaman aktif `/email-settings`.
- **Lapisan runtime delivery**: proses kirim email nyata yang dipakai job/notification lintas modul.

Jadi, **bukan dua fitur yang benar-benar terpisah**, tetapi juga **bukan satu layar tunggal**. Pola dokumentasinya:

- `docs/features/email-settings/` menjadi rumah utama untuk surface UI aktif, policy akses, konfigurasi provider, template, dan readiness arsitektur email.
- Runtime email delivery tetap dijelaskan di sini sebagai bagian dari flow end-to-end karena itu hasil bisnis dari settings ini.
- Jika runtime email menjadi bagian dari orkestrasi notifikasi lintas channel (in-app + email + observability), dokumen ini harus cross-link ke feature notifikasi, bukan menduplikasi semua detailnya.

Audit runtime saat ini menunjukkan bahwa fitur berada pada fase **baseline observability + settings API + web page runtime wiring**:

- Status koneksi Mailtrap tersedia via endpoint API terpisah.
- Profile settings email (`GET/PUT /v1/hcm/email-settings`) tersedia di backend dan secret dimasking pada response.
- Endpoint test connection untuk SMTP dan Mailtrap sudah tersedia secara temporary/ephemeral (tanpa auto-save credential).
- Snapshot hasil test koneksi terakhir sudah disimpan untuk kebutuhan operasional dasar, tetapi belum ditampilkan sebagai panel audit khusus di UI.
- Halaman web aktif `/email-settings` sekarang memuat profile email runtime, Mailtrap health, serta save/test connection ke endpoint API email settings.
- Compose runtime via API sudah bisa kirim email nyata dan log outbound akan muncul di folder Sent pada halaman `/email`, tetapi source of truth endpoint-nya berada di feature notifications.
- Inbound balasan email sekarang bisa masuk ke Inbox runtime via webhook `POST /webhooks/email-inbound` (token-protected) dengan fallback polling IMAP opsional.
- Pengiriman email bisnis aktif tetap berjalan dari konfigurasi environment Laravel (`config/mail.php`, `config/services.php`) dan service/job backend.

## Akses

- Akses halaman web:
  - `GET /email-settings` -> middleware `hcm.web.global-admin`.
- Akses API email settings:
  - `GET /v1/hcm/email-settings`
  - `PUT /v1/hcm/email-settings`
  - `GET /v1/hcm/email-settings/mailtrap-status`
  - `POST /v1/hcm/email-settings/test-connection`
  - Semua berada pada group `v1/hcm` + middleware `api.token`, `tenant.context`.
  - Guard controller menggunakan `ensureGlobalHcmAdmin()`.

Catatan penting akses:

- Halaman web dibatasi global admin.
- Endpoint API email settings juga dibatasi global admin, jadi policy web dan API sekarang selaras.

## UI Aktif

### `/email-settings`

- Halaman aktif sekarang menjadi **runtime control-plane baseline** untuk admin global.
- Menampilkan profile email aktif dari settings group `email`, ringkasan sender/transport runtime, status Mailtrap, dan referensi fallback ENV/config Laravel.
- Modal SMTP dan Mailtrap pada template sekarang aktif untuk membaca profile settings tersimpan, menjalankan test connection secara ephemeral, dan menyimpan profile via endpoint API email settings.

### `/email-template`

- Route halaman ini sudah dihapus dari surface aktif pada 2026-05-10.
- CRUD runtime email template belum tersedia.

## Flow Bisnis End-to-End

### Flow existing (yang benar-benar aktif)

1. Global admin membuka `/email-settings` untuk melihat provider aktif, sender runtime, dan health Mailtrap.
2. Halaman memuat profile settings email tersimpan lewat `GET /v1/hcm/email-settings` dan status Mailtrap lewat `GET /v1/hcm/email-settings/mailtrap-status`.
3. Admin dapat membuka modal SMTP atau Mailtrap untuk mengubah profile aktif tanpa edit env manual.
4. Admin dapat menjalankan `POST /v1/hcm/email-settings/test-connection` dari modal untuk probe ephemeral sebelum save.
5. Setelah save, backend menyimpan settings group `email` terenkripsi/masked sesuai kontrak runtime.
6. Pengiriman email operasional sistem tetap dieksekusi oleh job/notification backend memakai mailer Laravel yang membaca profile runtime aktif.
7. Compose manual pada halaman `/email` tetap memakai endpoint notifications, bukan group email-settings.

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
  - `Provider configuration management API` -> tersedia baseline.
  - `Provider configuration management UI` -> aktif baseline pada halaman web.
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
  - `App\Http\Controllers\Api\Settings\HcmEmailSettingsController`
  - `App\Services\MailtrapAccountApiService`
- Compose runtime `/email`:
  - `App\Http\Controllers\Api\Notifications\NotificationController`
- Konfigurasi mail Laravel:
  - `config/mail.php`
  - `config/services.php` (mailtrap section)
- Producer email aktif (contoh):
  - `App\Services\InvoiceService`
  - `App\Jobs\SendInvoiceEmailJob`
  - `App\Jobs\SendPaymentReminder`
  - `App\Notifications\PasswordResetLinkNotification`
- UI blade/template:
  - `resources/views/settings/email-settings.blade.php`

## Boundary Fitur

Supaya dokumentasi tidak rancu, pembagiannya sebaiknya seperti ini:

### Masuk feature `email-settings`

- Halaman aktif `/email-settings`.
- Policy akses web dan API untuk area settings email.
- Konfigurasi provider (Mailtrap, SMTP, provider lain ke depan).
- Health check, test connection, masking secret, audit perubahan setting.
- Readiness arsitektur untuk mendukung provider email platform mana pun.

### Tidak berdiri sebagai feature terpisah, tetapi menjadi runtime layer dari feature ini

- Pengiriman email nyata dari invoice, reminder, password reset, approval, dan event lain.
- Pemakaian mailer Laravel oleh service/job/notification backend.
- Dampak setting provider terhadap keberhasilan delivery email.

### Masuk feature lain sebagai domain owner, lalu cross-link ke sini

- Notifikasi lintas channel dan observability delivery global -> lihat `docs/features/notifications/README.md`.
- Billing invoice/reminder sebagai proses bisnis -> tetap documented di feature billing/subscription terkait, lalu refer ke feature email-settings untuk control-plane email.
- Compose manual halaman `/email` -> kontrak API utamanya ada di `docs/api/notifications-api.md`.

Aturan praktisnya:

- Jika yang dibahas adalah **panel admin email bawaan template aktif**, dokumentasi utama taruh di feature ini.
- Jika yang dibahas adalah **event notifikasi lintas channel**, dokumentasi utama taruh di feature notifikasi lalu refer ke feature ini untuk aspek provider/settings.
- Jika yang dibahas adalah **proses bisnis pemilik event** (misal invoice reminder), dokumentasi utama tetap di feature bisnisnya, lalu refer ke email settings sebagai kanal delivery.

## Kontrak API

Runtime API yang terkonfirmasi saat ini:

- `GET /v1/hcm/email-settings/mailtrap-status`
  - Auth: bearer token (`api.token`).
  - Guard: global HCM admin only.
  - Response success envelope:
    - `success: true`
    - `data.provider`
    - `data.accountId`
    - `data.credentialSource`
    - `data.tokenConfigured`
    - `data.tokenLast4`
    - `data.connected`
    - `data.visibleTokenCount`
    - `data.visibleTokens[]`
    - `data.error`
    - `data.mode`
- `GET /v1/hcm/email-settings`
  - Auth: bearer token (`api.token`).
  - Guard: global HCM admin only.
  - Mengembalikan profile aktif dengan secret masked (`passwordMasked`, `apiTokenMasked`).
- `PUT /v1/hcm/email-settings`
  - Auth: bearer token (`api.token`).
  - Guard: global HCM admin only.
  - Menyimpan profile aktif ke group `settings=email` dan mengembalikan metadata `updatedBy` + `updatedAt`.
- `POST /v1/hcm/email-settings/test-connection`
  - Auth: bearer token (`api.token`).
  - Guard: global HCM admin only.
  - Rate limit: `5 requests / minute`.
  - Payload temporary: tidak otomatis menulis ke tabel `settings`.
  - Snapshot hasil test terakhir ditulis ke settings metadata untuk observability dasar.
  - Mendukung `provider=smtp` dan `provider=mailtrap`.
  - Normalized error codes saat ini: `TIMEOUT`, `DNS_ERROR`, `CONNECTION_REFUSED`, `TLS_ERROR`, `AUTH_FAILED`, `CONNECTION_FAILED`, `CONFIGURATION_ERROR`.

Catatan:

- Halaman web `/email-settings` sekarang memakai endpoint save/test ini untuk baseline control-plane runtime.
- Belum ada endpoint CRUD untuk email templates.
- Compose email manual tetap memakai `POST /v1/hcm/notifications/send-email`.
- Endpoint runtime terdokumentasi di `docs/api/openapi.yaml` dan `docs/api/email-settings-api.md`.

## Existing Vs Target

### Existing

- Kesehatan koneksi Mailtrap dapat dipantau dari UI.
- Pengiriman email aplikasi masih tergantung env deploy (`MAIL_MAILER`, `MAIL_HOST`, dsb) pada surface web aktif.
- Halaman `/email-settings` sekarang aktif untuk load/save/test profile settings runtime.
- Route `/email-template` sudah dihapus dari surface aktif.

### Target

- Pengaturan provider email multi-platform dari UI (tanpa edit env manual untuk operasi harian).
- Abstraction provider yang memungkinkan berpindah provider tanpa ubah logic bisnis.
- CRUD template email terstruktur (versioning, preview, variable validation, active/inactive).
- Auditability penuh: siapa ubah setting, kapan, dan dampaknya ke delivery.

## Status

Module version: `0.2 (audit-corrected baseline)`
Status: `In Progress (settings API + connection probe active, web page baseline active)`
Last updated: `2026-05-17`

Lihat backlog pengerjaan granular pada `tracker.md`.
