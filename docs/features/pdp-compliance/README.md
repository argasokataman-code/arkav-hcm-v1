# Kepatuhan UU PDP No. 27 Tahun 2022

**Status:** 🟢 COMPLY — Cycle 1-6 selesai (25 findings implemented)  
**Deadline Hukum:** Oktober 2024 (Pasal 74 — masa transisi 2 tahun sejak UU diundangkan, sudah terlewat)  
**Audit dilakukan:** 5 Mei 2026  
**Cycle 1-3 completed:** 5 Mei 2026 (test gate: 950 PHPUnit + 203 Vitest = 1153 tests ✓)  
**Cycle 4 completed:** 5 Mei 2026 (C5 encryption at-rest + H3 AI consent)  
**Cycle 5 completed:** 22 Juni 2026 (H2 breach notification + M2/M3 retention + M4 withdraw consent)  
**Cycle 6 completed:** 22 Juni 2026 (L1 T&C + M8 session timeout + H7 cookie consent + M6 photo biometric + L2 Data Saya + L4 payslip encryption)  
**Test coverage:** 121 PHPUnit + 23 Vitest = 144 PDP-specific tests ✓  
**Total temuan:** 25 (6 CRITICAL · 7 HIGH · 8 MEDIUM · 4 LOW) — **ALL RESOLVED**

---

## Apa itu UU PDP dan Kenapa Penting?

**UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi** adalah undang-undang Indonesia yang mengatur bagaimana sebuah organisasi boleh mengumpulkan, menyimpan, menggunakan, dan menghapus data pribadi seseorang.

ARCAV HCM sebagai platform SaaS HR memproses data pribadi dalam jumlah besar setiap hari:
- Data identitas karyawan (NIK, tanggal lahir, agama, status perkawinan)
- Data keuangan (nomor rekening bank, NPWP, slip gaji)
- Data biometrik (foto selfie untuk absensi)
- Data lokasi/GPS (koordinat check-in/check-out absensi)
- Data kontak pribadi (nomor telepon, alamat, emergency contacts)

**Jika melanggar UU PDP, sanksinya:**
- Pasal 57: Denda administratif **maksimal 2% dari pendapatan tahunan** per pelanggaran
- Pasal 67-68: Pidana **penjara 4-6 tahun** + denda **Rp 4-6 miliar** untuk pelanggaran data spesifik
- Reputasi: Kehilangan kepercayaan tenant/pelanggan, berisiko media coverage negatif

---

## Siapa yang Terdampak?

| Pihak | Peran sebagai | Data yang diproses |
|---|---|---|
| **ARCAV (kita)** | Pengendali Data Pribadi (Data Controller) | Data semua tenant + karyawan semua tenant |
| **Perusahaan tenant** | Pengendali + Pemroses (co-controller) | Data karyawan perusahaannya sendiri |
| **Karyawan tenant** | Subjek Data Pribadi | Data diri mereka sendiri |
| **Owner perusahaan** | Subjek Data Pribadi | Data saat onboarding |

---

## Flow Bisnis Saat Ini vs Target (Gap Overview)

### 1. Alur Onboarding Perusahaan Baru

**Saat ini (MELANGGAR):**
```
Calon tenant → Isi form onboarding (nama, email, telepon, alamat)
→ Submit → Data langsung disimpan
→ TIDAK ADA: consent checkbox, info tujuan penggunaan data, link privacy policy
```

**Target (COMPLY):**
```
Calon tenant → Isi form onboarding
→ Muncul ringkasan: "Data Anda akan digunakan untuk..."
→ Wajib centang: "Saya setuju Kebijakan Privasi dan Syarat & Ketentuan"
→ Wajib verifikasi email sebelum akun aktif
→ Timestamp consent disimpan di DB
→ Submit → Data disimpan BESERTA record consent
```

### 2. Alur Input Data Karyawan Baru

**Saat ini (MELANGGAR):**
```
HR Admin → Isi form tambah karyawan (NIK, tanggal lahir, agama, rekening bank)
→ Simpan → Langsung masuk DB plaintext
→ TIDAK ADA: persetujuan karyawan, disclosure tujuan, audit trail
→ NIK/NPWP/bank tersimpan tanpa enkripsi
```

**Target (COMPLY):**
```
HR Admin → Isi form tambah karyawan
→ Form menampilkan: "Data sensitif ini dikumpulkan atas dasar hukum hubungan kerja (Pasal 20 c UU PDP)"
→ Sistem catat audit trail: siapa yang input, kapan, IP address
→ Data sensitif (NIK, NPWP, nomor rekening) disimpan terenkripsi di DB
→ Karyawan mendapat notifikasi bahwa profilnya telah dibuat
```

### 3. Alur Absensi dengan Selfie dan GPS

**Saat ini (MELANGGAR):**
```
Karyawan → Check-in → Sistem ambil foto selfie + koordinat GPS
→ Disimpan permanen di DB
→ TIDAK ADA: info kepada karyawan bahwa biometrik diambil, consent khusus biometrik
```

**Target (COMPLY):**
```
Karyawan → Pertama kali pakai fitur absensi
→ Muncul: "Fitur absensi menggunakan foto wajah (biometrik) dan lokasi GPS untuk verifikasi kehadiran"
→ Karyawan centang persetujuan → Tersimpan sebagai consent record
→ Check-in diproses
→ Data selfie di-hash sebelum disimpan (bukan raw file yang mudah di-reverse)
→ Retensi: foto dan GPS dihapus otomatis setelah X tahun
```

### 4. Alur Penghapusan Data Karyawan (Right to Erasure)

**Saat ini (TIDAK ADA MEKANISME):**
```
Karyawan minta data dihapus → Tidak ada endpoint/prosedur → Data tetap ada selamanya
```

**Target (COMPLY):**
```
Karyawan → Ajukan "Permintaan Hapus Data" via portal
→ Diterima oleh admin → Direview dalam 3 hari kerja
→ Jika disetujui: User, EmployeeProfile, AttendanceRecord, AiChatLog di-soft delete
→ Karyawan menerima konfirmasi email
→ Setelah grace period (30 hari) → Hard delete otomatis
→ Log permintaan disimpan sesuai kewajiban audit
```

### 5. Alur Notifikasi Kebocoran Data (Breach Notification)

**Saat ini (TIDAK ADA):**
```
Terjadi kebocoran data → Tidak ada deteksi → Tidak ada notifikasi → Langsung melanggar Pasal 46
```

**Target (COMPLY):**
```
Terjadi insiden → Team security deteksi
→ Dalam 24 jam: Create security incident report di sistem
→ Dalam 3×24 jam: Kirim notifikasi ke subjek data yang terdampak (via email)
→ Dalam 3×24 jam: Lapor ke BSSN/lembaga pengawas
→ Log incident disimpan permanen
```

---

## Hak-Hak Subjek Data Pribadi (UU PDP Pasal 5-13)

Semua hak ini **sudah bisa** dipenuhi oleh sistem:

| Hak (Pasal) | Keterangan | Status Sistem |
|---|---|---|
| **Pasal 5** — Hak Informasi | Tahu data apa yang dikumpulkan dan tujuannya | ✅ Privacy Policy + Data Saya portal |
| **Pasal 6** — Hak Koreksi | Minta perbaikan data yang tidak akurat | ✅ Profile update + notification |
| **Pasal 7** — Hak Akses | Minta salinan data diri | ✅ `GET /v1/hcm/data-privacy/me/my-data` |
| **Pasal 8** — Hak Hapus | Minta data dihapus | ✅ Erasure request workflow |
| **Pasal 9** — Hak Tarik Persetujuan | Cabut persetujuan yang pernah diberikan | ✅ Withdraw consent (scope: ai_chat/biometric/all) |
| **Pasal 10** — Hak Keberatan | Keberatan atas pemrosesan tertentu | ✅ Withdraw consent + photo consent |
| **Pasal 11** — Hak Tunda/Henti | Minta penundaan pemrosesan sementara | ✅ Withdraw consent acts as processing stop |
| **Pasal 13** — Hak Portabilitas | Download data dalam format terstruktur | ✅ `GET /v1/hcm/data-privacy/me/my-data/export` |

---

## Status Kepatuhan per Area

| Area | Status | Temuan Utama |
|---|---|---|
| **Consent & Persetujuan** | 🟢 Done | Biometric, AI, photo, cookie consent — semua ada endpoint |
| **Informasi ke Subjek** | 🟢 Done | Privacy Policy proper, T&C rewritten, Data Saya portal |
| **Keamanan Data** | 🟢 Done | NIK/NPWP/bank encrypted at-rest, session timeout, payslip encryption |
| **Transfer ke Pihak Ketiga** | 🟢 Done | Disclosure di Privacy Policy (Midtrans, AI provider, Cloudflare) |
| **Hak Hapus Data** | 🟢 Done | Erasure request → admin approve → anonymize + soft-delete |
| **Notifikasi Breach** | 🟢 Done | Security incident CRUD + email notification job |
| **Audit Trail** | 🟢 Done | Export audit log + HCM activity log |
| **Retensi Data** | 🟢 Done | AI chat (1yr) + attendance (5yr) + erasure records (90d) auto-purge |
| **Hak Akses Mandiri** | 🟢 Done | Data Saya portal + export JSON |
| **DPO** | 🟡 Configured | Config `pdp.dpo_*` aktif, SK formal pending |

---

## Rencana Perbaikan (6 Siklus)

| Siklus | Fokus | Status | Evidence |
|---|---|---|---|
| **Cycle 1** | Quick wins: consent onboarding, email verif, privacy policy, export log | ✅ DONE | C1, C2, C6 + privacy policy rewrite + export audit log |
| **Cycle 2** | Consent karyawan, biometrik GPS, notifikasi perubahan data | ✅ DONE | C3, C4 biometric consent, M5 profile change notification |
| **Cycle 3** | SoftDeletes User/EmployeeProfile, right to erasure endpoint | ✅ DONE | H1 erasure mechanism, H5 export logging, H6 privacy policy |
| **Cycle 4** | Enkripsi NIK/NPWP/bank at-rest, AI Chat disclosure | ✅ DONE | C5 encryption + H3 AI consent |
| **Cycle 5** | Breach notification system, disclosure pihak ketiga, retensi data | ✅ DONE | H2 API+job+email, H4 disclosure, M2/M3 retention, M4 withdraw consent |
| **Cycle 6** | T&C rewrite, session timeout, cookie consent, photo biometric, Data Saya portal, payslip encryption | ✅ DONE | L1 T&C verified, M8 session-check API, H7 cookie-consent API, M6 photo_consent, L2 my-data + export, L4 PayslipEncryptionService |

---

## Dokumentasi Terkait

- [README.md (ini)](./README.md) — Ringkasan bisnis + status
- [AUDIT-REPORT.md](./AUDIT-REPORT.md) — 25 findings lengkap dengan evidence kode
- [FIX-PLAN-CYCLES.md](./FIX-PLAN-CYCLES.md) — Rencana teknis 6 siklus + exit criteria
- [IMPLEMENTATION.md](./IMPLEMENTATION.md) — Panduan implementasi teknis per finding
- [TRACKER.md](./TRACKER.md) — Status tracker real-time per finding

---

## Referensi Hukum

- **UU No. 27 Tahun 2022** tentang Pelindungan Data Pribadi
- **PP implementasi UU PDP** (dalam proses terbit — pantau update BSSN/Kominfo)
- **Pasal 74** — masa transisi 2 tahun: berlaku efektif Oktober 2024
- **Lembaga pengawas:** Komisi PDP (di bawah Presiden), BSSN untuk aspek keamanan siber
