# Subscriptions Module - E2E UI Testing

## Objective

Memastikan alur UI Subscriptions berjalan end-to-end untuk admin: create, update, cancel, delete, filter, dan validasi akses.

## Mandatory Execution Order (Wajib)

Sebelum menyatakan task selesai, jalankan urutan ini:

1. Role permission + use case check (backend policy tetap source of truth).
2. UIUX cross-check per role (HCM admin dan company/non-admin).
3. Manual UI E2E click-by-click sesuai skenario di dokumen ini.

## Environment

- App URL: `http://localhost:8000`
- Admin user: HCM admin
- Non-admin user: company user
- API token endpoint aktif: `GET /api-token`

## Local Execution Setup

Gunakan baseline berikut sebelum menjalankan test manual UI:

1. Jalankan app lokal
   - Backend health: `http://127.0.0.1:8007/health`
   - Frontend proxy: `http://127.0.0.1:5179/login`
   - Start service dari root: `./run.sh`
2. Seed data SaaS untuk UI flow
   - `cd backend && php artisan db:seed --class=SaasUiFlowSeeder`
3. Akun uji
   - Admin: `qa.login@example.com / StrongPass1`
   - Company / non-admin: `demo.owner01@example.com / StrongPass1`
   - Company code (jika login mode company dipakai): `demo_co_01`
4. Browser execution
   - Manual browser biasa diperbolehkan.
   - Jika ingin headed automation browser nyata, gunakan Playwright dan install browser: `npx playwright install chromium`

## Scenario 1 - Open Page and Load Data

1. Login sebagai admin.
2. Buka `/saas/subscriptions`.
3. Expected:
   - List subscriptions tampil.
   - Tombol Add Subscription tampil.
   - Tidak ada error JS kritis di console.

## Scenario 2 - Add Subscription

1. Klik Add Subscription.
2. Isi:
   - Company: pilih company valid
   - Package: pilih package active
   - Start Date: hari ini
   - Billing Cycle: monthly
3. Submit.
4. Expected:
   - Toast sukses.
   - Record baru muncul di list.
   - Status default active.

## Scenario 3 - Edit Subscription

1. Klik icon edit di row existing.
2. Ubah package atau billing cycle.
3. Submit.
4. Expected:
   - Toast update sukses.
   - Data list terupdate.

## Scenario 4 - Cancel Subscription

1. Klik icon cancel pada row status active.
2. Konfirmasi action.
3. Expected:
   - Toast cancel sukses.
   - Status berubah jadi cancelled.

## Scenario 5 - Delete Subscription

1. Klik icon delete pada row test.
2. Konfirmasi delete.
3. Expected:
   - Toast delete sukses.
   - Row hilang dari list.

## Scenario 6 - Search and Filter

1. Isi search dengan nama company/package.
2. Expected: list terfilter.
3. Ubah status filter (mis. `trial`/`cancelled`).
4. Expected: list sesuai status.
5. Ubah cycle filter (`monthly|yearly`).
6. Expected: list sesuai billing cycle.
7. Klik Reset.
8. Expected: semua filter kosong dan list kembali default.

## Scenario 7 - Access Restriction Non-Admin

1. Login sebagai non-admin.
2. Coba create/update/delete/cancel via UI/API.
3. Expected:
   - Request mutasi ditolak `403 ADMIN_REQUIRED`.

## UI Regression Checklist

- Table responsive desktop/mobile.
- Badge status konsisten untuk semua status.
- Pagination next/prev bisa diklik.
- Modal add/edit bisa dibuka-tutup stabil.

## Exit Criteria

- Semua flow admin pass.
- Restriksi non-admin terverifikasi.
- Tidak ada error JS kritis.

## Latest Execution Snapshot

Tanggal eksekusi: 2026-04-13

Automated API-role validation yang sudah dijalankan:
- `php artisan test --filter=SubscriptionServiceTest`
- Hasil: `8 passed (33 assertions)`

Automated web-role validation yang sudah dijalankan:
- `php artisan test --filter=WebHcmRouteGuardTest`
- Hasil: `10 passed (1091 assertions)`

Playwright browser E2E validation yang sudah dijalankan:
- `cd backend && npm run e2e:subscriptions`
- Hasil: `2 passed`

Cakupan validasi role:
- Admin flow: create, list/filter, update, delete, renew.
- Non-admin/company flow: list/show read-only diizinkan; create/update/delete/renew ditolak dengan `403 ADMIN_REQUIRED`.
- Web access flow: halaman `/saas/subscriptions` terverifikasi bisa diakses user terautentikasi (melalui guard web), dan tamu tetap terblokir oleh guard global.
- Browser E2E flow (Chromium):
   - Admin scenario: open/list/filter/create/edit/view/cancel/delete `PASS`
   - Company/non-admin scenario: UI read-only + API mutate blocked (`403 ADMIN_REQUIRED`) `PASS`

## Manual UI E2E Execution Log

| Date | Role | Scenario | Result | Notes |
|------|------|----------|--------|-------|
| 2026-04-13 | HCM Admin | Scenario 1-7 | PASS | Dieksekusi via Playwright browser E2E (`npm run e2e:subscriptions`) |
| 2026-04-13 | Company/Non-Admin | Scenario 7 (+ UI visibility check) | PASS | UI read-only terverifikasi + POST mutate ditolak `403 ADMIN_REQUIRED` |
