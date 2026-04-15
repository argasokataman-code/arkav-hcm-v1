# Export Reconciliation - E2E Testing

Dokumen ini berisi skenario manual untuk memastikan gate export berjalan sebelum action finansial berisiko.

## Role Gate (wajib sebelum eksekusi)

1. HCM Admin / Finance Admin / Accounting:
- Boleh menjalankan export reconciliation.
- Boleh menjalankan action berisiko setelah evidence valid.

2. Karyawan / customer subscriber non-admin:
- Tidak menjadi aktor export reconciliation.
- Tidak boleh dipaksa menyelesaikan langkah export manual.
- Jika membuka flow self-service, hanya melihat status hasil transaksi/slip sesuai scope self.

## Preconditions

1. User admin valid dan punya akses ke modul target.
2. Data payroll run / invoice / payment tersedia.
3. Endpoint export sudah aktif pada environment uji.
4. Validasi role dilakukan dulu (admin vs non-admin) sebelum skenario action berisiko.

## Setup baseline manual UI E2E

1. Runtime lokal:
- `http://127.0.0.1:8007/health` harus `200`.
- `http://127.0.0.1:5179/login` dapat diakses.

2. Seed data:
- `cd backend && php artisan db:seed --class=SaasUiFlowSeeder`

3. Akun uji:
- Admin QA: `qa.login@example.com / StrongPass1`
- Non-admin contoh: `demo.owner01@example.com / StrongPass1`

4. Evidence run:
- Simpan screenshot + response code untuk tiap skenario.

## Scenario 1 - Payroll Finalize wajib export dulu

Langkah:

1. Buka halaman payroll run aktif.
2. Pastikan belum pernah export untuk run ini.
3. Tekan Finalize.

Expected:

- API menolak dengan `EXPORT_RECON_REQUIRED` (422).
- UI menampilkan instruksi untuk export terlebih dahulu.

Lanjut:

1. Lakukan export reconciliation.
2. Ulangi Finalize.

Expected:

- Finalize berhasil.
- Audit trail menyimpan reference evidence export.

Tambahan role check:

1. Login non-admin/costumer.
2. Pastikan tidak ada CTA export reconciliation pada halaman non-admin.

Expected:

- Tidak ada blokir UX yang meminta customer melakukan export manual.

## Scenario 2 - Payroll Disburse scope mismatch

Langkah:

1. Export untuk run A.
2. Coba disburse run B.

Expected:

- API menolak `EXPORT_RECON_SCOPE_MISMATCH`.
- Tidak ada line payment yang berubah.

## Scenario 3 - Invoice Mark Paid tanpa evidence

Langkah:

1. Pilih invoice status sent/issued.
2. Klik mark paid tanpa export.

Expected:

- Gagal `EXPORT_RECON_REQUIRED`.

Lanjut:

1. Export invoice reconciliation.
2. Mark paid ulang.

Expected:

- Berhasil.
- Evidence terkait tersimpan.

## Scenario 4 - Payment Verify dengan data stale

Langkah:

1. Export payment list.
2. Ubah data payment/invoice dari tab lain.
3. Verify payment dengan evidence lama.

Expected:

- Jika strict mode aktif: gagal `EXPORT_RECON_STALE_DATA`.
- Jika strict mode nonaktif: warning/log tetap tercatat.

## Scenario 5 - RBAC dan tenant boundary

Langkah:

1. Login non-admin.
2. Coba trigger export reconciliation.

Expected:

- Ditolak 403.

Langkah:

1. Login admin tenant A.
2. Akses evidence tenant B.

Expected:

- Ditolak 403/404 sesuai kebijakan.

## Scenario 6 - THR batch disburse/post-payroll gated for admin only

Langkah:

1. Login admin, buka flow THR batch.
2. Jalankan disburse/post-payroll tanpa evidence.

Expected:

- API menolak `EXPORT_RECON_REQUIRED` pada action yang digate.

Lanjut:

1. Buat evidence export untuk batch yang sama.
2. Ulang action disburse/post-payroll.

Expected:

- Action tidak lagi ditolak oleh gate reconciliation.

Role check:

1. Login non-admin/costumer.
2. Pastikan tidak ada permintaan export manual pada flow yang bukan scope admin.

Expected:

- Non-admin tidak diminta export manual; akses tetap mengikuti role endpoint.

## Scenario 7 - PKWT post-payroll gated for admin only

Langkah:

1. Login admin, jalankan post-payroll PKWT tanpa evidence.

Expected:

- API menolak `EXPORT_RECON_REQUIRED`.

Lanjut:

1. Buat evidence export untuk periode PKWT yang sama (`YYYY-MM`).
2. Ulang post-payroll.

Expected:

- Action tidak lagi ditolak oleh gate reconciliation.

## Evidence QA yang harus dikumpulkan

1. Screenshot UI sebelum dan sesudah export.
2. Response payload error code `EXPORT_RECON_*`.
3. Log audit yang menghubungkan export evidence ke action.
4. Catatan pass/fail per skenario.

## Manual Execution Log Template

| Tanggal | Environment | Role | Skenario | Hasil (Pass/Fail) | Bukti (screenshot/log) | Catatan |
|---|---|---|---|---|---|---|
| 2026-04-15 | local | HCM Admin | Scenario 1 | Pass | link/path | - |
| 2026-04-15 | local | Non-admin | Scenario 1 role check | Pass | link/path | Tidak ada CTA export di flow non-admin |

## Exit Criteria

- Semua skenario critical pass.
- Tidak ada bypass action tanpa evidence pada scope fase 1.
- Error message jelas dan bisa ditindaklanjuti user.
- Tidak ada flow customer/non-admin yang dipaksa export manual.
