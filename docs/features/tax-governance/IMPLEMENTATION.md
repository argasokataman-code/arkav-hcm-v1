# Tax Governance & Taxonomy Implementation

## Ringkasan Teknis

Temuan utama implementasi saat ini adalah adanya perbedaan antara surface web yang terlihat seperti modul pajak (`/tax-rates`) dan surface runtime yang benar-benar memengaruhi hasil payroll. Implementasi teknis yang aktif tidak berpusat di route tersebut.

## Target Arsitektur Yang Dikunci

Mengikuti keputusan produk final di [DECISION.md](DECISION.md), implementasi akan dipisah menjadi dua plane:

1. Runtime control plane:
  - policy/rate authoring, approval, publication, effective-dated snapshots.
  - dipakai langsung oleh payroll engine sebagai sumber hitung resmi.

2. Governance dashboard plane:
  - projection/read model lintas tenant subscribe untuk global admin.
  - fokus pada auditability, anomaly tracking, drift detection, dan compliance evidence.

Identifier strategy untuk entitas tax governance baru: UUID-only di kontrak publik.

## Subdomain Implementasi

1. **Tenant statutory tax implementation**
  - focus: tax profile employee, policy payroll tax, per-period tax output, tenant self-audit.

2. **Platform billing tax implementation**
  - focus: tax atas service fee platform berdasarkan subscription/invoice.
  - cycle support wajib: monthly, yearly, custom.
  - output wajib: taxable base, tax amount, total, rounding policy snapshot per invoice line.

## Surface Runtime Yang Aktif

### 1. Web shell `/tax-rates`

- Route web: `backend/routes/web.php`
- View: `backend/resources/views/tax-rates.blade.php`
- Guard: `hcm.web.admin`
- Temuan: halaman masih berisi contoh tabel VAT/GST/HST dan modal template. Belum ada query tenant, controller API, atau JS page module khusus yang memuat/menyimpan tax rate runtime.

### 2. Tax identity karyawan

- Write path: `backend/app/Services/Hcm/EmployeeSnapshotService.php`
- Store: `employee_tax_profiles`
- Data utama: `npwp`, `tax_status`, `ptkp_status`, `effective_date`, `end_date`
- Perilaku: saat payload employee memuat tax fields, service akan menormalisasi tax status lalu update/insert row efektif terbaru.

### 3. Validasi import employee

- Path: `backend/app/Http/Controllers/Api/HcmEmployeeController.php`
- Perilaku: import employee memvalidasi `tax_status` agar hanya menerima enum tax status Indonesia yang didukung; alias kompatibilitas lama tetap diterima untuk migrasi.

### 4. Master basis pajak payroll

- Path model/controller: `backend/app/Models/HcmSalaryComponent.php`, `backend/app/Http/Controllers/Api/HcmSalaryComponentController.php`
- Flag yang relevan:
  - `include_pph21_ter_gross`
  - `include_pph21_annual_reconciliation`
- Dampak: master komponen menentukan apakah nominal tertentu masuk ke basis bruto TER.

### 5. Engine kalkulasi payroll

- Path: `backend/app/Support/PayrollDraftBuilder.php`
- Perilaku utama:
  - membangun `taxableGross` dari upah pokok, tunjangan tetap, overtime, assignment custom, dan adjustment lain berdasarkan flag komponen;
  - membaca `employee_tax_profiles.tax_status`;
  - fallback ke `TK0` bila tax profile kosong;
  - menghitung PPh21 bulanan lewat lookup TER category A/B/C;
  - menulis payroll line `pph21_ter` dengan metadata audit.

## Bukti Bahwa `/tax-rates` Belum Menjadi Control Plane Aktif

- Tidak ditemukan endpoint `/v1/.../tax-rates` atau controller API tax governance yang aktif.
- Tidak ditemukan JS module page khusus untuk `/tax-rates` yang melakukan fetch/save data runtime.
- View `/tax-rates` berisi data contoh statis dan modal form template yang submit ke URL halaman yang sama tanpa kontrak backend mutasi.
- Kontrak API pajak yang aktif justru tersebar pada API employee, salary components, dan payroll.

## Dampak Implementasi Saat Ini

- Positif:
  - payroll bulanan sudah bisa menghitung PPh21 TER secara otomatis;
  - metadata payroll line cukup kaya untuk audit teknis dasar;
  - missing tax profile sudah terdeteksi sebagai anomaly runtime.
- Risiko:
  - operator dapat salah mengartikan `/tax-rates` sebagai sumber kebenaran;
  - governance pajak sulit dijelaskan ke auditor karena bukti tersebar;
  - perubahan tax basis menuntut koordinasi lintas employee master, salary component master, dan payroll run.

## Area Test Yang Sudah Ada

- `backend/tests/Feature/HcmPayrollApiTest.php`
  - memverifikasi line `pph21_ter`, source metadata, dan beberapa kombinasi tax status.
- `backend/tests/Feature/HcmEmployeeApiTest.php`
  - memverifikasi persistence `employee_tax_profiles`.
- `backend/tests/Feature/HcmSalaryComponentApiTest.php`
  - memverifikasi flag `includePph21TerGross` dan field terkait di CRUD master komponen.

## Gap Implementasi Yang Belum Ditutup

1. Tidak ada domain model resmi untuk tax rate/tax rule governance yang backing `/tax-rates`.
2. Tidak ada audit trail terpusat untuk perubahan taxonomy/rule pajak lintas modul.
3. Tidak ada dashboard coverage tax profile dan taxable component drift.
4. Tidak ada flow review annual reconciliation yang explicit walau metadata dasar sudah tersedia di master komponen.
5. Tidak ada guard UX yang menjelaskan bahwa `/tax-rates` masih placeholder/non-authoritative.
6. Belum ada data model UUID-first untuk domain tax governance baru.
7. Belum ada projection lintas tenant subscribe untuk governance dashboard global.
8. Belum ada billing tax engine resmi untuk domain biaya layanan aplikasi.
9. Belum ada laporan tenant self-audit pack dan platform billing tax pack yang terstandardisasi.

## Rekomendasi Implementasi Bertahap

1. Definisikan kontrak API tax governance UUID-only (OpenAPI + feature API doc).
2. Implementasikan model runtime control plane dengan effective-dated versioning dan publication states.
3. Implementasikan governance projection lintas tenant subscribe untuk global admin dashboard.
4. Tambahkan approval workflow dan immutable audit events untuk perubahan policy/rate.
5. Tambahkan test negatif agar `/tax-rates` tidak lagi misleading serta test integrasi runtime->governance projection.
6. Implementasikan billing tax snapshot per invoice untuk package monthly/yearly/custom.
7. Tambahkan reporting API untuk tenant self-audit pack dan global platform billing tax pack.