# Subscriptions Audit Tracker

## Status Snapshot

- Tanggal: 2026-04-19
- Status: guarded baseline revalidated
- Scope audit: subscriptions API/UI wiring, access control, UUID payload contract, lintas modul billing/packages/HCM employee-limit

## Changes Closed In This Audit

- Subscription list/detail API sekarang menolak non-admin dengan `403 ADMIN_REQUIRED`, termasuk direct bearer-token access.
- `subscriptions-management.js` sekarang mengirim `company_id` sebagai UUID company dan `package_uuid` sebagai UUID package pada create flow.
- Manager JS tidak lagi memanggil list subscriptions sensitif saat permission `subscription.manage` tidak ada; halaman masuk unauthorized/read-only state.
- Vitest regression ditambah untuk memastikan manager JS mengirim payload UUID yang selaras dengan backend.

## Remaining Gaps

- Browser Playwright E2E untuk subscriptions ditunda pada audit 2026-04-19 ini atas arahan user; bukan blocker untuk closure non-E2E.
- Wizard upgrade/downgrade, recurring invoice generator, dan kebijakan global feature-gating by subscription masih belum ada.
- Konsistensi istilah legacy status seperti `paused` vs `suspended` masih perlu perapian seed/UI lintas modul.

## Evidence

- `php artisan test tests/Feature/SubscriptionServiceTest.php tests/Feature/SaasSubscriptionsAdminOnlyTest.php tests/Feature/SubscriptionManagementTest.php tests/Feature/InvoicePaidActivatesSubscriptionTest.php tests/Feature/EmployeeLimitEnforcementTest.php tests/Feature/SaasCompanyBillingOverviewApiTest.php tests/Feature/ConvertExpiredTrialsJobTest.php tests/Feature/PublicOnboardingApiTest.php tests/Feature/WebHcmRouteGuardTest.php` → `61 passed (1572 assertions)`
- `npm run test:ui -- tests/ui/subscriptions-api-contract.test.js` → `6 passed`
- `npm run build` → success
- `scripts/check-api-docs-sync.sh` → no backend API surface changes detected
- Runtime source of truth: `backend/app/Http/Controllers/Api/SubscriptionController.php`, `frontend/resources/js/subscriptions-management.js`