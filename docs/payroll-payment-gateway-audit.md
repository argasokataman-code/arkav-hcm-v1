# Payroll Payment Gateway Audit

> **Date:** 2026-05-18
> **Scope:** Payroll Run (`disburse` flow) vs model payroll umum Indonesia / Asia
> **Context:** Apakah payment gateway diperlukan atau cukup export Excel?

---

## 1. Temuan: Implementasi `disburse()` Saat Ini

### Letak Code
- **File:** `backend/app/Http/Controllers/Api/Payroll/HcmPayrollRunController.php`
- **Method:** `disburse()` (line 296–541)
- **Route:** `POST /v1/hcm/payroll-runs/{id}/disburse`

### Yang Dilakukan `disburse()` — Analisis
| Langkah | Keterangan |
|---------|-----------|
| Validasi permission `payroll.disburse` | ✅ Benar |
| Cek gate reconciliation export | ✅ |
| Auto-finalize jika masih draft | ✅ |
| Hitung net pay per user | ✅ (dari payroll lines, addition - deduction) |
| Filter hanya net pay > 0 | ✅ |
| **Simpan `paymentStatus = 'paid'`** | ✅ tapi **SIMULASI SAJA** |
| **Set `gatewayReference = PAY-{timestamp}-{runId}-{random}** | ✅ format simulasi |
| **Set `paymentChannel = 'gateway-simulated'** | ✅ bukan gateway sungguhan |
| **Panggil Xendit / transfer uang sungguhan** | ❌ **TIDAK ADA** |

### Kesimpulan Kode
> **`disburse()` tidak mentransfer uang. Hanya menandai baris sebagai 'paid' di database.**
> Ini adalah **mock/simulasi disbursement**, bukan integrasi payment gateway sungguhan.

### XenditService — Import Tapi Tidak Dipakai di `disburse()`
- `XenditService` di-import (line 16) dan ada method `startMockHostedCheckout()` yang menggunakannya untuk **flow checkout terpisah**.
- Tapi di method `disburse()` yang jadi alur utama payroll, `XenditService` **tidak pernah dipanggil**.

---

## 2. Model Payroll Umum: Indonesia / Asia Tenggara

### 2.1. Alur Payroll Dominan (Enterprise + UKM)

```
1. HITUNG
   Gross Salary + Allowances - Deductions (PPh21, BPJS, pinjaman, dll)
   ↓
2. VALIDASI
   Approve / Finalize oleh finance/HR
   ↓
3. DISBURSEMENT
   ┌─ A. UKM (< 50 karyawan): Generate Excel → transfer manual via mobile banking
   ├─ B. Medium (50-500): Generate file format bank (BCA/XLS/CSV) → upload ke bank portal
   ├─ C. Enterprise (500+): API langsung ke bank (BI-FAST / host-to-host) atau payroll BPO
   └─ D. Payroll SaaS (Gadjian, Talenta): Punya aggregator bank sendiri (kemitraan)
   ↓
4. REPORTING
   Payslip PDF, Bank Transfer Summary, PPh21 Annual, BPJS Report
```

### 2.2. Payment Gateway untuk Payroll?
| Gateway | Cocok untuk Payroll? | Alasannya |
|---------|---------------------|-----------|
| Xendit Invoice | ❌ Tidak | Invoice = customer bayar ke merchant. Payroll = perusahaan bayar ke karyawan. Arahnya terbalik. |
| Xendit Disbursement | ⚠️ Mungkin | Xendit punya API disbursement, tapi butuh KYC + saldo mengendap. |
| Midtrans | ❌ Tidak | Sama, untuk payment collection, bukan payout. |
| Bank API langsung (BCA, Mandiri) | ✅ Ya | Tapi butuh kerjasama bisnis + IP whitelist + biaya integrasi. |
| BI-FAST | ✅ Ya | Standar nasional transfer antar bank, real-time. Tapi butuh licensed participant. |
| **Excel/CSV → upload bank portal** | ✅ **Paling umum** | Semua bank Indonesia support upload file batch transfer. |

### 2.3. Realitas Pasar
```
┌─────────────────────────────────────────────────────────┐
│ 90% UKM di Indonesia:                                 │
│   "Saya hitung gaji, export Excel, tinggal upload      │
│    ke BCA/ Mandiri/ BSI portal, transfer otomatis."    │
├─────────────────────────────────────────────────────────┤
│ 8% Medium Enterprise:                                  │
│   "Kami pakai file format bank, upload ke portal        │
│    cash management, approval 2 layer."                 │
├─────────────────────────────────────────────────────────┤
│ 2% Enterprise / Payroll SaaS:                          │
│   "API langsung ke bank / BI-FAST / partnership bank." │
└─────────────────────────────────────────────────────────┘
```

---

## 3. Rekomendasi

### Rekomendasi Utama: **Hapus / Simplify `disburse`, Fokus ke Export + Payslip**

| No | Aksi | Prioritas | Alasan |
|----|------|-----------|--------|
| 1 | **Generate Excel/CSV batch transfer per bank format** | 🔴 **HIGH** | Kebutuhan #1 semua UKM/menengah. Format: BCA (XLS), Mandiri (CSV), BRI/BSI. |
| 2 | **Payslip PDF per karyawan** | 🔴 **HIGH** | Wajib compliance: UU No.27/2022 tentang Perlindungan Data Pribadi + UU Ketenagakerjaan. |
| 3 | **Export payroll summary per periode** | 🟡 MEDIUM | Rekap per departemen, total biaya gaji, PPh21, BPJS. |
| 4 | **Payment Gateway (opsional)** | 🟢 **RENDAH** | Hanya relevan jika target pasar = SaaS payroll aggregator. Butuh partnership bank. |

### Rekomendasi Teknis: Ubah `disburse()` menjadi `Generate Transfer File`

```
Sebelum:
  disburse(Request) → mark paid di DB + gateway-simulated

Sesudah:
  export-transfer-file(Request) → return file:
    - Format: CSV / XLSX (BCA, Mandiri, Bri, BSI)
    - Columns: account_number, bank_code, amount, employee_name, description
    - Tidak mengubah status di DB (biarkan paymentStatus tetap unpaid)
    - Admin tinggal upload file ini ke bank portal masing-masing
```

### Flow Baru yang Direkomendasikan

```
PAYROLL RUN (finalized)
    ↓
(optional) Send payslip PDF email ke karyawan
    ↓
GENERATE TRANSFER FILE (.xlsx / .csv)
    ↓
Admin download → upload ke bank portal → transfer selesai
    ↓
(optional) Admin kembali ke app → tandai manual "Sudah Dibayar"
    ↓
Selesai
```

### Catatan: Kenapa Payment Gateway Tidak Prioritas

1. **Arah pembayaran salah**: Xendit/Midtrans adalah `collection gateway` (customer → merchant), bukan `disbursement gateway` (company → employee).
2. **Biaya tinggi**: Setiap transfer via gateway kena fee 1-2% × jumlah karyawan. Untuk payroll 100 orang × Rp5jt = fee Rp5-10jt/bulan.
3. **Kompleksitas operasional**: Butuh saldo mengendap, settlement reporting, reconciliation dengan bank statement.
4. **Target pasar**: Mayoritas user HCM adalah perusahaan yang sudah punya rekening korporasi dan akun bank portal. Mereka butuh file, bukan API transfer.

### Jika Tetap Ingin Integrasi Pembayaran di Masa Depan
- Bukan payment gateway (Xendit Invoice), tapi **disbursement API bank**:
  - BCA Host-to-Host / BCA Virtual Account Payout
  - Mandiri Cash Management
  - BI-FAST (butuh licensed participant atau aggregator seperti Ayoconnect, Finfleet)
- Atau partnership dengan **payroll aggregator** (Gaji.id, Talenta, etc.)

---

## 4. Kesimpulan

> **Payroll Arcav HCM saat ini sudah over-engineering di `disburse` dengan simulasi payment gateway yang tidak realistis.**
>
> **Rekomendasi:**
> - ✅ **YA** — payroll cukup sampai generate Excel/CSV + payslip PDF
> - ❌ **TIDAK** — tidak perlu payment gateway untuk MVP / UKM / menengah
> - Ubah `disburse()` menjadi `export-transfer-file()` dengan format bank Indonesia
> - Simpan payment gateway hanya sebagai opsi add-on untuk target enterprise

### Referensi
- UU No.27/2022 tentang PDP
- UU No.13/2003 tentang Ketenagakerjaan (PPh21, BPJS)
- Praktik umum payroll Indonesia