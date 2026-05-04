# BPJS Governance Feature

## Ringkasan Bisnis

BPJS Governance adalah modul operasional untuk memastikan perusahaan mengelola iuran BPJS secara benar, terpisah dari domain pajak PPh 21. Modul ini dipakai untuk:

1. Menetapkan policy tarif iuran per program BPJS (Kesehatan/JHT/JP/JKK/JKM).
2. Memastikan data kepesertaan BPJS karyawan lengkap dan mutakhir.
3. Menghasilkan checklist kepatuhan BPJS untuk audit internal.

## Scope Halaman dan API Aktif

Halaman web:
1. `/bpjs-governance` (overview)
2. `/bpjs-governance/policies`
3. `/bpjs-governance/employee-membership`
4. `/bpjs-governance/reports`
5. `/bpjs-governance/rate-baselines`

API runtime:
1. `GET /v1/hcm/bpjs-governance/reference`
2. `GET /v1/hcm/bpjs-governance/policies`
3. `PUT /v1/hcm/bpjs-governance/policies/{policyRef}` (UUID-first, numeric fallback)
4. `GET /v1/hcm/bpjs-governance/employee-membership`
5. `PUT /v1/hcm/bpjs-governance/employee-membership/{userId}`
6. `GET /v1/hcm/bpjs-governance/reports`
7. `GET /v1/hcm/bpjs-governance/reports/export`
8. `GET /v1/hcm/bpjs-governance/rate-baselines`
9. `PUT /v1/hcm/bpjs-governance/rate-baselines/{programCode}/{contributionParty}`

## End-to-End Business Flow

1. Admin membuka tab `Policy Setup` untuk memperbarui policy iuran BPJS yang sudah disediakan sistem.
2. Sistem mengunci field struktural (`programCode`, `contributionParty`, `wageBase`, periode berlaku) agar tenant tidak mengubah baseline regulasi inti.
3. Tenant hanya mengubah field operasional (`ratePercent`, `legalBasis`, `notes`, `isActive`) sesuai kebutuhan compliance internal.
4. Sistem memvalidasi perubahan tarif terhadap baseline regulasi dan menolak field immutable jika dikirim di endpoint update.
5. Admin membuka tab `Employee Membership` untuk melengkapi nomor BPJS Kesehatan dan BPJS Ketenagakerjaan per karyawan.
6. Sistem menghitung status membership (`complete|partial|missing`) berdasarkan ketersediaan dua nomor BPJS.
7. Admin membuka tab `Compliance Reports` untuk melihat skor kepatuhan dengan evidence terstruktur (coverage pasangan program, audit rate/wage base/legal basis, completion rate membership).

## Lifecycle dan Arti Bisnis

Lifecycle policy:
1. `isActive = true` berarti policy dipakai sebagai baseline compliance pada periode aktif.
2. `effectiveStartDate` dan `effectiveEndDate` dikelola sebagai baseline terkontrol dan tidak diubah dari UI tenant.
3. Penyesuaian tenant difokuskan pada tarif (`ratePercent`) plus metadata audit (`legalBasis`, `notes`).

Lifecycle membership:
1. `missing`: kedua nomor BPJS kosong.
2. `partial`: hanya salah satu nomor terisi.
3. `complete`: nomor BPJS Kesehatan dan Ketenagakerjaan terisi.

## Keputusan Desain dan Percabangan

1. BPJS dipisahkan penuh dari PPh 21 karena domain regulasi dan proses audit berbeda.
2. Policy BPJS tidak lagi mengambil data dari salary component master.
3. Update membership disimpan pada data benefit karyawan terbaru agar tetap kompatibel dengan payroll runtime existing.
4. Endpoint membership update menggunakan `userId` numeric tenant-scoped (bukan UUID) sesuai kontrak runtime saat ini.

## Existing vs Target

Existing sebelumnya:
1. BPJS UI mengandalkan endpoint non-BPJS (`/hcm/salary-components`, `/hcm/employees`, `/hcm/employees/{id}`).
2. BPJS belum memiliki API contract khusus.

Target saat ini:
1. BPJS menggunakan endpoint khusus `/v1/hcm/bpjs-governance/*`.
2. Policy berjalan dalam mode update-only, sedangkan membership tetap dapat diperbarui dari modul BPJS.
3. Validasi policy memaksa baseline regulasi per program+party dan mencegah overlap periode aktif.
4. Dokumentasi API OpenAPI + feature API BPJS telah disinkronkan.

## Cross-check Role dan Permission

Web guard:
1. Halaman BPJS Governance berada di guard `hcm.web.admin`.

Permission API minimum:
1. `payroll.view` untuk read reference/policies/membership/reports.
2. `payroll.manage` untuk update policy (create endpoint dikunci di runtime tenant).
3. `employee.manage` untuk update membership karyawan.

## Gap dan Catatan Lanjutan

Semua gap prioritas telah diselesaikan pada sesi ini (2026-05-03):

1. **UUID migration** — `updatePolicy` kini menerima UUID atau numeric ID. Frontend menggunakan UUID untuk semua operasi edit policy.
2. **Membership versioning** — Setiap `updateEmployeeMembership` selalu membuat record `EmployeeBenefit` baru (tidak mutate record lama), sehingga riwayat perubahan tersimpan secara historis.
3. **Export report** — `GET /v1/hcm/bpjs-governance/reports/export` tersedia dengan header `Content-Disposition: attachment`. UI reports menyediakan tombol "Export JSON".
4. **Konfigurasi baseline tarif** — Tabel `hcm_bpjs_governance_rate_baselines` menyimpan override per tenant. Endpoint `GET /rate-baselines` dan `PUT /rate-baselines/{programCode}/{contributionParty}` tersedia. Halaman "Konfigurasi Baseline" di UI memungkinkan admin mengedit tarif min/maks per program+party. Jika tidak dikonfigurasi, sistem fallback ke `systemRegulatoryMatrix()` (default regulasi nasional).
5. **Policy update-only lock** — Endpoint create policy tenant dikunci (`422 BPJS_POLICY_CREATE_DISABLED`). UI policy mengunci field struktural (`program`, `porsi`, `basis upah`, `periode`) dan hanya mengirim update untuk `ratePercent`, `legalBasis`, `notes`, serta `isActive`.

Catatan sisa (tidak blocking):
- Membership update terbaru menggunakan data `EmployeeBenefit` paling baru; query riwayat versi per karyawan belum tersedia sebagai endpoint khusus (tidak dibutuhkan untuk flow operasional saat ini).
- Policy PUT masih mendukung lookup numeric ID sebagai fallback (kompatibilitas backward); akan dihapus saat semua client migrasi ke UUID penuh.
