# Payroll — periode & run (Phase 1)

## Tujuan

Menutup **gap** antara master komponen gaji + kompensasi profil menuju **slip gaji per karyawan**: entitas periode, run draft/final, baris slip minimal.

## Alur produk (target penuh)

Lihat **`docs/planning/payroll-lifecycle.md`** — pre-payroll → actual → post-payroll. Implementasi kode saat ini baru menutup **inti actual** (draft hitung + finalisasi + baca baris self-service).

## API

`docs/api/hcm-payroll-api.md` — `payroll-periods`, `payroll-runs`, `payroll/my-slip-lines`, THR mass batch (`thr-batch`), run `purpose` **`monthly`** vs **`thr`**.

Tambahan endpoint aktif saat ini:

- `GET /payroll-periods/active` untuk mengambil periode payroll yang sedang open/aktif.
- `GET /payroll-runs/history` untuk daftar histori run bulanan (dengan filter + pagination).
- `GET /payroll-runs/{id}` mengembalikan detail run termasuk `auditTrail`.

## UI

- Halaman template **`/payslip`** belum di-wire ke API (backlog).
- Slip THR karyawan tidak punya halaman web terpisah; gunakan API **`GET /payroll/my-thr-slip`** dan unduhan PDF **`GET /payroll/thr-batch/lines/{line}/slip`** (self).
- Halaman **`/payroll-run`** sekarang dikunci ke **periode aktif** (tahun readonly, bulan disabled) agar eksekusi bulanan tidak mengambil periode historis.
- Halaman historis dipisah di **`/payroll-run-history`** untuk melihat run lama dan detail audit trail.

## Aturan bisnis (Phase 1)

- Draft: setiap karyawan **active/probation** selalu dapat minimal satu baris **upah pokok** (boleh 0); tunjangan tetap hanya jika nominal > 0.
- **Finalize** ditolak (`PAYROLL_RUN_EMPTY`) jika run tidak punya baris (mis. tidak ada karyawan eligible).
- Setelah finalize, periode induk bertanda **`posted`**.
- **THR mass:** halaman **`/payroll-thr`** — generate daftar dari profil + cut-off tahunan; assign mem-post run **`thr`** (komponen `thr`) untuk bulan **`paymentDate`**; `my-slip-lines` menggabung baris final monthly dan thr per bulan kalender.

## Reset QA THR

- **Artisan (disarankan):** `php artisan hcm:reset-thr-test-data` — lihat opsi `--year`, `--full`, `--keep-settings`, **`--fresh-slip-numbers`** (ganti `thr_slip_public_no` per baris).
- **SQL MySQL:** `THR_RESET_QUERY.sql` — query siap jalan (set `@thr_year`). Penjelasan & verifikasi: `THR_RESET_MANUAL.sql`.

## Backlog teknis

- Agregasi lembur disetujui per periode ke baris slip.
- Potongan (BPJS, PPh, dll.) dari `default_percent` master komponen.
- Slip PDF, unduhan, audit append-only, void run, kunci periode pre-payroll.
