# Release Note - 2026-04-22

## Scope

Release ini menutup hardening tenant billing + profile settings + permission visibility untuk area HCM berikut:

1. Tenant billing invoice list/detail/download/PDF.
2. Tenant profile settings runtime data loading.
3. Subscription employee slot visibility pada identity payload dan UI.
4. Penyelarasan visibilitas menu platform-only `Other Settings` agar global-admin-only.
5. Sinkronisasi dokumentasi API dan planning permission matrix.

## Business Impact

1. Tenant admin sekarang bisa melihat kapasitas employee package secara langsung di halaman profile settings tanpa harus buka halaman billing lain.
2. Menu `Other Settings` tidak lagi bocor ke tenant admin biasa, sehingga permukaan konfigurasi platform-only tidak tampil ke role yang tidak berhak.
3. Invoice tenant menampilkan metadata paket, billing cycle, dan next payment secara konsisten di list, detail modal, dan PDF.
4. Alur download invoice PDF lebih stabil karena path download backend sudah diselaraskan.

## Technical Changes

### Backend API and service

1. backend/app/Http/Controllers/Api/AuthController.php
- Menambahkan subscription.employeeSlots pada payload identity (limit, used, remaining, isUnlimited, isConfigured).

2. backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php
- Menambahkan eager loading company/subscription/package di list dan detail.
- Memperbaiki download path PDF agar sesuai storage path yang disimpan.

3. backend/app/Services/InvoiceService.php
- Menambahkan metadata package/billing pada formatter invoice.
- Mengganti HTML inline PDF ke Blade template profesional.

### Frontend and Blade wiring

1. frontend/resources/js/profile-settings-data.js
- Menambahkan Authorization bearer header pada request profile settings.
- Menambahkan renderer employee slots pada summary subscription.

2. backend/resources/views/profile-settings.blade.php
- Menyembunyikan tab Other Settings untuk non-global admin.
- Menambahkan placeholder UI employee slots.

3. frontend/resources/js/company-invoices.js
- Menambah fallback API call dan binary download path yang lebih robust.
- Menambah render metadata package/cycle/next payment pada list dan modal.
- Menambah fallback modal open/close dan print handler.

4. backend/resources/views/company/invoices.blade.php
- Meningkatkan layout preview invoice modal ke format profesional.

5. backend/resources/views/layout/partials/sidebar.blade.php
6. backend/resources/views/layout/partials/header.blade.php
- Membungkus menu Other Settings dengan guard global admin.

### PDF template

1. backend/resources/views/pdf/invoice.blade.php
- Template PDF invoice baru berbasis Blade.

### Tests

1. backend/tests/Feature/AuthApiTest.php
- Regression test payload subscription.employeeSlots.

2. backend/tests/Feature/HcmCompanyInvoiceHostedCheckoutTest.php
- Regression test download PDF binary.
- Regression test metadata package/cycle/next billing pada invoice detail.

3. backend/tests/Feature/SidebarAssetMenuVisibilityTest.php
- Regression test visibilitas Other Settings tidak muncul untuk tenant admin.

4. backend/tests/ui/profile-settings.wiring.test.js
- Validasi Authorization header + employee slots render.

5. backend/tests/ui/company-invoices.wiring.test.js
- Validasi list/detail/download/preview wiring dan fallback.

6. backend/tests/ui/subscription-checkout.wiring.test.js
- Validasi paid-success state tidak menampilkan form pay lagi.

## Documentation Sync

1. docs/api/openapi.yaml
- Menambahkan schema invoice tenant dan employeeSlots pada identity schemas.
- Merapikan struktur schema agar berada pada components.schemas.

2. docs/api/identity-api.md
- Menambahkan dokumentasi subscription.employeeSlots.

3. docs/api/company-billing-invoices-api.md
- Dokumen API khusus tenant company billing invoices.

4. docs/planning/active-hcm-templates-and-permissions.md
- Menambahkan catatan hardening pass 4 untuk Other Settings global-only.

5. .cursor/rules/role-permissions-with-features.mdc
- Menyelaraskan catatan policy visibilitas menu Other Settings.

## Validation Evidence

Urutan gate yang dijalankan:

1. bash scripts/check-api-docs-sync.sh
- Hasil: check-api-docs-sync: OK.

2. cd backend && php artisan migrate --force
- Hasil: Nothing to migrate.

3. cd backend && php artisan test tests/Feature/AuthApiTest.php tests/Feature/SidebarAssetMenuVisibilityTest.php tests/Feature/HcmCompanyInvoiceHostedCheckoutTest.php
- Hasil: 29 passed (268 assertions).

4. cd backend && npx vitest run tests/ui/profile-settings.wiring.test.js tests/ui/company-invoices.wiring.test.js tests/ui/subscription-checkout.wiring.test.js
- Hasil: 3 files passed, 10 tests passed.

5. cd backend && npm run build
- Hasil: build sukses.

## Delivery

1. Commit: 0e80d46.
2. Branch: main.
3. Remote: origin/main.
4. Push status: berhasil, local dan remote head sinkron di 0e80d46.
