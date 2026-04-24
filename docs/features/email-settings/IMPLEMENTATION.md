# Email Settings - Implementation Notes

## 1. Ruang Lingkup Runtime Saat Ini

### 1.1 Web routes

- `GET /email-settings` -> render `email-settings.blade.php`, middleware `hcm.web.global-admin`.
- `GET /email-template` -> render `email-template.blade.php`, middleware `hcm.web.global-admin`.

Implikasi:

- Surface UI sudah dibatasi platform-level global admin.
- Halaman ini belum memiliki endpoint POST/PUT khusus untuk persist konfigurasi email.

### 1.2 API route

- `GET /v1/hcm/email-settings/mailtrap-status` -> `HcmEmailSettingsController@mailtrapStatus`.
- `GET /v1/hcm/email-settings` -> fetch persisted profile aktif.
- `PUT /v1/hcm/email-settings` -> update persisted profile aktif.
- `POST /v1/hcm/email-settings/test-connection` -> uji koneksi SMTP/Mailtrap memakai payload sementara tanpa save.
- Hasil probe terakhir sekarang dipersist ke `settings` group `email` sebagai snapshot observability operasional.

Pipeline endpoint:

1. Guard global admin via `ensureGlobalHcmAdmin`.
2. Baca `services.mailtrap.api_token` dan `services.mailtrap.account_id`.
3. Jika credential tidak lengkap: return success dengan `connected=false` dan message konfigurasi belum lengkap.
4. Jika lengkap: call `MailtrapAccountApiService::listApiTokens()`.
5. Build payload observability (`visibleTokens`, `visibleTokenCount`, `tokenLast4`).
6. Jika exception: return success dengan `data.error` berisi pesan runtime.

## 2. Integrasi Konfigurasi Email

Konfigurasi email pengiriman aplikasi saat ini ada di:

- `config/mail.php` untuk mailer Laravel (`smtp`, `ses`, `postmark`, `resend`, dsb).
- `config/services.php` untuk credential provider (termasuk `mailtrap`).

Karakteristik sekarang:

- Konfigurasi bersumber dari environment deployment.
- Tidak ada bridging ke tabel settings untuk override runtime dari panel admin.
- Tidak ada mekanisme encrypt/decrypt secret dari UI settings.

## 3. Integrasi Pengiriman Email Operasional

Contoh komponen yang benar-benar mengirim email:

- `InvoiceService` (`Mail::to(...)->send(new InvoiceMailable(...))`).
- Job reminder/invoice dan notification password reset.

Makna arsitektural:

- Sistem sudah punya producer email yang berjalan.
- Namun panel `/email-settings` belum menjadi control-plane producer tersebut.
- Perubahan provider masih dilakukan lewat env/config deployment.

Konsekuensi dokumentasi:

- Surface admin bawaan template aktif tetap dimiliki oleh feature `email-settings`.
- Runtime delivery email tidak perlu dipecah menjadi folder feature baru selama belum ada produk email terpisah seperti campaign center atau tenant mail console.
- Untuk domain lintas channel, dokumentasi teknis detail observability/retry tetap boleh tinggal di feature notifikasi, dengan feature `email-settings` sebagai sumber kebenaran control-plane email.

## 4. Gap Teknis Utama

1. **UI wiring gap**
   - Form SMTP/PHP Mailer di halaman web belum terhubung ke endpoint runtime `GET/PUT/test-connection`.
2. **Template CRUD gap**
   - `/email-template` dan modal add/edit/delete masih statis.
3. **Last test metadata gap (partial closed)**
   - Hasil terakhir probe koneksi sudah dipersist sebagai snapshot operasional, tetapi belum punya halaman audit/ringkasan UI khusus.
4. **Email template runtime gap**
   - Endpoint template CRUD/preview belum ada.
5. **Mailer runtime resolver gap**
   - Producer email operasional belum membaca profile settings ini sebagai control-plane aktif.

## 5. Rekomendasi Implementasi Bertahap

### Phase A - Hardening baseline

- Putuskan policy akses endpoint `mailtrap-status` (global-only atau semua hcmAdmin) dan selaraskan web+API.
- Tambahkan dokumentasi API ke OpenAPI + feature API doc.
- Tambahkan test feature untuk role matrix endpoint status.

### Phase B - Provider config management

- Tambah endpoint settings email:
   - `GET /v1/hcm/email-settings` (done)
   - `PUT /v1/hcm/email-settings` (done)
   - `POST /v1/hcm/email-settings/test-connection` (done, ephemeral mode)
- Simpan credential terenkripsi (`encrypted:array`/custom cast + key rotation policy).
- Pisahkan field sensitif (masking pada response).

### Phase C - Template runtime

- Tambah resource `email_templates` (subject, body_html, placeholders, status, scope).
- Implement CRUD API + preview render + validation placeholder.
- Integrasikan producer email agar dapat menggunakan template yang dikelola dari panel.

### Phase D - Provider abstraction

- Tambah `EmailTransportManager` internal untuk mapping provider profile -> Laravel mailer transport.
- Dukung fallback/priority (misal failover `smtp -> log`) sesuai policy.
- Tambahkan delivery observability yang provider-agnostic.

## 6. Dampak Bisnis

Jika roadmap di atas dijalankan:

- Operasional bisa pindah provider tanpa intervensi deploy manual untuk perubahan rutin.
- SLA notifikasi lebih terkontrol lewat test connection + observability.
- Risiko human error perubahan env production menurun.
- Customer enterprise dapat menggunakan penyedia email pilihan (provider neutrality).
