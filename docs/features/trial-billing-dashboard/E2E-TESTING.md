# Trial & Billing Dashboard — E2E TESTING (manual)

## Prasyarat

- Ada minimal:
  - 1 company status `trial`
  - 1 company status `active`
  - 1 company status `pending_payment` dengan invoice unpaid
- Admin account tersedia (HCM admin/operator).

## Skenario 1 — Admin bisa buka dashboard

- Login sebagai admin.
- Buka halaman dashboard trial/billing.
- Expected:
  - Tab **Trial** dan **Subscribed** tampil.
  - Non-admin tidak bisa akses (403/redirect sesuai implementasi web).

## Skenario 2 — Tab Trial menampilkan trial 1 bulan

- Buka tab Trial.
- Expected per row:
  - badge `TRIAL`
  - `trialEndsAt` sesuai rules (starts_at + 30 hari)
  - link “Detail invoice” kosong/disabled bila memang belum ada invoice pada trial.

## Skenario 3 — Tab Subscribed menampilkan paid vs pending

- Buka tab Subscribed.
- Expected:
  - company `ACTIVE` punya badge `ACTIVE` dan invoice terakhir `paid`.
  - company `PENDING_PAYMENT` punya badge `PENDING_PAYMENT` dan invoice `unpaid` + due date.

## Skenario 4 — Drill-down invoice detail

- Klik invoice terakhir pada salah satu row.
- Expected:
  - berpindah ke halaman detail invoice terpisah.
  - halaman detail menampilkan invoice id/no, amount_due, due_date, is_paid, subscription status, company, status email terakhir, dan tabel riwayat email penuh.

## Skenario 5 — Email status (automated) tampil

- Untuk invoice yang baru dibuat:
  - Expected status: `not_sent` lalu berubah menjadi `sent` setelah job berhasil.
- Untuk invoice yang gagal kirim:
  - Expected status: `failed` + tampil `last_error`.

## Skenario 6 — Negative: non-admin tidak bisa akses

- Login sebagai user non-admin.
- Akses URL dashboard.
- Expected:
  - ditolak (403) dan tidak ada data bocor.

## Skenario 7 — Negative: state mismatch

Kasus mismatch yang harus muncul warning:

- invoice sudah paid tetapi subscription masih `pending_payment`
- subscription `pending_payment` tetapi tidak ada invoice

Expected:
- UI menandai row dengan badge warning dan menyediakan link untuk investigasi (ke subscriptions/invoices).

## Skenario 8 — Duplicate subscription history tidak menggandakan row

- Siapkan 1 company dengan lebih dari 1 subscription history.
- Expected:
  - company hanya muncul satu kali pada tab yang sesuai.
  - row mengikuti subscription terbaru, bukan row lama.

