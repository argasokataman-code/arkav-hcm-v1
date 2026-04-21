# Mock Payment (Development) Implementation

## Tujuan Teknis

Dokumen ini menjelaskan bagaimana mock payment dev bekerja di runtime saat ini, surface mana yang paling mendekati payment real, dan gap apa yang masih harus diketahui QA atau developer sebelum memakai helper ini sebagai evidence.

## Surface Runtime

| Surface | Fungsi | Catatan |
|---|---|---|
| `/subscription-checkout` | Membuat subscription `pending_payment` dan invoice awal | Step pra-payment untuk flow tenant upgrade/package checkout |
| `/company/invoices` | Menampilkan invoice tenant dan memicu payment mock | Surface yang paling mendekati user flow nyata |
| `/mock-payment-tester.html` | HTML tester statis untuk helper dev | Sekarang juga bisa generate token tenant dan membuka hosted simulation lokal |
| `/mock-hosted-payment.html` | Hosted checkout lokal untuk dev | Meniru invoice URL, redirect, callback token, dan settlement webhook lokal |
| `POST /v1/hcm/billing/invoices/{id}/mock-pay` | Menutup invoice tenant dengan gateway `xendit_mock` | Sudah terdokumentasi di OpenAPI |
| `POST /v1/mock/invoices/create-and-pay` | Buat invoice dan payment langsung atau hosted pending flow | `flow_mode=instant|hosted` |
| `POST /v1/mock/payments/create` | Membayar invoice yang sudah ada | Runtime meminta `invoice_id` UUID |
| `GET /v1/mock/test-cards` | Menampilkan kartu uji mock | Hanya helper display/simulation |
| `POST /v1/mock/webhook/charge-succeeded` | Simulasi webhook lokal | Hosted mock flow bisa mewajibkan `callback_token` |

## Flow Yang Direkomendasikan Untuk Dev QA

### 1. Buat state billing seperti real user

- User masuk ke flow checkout package.
- Checkout membuat subscription `pending_payment` dan invoice unpaid.
- Evidence terlihat pada response checkout dan halaman `/company/invoices`.

Referensi runtime:

- `backend/resources/views/saas/subscription-checkout.blade.php`
- `backend/e2e/scenarios/landing-company-upgrade-payment.spec.js`

### 2. Trigger payment dari invoice tenant

Endpoint yang dipakai:

- `POST /v1/hcm/billing/invoices/{id}/mock-pay`

Controller:

- `backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php`

Urutan kerja penting:

1. Controller memastikan tenant context aktif.
2. Invoice dicari di company aktif, jadi tidak boleh cross-tenant.
3. Payment method mock dipetakan ke bentuk yang lebih production-like (`credit_card`, `bank_transfer`, `e_wallet`).
4. `MockPaymentGatewayService::createPayment()` menghasilkan `charge_id` mock.
5. Record `payments` ditulis dengan gateway `xendit_mock`.
6. Invoice diubah ke `paid` dan `is_paid=true`.
7. Bila invoice terhubung ke subscription, `activationService->activateIfEligible()` dijalankan.
8. Setelah commit, job email invoice dikirim best-effort.

Outcome ini yang membuat flow tersebut paling dekat ke payment real: state billing tenant berubah melalui surface invoice yang memang dipakai user, bukan lewat endpoint utilitas yang mem-bypass sebagian journey.

## Flow Utilitas Dev

### `POST /v1/mock/invoices/create-and-pay`

Controller:

- `backend/app/Http/Controllers/Api/MockPaymentController.php`

Service:

- `backend/app/Services/MockPaymentGatewayService.php`

Perilaku:

1. Memastikan mock mode aktif.
2. Mengambil `activeCompanyId` dari tenant context.
3. Membuat invoice baru berstatus `draft`.
4. Bila `flow_mode=instant`, payment langsung dibuat `completed` atau `failed`.
5. Bila `flow_mode=hosted`, payment dibuat `pending` dan response mengembalikan hosted checkout URL, callback token, redirect URL, dan webhook simulation URL.
6. Bila payment sukses, invoice di-mark paid.
7. Bila ada subscription `pending_payment`/`trial` terbaru untuk company itu, subscription dapat diaktifkan.

Catatan penting:

- Flow ini tetap cepat untuk bootstrap test data, tetapi sekarang juga bisa meniru hosted checkout lokal saat perlu evidence redirect/callback.
- Saat `simulate_failure=true` pada mode `instant`, top-level request tetap bisa sukses secara HTTP karena endpoint ini terutama memodelkan “record berhasil dibuat”, sedangkan hasil payment harus dibaca dari `data.payment.status`.
- Pada mode `hosted`, settlement akhir dibaca dari hosted page dan redirect balik helper, bukan dari response create call pertama.

### `POST /v1/mock/payments/create`

Perilaku:

1. Menerima `invoice_id`, `amount`, `payment_method`, dan `simulate_failure`.
2. Runtime saat ini memvalidasi `invoice_id` sebagai UUID invoice.
3. Jika sukses, payment baru dibuat dan invoice ditandai paid.
4. Jika `simulate_failure=true`, controller mengembalikan error mock failure.

Status helper HTML:

- HTML tester tab `Pay Invoice` sekarang memakai UUID invoice dan dapat diisi otomatis dari hasil `create-and-pay`.
- HTML tester juga punya token generator yang memanggil `POST /v1/identity/auth/login` lalu menyimpan `auth_token` + `arcav_active_tenant` untuk request berikutnya.
- Hosted page `/mock-hosted-payment.html` memakai `callback_token` dari metadata payment untuk memanggil `POST /v1/mock/webhook/charge-succeeded`.

## Perbedaan Dengan Xendit Real

### Yang sudah mirip

- Ada invoice dan payment record nyata di database.
- Ada `gateway_reference` yang bisa dipakai sebagai jejak transaksi.
- Ada transisi bisnis nyata: invoice paid, payment completed, subscription active bila eligible.
- Ada gateway label `xendit_mock` pada tenant invoice flow sehingga mudah dibedakan saat audit.

### Yang belum mirip penuh

- Domain hosted masih lokal, bukan URL eksternal provider.
- Callback token masih dev-local dan tidak memakai signing secret provider.
- Settlement asynchronous masih disimulasikan oleh halaman hosted mock, bukan event jaringan provider sungguhan.

## Keterkaitan Dengan Xendit Runtime

Integrasi real saat ini tetap hidup di layer lain:

- `backend/app/Services/XenditService.php` membuat invoice Xendit sungguhan dan menerima `invoice_url` dari provider.
- `backend/app/Jobs/ProcessRecurringSubscriptionBilling.php` menyimpan `xendit_invoice_id` dan `invoice_url` saat recurring billing memakai Xendit.
- `backend/app/Http/Controllers/Api/PaymentWebhookController.php` memproses webhook Xendit real.

Artinya, mock payment dev dipakai untuk membuktikan transisi state bisnis lokal, sedangkan Xendit real dipakai untuk membuktikan integrasi gateway eksternal.

## Rekomendasi Pakai

- Untuk QA flow bisnis tenant: pakai checkout → `/company/invoices` → `mock-pay`.
- Untuk seeding dan smoke test cepat: pakai `/v1/mock/invoices/create-and-pay` mode `instant`.
- Untuk smoke test hosted-like: pakai `/v1/mock/invoices/create-and-pay` mode `hosted` lalu buka `/mock-hosted-payment.html` dari response/helper.
- Untuk debug payload cepat via browser tanpa membuka halaman tenant lengkap: pakai `/mock-payment-tester.html`; helper ini sekarang juga bisa generate token tenant sendiri, tetapi flow tenant invoice tetap lebih representatif untuk QA bisnis.