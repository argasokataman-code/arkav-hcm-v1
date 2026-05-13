# Tax Governance (PPh 21) Feature

## Ringkasan Bisnis

Tax Governance adalah modul operasional untuk memastikan perusahaan (tenant) mengelola kebijakan pajak penghasilan karyawan (PPh 21) secara benar, teraudit, dan terpisah dari domain BPJS. Modul ini dipakai untuk:

1. Menetapkan dan menerbitkan policy perhitungan PPh 21 (skema STATUTORY_PPH21 / TER).
2. Memastikan kelengkapan profil pajak karyawan (NPWP, status PTKP).
3. Menghasilkan snapshot compliance dan audit self-report untuk keperluan internal maupun external audit.
4. Memonitor anomali lintas tenant dan menghasilkan revenue capture otomatis dari domain events payroll.

## Scope Halaman dan API Aktif

Halaman web (guard `hcm.web.admin`):
1. `/tax-employees` (daftar employee tax profiles)
2. `/tax-employees/policies`
3. `/tax-employees/policies/{policyUuid}/edit`
4. `/tax-employees/tenant-compliance`
5. `/tax-employees/employee-tax-profiles`
6. `/tax-employees/reports`

API runtime (prefix `/v1/hcm/tax-governance`):

| Endpoint | Method | Keterangan |
|---|---|---|
| `/policies` | GET | Daftar policy tenant dengan filter status/tanggal |
| `/policies` | POST | Buat draft policy (idempotent via `draftKey`) |
| `/policies/{policyRef}` | GET | Detail policy (UUID-only) |
| `/policies/{policyRef}` | PATCH | Edit draft policy (optimistic lock via `version`) |
| `/policies/{policyRef}/submit` | POST | Submit draft ke status `submitted` |
| `/policies/{policyRef}/approve` | POST | Approve policy ke status `approved` |
| `/policies/{policyRef}/reject` | POST | Kembalikan policy `submitted|approved` ke `draft` |
| `/policies/{policyRef}/publish` | POST | Publish policy ke status `published` |
| `/policies/{policyRef}/events` | GET | Event history immutable |
| `/governance/dashboard` | GET | Dashboard lintas tenant untuk global admin |
| `/governance/anomalies` | GET | Registry anomali tenant |
| `/governance/break-glass/requests` | POST | Buat request break-glass |
| `/governance/break-glass/requests/{requestUuid}/approve` | POST | Approve request break-glass |
| `/governance/anomalies/{id}/resolve` | PATCH | Resolve anomali |
| `/governance/anomalies/{id}/acknowledge` | PATCH | Acknowledge anomali |
| `/reports/tenant-self-audit` | GET | Enhanced self-audit snapshot |
| `/reports/tenant-self-audit-export` | GET | Export self-audit (json/pdf) |
| `/reports/tenant-compliance-status` | GET | Compliance snapshot statutory + billing + employee profiles |
| `/platform-billing/policies` | GET/POST | Policy platform billing tax |
| `/platform-billing/reports` | GET | Report billing tax lintas tenant |
| `/platform-billing/invoices` | GET | Invoice snapshot billing tax |
| `/platform-tax-compliance/policies` | GET/POST | Kebijakan government layer |
| `/platform-tax-compliance/reports` | GET | Laporan tax payable & net profit platform |

## End-to-End Business Flow

### Flow Tenant (Perusahaan)

1. **Setup Policy**: Admin HR membuat draft policy PPh 21 (`POST /policies`) dengan skema STATUTORY_PPH21 atau TER, mencantumkan `regulationReference` (mis. `PP 58/2023 & PMK 168/PMK.03/2023`) dan `rateSchedules` sesuai kategori PTKP.
2. **Draft idempotent**: Pembuatan ulang dengan `draftKey` sama mengembalikan `200` alih-alih `201` — mencegah duplikasi policy.
3. **Edit draft**: Admin dapat mengedit selama status masih `draft` (`PATCH /policies/{policyRef}`), wajib kirim `version` untuk optimistic lock.
4. **Workflow aktif**: Tenant owner atau global admin kini dapat menjalankan transisi `submit → approve → publish` atau tetap publish langsung dari draft bila dibutuhkan untuk owner-direct mode.
5. **Policy efektif**: Policy dengan status `published` dan periode aktif digunakan sebagai baseline compliance saat snapshot diambil. Policy `draft` tidak dihitung.
6. **Supersede**: Publikasi policy baru dengan tanggal berlaku yang overlap dengan policy lama otomatis men-supersede policy lama.
7. **Compliance snapshot**: Admin HR atau auditor memanggil `GET /reports/tenant-compliance-status` untuk mendapatkan status kepatuhan tenant, mencakup:
   - Apakah ada policy `published` untuk bulan terpilih (`policy_configured`)
   - Kualitas profil pajak karyawan (`employee_pph21_compliance`): NPWP valid/invalid/missing, PTKP status, completion rate
   - Recommended actions untuk gap yang ditemukan
8. **Self-audit report**: Laporan enhanced dengan compliance checklist, change history, dan payroll impact tersedia via `GET /reports/tenant-self-audit`.

### Flow Platform (Super Admin)

1. Platform admin memantau compliance lintas tenant via dashboard dan anomaly registry.
2. Revenue platform domain subscription dan addon di-capture otomatis via event domain terkait.
3. Billing tax reports dan invoices tersedia untuk rekonsiliasi platform.
4. Halaman `platform-tax-compliance/policies` default ke mode **Overview (read-only)** untuk mengurangi risiko salah edit. Form edit hanya muncul setelah klik **Edit Konfigurasi Aktif** atau **Buat Konfigurasi Baru**, lalu simpan dilindungi dialog konfirmasi ringkasan perubahan.

## Lifecycle Policy Tenant

```
draft → published → superseded
               ↘ void
```

| Status | Arti Bisnis |
|---|---|
| `draft` | Policy sedang diedit; belum berlaku, tidak dihitung di compliance snapshot |
| `submitted` | Sudah disubmit dan menunggu approval / publikasi |
| `approved` | Sudah diapprove dan siap dipublikasikan |
| `published` | Berlaku aktif; dipakai sebagai baseline compliance saat efektif |
| `superseded` | Digantikan policy baru yang overlap; tetap bisa dibaca sebagai historis |
| `void` | Dibatalkan manual; tidak berlaku |

Setiap transisi status menghasilkan immutable audit event tersimpan di `hcm_tax_governance_policy_events`.

## Profil Pajak Karyawan — Kelengkapan dan Validasi

Compliance snapshot mengukur kualitas profil PPh 21 setiap karyawan aktif:

| Metrik | Penjelasan |
|---|---|
| `active_employees` | Total karyawan aktif tenant |
| `profiles_available` | Karyawan dengan record `EmployeeTaxProfile` |
| `complete_profiles` | NPWP valid (15-16 digit) DAN status PTKP terisi |
| `missing_npwp` | NPWP null atau kosong (belum diisi sama sekali) |
| `invalid_npwp_format` | NPWP terisi tapi format tidak valid (bukan 15-16 digit setelah normalisasi) |
| `missing_ptkp_status` | Status PTKP null atau kosong |
| `completion_rate` | `complete_profiles / active_employees * 100` (persen) |

**Validasi NPWP**: Format valid adalah 15 atau 16 digit setelah menghapus separator (`.` dan `-`). String placeholder seperti `INVALID-NPWP` dihitung sebagai `invalid_npwp_format`, bukan `missing_npwp`.

## Skema Perhitungan PPh 21

| Skema | Keterangan |
|---|---|
| `STATUTORY_PPH21` | Skema statutory berdasarkan PP 58/2023 & PMK 168/PMK.03/2023 (skema utama) |
| `TER` | Skema Tarif Efektif Rata-rata (Tarif Efektif Reguler); legacy, dinormalisasi ke statutory saat runtime |

Kategori PTKP standar:
- `TK/0`, `TK/1`, `TK/2`, `TK/3` (Tidak Kawin)
- `K/0`, `K/1`, `K/2`, `K/3` (Kawin)
- `K/I/0`, `K/I/1`, `K/I/2`, `K/I/3` (Kawin, penghasilan istri digabung)

## Keputusan Desain dan Percabangan

1. **Terpisah dari BPJS**: Domain PPh 21 dipisahkan penuh karena regulasi, proses audit, dan siklus update berbeda.
2. **UUID-only**: Semua identifier publik policy menggunakan UUID. Numeric legacy ID tidak lagi diterima pada path runtime Tax Governance.
3. **Optimistic lock**: Update draft wajib kirim `version` untuk mencegah race condition.
4. **Workflow owner-direct tetap didukung**: Submit/approve/reject/publish aktif di runtime, tetapi otorisasi masih berada pada tenant owner atau global admin. Approval chain multi-party formal belum ditambahkan.
5. **Owner-direct mode**: Policy tenant langsung bisa di-publish oleh tenant owner atau global admin tanpa approval chain.
6. **Revenue capture event-driven**: Capture revenue platform dilakukan otomatis via listener domain subscription/addon, tidak ada endpoint manual.
7. **Idempotency via `draftKey`**: Create policy dengan `draftKey` sama bersifat idempotent — aman di-retry tanpa duplikasi.

## Existing vs Target

| Aspek | Sebelumnya | Saat Ini |
|---|---|---|
| Domain | Dicampur dengan BPJS dalam satu area "tax" | Modul standalone Tax Governance terpisah |
| Skema | TER legacy | STATUTORY_PPH21 primary, TER masih diterima untuk transisi |
| Identifier | Numeric ID | UUID-only untuk policy path runtime |
| Workflow | Owner-direct | Owner-direct + explicit submit/approve/reject/publish aktif |
| Compliance snapshot | Tidak ada | Ada, termasuk employee_pph21_compliance (NPWP/PTKP quality) |
| Revenue capture | Manual / tidak ada | Event-driven otomatis via domain event platform |
| Anomaly registry | Tidak ada | Registry anomali tenant + resolve/acknowledge |

## Cross-check Role dan Permission

| Permission | Action | Scope |
|---|---|---|
| `tax.tenant.policy.view` | Lihat list policy, detail, event history, self-audit | Tenant sendiri |
| `tax.tenant.policy.draft.manage` | Buat/edit draft policy | Tenant sendiri |
| `tax.tenant.report.export` | Export self-audit, lihat compliance snapshot | Tenant sendiri |
| `tax.governance.dashboard.view_all` | Dashboard lintas tenant | Global |
| `tax.governance.anomaly.view_all` | Anomaly registry lintas tenant | Global |
| `tax.governance.break_glass.request` | Request break-glass | Global privileged |
| `tax.governance.break_glass.approve` | Approve break-glass | Global privileged |
| `tax.platform.policy.view` | Lihat policy platform billing/government layer | Platform domain |
| `tax.platform.policy.manage` | Kelola policy platform billing/government layer | Platform domain |
| `tax.platform.report.view_all` | Lihat report billing lintas tenant | Platform domain |
| `tax.platform.report.export_all` | Export invoice billing | Platform domain |

Web guard: semua halaman Tax Employees berada di guard `hcm.web.admin`.

## Gap dan Catatan Lanjutan

Semua gap prioritas sesi ini sudah ditutup:

1. **UUID migration**: Policy path runtime sekarang UUID-only. Frontend policy editor/list memakai UUID end-to-end.
2. **Workflow multi-step**: Endpoint `submit`, `approve`, `reject`, dan `publish` aktif. Audit event immutable tetap tercatat per transisi.
3. **Frontend halaman policies**: Halaman `/tax-employees/policies` dan `/tax-employees/policies/{policyUuid}/edit` aktif, termasuk tombol workflow di editor.
4. **Break-glass flow**: Endpoint request + approve sudah terpasang di routes dan controller, dengan persistence `hcm_tax_governance_break_glass_requests`.
5. **Dashboard lintas tenant**: Endpoint `GET /governance/dashboard` sudah aktif di routes runtime.
6. **PTKP otomatis**: Jika profile pajak belum memiliki PTKP, runtime menginfer default dari `EmployeeProfile.marital_status` (`single/divorced/widowed -> TK0`, `married -> K0`).

Catatan sisa (non-blocking):
- Approval chain multi-party formal belum ada; workflow aktif saat ini masih owner-direct / global-admin controlled.
- Inferensi PTKP otomatis saat ini hanya sampai level dasar `TK0/K0`; mapping tanggungan `TK1-TK3` / `K1-K3` tetap memerlukan data HR tambahan.

## NPWP Tenant & e‑Faktur (Panduan singkat)

1. NPWP tenant tidak selalu dibutuhkan untuk seluruh alur faktur. Kebutuhan NPWP tergantung pada status PKP tenant dan metode e‑Faktur yang dipakai:

   - Tenant **Badan PKP** (memiliki NPWP & PKP): **NPWP wajib** saat membuat e‑Faktur standar (faktur keluaran kode 01/02).
   - Tenant **Badan non‑PKP** atau **perorangan non‑bisnis**: dapat menggunakan **Faktur Digunggung** (kode 05) tanpa NPWP.

2. Rekomendasi UI/UX: jangan menampilkan pesan generik yang menyatakan "NPWP wajib" tanpa konteks. Tampilkan teks yang menjelaskan kondisi di atas dan sarankan verifikasi status PKP tenant sebelum membuat e‑Faktur.

3. Contoh teks ringkas untuk UI (Blade):

   "NPWP tenant diperlukan jika menggunakan e‑Faktur standar untuk tenant PKP. Untuk tenant non‑PKP, dapat menggunakan Faktur Digunggung tanpa NPWP. Verifikasi status PKP tenant sebelum membuat e‑Faktur."

4. Catatan implementasi:

   - Jika platform ingin memfasilitasi e‑Faktur otomatis, tambahkan field `tenant_npwp` dan `tenant_pkp_status` pada profil tenant, termasuk validasi format NPWP.
   - Pastikan alur pembuatan e‑Faktur memilih jenis faktur (standar vs digunggung) berdasarkan `tenant_pkp_status`.

## Panduan SPT Pajak Platform (Ringkasan Operasional)

### Cara Pakai
1. Pilih Masa Pajak (bulan & tahun) pada halaman `SPT Pajak Platform`.
2. Klik tombol **Hitung Kewajiban Pajak**.
3. Tab **Dashboard** menampilkan ringkasan KPI dan total kewajiban pajak untuk masa pajak yang dipilih.
4. Tab **SPT PPN** menampilkan rincian faktur keluaran per invoice (digunakan untuk formulir SPT Masa PPN — mis. 1111). Gunakan tombol ekspor untuk mengunduh CSV/Excel dengan kolom yang dibutuhkan oleh akuntan/DJP.
5. Tab **SPT PPh23** menampilkan rincian pemotongan per pembayaran dengan status `completed`. Gunakan ekspor untuk keperluan pelaporan / verifikasi.
6. Tarif PPN pada halaman ini bersifat read-only dan bersumber dari **Tax Compliance Settings** (government policy aktif). Jika ada perubahan regulasi, ubah policy di menu compliance, lalu muat ulang perhitungan SPT.

### Kolom Ekspor yang Direkomendasikan
- Untuk SPT PPN (per baris faktur): `invoice_no`, `issue_date`, `invoice_series` (e‑Faktur nomor/seri bila tersedia), `dpp` (Dasar Pengenaan Pajak), `ppn_rate`, `ppn_amount`, `buyer_npwp` (jika ada), `buyer_name`.
- Untuk SPT PPh23 (per baris pemotongan): `payment_ref`, `paid_at`, `invoice_ref` (jika relevan), `payer_npwp`, `payee_npwp`, `gross_amount`, `withholding_rate`, `tax_withheld`, `withholding_code`, `bank_ref`.

### Informasi Pajak (Ringkasan dan Batas Waktu)
- PPN: tarif 11% (UU HPP No. 7/2021). PPN Masa umumnya disetor dan dilaporkan mengikuti ketentuan fiskal (sering: setor paling lambat akhir bulan berikutnya; lapor lewat e‑Filing DJP). Pastikan mekanisme penyetoran dan bukti bank tercatat.
- PPh 23: tarif umum 2% (tergantung objek). Pemotongan dilakukan saat tenant membayar ke platform. Batas penyetoran biasanya tanggal 10 bulan berikutnya; pelaporan/penyampaian SPT Masa mengikuti ketentuan DJP (konfirmasi peraturan terbaru; sering ada tenggat pelaporan hingga tanggal 20 bulan berikutnya).
- PPh Final 0.5% (PP 23/2018): berlaku untuk wajib pajak tertentu; ambang omzet tahunan yang relevan adalah Rp 4.800.000.000 (empat koma delapan miliar rupiah) — pastikan verifikasi kriteria sebelum menerapkan PPh Final.

Catatan: semua angka di atas adalah estimasi yang disediakan sistem — wajib rekonsiliasi dan konfirmasi dengan akuntan/konsultan pajak sebelum penyetoran atau pelaporan ke DJP.

### Hal Wajib Dicek Sebelum Membuat e‑Faktur
- `tenant_npwp` harus terisi dan tervalidasi untuk pembuatan e‑Faktur standar (tenant PKP).
- `tenant_pkp_status` harus diketahui untuk memilih jenis faktur (standar vs digunggung).

### Disclaimer
- Data yang ditampilkan aplikasi adalah estimasi dan alat bantu; tidak menggantikan perhitungan akhir oleh akuntan.
- Simpan bukti pembayaran, nomor SSP, dan bukti setoran bank sebagai lampiran audit.

### Rekomendasi Implementasi Teknis
- Tambahkan field `tenant_npwp` (string) dan `tenant_pkp_status` (enum: `pkp`/`non_pkp`) pada profil tenant.
- Sediakan endpoint ekspor CSV/Excel untuk SPT PPN dan SPT PPh23 sesuai kolom yang direkomendasikan.
- Audit trail: catat siapa yang menjalankan perhitungan, timestamp, versi perhitungan, dan apakah ada override tarif.
- Tambahkan validasi format NPWP (15–16 digit setelah normalisasi) sebelum memungkinkan pembuatan e‑Faktur standar.

