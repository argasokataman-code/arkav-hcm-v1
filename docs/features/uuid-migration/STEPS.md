# UUID Migration - Steps Ringkas

Tanggal pembaruan: 19 April 2026

## Tujuan

Memberi urutan kerja praktis agar tim bisa menjawab 2 hal tanpa debat:
- sekarang statusnya sudah 100% atau belum,
- langkah berikutnya apa.

## Alur kerja wajib

1. Cek status real di database pada [runtime-db-table-tracker.md](runtime-db-table-tracker.md).
2. Cek gap API/runtime (route, controller, validation, OpenAPI) pada tracker yang sama.
3. Kerjakan gap per domain (billing, HCM, payroll, leave, dsb).
4. Tutup dengan regression test dan update status tracker.

## Definition of Done (100%)

Migrasi UUID dinyatakan 100% hanya jika semua terpenuhi:

- resource target tidak lagi memakai integer sebagai identifier publik utama,
- route/controller/validation sudah konsisten UUID,
- OpenAPI sinkron dengan runtime,
- regression test kritikal lulus,
- tracker runtime menyatakan tidak ada gap kritikal tersisa.

## Catatan penting

- Selama masih ada gap di checklist tracker, status tetap **BELUM 100%**.
- Jangan pakai dokumen plan lama sebagai sumber status final; gunakan runtime tracker.
