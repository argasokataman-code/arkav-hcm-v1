# Subscriptions — Happy Path & Negative Scenarios

Dokumen ini adalah “peta perilaku” modul subscription: apa yang terjadi pada happy path, dan bagaimana sistem menangani kondisi negatif (negative handling) yang relevan untuk UX + backend policy.

> Scope dokumen ini **sesuai kondisi repo saat ini**: sudah ada enforcement auto-terminate/suspend + employee-limit block, tetapi **belum ada** flow otomatis “upgrade paket → generate invoice → bayar → unlock fitur” (lihat **Gap** di bawah).

---

## 1) Entities & State yang terlibat

### 1.1 Subscription

- **Tabel**: `subscriptions`
- **Status** (implementasi): `active | trial | pending_payment | inactive | expired | cancelled | suspended`
- **Tanggal kunci**:
  - `starts_at`, `ends_at`
  - `trial_ends_at` (opsional)
  - `terminated_at`, `suspended_at` (audit)
- **Reason**:
  - `termination_reason`, `suspension_reason`

### 1.2 Invoice & Payment (Billing)

- **Invoice** (`invoices`): dipakai untuk menentukan **overdue** (`is_paid=false` dan `due_date` lewat grace window).
- **Payment** (`payments`): terhubung ke invoice.

Catatan penting:
- Auto-suspension karena overdue invoice **mengandalkan** `due_date` terisi (null tidak diproses).
- “Mark paid / verify payment” saat ini adalah **aksi admin**, bukan auto-unlock subscription.

### 1.3 EmployeeProfile (HCM)

- Limit employee mengandalkan `EmployeeProfile.company_id` + `employment_status != terminated`.
- Enforcement dilakukan pada **API create employee** dan **bulk upload**.

---

## 2) Happy Path (yang “ideal” sesuai implementasi sekarang)

### HP-0 — Paket katalog → langganan menunggu bayar → invoice dibayar → aktif

**Goal**: company belum mendapat entitlement sampai pembayaran tercatat.

- **Trigger sistem**: checkout tenant, onboarding public, atau job konversi trial membuat subscription `pending_payment` tanpa entry manual dari UI subscriptions.
- **API**: `POST /v1/saas/subscriptions` dengan `status=pending_payment`, `ends_at` = batas/tenggat jendela provisioning.
- **Invoice**: `POST /v1/saas/invoices` dengan `company_id` + **`subscription_id`** mengarah ke baris tersebut.
- **Bayar**: `PUT /v1/saas/invoices/{id}/mark-paid` (atau verify payment yang memicu `Invoice::markAsPaid()`).
- **Outcome**: subscription → `active`, `starts_at` = now, `ends_at` = now + siklus billing; limit karyawan / `activeSubscription()` baru mengikuti paket.

### HP-1 — Admin membuat subscription untuk company

**Goal**: company punya subscription aktif/trial dengan `ends_at` valid.

- **UI**: `/saas/subscriptions`
- **JS manager**: `frontend/resources/js/subscriptions-management.js`
- **API**: `POST /v1/saas/subscriptions` (admin-only)
- **Validasi kunci**:
  - `company_id` (UUID company), `package_uuid` (UUID package), `starts_at`, `billing_cycle`
  - `ends_at` **wajib** jika `status` = `active|trial` (required_if)
  - `amount` optional → auto-calc dari package
- **Outcome**:
  - subscription tersimpan; `plan_code` diisi dari package
  - Untuk `active|trial` langsung memberi entitlement.
  - Status **`pending_payment`** tidak dibuat manual dari UI ini; jika record berada di state itu, pemrosesan lanjut dilakukan lewat billing system.

**UX expectation**:
- Sukses → toast sukses + row muncul di tabel.
- Error validasi → toast danger dengan message API.

---

### HP-2 — Admin update subscription (mis. ganti package / ubah ends_at)

- **UI**: `/saas/subscriptions` (edit modal)
- **API**: `PUT /v1/saas/subscriptions/{id}` (admin-only)
- **Validasi kunci**:
  - Jika status diubah jadi `active|trial|pending_payment`, maka `ends_at` tidak boleh null (422 `VALIDATION_ERROR`)
- **Outcome**:
  - `plan_code` update jika `package_uuid` berubah
  - Jika **`package_uuid`** berubah, **`amount`** disinkronkan ke harga katalog (`monthly_price` / `yearly_price`) sesuai `billing_cycle` efektif
  - Record `pending_payment` diperlakukan sebagai state system-managed; operator mengelola invoice/payment, bukan memindahkan status ini dari modal subscriptions.

---

### HP-3 — Admin reactivate subscription yang expired/cancelled (manual override)

- **UI**: `/saas/subscriptions` (aksi reaktivasi manual di UI jika ada)
- **API**: `POST /v1/saas/subscriptions/{id}/renew` (admin-only)
- **Input**: `ends_at` future
- **Outcome**:
  - status → `active`
  - `starts_at` → now, `ends_at` → sesuai request

---

### HP-4 — Auto-terminate subscription saat `ends_at` lewat

- **Job**: `TerminateExpiredSubscriptionsJob`
- **Service**: `SubscriptionTerminationService::terminateExpiredSubscription()`
- **Query**: (`active|trial` dengan `ends_at < now()`) **atau** (`pending_payment` dengan `ends_at < now()`)
- **Outcome**:
  - status → `expired`
  - `terminated_at` + `termination_reason` terisi (pending: alasan “Provisioning window ended without payment” bila job tidak override reason)

**UX expectation**:
- UI subscription list akan menampilkan status `expired`.
- Akses aplikasi HCM/SaaS oleh company user **belum** otomatis digate di web layer; gating real harus di API per feature (lihat Gap).

---

### HP-5 — Auto-suspend subscription jika invoice overdue (7+ hari)

- **Job**: `SuspendServicesForOverdueInvoicesJob`
- **Service**: `SubscriptionTerminationService::suspendDueToOverdueInvoice()`
- **Rule**:
  - invoice: `is_paid=false`, `due_date` not null, `due_date < now()-7days`
  - subscription: company punya subscription status `active|trial`
- **Outcome**:
  - status → `suspended`
  - `suspended_at` + `suspension_reason` terisi

---

### HP-6 — Employee count enforcement (create / bulk upload)

**Goal**: company tidak bisa menambah employee melewati limit paket.

- **UI**: `/employees` + bulk upload modal (HCM Admin)
- **API**:
  - `POST /v1/hcm/employees`
  - `POST /v1/hcm/employees/bulk-upload`
- **Precondition**: tenant context ada (`X-Company-Id` / resolved active company)
- **Behavior**:
  - sebelum validasi payload employee dijalankan, API akan cek limit paket via `EmployeeCountValidator`
  - jika melebihi limit → `422` `EMPLOYEE_COUNT_EXCEEDED`
- **Outcome**:
  - create single: gagal, tidak ada employee baru
  - bulk: gagal pada saat melewati threshold, import all-or-nothing (rollback)

**UX expectation**:
- UI menampilkan error dari API (`EMPLOYEE_COUNT_EXCEEDED`) via toast/in-modal error.
- Tidak ada `window.alert/confirm/prompt` native.

---

## 3) Negative Scenarios (yang harus “kelihatan” di UI dan enforce di backend)

### NS-1 — Subscription `active|trial` tetapi `ends_at` kosong

**Kenapa penting**: auto-termination bergantung pada `ends_at`; kalau null, subscription bisa “nyangkut” aktif tanpa expiry.

- **API**:
  - Create: `POST /v1/saas/subscriptions` sudah enforce `required_if:status,active,trial`
  - Update: `PUT /v1/saas/subscriptions/{id}` akan return `422 VALIDATION_ERROR` jika status jadi `active|trial` tapi `ends_at` efektif null
- **UI**:
  - modal harus memaksa input end date untuk status active/trial
  - bila backend return 422, tampilkan toast danger dengan message.

---

### NS-2 — Invoice overdue tetapi `due_date` null

**Behavior**: invoice tanpa `due_date` **tidak akan** dianggap overdue oleh job auto-suspend.

- **Job**: query invoice `whereNotNull('due_date')` → invoice `due_date=null` di-skip
- **Impact**:
  - risiko: invoice unpaid tapi tanpa due_date tidak akan trigger auto-suspend
- **Mitigasi**:
  - enforce `due_date` required pada create invoice (sudah ada di `InvoiceController`)
  - audit data legacy: backfill due_date jika ada record lama.

---

### NS-3 — Customer menambah karyawan sampai melebihi limit paket

Ada dua varian:

1) **Runtime block (langsung saat create) — sudah ada**
   - `POST /v1/hcm/employees` / bulk upload → `422 EMPLOYEE_COUNT_EXCEEDED`
   - UX: tampilkan error jelas “limit plan tercapai”

2) **Drift (data sudah terlanjur over-limit) — ada mekanisme suspend**
   - `CheckEmployeeCountLimitsJob` akan suspend subscription jika `current > planLimit`
   - UX: UI harus memperlihatkan status `suspended` + reason (di halaman SaaS subscription, idealnya)

---

### NS-4 — Tenant context tidak ada saat create/bulk employee

- **API**:
  - create/bulk employee akan return `422 TENANT_CONTEXT_REQUIRED`
- **UI**:
  - ini harus muncul sebagai toast danger; biasanya menandakan user belum memilih company context atau bug di tenant resolver.

---

### NS-5 — Non-admin mencoba enumerate atau mutate subscription/invoice/payment

- **API**:
  - subscription list/detail/mutasi: `403 ADMIN_REQUIRED`
  - invoice/payment mutasi: `403 ADMIN_REQUIRED`
- **UI**:
  - halaman `/saas/subscriptions` sudah web-admin-only; jika state permission frontend tidak cukup, manager JS masuk unauthorized/read-only state dan tidak memanggil list sensitif
  - action button disembunyikan/disabled untuk non-admin (UX)
  - tetapi backend tetap source of truth: tetap 403

### NS-6 — Deep link Packages memakai identifier stale / salah format

- **Kasus**:
  - link lama mengirim `companyId` numeric lama, atau
  - `packageId` tidak lagi valid / tidak ada di katalog aktif.
- **UI**:
  - modal create tetap bisa dibuka, tetapi dropdown hanya akan preselect nilai yang valid dari opsi terbaru.
  - submit akan menampilkan toast error dari backend bila `package_uuid` tidak valid / tidak aktif.
- **API**:
  - `POST /v1/saas/subscriptions` memvalidasi `company_id` sebagai UUID company dan `package_uuid` sebagai UUID package aktif.
  - input invalid → `422 VALIDATION_ERROR` atau `422 PACKAGE_NOT_ACTIVE`.

---

## 4) Gap / next vs “upgrade → invoice → bayar → unlock” penuh

**Sudah ada (2026-04)**: alur dasar **katalog paket → `pending_payment` → invoice (`subscription_id`) → mark paid → `active`**, plus gate package aktif & sinkron `amount` saat ganti paket.

Masih **belum** (bila produk membutuhkan):
1) Wizard upgrade/downgrade otomatis + proration invoice
2) Purchase transaction generator terikat setiap renewal
3) Job recurring invoice bulanan
4) “Feature gate” global di luar modul yang sudah pakai `Company::activeSubscription()` / validator employee

Dokumen lanjutan bisa dipisah (mis. `UPGRADE-FLOW.md`) bila scope proration diputuskan.

---

## 5) Impact Map (UI ↔ API ↔ Services/Jobs)

### 5.1 Subscriptions (SaaS admin UI)
- **UI**: `/saas/subscriptions`
- **JS**: `frontend/resources/js/subscriptions-management.js`
- **API**:
  - `GET /v1/saas/subscriptions`
  - `POST /v1/saas/subscriptions` (admin)
  - `PUT /v1/saas/subscriptions/{id}` (admin)
  - `DELETE /v1/saas/subscriptions/{id}` (admin) sekarang ditolak dengan `409 SUBSCRIPTION_DELETE_DISABLED`; gunakan cancel/lifecycle action
  - `POST /v1/saas/subscriptions/{id}/renew` (admin)

### 5.2 Auto-management (jobs)
- **Terminate**: `TerminateExpiredSubscriptionsJob` → `SubscriptionTerminationService::terminateExpiredSubscription()`
- **Overdue invoice suspend**: `SuspendServicesForOverdueInvoicesJob` → `SubscriptionTerminationService::suspendDueToOverdueInvoice()`
- **Employee violation suspend**: `CheckEmployeeCountLimitsJob` → `SubscriptionTerminationService::suspendDueToEmployeeCountViolation()`

### 5.3 Employee limit runtime enforcement (HCM)
- **API**: `POST /v1/hcm/employees`, `POST /v1/hcm/employees/bulk-upload`
- **Service**: `EmployeeCountValidator`
- **Errors**:
  - `EMPLOYEE_COUNT_EXCEEDED` (422)
  - `TENANT_CONTEXT_REQUIRED` (422)

### 5.4 Billing module (invoice/payment/reminder)
- **API**: `/v1/saas/invoices`, `/v1/saas/payments`, `/v1/saas/reports/*`
- **Job reminder**: `SendPaymentReminder` (purchase-transaction module)
- **Keterkaitan ke subscription**:
  - overdue invoice → auto-suspend subscription
  - belum ada linkage “payment verified → auto-reactivate subscription” (manual/placeholder)

