# Export Reconciliation

## Ringkasan

Export Reconciliation adalah fitur kontrol sebelum aksi finansial yang irreversible seperti finalize payroll, disburse payroll/THR, post payroll, mark invoice paid, dan verify payment. Fitur ini memaksa operator mengekspor dataset ke CSV/XLSX, membandingkan angka sistem dengan verifikasi internal, lalu baru melanjutkan action berisiko.

## Akses

- Primary users: HCM Admin, Finance Admin, Accounting/Controller, dan internal auditor pembaca evidence.
- Non-primary users seperti customer/subscriber tidak diwajibkan menjalankan export manual ini.
- Gate action hanya relevan pada surface internal admin/operator.

## UI Aktif

- Saat ini runtime aktif terutama terhubung dari flow payroll/THR/PKWT dan billing melalui trigger export sebelum action sensitif.
- Browser mengunduh file evidence via `AuthApi.downloadV1Binary` setelah evidence berhasil dibuat.

## Flow Bisnis End-to-End

1. Operator berada di halaman/flow yang akan menjalankan action finansial sensitif.
2. Operator membuat export reconciliation untuk dataset yang relevan.
3. Sistem menyimpan evidence export lengkap dengan metadata siapa, kapan, dan filter apa yang dipakai.
4. Browser mengunduh file evidence.
5. Gate server memeriksa evidence valid sebelum mengizinkan finalize/disburse/mark-paid/verify.

## Lifecycle Dan Keputusan Bisnis

- Compare dulu, execute kemudian adalah prinsip utama modul ini.
- Evidence harus relevan dengan filter/periode/action yang sama dengan aksi yang akan dijalankan.
- Scope fase awal berfokus pada gate dan audit trail, belum pada rekonsiliasi dua arah otomatis dengan ERP eksternal.

## Integrasi

- Payroll Runs: gate finalize/disburse payroll run dan batch payroll terkait. Lihat `docs/features/payroll-runs/README.md`.
- Purchase Transactions, invoice, dan payment verification: billing action sensitif menjadi kandidat gate export. Lihat `docs/features/purchase-transaction/README.md`.
- Reporting: evidence export membantu audit dan investigasi saat angka finansial dipertanyakan. Lihat `docs/features/reporting/README.md`.
- Subscriptions dan Trial/Billing Dashboard: rollout gate di area billing harus sinkron dengan status invoice/subscription tenant. Lihat `docs/features/subscriptions/README.md` dan `docs/features/trial-billing-dashboard/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Documentation Structure

1. [README.md](README.md)
Ringkasan produk, alasan bisnis, scope, dan target outcome.

2. [IMPLEMENTATION.md](IMPLEMENTATION.md)
Desain teknis, alur sistem, data contract export, audit trail, dan rollout plan.

3. [API-CONTRACT.md](API-CONTRACT.md)
Draft endpoint untuk export + pre-action gate validation.

4. [TRACKING.md](TRACKING.md)
Status task implementasi lintas backend, frontend, QA, docs, dan security.

5. [E2E-TESTING.md](E2E-TESTING.md)
Skenario manual untuk validasi gate export sebelum finalize/payment.

---

## Existing Vs Target

- Existing: backend export endpoint dan gate prioritas runtime sudah aktif untuk flow tertentu, termasuk payroll/THR/PKWT.
- Existing: UI payroll dan flow terkait sudah bisa membuat evidence lalu memicu download file.
- Target: coverage gate lintas semua endpoint finansial berisiko dan rollout UI role polish masih berjalan.

## Catatan API (implementasi)

Endpoint `POST /v1/reconciliation/exports` mendukung payload UI yang sudah terpasang di payroll/THR/PKWT:

- `format` diperlakukan sebagai alias `fileFormat`
- `filters` diperlakukan sebagai alias `filterPayload`
- untuk `csv`, `filePath` boleh kosong: server akan menulis file evidence ke `reconciliation/generated/...` (disk `local`)
- setelah evidence berhasil dibuat dari UI payroll/THR/PKWT, browser memicu unduhan file via `AuthApi.downloadV1Binary` (GET evidence download dengan header token/tenant yang sama seperti API JSON).

Detail kontrak: [API-CONTRACT.md](API-CONTRACT.md) + `docs/api/openapi.yaml`.

---

## Kenapa Fitur Ini Dibutuhkan

Masalah operasional yang sering terjadi di area payroll dan billing:

- Aksi finalize/disburse/mark-paid sudah terlanjur jalan sebelum data direkonsiliasi.
- Tim harus audit manual setelah kejadian, yang biayanya lebih mahal.
- Koreksi pasca-proses berisiko menyebabkan perbedaan angka antar tim (Finance, HR, Accounting).
- Tidak ada bukti audit yang jelas bahwa data sudah di-review sebelum aksi berisiko.

Export Reconciliation dibuat untuk menutup gap ini dengan prinsip:

- compare dulu, execute kemudian,
- semua keputusan finansial penting punya evidence,
- dan setiap aksi berisiko dapat ditelusuri secara audit.

---

## Tujuan Produk

1. Menyediakan export dataset yang konsisten dengan state transaksi saat user akan mengeksekusi aksi berisiko.
2. Menjadikan export sebagai pre-check wajib pada flow tertentu.
3. Menyimpan jejak audit: siapa export, kapan export, dataset apa, lalu aksi apa yang dilakukan.
4. Menurunkan risiko salah bayar, salah posting, dan mismatch pelaporan.

---

## Scope

### In scope

- Export sebelum action untuk flow berikut:
  - Payroll run: finalize, disburse
  - THR batch: disburse, post payroll
  - PKWT compensation: post payroll, pay run
  - Invoices: mark paid
  - Payments: verify
- Format output minimal CSV dan XLSX.
- Gate logic: aksi diblokir jika belum ada export yang valid untuk filter/periode yang sama.
- Audit trail export dan action.

### Out of scope (fase awal)

- Rekonsiliasi otomatis dua arah dengan ERP eksternal.
- Rule engine approval multi-level yang kompleks.
- Auto-correction nilai transaksi.

---

## Primary Users

- HCM Admin
- Finance Admin
- Accounting/Controller
- Internal auditor (read evidence)

### Non-Primary Users

- Customer/subscriber (tenant user non-admin) tidak diwajibkan menjalankan export reconciliation manual.
- Flow customer tetap fokus ke status transaksi dan hasil akhir; kontrol export reconciliation diposisikan sebagai kontrol operasional internal admin/operator.

---

## Success Metrics (KPI)

- Persentase aksi finalize/payment yang punya evidence export valid: target >= 95%.
- Penurunan incident mismatch pasca-finalize/payment: target turun >= 60%.
- Waktu investigasi incident finansial: target turun >= 40%.
- Coverage endpoint berisiko yang sudah dilindungi gate export: target 100% pada scope fase 1.

---

## Risiko yang Dikurangi

- Salah nominal payout karena data belum final diverifikasi.
- Finalize run saat data masih berubah tanpa jejak snapshot.
- Invoice dianggap paid tanpa bukti rekonsiliasi.
- Sulit audit siapa memutuskan eksekusi dan berdasarkan dataset mana.

---

## Related Modules

- [Payroll Runs](../payroll-runs/README.md)
- [Payroll Items](../payroll-items/README.md)
- [Employee Salary](../employee-salary/README.md)
- [Purchase Transactions](../purchase-transaction/README.md)
- [Subscriptions](../subscriptions/README.md)
- [Reporting](../reporting/README.md)

---

## Status

Module version: 0.4 (Runtime backend active)
Status: In progress (backend + API + gate prioritas aktif, UI role polish berlanjut)
Last updated: 2026-04-15
