# UUID Migration - Ringkasan Eksekutif

Tanggal pembaruan: 19 April 2026

## Ringkasan

Dokumen ini menjadi ringkasan eksekutif untuk status migrasi UUID lintas domain. Fokusnya bukan satu fitur UI, tetapi kesehatan kontrak identifier, keamanan route/API, dan status transisi dari integer legacy ke UUID pada runtime aktif.

## Akses

- Audiens utama: backend engineer, reviewer API, dan tim audit runtime.
- Dokumen ini dipakai sebagai acuan keputusan teknis, bukan surface user-facing.

## UI Aktif

- Tidak ada halaman UI bisnis khusus.
- Evidence utama ada di tracker runtime database dan langkah operasional UUID migration.

## Flow Bisnis End-to-End

1. Tim mengecek status domain melalui tracker runtime.
2. Saat fixing runtime dilakukan, tim menjalankan migrate lalu retest scope terdampak.
3. Domain yang masih hybrid dicatat eksplisit sebagai compatibility layer, bukan dianggap selesai penuh.
4. Status lintas domain ditutup hanya bila tracker dan kontrak API sudah konsisten.

## Lifecycle Dan Keputusan Bisnis

- Target security/API UUID utama sudah dianggap selesai bila guard route, validasi, dan kontrak utama sudah aman.
- Full PK cutover baru dianggap selesai bila domain hybrid tidak lagi diperlukan.
- Domain hybrid dipertahankan sementara hanya untuk kompatibilitas transisi yang terkontrol.

## Integrasi

- OpenAPI dan API feature docs: status UUID harus selaras dengan kontrak aktif pada `docs/api/openapi.yaml` dan dokumen API per fitur.
- Reporting audit domain: domain yang masih hybrid perlu ditulis eksplisit di feature README terkait agar FE/BE tidak salah asumsi.
- Payroll, resignation, termination, reporting, dan feature HCM lain yang masih memakai UUID + numeric fallback harus merujuk tracker ini sebagai konteks migrasi lintas domain.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Jawaban cepat

Status proses UUID migration: **SUDAH selesai untuk target security/API, tetapi full PK cutover masih menyisakan domain hybrid**.

Yang sudah selesai saat ini:
- mayoritas tabel target security sudah UUID PK,
- OpenAPI target UUID utama sudah disapu,
- guard dasar (route + validasi) sudah banyak dipindahkan ke UUID.

Yang masih tinggal:
- area hybrid (`UUID + legacy integer`) masih ada di beberapa domain sebagai kompatibilitas transisi,
- tracker runtime masih menunjukkan tabel yang berstatus PROSES⚠️, jadi ini belum full cutover,
- gunakan [runtime-db-table-tracker.md](runtime-db-table-tracker.md) untuk detil bukti dan status per tabel.

Rule after fixing:
- setiap selesai fixing code yang menyentuh behavior runtime, jalankan dulu `cd backend && php artisan migrate --force`,
- setelah itu wajib rerun test pada scope terdampak,
- jangan klaim selesai tanpa bukti hasil migrate + test.

## Sumber kebenaran

1. Status runtime database real: [runtime-db-table-tracker.md](runtime-db-table-tracker.md)
2. Langkah operasional ringkas: [STEPS.md](STEPS.md)

## Cara pakai dokumen ini

- Kalau butuh jawaban cepat "sudah 100% belum": lihat bagian **Jawaban cepat** di file ini.
- Kalau butuh bukti detail per tabel/API: buka [runtime-db-table-tracker.md](runtime-db-table-tracker.md).
- Kalau butuh urutan kerja tim: buka [STEPS.md](STEPS.md).

## Status tinggalan

Checklist cepat untuk membaca apakah masih ada sisa:

- kalau tracker runtime masih punya `PROSES⚠️`, berarti masih ada tinggalan domain,
- kalau semua resource target sudah UUID namun ada tabel hybrid, berarti cutover belum penuh,
- kalau `Nothing to migrate` muncul setelah fixing, tetap lanjut ke retest dan catat hasilnya.

## Ruang lingkup yang sengaja dipisah

Dokumen lama yang sangat detail tetap disimpan untuk histori/audit, tapi **bukan** sumber keputusan harian.
Gunakan file ini + runtime tracker sebagai acuan utama tim.

## Existing Vs Target

- Existing: target security/API utama sudah selesai, tetapi beberapa domain masih hybrid UUID + integer legacy.
- Existing: rule migrate lalu retest setelah runtime fix masih wajib berlaku.
- Target: full cutover tanpa tabel/domain hybrid dan tanpa fallback numeric legacy.
