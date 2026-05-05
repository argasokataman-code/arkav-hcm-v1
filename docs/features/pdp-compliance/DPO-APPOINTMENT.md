# Penunjukan Data Protection Officer (DPO) — Template & Panduan

**Dokumen:** Formal DPO Appointment (M7c — UU PDP No. 27/2022)  
**Status:** Template tersedia — menunggu tindakan manajemen  
**Terakhir diperbarui:** 5 Mei 2026

---

## Dasar Hukum

- **Pasal 53–54 UU PDP No. 27/2022**: Pengendali data *wajib* menunjuk Petugas Pelindungan Data Pribadi (DPO/PPDP) jika pemrosesan data berskala besar, menyangkut data sensitif, atau dilakukan secara sistematis dan rutin.
- ARCAV HCM memproses data karyawan (NIK, biometrik, rekening, NPWP, GPS) secara rutin untuk seluruh tenant — kewajiban DPO berlaku.

---

## Konfigurasi Runtime Saat Ini

File: `backend/config/pdp.php`

```php
'dpo_name'           => env('PDP_DPO_NAME',  'Data Protection Officer'),
'dpo_email'          => env('PDP_DPO_EMAIL', 'dpo@arcav.id'),
'privacy_contact_url'=> env('PDP_PRIVACY_CONTACT_URL', '/contact'),
```

Nilai ini digunakan di:
- `backend/resources/views/misc/privacy-policy.blade.php` (kontak DPO publik)
- `backend/resources/views/emails/security/breach-notification.blade.php` (notifikasi breach)
- `backend/resources/views/emails/privacy/consent-withdrawal-confirmation.blade.php` (konfirmasi withdraw consent)

**Action operator:** Set variabel environment di production `.env`:
```env
PDP_DPO_NAME="[Nama Lengkap DPO]"
PDP_DPO_EMAIL="dpo@[domain-perusahaan]"
PDP_PRIVACY_CONTACT_URL="https://[domain-perusahaan]/kontak-privasi"
```

---

## Template Surat Penunjukan DPO

> Salin template di bawah, isi sesuai detail perusahaan, tandatangani oleh direktur/CEO, dan simpan sebagai dokumen internal resmi.

---

**SURAT KEPUTUSAN PENUNJUKAN**  
**PETUGAS PELINDUNGAN DATA PRIBADI (DPO)**  

Nomor: [SK-DPO-YYYY-NNN]  
Tanggal: [DD MMMM YYYY]

---

**Yang bertanda tangan di bawah ini:**

Nama  : [Nama Direktur/CEO]  
Jabatan : Direktur Utama / Chief Executive Officer  
Perusahaan : [Nama PT/CV]  
Bertindak atas nama : [Nama Entitas Legal Pengendali Data]

**MEMUTUSKAN:**

**Menetapkan:**

| | |
|---|---|
| **Nama** | [Nama Lengkap DPO] |
| **Jabatan internal** | [Jabatan, contoh: IT Security Manager / Legal Compliance Officer] |
| **Email resmi DPO** | dpo@[domain] |
| **Nomor HP/WA** | [+62...] |

sebagai **Petugas Pelindungan Data Pribadi (Data Protection Officer)** PT/CV [Nama Perusahaan], berlaku mulai **[DD MMMM YYYY]**.

---

**Lingkup Tanggung Jawab DPO:**

1. Memantau kepatuhan internal terhadap UU PDP No. 27/2022 dan regulasi terkait.
2. Memberikan saran kepada manajemen terkait kewajiban pelindungan data pribadi.
3. Menjadi titik kontak utama dengan subjek data yang mengajukan permohonan hak (akses, koreksi, penghapusan, penarikan consent).
4. Berkoordinasi dengan BSSN jika terjadi pelanggaran data pribadi (Pasal 46 UU PDP) dalam batas waktu **14 × 24 jam** sejak insiden terdeteksi.
5. Mengelola dan memperbarui Kebijakan Privasi dan dokumentasi kepatuhan.
6. Melakukan privacy impact assessment (PIA) untuk sistem baru yang memproses data sensitif.

---

**Wewenang DPO:**

1. Mengakses seluruh sistem yang memproses data pribadi karyawan/pengguna.
2. Mengajukan rekomendasi penghentian sementara pemrosesan jika terdapat risiko tinggi.
3. Melaporkan temuan kepatuhan langsung kepada Direksi tanpa melalui chain-of-command biasa.

---

**Tanda tangan:**

[Tempat], [Tanggal]

Direktur Utama,

&nbsp;&nbsp;&nbsp;

[Tanda Tangan + Cap Perusahaan]

**[Nama Direktur]**

---

## Checklist Onboarding DPO

Setelah surat SK ditandatangani, selesaikan langkah berikut:

- [ ] Update `.env` production dengan `PDP_DPO_NAME`, `PDP_DPO_EMAIL`, `PDP_PRIVACY_CONTACT_URL`
- [ ] Jalankan `php artisan config:cache` di server production setelah update `.env`
- [ ] Simpan scan SK di folder dokumen compliance perusahaan (mis. Google Drive / SharePoint)
- [ ] Daftarkan ke BSSN (jika sudah ada mekanisme registrasi DPO dari BSSN)
- [ ] Sosialisasikan kepada tim HCM: siapa DPO, cara menghubungi, skenario eskalasi
- [ ] Jadwalkan pelatihan DPO mengenai UU PDP dan regulasi terkait minimal 1× per tahun
- [ ] Tetapkan prosedur: subjek data bisa mengajukan hak via email DPO atau form `/kontak-privasi`

---

## Gap & Status

| Item | Status | Catatan |
|---|---|---|
| Config runtime DPO | ✅ Tersedia | `config/pdp.php`, env-driven |
| Privacy Policy menampilkan kontak DPO | ✅ Tersedia | `misc/privacy-policy.blade.php` |
| Email breach/consent menampilkan kontak DPO | ✅ Tersedia | template email mengambil dari config |
| Template SK DPO | ✅ Tersedia | Dokumen ini |
| SK DPO ditandatangani oleh manajemen | ⚠️ Pending | **Aksi manajemen — bukan kode** |
| DPO terdaftar di BSSN | ⚠️ Pending | Setelah SK ditandatangani |
| Env production diupdate | ⚠️ Pending | Setelah DPO ditunjuk |

---

## Catatan Penting

> **M7c adalah tindakan manajemen (non-kode)**, bukan implementasi teknis.  
> Engineering telah menyediakan semua infrastruktur yang diperlukan: config, integrasi email, privacy policy display.  
> Satu-satunya yang tersisa adalah penunjukan formal oleh manajemen dan update `.env` production.

Jika DPO belum ditunjuk, **placeholder** di config sudah mencegah system crash — namun nama dan email yang tampil di Privacy Policy dan email notifikasi adalah nilai default yang perlu diperbarui sebelum go-live/audit formal.
