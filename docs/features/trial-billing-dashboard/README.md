# Trial & Billing Dashboard (Company List)

## Dokumentasi

- **README.md** (ini) — overview, definisi status, dan apa yang tampil di list
- **IMPLEMENTATION.md** — rencana teknis (data model, query, API/UI wiring, email automation)
- **E2E-TESTING.md** — skenario manual (happy + negative)

## Tujuan

Admin internal butuh satu layar untuk memantau semua **Company/Tenant** dengan status:

- **TRIAL (1 bulan)**
- **PAID / ACTIVE**
- (opsional tapi sangat penting operasional) **PENDING_PAYMENT**

Layar ini harus:

- menampilkan badge status dengan jelas,
- menampilkan ringkasan invoice terakhir (amount, due date, paid/unpaid),
- menampilkan status email invoice (sudah terkirim / gagal / belum terkirim),
- menyediakan drill-down ke detail invoice dan riwayat pengiriman email.

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

