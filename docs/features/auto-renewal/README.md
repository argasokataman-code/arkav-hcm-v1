# Auto-Renewal Module

## Ringkasan

Feature doc ini dipakai untuk menjelaskan flow bisnis, boundary implementasi, dan urutan kerja modul auto-renewal.

Kontrak endpoint tidak lagi disalin di folder feature ini agar tidak drift.

Sumber kerja harian yang kanonik sekarang hanya tiga file di folder ini:
1. [README.md](README.md) - konteks bisnis, boundary, lifecycle, dan dokumen mana yang wajib dipakai.
2. [IMPLEMENTATION.md](IMPLEMENTATION.md) - apa yang dibangun dan urutan fase implementasi.
3. [tracker.md](tracker.md) - status runtime aktual per task.

Untuk kontrak endpoint, referensi tunggal tetap di `docs/api/saas-renewal-monitoring-api.md` dan `docs/api/openapi.yaml`.

Prinsip utama yang dijaga:
- anti double charge
- anti duplicate renewal
- anti race condition
- aman terhadap retry job
- aman terhadap duplicate webhook
- audit-friendly
- siap multi payment gateway

## Dokumen Inti (Wajib)

1. [README.md](README.md)
- baca dulu untuk memahami boundary bisnis dan status fase saat ini
- feature doc ini tidak menjadi sumber kontrak endpoint

2. [IMPLEMENTATION.md](IMPLEMENTATION.md)
- pakai section "Mulai Implementasi Dari Mana" sebagai urutan eksekusi
- PR pertama wajib hanya Fase 1

3. [tracker.md](tracker.md)
- update status setiap task runtime
- tracker ini jadi sumber kebenaran progres implementasi

Kontrak API yang relevan:
1. `docs/api/saas-renewal-monitoring-api.md` untuk endpoint monitoring renewal
2. `docs/api/openapi.yaml` untuk kontrak OpenAPI kanonik

Catatan pembersihan dokumen:
- file `API.md` sudah tidak dipakai dan tidak boleh lagi dijadikan referensi
- lampiran teknis lama di folder ini sudah dikonsolidasikan/hapus
- kontrak endpoint sekarang tunggal di `docs/api`
- folder feature ini sekarang hanya menyisakan `README.md`, `IMPLEMENTATION.md`, dan `tracker.md`

## Lifecycle Resmi

Status lifecycle yang dipakai:
- trial
- active
- payment_pending
- grace_period
- inactive
- suspended
- expired
- cancelled

Catatan naming:
- runtime existing masih memakai pending_payment di beberapa area.
- dokumen ini mematok target naming payment_pending sebagai state contract final.
- untuk renewal delinquency, target lifecycle setelah grace period berakhir adalah `inactive`.
- `suspended` dipertahankan untuk enforcement non-billing seperti pelanggaran atau manual policy action.

## Flow Bisnis End-to-End

1. Scheduler/Cron scan subscription yang mendekati renewal_due_at.
2. Validasi eligibility renewal.
3. Ambil lock database dan jalankan transaksi atomik.
4. Buat pending invoice dengan snapshot plan/pricing/tax.
5. Attempt payment via gateway adapter.
6. Tunggu webhook async/payment confirmation.
7. Mark invoice paid.
8. Extend subscription period.
9. Simpan subscription event audit.
10. Kirim notifikasi lifecycle.

Aturan keras:
- subscription tidak boleh di-extend hanya karena invoice dibuat.
- subscription hanya boleh di-extend bila invoice status paid.

## Boundary

In scope:
- renewal recurring subscription
- invoice generation + snapshot
- payment retry + grace handling
- webhook idempotency + reconciliation
- event audit trail
- entitlement access mapping
- observability dan security controls
- global super admin renewal monitoring (status + reason + anomaly diagnostics)

Not in scope:
- proration engine upgrade/downgrade
- redesign total UI subscription non-billing

## Status

Module version: 1.0-doc-blueprint
Status: feature docs cleaned; API contract moved to canonical docs/api
Last updated: 2026-05-14