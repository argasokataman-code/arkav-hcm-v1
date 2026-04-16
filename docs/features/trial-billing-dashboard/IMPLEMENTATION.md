# Trial & Billing Dashboard — IMPLEMENTATION (rencana)

## Scope UI

Tambahkan layar admin-only untuk memantau Company/Tenant berdasarkan status subscription:

- Tab **Trial**: `trial`
- Tab **Subscribed**: `active` + `pending_payment` (dengan badge berbeda)

Halaman ini fokus **monitoring**, bukan menggantikan CRUD packages/subscriptions/invoices/payments yang sudah ada.

## Data model & relasi yang dipakai

Sumber kebenaran yang sudah ada di sistem:

- `companies` (tenant)
- `subscriptions` (lifecycle: `trial`, `pending_payment`, `active`, dll.)
- `invoices` (membawa `subscription_id` dan `company_id`)
- `payments` (verify dapat memicu invoice paid / subscription activation tergantung implementasi controller)

Untuk list yang cepat, query harus memuat:

- company + active subscription (atau subscription latest per company),
- invoice terakhir per subscription (atau per company dengan filter subscription_id),
- ringkasan email status invoice.

## Email automation (invoice)

Tujuan:

- Saat invoice dibuat untuk `pending_payment`, sistem otomatis mengirim email ke kontak owner/company.
- Admin bisa melihat status pengiriman: terkirim / gagal / belum terkirim.

### Opsi tracking email (pilih salah satu)

**A) Tambah kolom di `invoices`** (paling sederhana)

- `sent_at` (datetime nullable)
- `last_email_error` (text nullable)
- `last_email_attempt_at` (datetime nullable)

Kelebihan: query list mudah.
Kekurangan: tidak ada history detail.

**B) Tabel log terpisah** (lebih lengkap)

`invoice_email_logs`:

- `invoice_id`
- `status` (`sent|failed`)
- `to_email`
- `provider_message_id` (optional)
- `error_message` (nullable)
- timestamps

Kelebihan: ada history dan audit.
Kekurangan: query list butuh join/aggregate.

Default recommended: **B** (karena kamu minta “terintegrasi dengan email secara automated” + detail).

### Trigger pengiriman otomatis

- Saat invoice dibuat (store) untuk subscription `pending_payment`:
  - enqueue job `SendInvoiceEmailJob`
- Retry policy:
  - exponential backoff untuk error transient
  - batas retry agar tidak spam

Kriteria sukses:

- email terkirim → catat `sent_at` / log row `sent`
- gagal → catat `last_error` / log row `failed`

## API contract (admin-only)

Halaman list butuh endpoint agregat (lebih efisien daripada client-side join).

Disarankan endpoint baru:

- `GET /v1/saas/companies/billing-overview?tab=trial|subscribed&search=...&page=...`

Response data per row:

- `companyId`, `companyCode`, `companyName`
- `subscription`: `id`, `status`, `billingCycle`, `startsAt`, `endsAt`, `trialEndsAt`, `planCode`, `packageName`
- `latestInvoice`: `id`, `invoiceNo` (jika ada), `amountDue`, `dueDate`, `isPaid`, `sentAt`, `lastEmailError`
- `emailStatus`: computed (`not_sent|sent|failed`)

Security:

- wajib middleware token
- wajib admin guard (403 non-admin)

Dokumentasi API + OpenAPI harus diupdate saat implementasi endpoint dibuat.

## Mapping status → badge → tindakan (UI)

Halaman list wajib konsisten dalam menampilkan status dan CTA (atau link) yang relevan.

| Subscription status | Badge | Invoice last | Email status | Tindakan utama di row |
|---------------------|-------|--------------|--------------|-----------------------|
| `trial` | `TRIAL` | biasanya none | none | “Lihat company” + countdown `trialEndsAt` |
| `pending_payment` | `PENDING_PAYMENT` | `unpaid` | `not_sent/sent/failed` | “Bayar” + “Detail invoice” + (opsional) “Resend email” bila failed |
| `active` | `ACTIVE` | `paid` | `sent` (atau none jika manual) | “Detail invoice terakhir” + “Lihat subscription” |
| `suspended` | `SUSPENDED` | optional | optional | “Investigasi” + link ke invoices/subscriptions |
| `expired` | `EXPIRED` | optional | optional | “Renew/upgrade” (admin flow) |
| `cancelled` | `CANCELLED` | optional | optional | “Investigasi” |

Jika state mismatch:

- invoice sudah paid tapi subscription masih `pending_payment` → badge warning `STATE_MISMATCH`
- subscription `pending_payment` tapi invoice missing → badge warning `INVOICE_MISSING`

## Error codes (selaras dokumentasi API) untuk dashboard

Prinsip: pakai code yang sudah umum dipakai di repo:

- `401` → `AUTH_UNAUTHORIZED`
- `403` → `AUTH_FORBIDDEN` atau `TENANT_FORBIDDEN`
- `404` → `NOT_FOUND` (atau code feature-specific yang sudah ada; mis. termination punya `TERMINATION_NOT_FOUND`)
- `422` → `VALIDATION_ERROR`
- `429` → `AUTH_TOO_MANY_ATTEMPTS` (jika throttle)

Tabel skenario (canonical):

| Scenario | HTTP | `error.code` |
|----------|------|--------------|
| Non-admin akses endpoint | `403` | `AUTH_FORBIDDEN` |
| Query `tab` invalid | `422` | `VALIDATION_ERROR` |
| Invoice not found | `404` | `NOT_FOUND` |
| Invoice bukan milik tenant aktif | `403` | `TENANT_FORBIDDEN` |
| Resend email gagal (provider) | `422` atau `502` | `VALIDATION_ERROR` (atau `NOT_FOUND` bila invoice invalid) |
| State mismatch terdeteksi saat action | `422` | `VALIDATION_ERROR` |

Jika perlu membedakan UI tanpa membuat variasi code baru, gunakan `error.message` yang spesifik + `details.field` (mis. `invoiceId`, `subscriptionId`) untuk menunjuk sumber masalah.

## Business rules (trial 1 bulan)

- Trial duration default: 30 hari dari `starts_at`
- Saat trial berakhir:
  - opsi A: otomatis set `expired` (job harian) dan nonaktifkan akses fitur berbayar
  - opsi B: auto-create invoice dan pindah ke `pending_payment`

Keputusan ini harus konsisten dengan service/subscription jobs yang sudah ada (jika sudah ada auto-management).

## Negative scenarios yang wajib ditangani di UI

- Company belum punya subscription → tampil “No subscription” + CTA ke subscriptions screen (admin-only)
- Subscription `pending_payment` tapi invoice belum ada → tampil warning badge “Invoice missing”
- Invoice sudah paid tapi subscription masih pending_payment → tampil warning “state mismatch”
- Email send gagal → tampil “Failed” + tombol “Retry send” (admin-only) jika disediakan endpoint

