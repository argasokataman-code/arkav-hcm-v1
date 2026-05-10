# SaaS Tax Reporting API

Prefix: /v1/saas/tax
Guard: bearer token + global HCM admin / global super admin scope
Response envelope: { success, data?, error? }

## Purpose

Endpoint di prefix ini dipakai untuk pelaporan pajak platform tingkat SaaS, terpisah dari layar governance policy di /v1/hcm/tax-governance/platform-tax-compliance.

Boundary runtime:
- Halaman settings / compliance: mengelola policy, tarif, dan konfigurasi governance platform.
- Halaman tax reporting: membaca ringkasan dashboard, SPT PPN, SPT PPh 23, dan estimasi tahunan PPh Badan.

## Endpoints

### GET /active-ppn-rate

Query:
- Tidak ada.

Output utama:
- ppn_rate
- source (compliance_settings/default)
- billing_month (opsional)
- policy_version (opsional)

Kegunaan:
- Menyediakan tarif PPN aktif single source of truth untuk halaman SPT Platform.
- Jika policy government tax compliance aktif belum ada, endpoint mengembalikan fallback default runtime.

### GET /dashboard

Query:
- month: YYYY-MM, opsional
- ppn_rate: angka desimal, opsional

Output utama:
- revenue_summary
- tax_obligations
- total_kewajiban_pajak

Kegunaan:
- Ringkasan KPI pajak platform untuk dashboard reporting.

### GET /dashboard/export

Query:
- month: YYYY-MM, opsional
- ppn_rate: angka desimal, opsional
- format: xlsx, opsional, default xlsx

Format export yang didukung:
- xlsx only

Output:
- Binary XLSX ringkasan dashboard pajak platform.

### GET /spt-ppn

Query:
- month: YYYY-MM, opsional
- ppn_rate: angka desimal, opsional

Output utama:
- form_type
- summary
- detail_penyerahan

Kegunaan:
- Data SPT Masa PPN 1111 berbasis invoice periode berjalan.

### GET /spt-ppn/export

Query:
- month: YYYY-MM, opsional
- ppn_rate: angka desimal, opsional
- format: xlsx, opsional, default xlsx

Format export yang didukung:
- xlsx only

Output:
- Binary XLSX detail SPT PPN.

### GET /spt-pph23

Query:
- month: YYYY-MM, opsional

Output utama:
- form_type
- summary
- detail_pemotongan

Kegunaan:
- Data SPT Masa PPh 23 berbasis payment completed.

### GET /spt-pph23/export

Query:
- month: YYYY-MM, opsional
- format: xlsx, opsional, default xlsx

Format export yang didukung:
- xlsx only

Output:
- Binary XLSX detail SPT PPh 23.

### GET /spt-pph-badan

Query:
- year: YYYY, opsional, default tahun berjalan

Output utama:
- year
- form_type
- summary
- monthly_breakdown
- catatan

Kegunaan:
- Estimasi internal SPT Tahunan PPh Badan untuk monitoring platform.
- Bukan pengganti filing final DJP.

### GET /spt-pph-badan/export

Query:
- year: YYYY, opsional, default tahun berjalan
- format: xlsx, opsional, default xlsx

Format export yang didukung:
- xlsx only

Perubahan kontrak aktif:
- Export estimasi PPh Badan sekarang memakai Excel / XLSX.
- CSV tidak dipakai pada flow ini agar konsisten dengan rule export platform yang memakai Excel.
- Tarif PPN pada UI SPT Platform tidak lagi editable manual; tarif dibaca dari endpoint active-ppn-rate.

Output:
- Binary XLSX dengan content-type application/vnd.openxmlformats-officedocument.spreadsheetml.sheet

## Error Contract

### 401 Unauthorized

Token tidak valid atau tidak tersedia.

### 403 Forbidden

Actor bukan global admin yang berhak mengakses reporting lintas tenant.

### 422 Validation Error

Contoh:
- format selain xlsx pada endpoint export PPh Badan.

## UI Consumers

Halaman utama:
- /saas/platform-tax

Frontend runtime:
- frontend/resources/js/platform-tax.js

Catatan export runtime:
- Semua tab pada halaman SPT Platform sekarang memakai backend XLSX export.
- Flow window.print tidak lagi dipakai untuk Dashboard/SPT PPN/SPT PPh 23.

## Notes

- Endpoint ini hanya untuk reporting dan estimasi.
- Policy compliance tetap dikelola di domain tax governance platform compliance.
- Untuk filing final, hasil runtime harus direkonsiliasi dengan tim finance / accounting.
