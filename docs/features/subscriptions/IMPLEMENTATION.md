# Subscriptions Module - Implementation Guide

## Overview

Subscriptions module mengelola hubungan company dengan package SaaS: create langganan, update status, override reaktivasi manual, dan lifecycle tracking.

> Untuk “behaviour” (happy path + negative handling), baca dulu: **`SCENARIOS.md`**.

## Architecture

Backend:
- Controller: `backend/app/Http/Controllers/Api/SubscriptionController.php`
- Models: `Subscription`, `Package`
- API routes: `backend/routes/api.php` pada prefix `/v1/saas`

Auto-management (scheduler/jobs):
- Jobs: `TerminateExpiredSubscriptionsJob`, `SuspendServicesForOverdueInvoicesJob`, `CheckEmployeeCountLimitsJob`
- Service: `backend/app/Services/SubscriptionTerminationService.php`
- Aktivasi setelah bayar: `backend/app/Services/SubscriptionActivationFromInvoiceService.php` (dipanggil dari `Invoice::markAsPaid()`)

Employee plan enforcement (HCM integration):
- Service: `backend/app/Services/EmployeeCountValidator.php`
- Enforcement point: `backend/app/Http/Controllers/Api/HcmEmployeeController.php` pada create & bulk upload

Frontend:
- View: `backend/resources/views/saas/subscriptions.blade.php`
- Manager: `frontend/resources/js/subscriptions-management.js`

Web routes:
- `/saas/subscriptions`
- `/subscription`

## API Contract

### 1) List Subscriptions

`GET /v1/saas/subscriptions`

Filters:
- `status`
- `company_id`
- `plan_code`
- `billing_cycle`
- `search` (company/package/plan_code)

Response:
- `success`
- `data[]`
- `pagination`

### 2) Create Subscription (Admin)

`POST /v1/saas/subscriptions`

Validation:
- `company_id` required (UUID company)
- `package_uuid` required (UUID package)
- `status` required
- `starts_at` required
- `billing_cycle` required (`monthly|yearly`)
- `amount` optional (auto-calc from package jika null)
- `ends_at` required jika `status` = `active|trial|pending_payment`
- `status` boleh `pending_payment` (menunggu pembayaran; tidak dianggap “active subscription” untuk limit karyawan sampai invoice dibayar).
- Untuk `active|trial|pending_payment`, package harus `status=active` di DB; selain itu → `422` `PACKAGE_NOT_ACTIVE`.

Behavior:
- `plan_code` didenormalisasi dari package.
- Jika amount kosong, otomatis diisi dari `monthly_price` atau `yearly_price` package.
- **Update `package_uuid`**: `plan_code` dan **`amount`** diselaraskan ulang ke harga katalog paket baru (mengikuti `billing_cycle` payload atau nilai yang ada).

### 2b) Integrasi invoice → aktif (`pending_payment`)

1. Buat subscription `status=pending_payment` + `ends_at` (batas jendela provisioning / tenggat bayar).
2. Buat invoice `POST /v1/saas/invoices` dengan **`subscription_id`** yang sama `company_id`.
3. Tandai invoice dibayar (`PUT .../mark-paid` atau alur payment verify yang memanggil `Invoice::markAsPaid()`).
4. Sistem mengaktifkan subscription: `status` → `active`, `starts_at` → now, `ends_at` → now + 1 bulan/tahun sesuai `billing_cycle`, `trial_ends_at` → null.

Kolom `invoices.subscription_id` ditambahkan migrasi `2026_04_23_160000_add_subscription_id_to_invoices.php` (dijadwalkan **setelah** pembuatan tabel `invoices`). File `2026_04_16_210000_add_subscription_id_to_invoices.php` adalah **no-op** agar urutan batch lama tetap valid.

### 2c) Company “active subscription”

`Company::activeSubscription()` dan `Subscription::activeForCompany()` memfilter `ends_at` **di masa depan** (selain status), agar baris `active` yang sudah lewat `ends_at` tidak dianggap entitlement sampai job terminate mengejar.

### 3) Show Subscription

`GET /v1/saas/subscriptions/{subscription}`

Return detail lengkap dalam format `formatSubscription()`.

### 4) Update Subscription (Admin)

`PUT /v1/saas/subscriptions/{subscription}`

Partial update diperbolehkan (`sometimes`) termasuk `status`, `package_uuid`, `starts_at`, `ends_at`, `billing_cycle`, `auto_renew`, `amount`.

Guard tambahan (domain validation):
- Jika payload mengubah `status` menjadi `active|trial|pending_payment`, maka `ends_at` efektif **tidak boleh null** (jika null → `422 VALIDATION_ERROR`).

### 5) Delete/Cancel Subscription (Admin)

`DELETE /v1/saas/subscriptions/{subscription}`

Endpoint hard delete sekarang diblok dan mengembalikan `409 SUBSCRIPTION_DELETE_DISABLED`. Pengakhiran lifecycle operasional harus memakai cancel/status action, bukan menghapus record subscription.

### 6) Manual Reactivation (Admin)

`POST /v1/saas/subscriptions/{subscription}/renew`

Input:
- `ends_at` required dan harus date future.

Effect:
- status jadi `active`
- `starts_at` = now
- `ends_at` sesuai request
- `trial_ends_at` null

Tidak berlaku untuk `pending_payment` → `422 SUBSCRIPTION_INVALID_STATE` (aktifkan lewat invoice dibayar).

## Access Control

- Semua endpoint subscriptions (`GET` list/detail + semua mutasi) menggunakan check global admin di controller.
- Implementasi admin-check runtime saat ini memakai `User::isGlobalHcmAdmin()`.
- Non-admin akan menerima `403 ADMIN_REQUIRED`, termasuk bila mencoba enumerate list/detail via bearer token langsung.

## Frontend Flow

File: `frontend/resources/js/subscriptions-management.js`

Flow utama:
1. `init()` bind event, load companies/packages/subscriptions hanya jika user punya `subscription.manage`; selain itu halaman masuk unauthorized/read-only state tanpa memanggil list sensitif.
2. `loadSubscriptions()` request list dengan query filter dari UI.
3. `renderSubscriptions()` tampilkan tabel + action buttons.
4. `handleSaveSubscription()` create/update payload dengan field backend (`company_id` UUID, `package_uuid` UUID).
5. `cancelSubscription()` update status menjadi `cancelled`.
6. `deleteSubscription()` hard delete endpoint.

## Implemented Improvements (2026-04-13)

- Harmonisasi ID form/event handler agar sesuai blade terbaru.
- Mapping payload sudah sinkron ke backend snake_case.
- Filter status/cycle/search aktif dan terkirim ke API.
- Modal add/edit disatukan dan stabil.
- Confirm action memakai Arcav confirm jika tersedia.
- Backend index ditambah filter `billing_cycle` dan `search`.
- Enumerasi list/detail via bearer token non-admin kini ditolak `403 ADMIN_REQUIRED`.
- Manager JS create flow kini mengirim `company_id` UUID (bukan numeric id) agar selaras dengan kontrak backend dan deep-link Packages.

## Known Gaps

- View details sebelumnya menggunakan `window.alert`; saat ini disarankan pakai `ArcavUi.showInfo` / modal template.
- Endpoint delete masih hard delete; bila policy perlu soft-cancel, perlu revisi API.

Gap besar terkait billing-upgrade (requirement product):
- Belum ada flow “upgrade paket otomatis → generate invoice → bayar → unlock fitur”.
- Belum ada recurring invoice generator bulanan berbasis subscription terbaru.

## Integration Notes (lintas modul)

### Subscriptions ↔ Billing (Invoices/Payments)
- Auto-suspend overdue invoice **menggunakan** invoice `is_paid=false` + `due_date` lewat grace period.
- Saat ini belum ada integrasi otomatis “payment verified → reactivate subscription”; bila dibutuhkan, perlu hook di PaymentController/InvoiceController atau webhook handler.

### Subscriptions ↔ HCM Employees (Seat/Employee enforcement)
- Employee limit enforced pada:
  - `POST /v1/hcm/employees`
  - `POST /v1/hcm/employees/bulk-upload`
- Error codes yang harus dipahami UI:
  - `EMPLOYEE_COUNT_EXCEEDED` (422)
  - `TENANT_CONTEXT_REQUIRED` (422)

---

## Integrasi FE ↔ BE (matriks wiring)

Sumber wiring UI: `frontend/resources/js/subscriptions-management.js` + `backend/resources/views/saas/subscriptions.blade.php`.  
Sumber API: `backend/routes/api.php` (prefix `/v1/saas`) + `SubscriptionController`.

| Area | Endpoint / perilaku BE | Sudah di-wire di UI SaaS? | Catatan |
|------|-------------------------|---------------------------|---------|
| Auth / role | `GET /v1/identity/auth/me` → `subscription.manage` | ✅ | Dipakai untuk memutuskan apakah halaman boleh load list sensitif atau masuk unauthorized/read-only state. |
| Dropdown company | `GET /v1/company?page=1&per_page=200` | ✅ | Mengisi select company di modal. |
| Dropdown package | `GET /v1/saas/packages?status=active&per_page=200` | ✅ | Mengisi select package di modal. |
| List + filter | `GET /v1/saas/subscriptions?...` | ✅ | Query `search`, `status`, `billing_cycle` dari filter card; endpoint sekarang admin-only. |
| Create | `POST /v1/saas/subscriptions` | ✅ | Payload: `company_id` UUID dari dropdown company, `package_uuid`, `status` (active/trial/…), `starts_at`, `ends_at`, `trial_ends_at` (wajib jika `trial`), `billing_cycle`, `auto_renew: true`. |
| Load untuk edit | `GET /v1/saas/subscriptions/{id}` | ✅ | Mengisi company (read-only saat edit), package, start, cycle, `ends_at`; status asal disimpan untuk PUT (tidak dipaksa ke `active`). |
| Update | `PUT /v1/saas/subscriptions/{id}` | ✅ | Body: `package_uuid`, `status` (nilai saat load), `starts_at`, `ends_at`, `billing_cycle`, `auto_renew` — bukan hitung ulang diam-diam tanpa input. |
| Cancel | `PUT /v1/saas/subscriptions/{id}` body `{ status: cancelled }` | ✅ | Tombol cancel untuk `active`, `trial`, `suspended`. |
| Delete | `DELETE /v1/saas/subscriptions/{id}` | ✅ | Hard delete. |
| Manual Reactivation | `GET /v1/saas/subscriptions/{id}` + `POST .../renew` | ✅ | Baris tabel: modal `#subscriptionRenewModal`. **Reactivate by ID** (admin): tombol toolbar → modal `#subscriptionRenewByIdModal` → Load (GET) → tanggal akhir → Reactivate (POST). Status eligible: expired, cancelled, suspended, inactive. |
| View detail | `GET /v1/saas/subscriptions/{id}` | ✅ | Refetch lalu `ArcavUi.showInfo` (bukan cache list saja). |
| Auto-terminate / suspend / employee job | Jobs + `SubscriptionTerminationService` | ❌ | **Tidak ada UI** di halaman ini; hanya efek ke data subscription (status) saat job jalan. |
| Billing invoice/payment | `/v1/saas/invoices`, `/v1/saas/payments`, … | ❌ | **Halaman lain** (`/saas/invoices`, dll.); tidak terintegrasi di layar subscription. |
| HCM employee limit | `POST /v1/hcm/employees`, bulk-upload | ❌ | **Modul HCM** (`employees-data.js`); bukan file subscription — tapi **sudah terhubung ke policy subscription** di BE. |

### Markup legacy

Modal `#actionsModal` (Pause/Resume) dan `#deleteModal` duplicate sudah **dihapus** dari Blade; aksi destructive memakai `ArcavUi.confirmDelete` + inline actions.

---

## TODO FE (Subscriptions SaaS page)

Selesai baru-baru ini: filter + badge `suspended`, field `ends_at` + suggest dari start/cycle, **status + `trial_ends_at` di modal** (create/edit) dengan validasi BE (`trial_ends_at` wajib jika `trial`, antara `starts_at` dan `ends_at`), manual reactivation + modal, **Reactivate by ID** (GET + POST tanpa baris di halaman), notice read-only EN/ID ringkas, view detail refetch, hapus dead modal, `per_page` list selaras BE.

Masih terbuka (polish):

1. Menyeragamkan bahasa **semua** toast/error di `subscriptions-management.js` (saat ini campuran EN/ID).

---

## TODO BE (Subscriptions + integrasi billing/HCM)

Sudah ada / stabil:

1. CRUD subscription + renew endpoint + validasi `ends_at` untuk `active|trial`.  
2. Jobs auto-terminate, auto-suspend overdue invoice, check employee limit + service suspend/reactivate.  
3. Enforcement employee limit di `HcmEmployeeController` + `EmployeeCountValidator`.  
4. List `GET /v1/saas/subscriptions` menghormati query `per_page` (1–100). Update mengizinkan `status: suspended` (selaras job/service).

Perlu keputusan implementasi (gap produk):

5. **Flow upgrade + invoice + payment gate**  
   - Endpoint atau service orchestration: upgrade request → buat `PurchaseTransaction` / `Invoice` → status subscription `pending_payment` atau tetap `active` dengan `metadata` gate — **belum**.  
   - Hook **payment verified / invoice paid → `reactivateSuspended()`** atau set package baru — **belum** terstandar di `PaymentController` / `InvoiceController`.

6. **Recurring billing**  
   - Job harian/bulanan: generate invoice per `billing_cycle` + company — **belum**.

7. **OpenAPI**  
   - `/v1/saas/subscriptions*` sudah ada ringkas di `docs/api/openapi.yaml` + skema `SaasSubscription`; perkaya error domain bila perlu QA.

8. **Konsistensi status (`suspended` vs istilah “paused”)**  
   - Di DB, `subscriptions.status` adalah `string(50)` (bukan enum ketat), jadi nilai legacy seperti `paused` dari seeder **bisa** tercipta tanpa error SQL.  
   - `PUT` update sekarang mengizinkan `suspended` (selain subset lain); **create** tetap subset tanpa `suspended` (biasanya dari job).  
   - Tindakan lanjut: rapikan seed/UI agar tidak memakai status yang tidak pernah ditangani UI (mis. ganti `paused` → `inactive` atau `suspended` sesuai makna bisnis).

9. **Gating akses modul HCM/SaaS by subscription**  
   - Saat `suspended` / `expired`, modul mana yang harus 403: perlu middleware terpusat atau cek di controller — **belum** didokumentasikan sebagai satu kebijakan.

---

## Verification Checklist

- Open `/saas/subscriptions` dan list tampil.
- Add subscription baru sukses.
- Edit subscription berhasil update.
- Cancel subscription mengubah status ke `cancelled`.
- Manual reactivation (row eligible) memanggil `POST .../renew` dengan `ends_at` baru; **Reactivate by ID** memuat `GET .../{id}` lalu reaktivasi.
- Delete subscription menghapus record.
- Filter status/cycle/search bekerja sesuai query.
- Non-admin list/detail/mutasi mendapat `403 ADMIN_REQUIRED`.
