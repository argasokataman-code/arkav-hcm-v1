# Reporting Tracker

## Status Snapshot

- Date: 2026-04-20
- Status: In Progress
- Focus saat ini: hardening multi-tenant reporting + penyelarasan source-of-truth docs

## Latest Evidence

- `php artisan migrate --force` → `Nothing to migrate`
- `php artisan test tests/Feature/ReportSnapshotApiTest.php tests/Feature/ReportControllerTest.php` → PASS (`17` tests, `115` assertions)
- `npm run test:ui -- tests/ui/reports-hub.wiring.test.js tests/ui/reports-api-sync.wiring.test.js` → PASS (`3` tests)
- `npm run build` → PASS
- `./scripts/check-api-docs-sync.sh` → PASS

## Fixed in Latest Pass

- Legacy report API menolak override `company_id` yang bertentangan dengan `X-Company-Id` aktif.
- Reports Hub menampilkan pesan backend error envelope secara langsung.
- Snapshot detail/export dibuktikan aman lintas tenant dan menerima UUID path.
- README reporting diseragamkan ke schema feature-doc repo.

## Open Gaps

- Belum semua halaman report lama dimigrasikan ke snapshot/HCM-native contract.
- Belum ada history/export browser terpisah untuk user non-teknis.
- Export payload BI-specific per report type masih belum ada.