# Trial & Billing Dashboard (Company List)

## Ringkasan

Fitur ini menyediakan satu layar operasional untuk memantau semua company/tenant berdasarkan status trial, active, dan pending payment. Dashboard ini dipakai admin internal untuk membaca kesehatan billing tenant secara cepat, termasuk invoice terakhir dan status pengiriman email invoice.

## Akses

- Hanya admin internal platform yang boleh melihat list maupun detail invoice.
- Non-admin harus ditolak di UI dan API.

## UI Aktif

- Halaman overview: `/saas/billing-overview`.
- Halaman detail invoice: `/saas/billing-overview/invoices/{invoice}`.

## Flow Bisnis End-to-End

1. Admin membuka `/saas/billing-overview`.
2. Sistem menampilkan daftar company berdasarkan subscription terbaru dan status trial/billing saat ini.
3. Admin membaca badge status, invoice terakhir, dan email status untuk tiap company.
4. Jika butuh audit lebih dalam, admin membuka halaman detail invoice terpisah untuk melihat riwayat email lengkap.

## Lifecycle Dan Keputusan Bisnis

- Trial, pending payment, dan active mengikuti kombinasi `subscriptions` dan `invoices`.
- Dashboard harus selalu memakai subscription terbaru per company agar tidak menampilkan status history yang sudah usang.
- Mismatch state tetap harus ditampilkan sebagai warning operasional, bukan disembunyikan.

## Integrasi

- Subscriptions: status row dashboard mengikuti subscription terbaru per company. Lihat `docs/features/subscriptions/README.md`.
- Purchase Transactions dan invoice/payment surfaces: ringkasan invoice terakhir dan email status berasal dari ekosistem billing yang sama. Lihat `docs/features/purchase-transaction/README.md`.
- Domain Management: konteks tenant operasional sering dibaca bersamaan dengan domain custom tenant. Lihat `docs/features/domain-management/README.md`.
- Reporting dan Super Admin Dashboard: health billing dan mismatch state menjadi input dashboard/platform analytics yang lebih luas. Lihat `docs/features/reporting/README.md` dan `docs/features/super-admin-dashboard/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Dokumentasi

- **README.md** (ini) — overview, definisi status, dan apa yang tampil di list
- **IMPLEMENTATION.md** — rencana teknis (data model, query, API/UI wiring, email automation)
- **E2E-TESTING.md** — skenario manual (happy + negative)

## Existing Vs Target

- Existing: list overview sudah menampilkan badge mismatch, drill-down detail invoice memakai halaman terpisah, dan riwayat `invoice_email_logs` sudah bisa dibaca dari UI.
- Target: detail kontrak API dan automasi email/billing masih perlu pematangan lanjutan di implementation doc.

## Tujuan

Admin internal butuh satu layar untuk memantau semua **Company/Tenant** dengan status:

- **TRIAL (1 bulan)**
- **PAID / ACTIVE**
- (opsional tapi sangat penting operasional) **PENDING_PAYMENT**

Layar ini harus:

- menampilkan badge status dengan jelas,
- menampilkan ringkasan invoice terakhir (amount, due date, paid/unpaid),
- menampilkan status email invoice terakhir (sudah terkirim / gagal / belum terkirim),
- menyediakan halaman detail invoice terpisah dengan riwayat email penuh.

## Akses & role

- **Hanya admin** (HCM admin / operator platform) boleh melihat list dan detail invoice.
- Non-admin harus mendapat `403` dari API dan tidak melihat menu/tab di UI.

## Definisi status (flagging)

Sumber kebenaran: `subscriptions` + `invoices`.

- **TRIAL**:
  - `subscription.status = trial`
  - `trial_ends_at` terisi
  - default durasi trial: **30 hari** (1 bulan)
- **PENDING_PAYMENT**:
  - `subscription.status = pending_payment`
  - ada invoice aktif yang belum paid (by `subscription_id`/`company_id`)
- **PAID / ACTIVE**:
  - `subscription.status = active`
  - invoice terakhir untuk subscription sudah paid (atau activation terjadi via payment verify)

Aturan tampilan:

- Dashboard mengambil **subscription terbaru** per company, bukan seluruh history row.
- Jika company punya subscription lama dan terbaru berbeda status, row mengikuti status terbaru itu.
- Jika state mismatch terjadi, row menampilkan badge warning operasional (`STATE_MISMATCH` atau `INVOICE_MISSING`).

## Tampilan list (dua tab)

UI menampilkan dua tab:

1. **Trial**
2. **Subscribed**

Ketentuan:

- Tab **Trial** menampilkan company dengan status `trial`.
- Tab **Subscribed** menampilkan:
  - `active` (paid)
  - `pending_payment` (belum bayar) dengan badge berbeda

## Kolom minimal per company row

- Company: `code`, `name`
- Status badge: `TRIAL` / `ACTIVE` / `PENDING_PAYMENT`
- Trial end / subscription end date
- Paket: `planCode` / package name
- Invoice terakhir:
  - invoice no/id, `amount_due`, `due_date`, `is_paid`
  - `subscription_id` (untuk konsistensi)
- Email status:
  - `sent_at` (terakhir terkirim)
  - `last_error` (jika gagal)

Catatan: field email status mungkin perlu ditambahkan di data model invoice atau tabel log (lihat IMPLEMENTATION).

## Status implementasi saat ini

- List overview sudah menampilkan badge mismatch untuk kasus invoice sudah paid tetapi subscription masih `pending_payment`, dan untuk kasus subscription `pending_payment` tanpa invoice terbaru.
- Drill-down detail invoice sekarang memakai halaman terpisah, bukan modal.
- Halaman detail invoice memuat riwayat penuh `invoice_email_logs` agar audit kirim email bisa dibaca dari UI.

