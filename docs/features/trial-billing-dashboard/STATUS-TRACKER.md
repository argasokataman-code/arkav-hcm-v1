# Tracker Status Trial & Billing Dashboard

Pembaruan terakhir: 2026-04-19

## Ringkasan Status

- Status: sudah terimplementasi untuk flow dashboard yang berpusat pada company.
- Cakupan: overview admin-only untuk tab Trial dan Subscribed, memakai subscription terbaru per company, ringkasan invoice terbaru, dan ringkasan log email terbaru.
- Status validasi: validasi wiring frontend, regresi backend, pengecekan migrate, rerun onboarding terkait CTA registrasi landing, dan smoke test browser per role sudah hijau pada pengecekan terakhir.

## Evidence Terbaru

- [backend/tests/Feature/SaasCompanyBillingOverviewApiTest.php](../../../backend/tests/Feature/SaasCompanyBillingOverviewApiTest.php) membuktikan satu company hanya muncul satu kali dan dashboard memakai subscription terbaru.
- [backend/tests/ui/saas-billing-overview.wiring.test.js](../../../backend/tests/ui/saas-billing-overview.wiring.test.js) membuktikan wiring frontend ke endpoint billing overview sudah sesuai.
- [docs/api/saas-billing-overview-api.md](../../../docs/api/saas-billing-overview-api.md) sudah sinkron dengan kontrak aktif dan perilaku satu baris per company.
- [backend/tests/Feature/PublicOnboardingApiTest.php](../../../backend/tests/Feature/PublicOnboardingApiTest.php) lulus penuh saat direrun setelah `php artisan migrate --force` dengan hasil `7 passed (34 assertions)`; ini jadi cross-check bahwa CTA landing ke form trial dan skenario negatif onboarding tidak rusak saat audit flow billing/onboarding terkait.
- Smoke test browser 2026-04-19 pada `http://127.0.0.1:8007` membuktikan role HCM Admin bisa login, membuka overview tab `Subscribed`, melihat badge `State Mismatch`, lalu masuk ke halaman detail invoice dan membaca riwayat email penuh; role non-admin yang login dengan akun biasa terdorong keluar dari `/saas/billing-overview` ke `/employee-dashboard`.
- Perbaikan terakhir di UI menghapus ketergantungan token localStorage untuk halaman billing web sehingga halaman detail invoice tetap bisa memuat data saat user masuk melalui session login web yang sah.

## Gap Saat Ini

- Gap yang sebelumnya dibuka untuk history email penuh, halaman detail invoice terpisah, dan badge mismatch pada row overview sudah ditutup pada implementasi 2026-04-19.
- Saat ini belum ada gap kritikal yang memblokir flow utama Trial & Billing Dashboard.

## Catatan

- Tracker ini adalah titik baca utama untuk status fitur tanpa harus menelusuri seluruh dokumen implementasi.
- Jika flow berubah lagi, perbarui file ini lebih dulu, lalu sinkronkan dokumen implementasi dan dokumen API terkait.