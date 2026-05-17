# Email Settings Tracker

## Snapshot

- Date: 2026-05-17
- Status: In Progress (docs parity restored; settings API active; web page baseline active)
- Owner: Engineering (Backend + Frontend)

## Audit Correction 2026-05-17

- Source of truth runtime saat ini:
	- Web aktif `GET /email-settings` sekarang memuat profile runtime, Mailtrap health, dan modal save/test connection berbasis API.
	- Route `/email-template` sudah dihapus dari surface aktif.
	- API email settings yang aktif hanya `GET /v1/hcm/email-settings`, `PUT /v1/hcm/email-settings`, `GET /v1/hcm/email-settings/mailtrap-status`, dan `POST /v1/hcm/email-settings/test-connection`.
	- Compose manual halaman `/email` memakai `POST /v1/hcm/notifications/send-email`, bukan group `email-settings`.
- Hardening yang sudah ditutup pada audit correction ini:
	- `mailtrap-status` sekarang melaporkan `credentialSource` / `mode` dengan benar (`settings|env`).
	- `test-connection` sekarang di-throttle `5 requests / minute`.
	- Probe SMTP tidak lagi memantulkan username mentah di response/snapshot; hanya nilai masked.
	- Fallback raw exception Mailtrap pada endpoint status disanitasi ke error generik.
	- Service layer sekarang memfilter ulang `email_last_test_details` agar key sensitif tidak pernah tersimpan mentah.
	- Save profile mempertahankan secret existing jika field secret di request memang tidak dikirim.
	- Ghost modal Blade untuk route `/email-template` yang sudah dihapus ikut dibersihkan dari partial global.
- Catatan historis:
	- Beberapa item lama di tracker ini merekam eksperimen wiring UI/modal dan compose path yang tidak lagi menjadi runtime aktif.
	- Gunakan section ini, route Laravel, dan dokumen API sebagai sumber kebenaran saat audit/fixing lanjutan.

## Current Capability Check

1. Runtime Mailtrap status API: available.
2. Email settings web page (`/email-settings`): available as runtime control-plane baseline.
3. SMTP/PHP Mailer credential persistence from active UI: available for baseline save/test flow.
4. Email template CRUD runtime: not available, and `/email-template` route is removed from active surface.
5. Provider-agnostic email control-plane from admin panel: not available.
6. Compose send API runtime: available via `POST /v1/hcm/notifications/send-email`.
7. Sent mailbox runtime list on `/email` (outbound log source): available (outbound only).
8. Inbound webhook runtime (`POST /webhooks/email-inbound`): available (token-protected + idempotent).
9. Polling fallback IMAP (`php artisan email:poll-imap-inbox`): available (opsional, konfigurasi-driven).
10. Outbound delivery status webhook (`POST /webhooks/email-delivery-status`): available (token-protected + delivery UUID correlation).

## Evidence (Code Surface)

- `backend/app/Http/Controllers/Api/Settings/HcmEmailSettingsController.php`
- `backend/app/Services/MailtrapAccountApiService.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/config/services.php`
- `backend/config/mail.php`
- `backend/app/Console/Commands/EmailSendTestCommand.php`
- `backend/tests/Unit/EmailSettingsServiceTest.php`
- `backend/resources/views/settings/email-settings.blade.php`
- `backend/routes/api.php`
- `backend/routes/web.php`
- `backend/tests/Feature/HcmEmailComposeApiTest.php`
- `backend/tests/Feature/EmailComposeWebTest.php`
- `backend/resources/views/email.blade.php`
- `frontend/resources/js/email.js`
- `backend/routes/api/notifications.php`
- `backend/app/Http/Controllers/Api/Notifications/NotificationController.php`
- `backend/app/Http/Controllers/Api/EmailInboundWebhookController.php`
- `backend/app/Http/Controllers/Api/EmailDeliveryStatusWebhookController.php`
- `backend/app/Console/Commands/PollEmailInboxImapCommand.php`
- `backend/tests/Feature/EmailInboundWebhookTest.php`
- `backend/tests/Feature/EmailDeliveryStatusWebhookTest.php`

## Gap Register

1. Coverage frontend/Vitest untuk wiring `/email-settings` masih perlu ditambahkan.
2. Runtime email template belum ada dan route `/email-template` sudah dihapus dari surface aktif.
3. Compose manual memakai feature notifications; jangan menaruh kontraknya lagi di group `email-settings`.
4. Provider abstraction/failover masih belum ada.
5. Hardening security untuk test-connection dan secret handling masih perlu dilanjutkan.

## Single-Track Master Todo

Status legend:

- TODO: belum dikerjakan.
- IN PROGRESS: sedang dikerjakan.
- DONE: selesai dan sudah ada evidence.

Prinsip eksekusi:

- Kerjakan **1 item per 1 commit kerja** bila memungkinkan.
- Jangan lompat ke wiring UI sebelum kontrak API dan persistence untuk slice itu jelas.
- Setiap item selesai wajib punya evidence: route/controller/test/UI verification yang relevan.

Active pointer:

- Current execution start point: Item 56 (active).
- Recommended order: strictly ascending, kecuali ada blocker teknis yang memaksa swap.

### A. Hardening Dasar Yang Sudah Ada

1. DONE - Source of truth scope fitur email diputuskan platform-global only.
2. DONE - Policy final endpoint `mailtrap-status` diputuskan global-admin only.
3. DONE - Guard web route dan API route diselaraskan ke global-admin policy.
4. DONE - Regression test unauthorized access ke `mailtrap-status` ditambahkan.
5. DONE - Regression test authorized global-admin access ke `mailtrap-status` ditambahkan.
6. DONE - Ditutup sebagai N/A karena policy final diputuskan platform-global only (bukan tenant-aware).
7. DONE - OpenAPI disinkronkan untuk endpoint `GET /v1/hcm/email-settings/mailtrap-status`.
8. DONE - `docs/api/email-settings-api.md` ditambahkan untuk kontrak feature email.
9. DONE - `scripts/check-api-docs-sync.sh` sudah dijalankan (output: no changed files).

### B. Model Data Dan Persistence Settings

10. DONE - Model data canonical baseline ditetapkan pada key settings group `email` (provider, sender identity, smtp profile, mailtrap profile).
11. DONE - Diputuskan reuse tabel `settings` (group `email`) untuk baseline persistence.
12. DONE - Field minimum provider SMTP ditetapkan dan tervalidasi di endpoint update (`provider`, `fromAddress`, `fromName`, `smtp.host`, `smtp.port`, `smtp.username`, `smtp.password`, `smtp.encryption`).
13. DONE - Field baseline provider API-based untuk Mailtrap ditetapkan (`mailtrap.accountId`, `mailtrap.apiToken`).
14. DONE - Masking response secret diterapkan (`passwordMasked`, `apiTokenMasked`) untuk endpoint profile settings.
15. DONE - Strategi enkripsi at rest ditetapkan: prefix `enc::` + `Crypt::encryptString`, dengan fallback baca plaintext legacy.
16. DONE - Migration seed baseline key settings email ditambahkan.
17. DONE - Helper read/write secret terenkripsi diimplementasikan dalam `EmailSettingsService` (`writeSecret`, `readSecret`).
18. DONE - Service layer `EmailSettingsService` sudah dipakai controller untuk read/write profile.
19. DONE - Unit test encryption/decryption profile email ditambahkan (`EmailSettingsServiceTest`).
20. DONE - Unit test masking secret pada response profile ditambahkan (`EmailSettingsServiceTest`).

### C. API Settings Runtime

21. DONE - Endpoint `GET /v1/hcm/email-settings` ditambahkan untuk fetch current profile.
22. DONE - Endpoint `PUT /v1/hcm/email-settings` ditambahkan untuk simpan/update profile.
23. DONE - Validasi request per provider sudah diterapkan (`smtp` vs `mailtrap`).
24. DONE - Payload profile disatukan via helper transformer internal (`profilePayload()`).
25. DONE - Metadata update (`updatedBy`, `updatedAt`) dipersist ke settings (`email_last_updated_*`) dan tetap dikembalikan di response.
26. DONE - Happy path fetch current profile sudah tercakup di unit test controller.
27. DONE - Update profile SMTP sudah tercakup di unit test controller.
28. DONE - Update profile provider API-based (Mailtrap) sudah tercakup di unit test controller.
29. DONE - Invalid payload settings email sudah tercakup di unit test controller.
30. DONE - Forbidden access non-admin sudah tercakup di unit test controller.

### D. Test Connection / Health Check Yang Benar

31. DONE - Kontrak endpoint `POST /v1/hcm/email-settings/test-connection` ditetapkan dan didokumentasikan di OpenAPI + feature API doc.
32. DONE - Diputuskan test-connection boleh memakai payload sementara tanpa menyimpan credential ke persistence.
33. DONE - SMTP transport probe aman dan timeout-bounded diimplementasikan via Symfony SMTP transport `start()/stop()` tanpa kirim email sungguhan.
34. DONE - Provider API probe untuk Mailtrap diimplementasikan via `MailtrapAccountApiService::testConnection()`.
35. DONE - Error normalization ditambahkan untuk DNS/auth/timeout/TLS/connection/configuration failure.
36. DONE - Endpoint `test-connection` sekarang menyimpan snapshot hasil test terakhir ke settings metadata (`email_last_test_*`).
37. DONE - Regression test successful SMTP connection probe ditambahkan pada controller/service layer.
38. DONE - Regression test failed SMTP credential probe ditambahkan pada controller/service layer.
39. DONE - Regression test timeout/network-style failure probe ditambahkan pada controller/service layer.
40. DONE - UI state loading/success/error untuk test-connection sudah aktif baseline pada modal SMTP dan Mailtrap.

### E. UI Wiring Halaman `/email-settings`

41. DONE - Inline script `email-settings.blade.php` sudah dipindah ke modul JS (`email-settings-data.js`).
42. DONE - Card Mailtrap status sudah terikat ke contract API final `GET /v1/hcm/email-settings/mailtrap-status`.
43. DONE - Form PHP Mailer placeholder sudah diganti menjadi form runtime baseline yang membaca profile aktif.
44. DONE - Form SMTP placeholder sudah diganti menjadi form runtime baseline yang membaca profile aktif.
45. DONE - Selector provider aktif baseline sudah tersedia via provider switch.
46. TODO - Tambahkan field dynamic per provider (SMTP vs API provider).
47. DONE - Masking secret credential existing sudah ditampilkan di UI (`passwordMasked` / `apiTokenMasked`).
48. DONE - Tombol `Test Connection` sudah aktif pada modal SMTP dan Mailtrap.
49. DONE - Guard unsaved changes baseline sudah aktif via `beforeunload`.
50. DONE - Disabled/loading state baseline saat save sudah aktif di shell + tombol submit modal.
51. DONE - Success/error/info feedback konsisten sudah ditambahkan via toast + alert fallback.
52. DONE - Empty-state baseline sudah aktif melalui warning saat profile/provider belum tersimpan.
53. DONE - Frontend wiring test load current settings sudah ditambahkan (`email-settings.wiring.test.js`).
54. DONE - Frontend wiring test save settings success path sudah ditambahkan (`email-settings.wiring.test.js`).
55. DONE - Frontend wiring test validation/error path sudah ditambahkan (`email-settings.wiring.test.js`).

### F. Provider Abstraction Internal

56. TODO - Definisikan interface internal `EmailTransportManager` / `EmailProviderResolver`.
57. TODO - Implement adapter SMTP generic.
58. TODO - Implement adapter Mailtrap.
59. TODO - Siapkan adapter placeholder untuk SES/Postmark/Resend agar extensible.
60. TODO - Tambahkan failover policy internal jika provider utama gagal.
61. DONE - Config resolver baseline sudah membaca profile aktif (`provider=smtp`) lalu apply ke Laravel mailer runtime via `AppServiceProvider`.
62. TODO - Putuskan apakah perubahan settings butuh cache bust / config refresh strategy.
63. DONE - Unit test resolver provider aktif + fallback `provider=mailtrap` ke default SMTP Mailtrap (`live.smtp.mailtrap.io`, username `api`, password token) ditambahkan.
64. TODO - Tambahkan unit test fallback/failover behavior bila didukung.

### G. Email Template Runtime

65. TODO - Definisikan model data `email_templates`.
66. TODO - Tentukan kolom minimum: key, title, subject, body_html, body_text, placeholders, is_active, scope.
67. TODO - Buat migration tabel template email.
68. TODO - Buat model/repository/service untuk template email.
69. TODO - Tentukan daftar template bawaan yang harus diseed.
70. TODO - Buat seeder template default (welcome, password reset, invoice, reminder, leave request, dll).
71. TODO - Tambahkan endpoint list templates.
72. TODO - Tambahkan endpoint create template.
73. TODO - Tambahkan endpoint update template.
74. TODO - Tambahkan endpoint delete/deactivate template.
75. TODO - Tambahkan endpoint preview render template dengan sample payload.
76. TODO - Tambahkan feature test list templates.
77. TODO - Tambahkan feature test create/update template.
78. TODO - Tambahkan feature test preview template.
79. TODO - Tambahkan feature test forbidden template mutation untuk non-admin.

### H. UI Wiring Halaman `/email-template`

80. TODO - Ganti card template statis menjadi list runtime dari API.
81. TODO - Ganti modal add template menjadi form submit ke API.
82. TODO - Ganti modal edit template menjadi form edit runtime.
83. TODO - Ganti modal delete placeholder menjadi delete/deactivate flow runtime.
84. TODO - Tambahkan placeholder variable helper di editor template.
85. TODO - Tambahkan preview template di UI sebelum save.
86. TODO - Tambahkan search/filter template bila jumlah template mulai banyak.
87. TODO - Tambahkan active/inactive toggle template.
88. TODO - Tambahkan frontend wiring test list/create/edit/delete template.
89. TODO - Tambahkan frontend wiring test preview template.

### I. Integrasi Ke Producer Email Yang Sudah Ada

90. TODO - Inventarisasi semua producer email aktif di codebase.
91. TODO - Pisahkan producer yang cukup pakai mailer aktif vs yang harus template-managed.
92. TODO - Integrasikan `InvoiceService` ke template runtime bila template invoice disiapkan.
93. TODO - Integrasikan `SendInvoiceEmailJob` ke provider resolver baru.
94. TODO - Integrasikan `SendPaymentReminder` ke provider resolver baru.
95. TODO - Evaluasi `PasswordResetLinkNotification` apakah tetap native atau ikut template-managed.
96. TODO - Tambahkan fallback aman jika template tertentu belum ada.
97. TODO - Tambahkan logging template key + provider used pada setiap delivery email.
98. TODO - Tambahkan regression test invoice email setelah provider resolver aktif.
99. TODO - Tambahkan regression test reminder email setelah provider resolver aktif.

### J. Observability Dan Audit Trail

100. TODO - Catat siapa yang mengubah settings email dan kapan.
101. DONE - Catat provider aktif pada delivery log + webhook status update (`transportSource`, `transportHost`, marker status callback provider).
102. TODO - Catat template key/version pada setiap delivery email log.
103. TODO - Tambahkan last successful connection check metadata.
104. TODO - Tambahkan last failed connection check metadata.
105. TODO - Tambahkan halaman/ringkasan mini audit changes bila dibutuhkan di settings.
106. TODO - Tambahkan feature test audit trail perubahan settings.

### K. Security Dan Operational Guard

107. TODO - Pastikan secret tidak pernah dikembalikan utuh ke frontend.
108. TODO - Pastikan secret tidak ikut ke log aplikasi biasa.
109. TODO - Pastikan endpoint test-connection tidak menyimpan credential sementara tanpa consent eksplisit.
110. TODO - Tambahkan rate-limit pada endpoint test-connection.
111. TODO - Tambahkan timeout hard limit pada semua probe provider.
112. TODO - Tambahkan sanitasi error message agar tidak membocorkan credential.
113. TODO - Tambahkan negative test untuk leakage secret di response.

### L. Final QA Dan Closure

114. TODO - Tambahkan E2E testing doc khusus feature email settings bila runtime wiring mulai aktif.
115. TODO - Jalankan PHPUnit targeted untuk settings/template/provider resolver.
116. TODO - Jalankan Vitest targeted untuk wiring `/email-settings` dan `/email-template`.
117. TODO - Jalankan `bash scripts/local-test-gate.sh` setelah slice runtime utama selesai.
118. TODO - Lakukan UIUX cross-check role global-admin vs non-admin.
119. TODO - Validasi lagi matrix di `docs/planning/active-hcm-templates-and-permissions.md` setelah implementation final.
120. TODO - Final review: pastikan feature email settings benar-benar menjadi control-plane email, bukan lagi placeholder UI.

## Recommended First 10 Execution Items

Kalau mau langsung gas tanpa bingung prioritas, urutan awal terbaik:

1. Tentukan source of truth scope fitur email: platform-global only atau tenant-aware.
2. Tentukan policy final endpoint `mailtrap-status`: global-admin only atau semua HCM admin.
3. Selaraskan guard web route dan API route sesuai policy final email settings.
4. Tambahkan regression test unauthorized access ke endpoint `mailtrap-status`.
5. Tambahkan regression test authorized global-admin access ke endpoint `mailtrap-status`.
6. Sinkronkan OpenAPI untuk endpoint `GET /v1/hcm/email-settings/mailtrap-status`.
7. Tambahkan `docs/api/email-settings-api.md` khusus kontrak feature email.
8. Putuskan persistence layer: reuse tabel `settings` atau buat tabel khusus `email_settings` / `email_provider_profiles`.
9. Buat migration persistence settings email.
10. Tambahkan endpoint `GET /v1/hcm/email-settings` untuk fetch current profile.

## Nonstop Execution Queue

Supaya pengerjaan enak dipantau dan tidak putus di tengah, jalur kerjanya pakai antrean tetap berikut:

1. Selesaikan item 1-5 sampai RBAC + regression baseline email benar-benar stabil.
2. Lanjut item 6-10 sampai kontrak API + keputusan persistence + migration dasar beres.
3. Setelah itu langsung lanjut item 21-30 untuk menghidupkan fetch/save settings runtime.
4. Begitu settings runtime hidup, lanjut item 31-40 untuk `test-connection` yang benar.
5. Baru sesudah backend slice stabil, lanjut item 41-55 untuk wiring UI `/email-settings`.
6. Setelah settings hidup, lanjut item 65-89 untuk template runtime dan UI `/email-template`.
7. Terakhir sambungkan producer email existing di item 90-120 sampai control-plane email benar-benar usable.

Aturan eksekusi nonstop:

- Jangan buka slice baru kalau slice sebelumnya belum punya test atau validation minimal.
- Kalau ada blocker, geser hanya ke item terdekat yang tidak merusak urutan dependensi.
- Setiap selesai 1 item, update status item itu jadi `DONE` atau `IN PROGRESS`, jangan tunggu batch besar.

## Sprint Plan 31-100

Supaya eksekusinya tidak berhenti di tengah dan tetap kebaca cepat, jalur kerja item 31-100 dipecah jadi sprint outcome berikut:

1. Sprint 31-40: tuntaskan `test-connection` backend, error normalization, regression coverage, lalu UI state dasar untuk tombol test.
2. Sprint 41-50: wire halaman `/email-settings` ke endpoint runtime (`GET`, `PUT`, `POST test-connection`) termasuk provider selector, masking secret, loading state, dan unsaved changes guard.
3. Sprint 51-60: rapikan UX success/error toast, empty state, frontend wiring tests, lalu definisikan interface internal provider resolver/failover.
4. Sprint 61-70: implement config resolver runtime, putuskan cache/config refresh strategy, dan siapkan schema + seeder dasar `email_templates`.
5. Sprint 71-80: hidupkan API template runtime (list/create/update/delete/preview) dan regression coverage backend-nya.
6. Sprint 81-90: wire halaman `/email-template` ke API runtime termasuk add/edit/delete, preview, search/filter, dan toggle aktif.
7. Sprint 91-100: integrasikan producer email existing satu per satu, tambah logging provider/template, dan tutup audit baseline perubahan settings.

Detail outcome per blok 10 item:

1. Item 31-40: backend probe selesai, frontend belum.
2. Item 41-50: form email settings usable end-to-end untuk global admin.
3. Item 51-60: UI polish stabil dan abstraction provider mulai terbentuk.
4. Item 61-70: resolver runtime + fondasi data template siap.
5. Item 71-80: template API usable untuk operasi CRUD dasar.
6. Item 81-90: template UI usable untuk operasi admin harian.
7. Item 91-100: delivery integration mulai menyentuh producer nyata dan audit trail minimal tersedia.

## Sprint Plan 101-120

Blok akhir diarahkan ke observability, hardening, dan closure evidence agar feature benar-benar bisa ditutup, bukan sekadar “jalan di lokal”.

1. Sprint 101-106: rapikan audit trail delivery dan perubahan settings, termasuk last success/fail probe metadata dan regression test audit.
2. Sprint 107-113: hardening security untuk secret handling, logging hygiene, rate limit, timeout cap, sanitasi error, dan leakage tests.
3. Sprint 114-120: closure pack berisi E2E doc, PHPUnit/Vitest evidence, local test gate, UIUX role cross-check, permission matrix final, dan final review control-plane readiness.

Detail outcome per blok akhir:

1. Item 101-106: observability backend cukup untuk investigasi operasional dasar.
2. Item 107-113: surface `email-settings` aman untuk dipakai admin tanpa bocor credential ke response/log.
3. Item 114-120: evidence completion lengkap dan feature bisa di-claim ready dengan dasar test + doc + matrix role yang sinkron.

## Current Execution Note

- Slice aktif: baseline web control-plane sudah aktif; backlog berikutnya fokus ke provider abstraction, template runtime, dan coverage frontend.
- Evidence code saat ini:
	- `backend/resources/views/settings/email-settings.blade.php` menjadi source of truth halaman aktif dan sekarang me-wire profile runtime + Mailtrap health + save/test modal.
	- `backend/app/Http/Controllers/Api/Settings/HcmEmailSettingsController.php` tetap memegang profile settings, Mailtrap status, dan test-connection API runtime.
	- `backend/routes/api/email-settings.php` sekarang hanya memuat empat endpoint settings/probe yang benar-benar menjadi scope feature email-settings.
	- `backend/routes/api/notifications.php`, `backend/tests/Feature/HcmEmailComposeApiTest.php`, dan `frontend/resources/js/email.js` menjadi source of truth compose manual `/email`.
	- `docs/api/openapi.yaml`, `docs/api/email-settings-api.md`, `docs/features/email-settings/README.md`, dan matrix permission sudah diselaraskan ulang terhadap runtime aktif.
- Kondisi saat ini:
	- Halaman `/email-settings`: aktif sebagai baseline control-plane untuk konfigurasi efektif mail runtime.
	- API profile settings: aktif via `GET/PUT /v1/hcm/email-settings`.
	- API test connection: aktif via `POST /v1/hcm/email-settings/test-connection`.
	- API Mailtrap status: aktif via `GET /v1/hcm/email-settings/mailtrap-status` dengan `credentialSource` dan `mode` yang akurat (`settings|env`).
	- Compose UI `/email`: aktif via `POST /v1/hcm/notifications/send-email`.
	- Surface `/email-template`: tidak aktif lagi dan belum memiliki runtime pengganti.
