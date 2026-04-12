# Payroll — siklus proses (referensi produk)

Dokumen ini mengikat **alur 3 tahap** (pre-payroll → actual payroll → post-payroll) ke **modul yang sudah / akan ada** di Arcav, agar pengembangan fitur tidak “nyasar” dari proses administrasi nyata.

## Ringkasan 3 tahap

| Tahap | Tujuan | Status di Arcav (April 2026) |
|-------|--------|-------------------------------|
| **Pre-payroll** | Kebijakan, kumpulkan data, validasi | **Sebagian:** master komponen gaji, cuti/absensi/lembur, **profil karyawan + departemen/jabatan (FK)**, kebijakan (`policies`). Belum: cut-off payroll, “lock” periode, checklist validasi terpusat. |
| **Actual payroll** | Hitung gaji kotor → potongan → bersih, rekonsiliasi | **Berjalan (fondasi):** tabel **`hcm_payroll_periods` / `hcm_payroll_runs` / `hcm_payroll_lines`**, API **`POST .../calculate-draft`** (isi draft dari `base_salary` + `fixed_allowance` + master komponen), **`POST .../finalize`**, **`GET /payroll/my-slip-lines`** (self, hanya jika final). Masih **belum:** roll-up lembur periode, potongan % (BPJS/PPh), net pay terjamin, mesin TER penuh. |
| **Post-payroll** | Kepatuhan, pembayaran, buku besar, laporan | **Minimal:** karyawan bisa baca baris slip via API setelah final; **belum:** halaman `/payslip` di-wire, PDF, transfer batch, export pajak, jurnal GL, audit trail terpisah. |

## Pre-payroll — data yang jadi input wajib

- **Organisasi:** `employee_profiles.department_id` → `departments`, `designation_id` → `designations` (selaras master HR).
- **Status & kompensasi:** `employment_status`, `base_salary`, `fixed_allowance`.
- **Waktu & upah variabel:** absensi, cuti, lembur (sudah ada modul terpisah).
- **Kebijakan:** policies + referensi regulasi di master komponen gaji.

## Actual payroll — target integrasi

- Tarik snapshot karyawan aktif + kompensasi + komponen recurring + variabel periode.
- Hitung sesuai kategori komponen (`hcm_salary_components`) dan aturan ID (TER, BPJS, lembur ÷173, dll.) — detail per komponen di `docs/features/payroll-salary-components/README.md`.

## Post-payroll — target integrasi

- Slip final, file pemotongan, bukti setor, agregat untuk finance — **backlog** sampai mesin payroll periode ada.

## Alur target (ringkas) — sampai payslip di tangan karyawan

1. **Kunci input periode** — absensi / cuti / lembur + kompensasi & master komponen (sebagian sudah ada sebagai modul terpisah).
2. **Jalankan payroll** — hitung per karyawan (Phase 1: draft dari profil; berikutnya: variabel periode + aturan potongan).
3. **Approve / posting** — opsional kebijakan; saat ini digantikan **`finalize`** run (satu final per periode).
4. **Generate slip** — baris di `hcm_payroll_lines` untuk run `finalized`; UI PDF / export masih backlog.
5. **Akses karyawan** — `GET /v1/hcm/payroll/my-slip-lines`; halaman web **`/payslip`** + unduh file menyusul.
6. **Audit** — `finalized_at` / `finalized_by_user_id` pada run; log perubahan baris menyusul.

## To-do produk (urutan masuk akal)

1. ~~Struktur data dept/jabatan di profil (FK)~~ — landasan laporan & aturan.
2. ~~Definisi **periode payroll** + entitas **run** (`draft` / `finalized`)~~ — April 2026.
3. ~~**Payroll line** per karyawan per periode (komponen + jumlah) — versi minimal~~.
4. Wire **UI admin** (payroll run) + **UI `/payslip`** + export PDF / pajak — sesuai prioritas klien.
5. Roll-up **lembur disetujui** per periode ke baris slip; potongan dari master komponen.

## Tautan

- `docs/features/payroll-salary-components/README.md`
- `docs/features/payroll-runs/README.md`
- `docs/features/employee-salary/README.md`
- `docs/api/hcm-payroll-api.md`
- `docs/features/employees-organization/README.md`
- `docs/api/hcm-employees-api.md`
