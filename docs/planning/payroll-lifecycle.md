# Payroll — siklus proses (referensi produk)

Dokumen ini mengikat **alur 3 tahap** (pre-payroll → actual payroll → post-payroll) ke **modul yang sudah / akan ada** di Arcav, agar pengembangan fitur tidak “nyasar” dari proses administrasi nyata.

## Ringkasan 3 tahap

| Tahap | Tujuan | Status di Arcav (April 2026) |
|-------|--------|-------------------------------|
| **Pre-payroll** | Kebijakan, kumpulkan data, validasi | **Aktif dengan gap governance:** master komponen gaji, cuti/absensi/lembur, profil karyawan + assignment payroll item, dan metadata kontrak PKWT sudah menjadi input runtime. Belum: checklist validasi terpusat lintas modul dan lock/cut-off operasional yang benar-benar formal. |
| **Actual payroll** | Hitung gaji kotor → potongan → bersih, rekonsiliasi | **Aktif:** period/run/lines, calculate draft, finalize, void-before-paid, roll-up lembur approved, deduction engine runtime, THR batch → payroll purpose `thr`, dan PKWT compensation → payroll purpose `pkwt_compensation` sudah hidup pada surface yang dipublikasikan. |
| **Post-payroll** | Kepatuhan, pembayaran, buku besar, laporan | **Aktif sebagian:** `/payslip` web + PDF bulanan, slip THR PDF, disbursement payroll/THR, history payroll, dan gate export reconciliation sudah tersedia. Belum: jurnal GL formal, export pajak/back-office yang lengkap, dan audit append-only terpisah dari payload runtime. |

## Pre-payroll — data yang jadi input wajib

- **Organisasi:** `employee_profiles.department_id` → `departments`, `designation_id` → `designations` (selaras master HR).
- **Status & kompensasi:** `employment_status`, `base_salary`, `fixed_allowance`.
- **Recurring custom items:** assignment payroll item per karyawan dari feature payroll items.
- **Waktu & upah variabel:** absensi, cuti, lembur (sudah ada modul terpisah).
- **Kebijakan:** policies + referensi regulasi di master komponen gaji + aturan tahunan THR / metadata kontrak PKWT.

## Actual payroll — target integrasi

- Tarik snapshot karyawan aktif + kompensasi + komponen recurring + variabel periode.
- Hitung sesuai kategori komponen (`hcm_salary_components`) dan aturan ID (TER, BPJS, lembur ÷173, dll.) — detail per komponen di `docs/features/payroll-salary-components/README.md`.
- Bentuk run khusus saat diperlukan: `monthly` untuk payroll reguler, `thr` untuk Tunjangan Hari Raya, dan `pkwt_compensation` untuk kompensasi kontrak berakhir.

## Post-payroll — target integrasi

- Slip final employee, PDF bulanan, slip THR PDF, histori run, dan gate export reconciliation sudah aktif.
- File pemotongan/pajak penuh, jurnal GL, dan artefak finance formal masih menjadi backlog operasional berikutnya.

## Alur target (ringkas) — sampai payslip di tangan karyawan

1. **Kunci input periode** — absensi / cuti / lembur + kompensasi & master komponen (sebagian sudah ada sebagai modul terpisah).
2. **Jalankan payroll** — hitung per karyawan untuk run `monthly`, atau batch tahunan/kontrak untuk `thr` dan `pkwt_compensation`.
3. **Approve / posting** — runtime aktif memakai **`finalize`** untuk payroll reguler; koreksi pasca-final dilakukan via `void` selama belum ada line `paid`.
4. **Generate slip** — baris final tersimpan di `hcm_payroll_lines`; PDF bulanan dan slip THR sudah tersedia pada surface aktif.
5. **Akses karyawan** — `/payslip` memakai `my-slip`, `my-slip-latest-period`, dan `my-slip-pdf`; THR self-service memakai `my-thr-slip` + PDF per line.
6. **Audit** — `finalizedAt`, `finalizedBy*`, `voidedAt`, dan `voidedBy*` sudah ada pada runtime payroll run; audit append-only terpisah masih enhancement lanjutan.

## To-do produk (urutan masuk akal)

1. ~~Struktur data dept/jabatan di profil (FK)~~ — landasan laporan & aturan.
2. ~~Definisi **periode payroll** + entitas **run** (`draft` / `finalized`)~~ — April 2026.
3. ~~**Payroll line** per karyawan per periode (komponen + jumlah) — versi minimal~~.
4. ~~Wire **UI admin** (payroll run) + **UI `/payslip`** + export PDF dasar~~.
5. ~~Roll-up **lembur disetujui** per periode ke baris slip; potongan runtime dari master komponen~~.
6. Putuskan artefak kepatuhan lanjutan: jurnal GL, export pajak formal, dan audit terpisah bila bisnis membutuhkannya.

## Surface aktif per halaman sidebar payroll

- **Payroll Operations**
	- Compensation Management: `docs/features/employee-salary/README.md`
	- Process Monthly Payroll + Payroll History: `docs/features/payroll-runs/README.md`
	- THR Payroll: `docs/features/payroll-thr/README.md`
	- PKWT Compensation: `docs/features/payroll-pkwt-compensation/README.md`
- **Payroll Records & Setup**
	- Payslips: `docs/features/payslip/README.md`
	- Salary Components: `docs/features/payroll-salary-components/README.md`

Tracker evidence formal untuk surface sidebar payroll yang aktif:

- `docs/features/employee-salary/tracker.md`
- `docs/features/payroll-runs/tracker.md`
- `docs/features/payroll-salary-components/tracker.md`
- `docs/features/payslip/tracker.md`
- `docs/features/payroll-thr/tracker.md`
- `docs/features/payroll-pkwt-compensation/tracker.md`

## Tautan

- `docs/features/payroll-salary-components/README.md`
- `docs/features/payroll-runs/README.md`
- `docs/features/payslip/README.md`
- `docs/features/payroll-thr/README.md`
- `docs/features/payroll-pkwt-compensation/README.md`
- `docs/features/employee-salary/README.md`
- `docs/api/hcm-payroll-api.md`
- `docs/features/employees-organization/README.md`
- `docs/api/hcm-employees-api.md`
