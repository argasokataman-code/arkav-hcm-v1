# Tracker Mock Payment (Development)

Pembaruan terakhir: 2026-04-21

## Ringkasan Status

- Status: dev helper aktif dan cukup untuk menguji transisi billing sampai sukses.
- Flow yang direkomendasikan: checkout package/upgrade → `/company/invoices` → `POST /v1/hcm/billing/invoices/{id}/mock-pay`.
- Cakupan utama: pembuatan payment record, invoice paid, subscription activation saat eligible, dan helper utilitas `/v1/mock/*` untuk smoke test cepat.

## Evidence Terbaru

- `backend/e2e/scenarios/landing-company-upgrade-payment.spec.js` membuktikan subscription `pending_payment` dibuat dari checkout, invoice muncul di `/company/invoices`, lalu mock payment menghasilkan payment `completed` dengan gateway `xendit_mock`.
- `backend/e2e/scenarios/mock-payment-tester.spec.js` membuktikan helper `/mock-payment-tester.html` bisa dipakai dari browser untuk membuat invoice gagal lebih dulu lewat `create-and-pay`, mengisi otomatis UUID invoice ke tab `Pay Invoice`, lalu menyelesaikan payment existing invoice sampai status `completed`.
- `backend/e2e/scenarios/mock-payment-tester.spec.js` juga membuktikan helper bisa generate bearer token tenant dari halaman yang sama, membuat hosted mock payment `pending`, membuka `/mock-hosted-payment.html`, lalu menyelesaikan settlement lewat webhook simulation dan redirect balik ke helper dengan status `completed`.
- `backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php` menunjukkan invoice tenant yang dibayar lewat mock flow akan:
  - membuat payment dengan `gateway_reference` mock,
  - mengubah invoice menjadi paid,
  - memanggil aktivasi subscription bila invoice terkait subscription,
  - mendispatch email invoice setelah commit.
- `backend/app/Http/Controllers/Api/MockPaymentController.php` dan `backend/app/Services/MockPaymentGatewayService.php` menyediakan endpoint utilitas dev untuk create-and-pay, pay existing invoice, test cards, dan webhook simulation.
- `docs/api/openapi.yaml` sudah memiliki kontrak untuk `POST /v1/hcm/billing/invoices/{id}/mock-pay`.

## Gap Saat Ini

- Hosted simulation sekarang sudah tersedia di `POST /v1/mock/invoices/create-and-pay` lewat `flow_mode=hosted`, tetapi domain, signature, dan callback token-nya tetap local/dev-only sehingga belum identik dengan provider eksternal nyata.
- Helper dev sudah meniru hosted invoice URL, redirect browser, callback token, dan webhook settlement lokal, tetapi belum meniru network boundary atau signing secret provider production.

## Update Validasi 2026-04-21

- `/mock-payment-tester.html` tab `Pay Invoice` sudah disinkronkan ke UUID invoice runtime.
- `/mock-payment-tester.html` sekarang punya token generator tenant langsung dari endpoint login, menyimpan `auth_token` + `arcav_active_tenant`, dan menyediakan tombol copy token.
- `/mock-payment-tester.html` sekarang juga membawa `tenant.context` dari `arcav_active_tenant`, sehingga request browser selaras dengan contract tenant-aware `/v1/mock/*`.
- `/mock-hosted-payment.html` menutup gap hosted simulation lokal dengan callback token, redirect balik ke helper, dan settlement melalui `POST /v1/mock/webhook/charge-succeeded`.
- `backend/app/Services/MockPaymentGatewayService.php` sudah memakai status invoice `draft` saat membuat invoice unpaid dari shortcut `create-and-pay`, sehingga tidak lagi gagal pada enum status invoice.
- `backend/tests/Feature/MockPaymentControllerTest.php` menutup regresi untuk create payment by invoice UUID, hosted flow callback token enforcement, dan webhook simulation by payment UUID.

## Catatan

- Tracker ini fokus pada readiness dokumentasi dan pemakaian dev, bukan klaim parity penuh dengan payment gateway real.
- Jika helper HTML atau kontrak utilitas berubah, perbarui tracker ini bersamaan dengan quick guide dan feature docs.