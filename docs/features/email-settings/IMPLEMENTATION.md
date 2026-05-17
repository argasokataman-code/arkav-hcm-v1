# Email Settings - Implementation Notes

## 1. Ruang Lingkup Runtime Saat Ini

### 1.1 Web routes

- `GET /email-settings` -> render `email-settings.blade.php`, middleware `hcm.web.global-admin`.

Implikasi:

- Surface UI sudah dibatasi platform-level global admin.
- Surface web aktif sekarang sudah me-wire submit save/test ke API settings memakai modal SMTP dan Mailtrap yang ada di template.
- Route `/email-template` sudah dihapus dari surface aktif pada 2026-05-10.

### 1.2 API route

- `GET /v1/hcm/email-settings/mailtrap-status` -> `HcmEmailSettingsController@mailtrapStatus`.
- `GET /v1/hcm/email-settings` -> fetch persisted profile aktif.
- `PUT /v1/hcm/email-settings` -> update persisted profile aktif.
- `POST /v1/hcm/email-settings/test-connection` -> uji koneksi SMTP/Mailtrap memakai payload sementara tanpa save.
- Endpoint `test-connection` sekarang di-throttle `5 requests / minute` untuk membatasi brute-force probe dan spam koneksi.
- Semua endpoint di atas dibatasi ke global HCM admin via `ensureGlobalHcmAdmin()`.
- Response probe sekarang tidak lagi memantulkan username SMTP mentah; hanya versi masked yang disimpan pada `details.usernameMasked`.
- Fallback exception Mailtrap pada endpoint status juga disanitasi ke pesan generik agar tidak membocorkan raw upstream message.
- Snapshot hasil probe di `settings` sekarang ikut difilter ulang pada service layer untuk mencegah drift sensitif bila payload detail probe berubah di masa depan.
- Update profile mempertahankan secret existing bila field secret tidak dikirim ulang pada request save profile.
- Hasil probe terakhir sekarang dipersist ke `settings` group `email` sebagai snapshot observability operasional.

Pipeline endpoint:

1. Guard global admin via `ensureGlobalHcmAdmin`.
2. Baca `services.mailtrap.api_token` dan `services.mailtrap.account_id`.
3. Jika credential tidak lengkap: return success dengan `connected=false` dan message konfigurasi belum lengkap.
4. Jika lengkap: call `MailtrapAccountApiService::listApiTokens()`.
5. Build payload observability (`visibleTokens`, `visibleTokenCount`, `tokenLast4`, `credentialSource`, `mode`).
6. Jika exception: return success dengan `data.error` berisi pesan runtime.

## 2. Integrasi Konfigurasi Email

Konfigurasi email pengiriman aplikasi saat ini ada di:

- `config/mail.php` untuk mailer Laravel (`smtp`, `ses`, `postmark`, `resend`, dsb).
- `config/services.php` untuk credential provider (termasuk `mailtrap`).

Karakteristik sekarang:

- Konfigurasi bersumber dari environment deployment.
- Backend sudah memiliki bridging ke tabel settings group `email` untuk profile settings API.
- Halaman web aktif sekarang memakai bridging tersebut sebagai baseline control-plane UI.
- Mekanisme encrypt/decrypt secret sudah ada di service layer untuk endpoint profile settings.

## 3. Integrasi Pengiriman Email Operasional

Contoh komponen yang benar-benar mengirim email:

- `InvoiceService` (`Mail::to(...)->send(new InvoiceMailable(...))`).
- Job reminder/invoice dan notification password reset.

Makna arsitektural:

- Sistem sudah punya producer email yang berjalan.
- Panel `/email-settings` sekarang menjadi baseline control-plane settings/probe, walau deliverability real tetap bergantung pada provider/DNS eksternal.
- Perubahan provider masih dilakukan lewat env/config deployment.
- Compose manual halaman `/email` memakai endpoint notifications, bukan group email-settings.

Konsekuensi dokumentasi:

- Surface admin bawaan template aktif tetap dimiliki oleh feature `email-settings`.
- Runtime delivery email tidak perlu dipecah menjadi folder feature baru selama belum ada produk email terpisah seperti campaign center atau tenant mail console.
- Untuk domain lintas channel, dokumentasi teknis detail observability/retry tetap boleh tinggal di feature notifikasi, dengan feature `email-settings` sebagai sumber kebenaran control-plane email.

## 4. Gap Teknis Utama

1. **Frontend test coverage gap**
   - Wiring halaman web aktif sudah terhubung ke endpoint runtime `GET/PUT/test-connection`, tetapi coverage frontend/Vitest belum ditambahkan.
2. **Template CRUD gap**
   - Runtime email template belum ada, dan route `/email-template` sudah dihapus dari surface aktif.
3. **Last test metadata gap (partial closed)**
   - Hasil terakhir probe koneksi sudah dipersist sebagai snapshot operasional, tetapi belum punya halaman audit/ringkasan UI khusus.
4. **Email template runtime gap**
   - Endpoint template CRUD/preview belum ada.
5. **Mailer runtime resolver gap**
   - Producer email operasional belum membaca profile settings ini sebagai control-plane aktif.
6. **Documentation parity gap (closed 2026-05-17)**
   - OpenAPI, feature API doc, README, tracker, dan permission matrix sempat drift dari runtime aktif; telah diselaraskan kembali.

## 5. Rekomendasi Implementasi Bertahap

### Phase A - Hardening baseline

- Pertahankan policy global-only untuk web + API email settings.
- Jaga sinkronisasi dokumentasi API ke OpenAPI + feature API doc.
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
