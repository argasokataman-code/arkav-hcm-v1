# Subscriptions Audit Tracker

## Status Snapshot

- Tanggal: 2026-04-24
- Status: code-1 guard + upgrade UX queue visibility
- Scope audit: guard route web template sensitif, queue approval subscription change, dan clarity UX target paket blocked feature

## Changes Closed In This Audit

- Route web `/pages`, `/blogs`, `/testimonials` kini dibatasi middleware `hcm.web.primary-super-admin` (hanya admin code-1).
- Endpoint queue approval global subscription change (`GET /v1/saas/subscription-change-requests`, `POST approve/reject`) kini menolak super-admin non-code-1 dengan `403 PRIMARY_SUPER_ADMIN_REQUIRED`.
- Halaman `/upgrade` kini menampilkan target paket rekomendasi saat redirect `?blocked=<feature>`, riwayat request tenant, dan queue pending untuk admin code-1.
- Subscription list/detail API sekarang menolak non-admin dengan `403 ADMIN_REQUIRED`, termasuk direct bearer-token access.
- `subscriptions-management.js` sekarang mengirim `company_id` sebagai UUID company dan `package_uuid` sebagai UUID package pada create flow.
- Manager JS tidak lagi memanggil list subscriptions sensitif saat permission `subscription.manage` tidak ada; halaman masuk unauthorized/read-only state.
- Vitest regression ditambah untuk memastikan manager JS mengirim payload UUID yang selaras dengan backend.

## Remaining Gaps

- Browser Playwright E2E untuk subscriptions ditunda pada audit 2026-04-19 ini atas arahan user; bukan blocker untuk closure non-E2E.
- Wizard upgrade/downgrade, recurring invoice generator, dan kebijakan global feature-gating by subscription masih belum ada.
- Konsistensi istilah legacy status seperti `paused` vs `suspended` masih perlu perapian seed/UI lintas modul.

## Evidence

- `bash scripts/local-test-gate.sh` → **failed** karena existing regression di `Tests\Feature\HcmPayrollRunApiTest::admin can reset payments` (403 vs expected 200, bukan scope perubahan task ini).
- `vendor/bin/phpunit --filter test_pages_blogs_testimonials_only_primary_super_admin_code_one_can_access tests/Feature/WebHcmRouteGuardTest.php` → `OK (1 test, 9 assertions)`.
- `vendor/bin/phpunit tests/Feature/HcmSubscriptionChangeApiTest.php` → `OK (6 tests, 29 assertions)`.
- `npx vitest run tests/ui/subscriptions-api-contract.test.js` → `6 passed`.
- `npm run build` → success.
- `bash scripts/check-api-docs-sync.sh` → `check-api-docs-sync: no changed files`.
- `php artisan test tests/Feature/SubscriptionServiceTest.php tests/Feature/SaasSubscriptionsAdminOnlyTest.php tests/Feature/SubscriptionManagementTest.php tests/Feature/InvoicePaidActivatesSubscriptionTest.php tests/Feature/EmployeeLimitEnforcementTest.php tests/Feature/SaasCompanyBillingOverviewApiTest.php tests/Feature/ConvertExpiredTrialsJobTest.php tests/Feature/PublicOnboardingApiTest.php tests/Feature/WebHcmRouteGuardTest.php` → `61 passed (1572 assertions)`
- `npm run test:ui -- tests/ui/subscriptions-api-contract.test.js` → `6 passed`
- `npm run build` → success
- `scripts/check-api-docs-sync.sh` → no backend API surface changes detected
- Runtime source of truth: `backend/app/Http/Controllers/Api/HcmSubscriptionChangeController.php`, `backend/routes/web.php`, `frontend/resources/js/upgrade-data.js`, `frontend/resources/js/subscriptions-management.js`