# Purchase Transaction Tracker

## Status Snapshot

- Date: 2026-04-26
- Status: In Progress
- Focus saat ini: route hardening, transaction integrity, source-of-truth docs untuk split contract transaksi, dan integrasi context invoice render

## Latest Evidence

- `php artisan test tests/Feature/WebHcmRouteGuardTest.php` → PASS (`11` tests, `1399` assertions)
- `php artisan test tests/Feature/TransactionControllerTest.php tests/Feature/PurchaseTransactionServiceTest.php` → PASS (`15` tests, `115` assertions)
- `npm run test:ui -- tests/ui/purchase-transactions.wiring.test.js` → PASS (`3` tests)

## Fixed in Latest Pass

- `/saas/transactions` dan `/purchase-transaction` sekarang diproteksi `hcm.web.admin`.
- Legacy create transaction sekarang meresolusikan `subscription_id` UUID ke FK integer internal sebelum insert.
- Purchase bearer contract menolak create transaction dengan subscription-company mismatch.
- Frontend transaksi menampilkan pesan backend error envelope secara langsung.
- README feature dan API markdown sekarang jujur terhadap runtime split contract.
- Invoice PDF context sekarang terintegrasi: issuer dari Business Settings, Bill To dari Company Profile tenant, dan terms dari Invoice Settings tenant.
- Subject/signature email invoice mengikuti nama issuer dari Business Settings agar konsisten dengan dokumen PDF.

## Open Gaps

- Path `/v1/saas/transactions` masih punya split contract legacy vs bearer dan belum dikonsolidasikan.
- Export CSV belum punya regression terpisah untuk filter-specific behavior.
- Dokumen IMPLEMENTATION/E2E yang lebih luas masih memuat konteks billing lama yang belum sepenuhnya dipangkas ke scope transaksi aktif.
