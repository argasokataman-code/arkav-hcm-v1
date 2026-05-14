# Mock Payment (Development)

## Ringkasan

Fitur ini menyediakan flow pembayaran development-only untuk meniru hasil akhir payment gateway tanpa memanggil hosted checkout Xendit sungguhan. Tujuan utamanya adalah menguji alur billing sampai invoice berubah menjadi paid, payment record tercipta, dan subscription `pending_payment` bisa aktif menjadi `active`.

Dokumentasi ini sengaja membedakan dua mode:

- flow yang paling mendekati payment real, yaitu company membuka invoice lalu men-trigger mock pay dari surface billing tenant;
- flow utilitas dev, yaitu endpoint `/v1/mock/*` dan tester HTML statis untuk smoke test cepat.

## Akses

- Dev only: endpoint mock aktif saat aplikasi local atau saat `app.mock_payments_enabled` diaktifkan.
- Semua endpoint mock membutuhkan `api.token`.
- Endpoint `/v1/mock/*` dan `/v1/hcm/billing/invoices/{id}/mock-pay` juga membutuhkan `tenant.context`, jadi user harus punya company aktif.
- Surface web yang paling relevan untuk tenant owner / billing user adalah `/company/invoices`.

## UI Aktif

- Payment-like company flow: `/company/invoices`.
- Step sebelum payment: `/subscription-checkout` menyiapkan subscription `pending_payment` dan invoice draft/unpaid.
- Utility tester: `/mock-payment-tester.html`.

Catatan penting:

- `/company/invoices` adalah flow dev yang paling dekat ke payment real karena memakai invoice tenant yang benar-benar dibuat dari checkout lalu men-trigger mutasi invoice/payment/subscription seperti alur billing normal.
- `/mock-payment-tester.html` cocok untuk smoke test cepat, sekarang punya generator bearer token tenant, tab `Pay Invoice` berbasis UUID, dan bisa membuka hosted simulation lokal via `/mock-hosted-payment.html`.

## Flow Bisnis End-to-End

### Flow yang paling mendekati payment real

1. User tenant memilih paket atau upgrade di `/subscription-checkout`.
2. Checkout membuat subscription dengan status `pending_payment` dan invoice tenant yang belum dibayar.
3. User membuka `/company/invoices` untuk melihat invoice tersebut.
4. User men-trigger endpoint `POST /v1/hcm/billing/invoices/{id}/mock-pay`.
5. Sistem membuat record payment dengan gateway `xendit_mock` dan `gateway_reference` mock.
6. Invoice di-mark paid di database.
7. Jika invoice terkait subscription, service aktivasi menjalankan transisi `pending_payment` menjadi `active`.
8. UI invoice dan profil subscription merefleksikan status sukses setelah reload.

### Flow utilitas dev cepat

1. Dev membuka `/mock-payment-tester.html` atau memanggil endpoint `/v1/mock/*` langsung via curl/Postman.
2. Dev bisa memilih `create-and-pay` mode `instant` untuk shortcut satu request, atau mode `hosted` untuk membuat payment `pending` lalu meneruskan browser ke hosted mock checkout lokal.
3. Pada hosted mode, helper menerima hosted URL, callback token, dan redirect URL balik ke helper; halaman hosted mock lalu menyelesaikan settlement lewat webhook simulation lokal.
4. Sistem tetap menulis record invoice/payment sungguhan di database, sehingga hasil akhirnya bisa dipakai untuk memverifikasi reporting, dashboard billing, atau subscription activation.

## Lifecycle Dan Keputusan Bisnis

- Mock payment harus menghasilkan side effect bisnis nyata di database, bukan sekadar response dummy. Karena itu invoice, payment, dan activation subscription tetap dipersist.
- Flow dev yang disarankan untuk QA adalah invoice tenant di `/company/invoices`, bukan endpoint utilitas mentah, karena surface ini paling mendekati journey user saat akan membayar invoice.
- Mock payment sekarang menyediakan hosted simulation lokal untuk meniru invoice URL, redirect browser, callback token, dan penyelesaian settlement asynchronous lokal.
- Untuk kebutuhan smoke test cepat, endpoint `/v1/mock/invoices/create-and-pay` tetap dipertahankan walaupun lebih pendek dari flow real.
- Saat `simulate_failure=true` pada mode `instant`, dev tetap harus membaca `payment.status` atau payload detail. Pada mode `hosted`, hasil akhir dibaca dari redirect balik helper dan settlement webhook simulation.

## Integrasi

- Landing Pages: checkout package dapat berujung ke subscription `pending_payment` dan invoice. Lihat `docs/features/landing-pages/README.md`.
- Subscriptions: mock payment menjadi salah satu cara dev menguji transisi `pending_payment` ke `active`. Lihat `docs/features/subscriptions/README.md`.
- Trial & Billing Dashboard: invoice/payment hasil mock tetap muncul sebagai data billing tenant yang sah. Lihat `docs/features/trial-billing-dashboard/README.md`.
- Purchase Transaction / billing ledger: payment hasil mock ikut memperkaya surface billing operasional. Lihat `docs/features/purchase-transaction/README.md`.
- Identity & Auth: bearer token dan company context tetap mengikuti flow auth tenant biasa. Lihat `docs/features/identity-auth/README.md`.

## Kontrak API

- OpenAPI saat ini sudah mencakup flow tenant invoice mock pay di `POST /v1/hcm/billing/invoices/{id}/mock-pay`.
- Endpoint utilitas dev `/v1/mock/*` adalah contract runtime development-only yang dipakai untuk smoke test dan helper tooling.
- Dokumentasi API dev eksplisit tersedia di `docs/api/mock-payment-api.md`.
- Source of truth runtime tetap berada di route dan controller backend:
  - `backend/routes/api.php`
  - `backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php`
  - `backend/app/Http/Controllers/Api/MockPaymentController.php`

## Existing Vs Target

- Existing: endpoint hosted checkout tenant invoice (`POST /v1/hcm/billing/invoices/{id}/mock-hosted-checkout`) sekarang berjalan **Xendit-first** saat konfigurasi Xendit tersedia, dan hanya fallback ke hosted simulator lokal saat mode dev/mock diperlukan.
- Existing: utility endpoint `/v1/mock/*`, `/mock-payment-tester.html`, dan `/mock-hosted-payment.html` tersedia untuk pengujian cepat di environment development.
- Existing: hasil mock payment menulis invoice/payment nyata ke database sehingga dashboard billing dan modul lain bisa membaca outcome yang sama seperti flow billing normal.
- Existing: pada mode Xendit-first, invoice tenant menghasilkan `payment.gateway = xendit`, metadata `xendit_invoice_id`, dan redirect ke `checkout.xendit.co` untuk channel VA/QR/paylater/e-wallet sesuai konfigurasi akun Xendit.
- Target: endpoint path lama akan diganti nama ke path netral pada major cleanup berikutnya (saat ini dipertahankan demi backward compatibility frontend).
- Gap aktif: coverage event webhook spesifik channel (misalnya `qr_code` atau `fva_paid`) belum menjadi hard requirement karena flow saat ini memakai status invoice (`invoice.paid`/`invoice.expired`) sebagai source of truth settlement.

## Dokumentasi

- [README.md](README.md) — overview bisnis, akses, lifecycle, dan kapan memakai flow mock tertentu.
- [IMPLEMENTATION.md](IMPLEMENTATION.md) — mapping route/controller/service dan perbedaan mock vs Xendit real.
- [tracker.md](tracker.md) — snapshot status, evidence, dan gap runtime/dokumentasi yang masih terbuka.
- [../../MOCK-PAYMENT-GATEWAY-GUIDE.md](../../MOCK-PAYMENT-GATEWAY-GUIDE.md) — quick-start dev guide yang seragam dengan feature docs ini.

## Status

- Module status: `Development helper in active use`
- Last updated: `2026-04-21`
- Tracker: [tracker.md](tracker.md)

## Test & Evidence

- `backend/e2e/scenarios/landing-company-upgrade-payment.spec.js` membuktikan flow checkout `pending_payment` → `/company/invoices` → `mock-pay` → payment `completed` → invoice paid.
- `backend/e2e/scenarios/mock-payment-tester.spec.js` membuktikan helper browser `/mock-payment-tester.html` dapat generate token tenant, membuat invoice gagal terlebih dulu, mengalirkan UUID invoice ke tab `Pay Invoice`, lalu melunasi invoice existing sampai payment `completed`.
- `backend/e2e/scenarios/mock-payment-tester.spec.js` juga membuktikan mode hosted bisa membuat payment `pending`, membuka `/mock-hosted-payment.html`, lalu menyelesaikan settlement dan redirect kembali ke helper dengan status `completed`.
- `backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php` menunjukkan payment mock tenant memakai gateway `xendit_mock`, mengubah invoice menjadi paid, dan men-trigger aktivasi subscription bila eligible.
- `backend/app/Http/Controllers/Api/MockPaymentController.php` dan `backend/app/Services/MockPaymentGatewayService.php` menjadi utilitas dev untuk quick create/pay, hosted simulation, callback token enforcement, dan test-card simulation, dengan helper browser yang juga membawa tenant context aktif dari localStorage.