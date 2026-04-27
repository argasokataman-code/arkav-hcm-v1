# Tax Governance & Taxonomy

## Ringkasan

Feature pajak di aplikasi ini masih dalam transisi menuju modul kontrol tunggal. Surface `/tax-rates` sudah terhubung ke endpoint runtime governance (tenant compliance, self-audit JSON/PDF, platform billing tax policy/report), tetapi belum seluruh peta screen terpisah sesuai rencana UI/UX multi-route.

Kontrol pajak runtime yang benar-benar memengaruhi hasil payroll saat ini tersebar di tiga lapisan utama:

1. `employee_tax_profiles` untuk status pajak karyawan (`TK0`-`TK3`, `K0`-`K3`) dan NPWP.
2. `hcm_salary_components` untuk menentukan komponen mana yang masuk bruto TER (`includePph21TerGross`) atau rekonsiliasi tahunan (`includePph21AnnualReconciliation`).
3. `PayrollDraftBuilder` untuk menghitung PPh21 bulanan berbasis lookup TER A/B/C saat draft payroll dibentuk.

Artinya, risiko audit terbesar saat ini bukan sekadar siapa yang dapat membuka `/tax-rates`, tetapi apakah tiga surface runtime itu konsisten, terdokumentasi, dan punya guard yang cukup ketika ada perubahan data payroll.

## Keputusan Produk Final

Keputusan produk sudah dikunci: tax governance akan berjalan sebagai **dua plane sekaligus**.

1. Runtime control plane untuk aturan/tarif pajak yang authoritative ke engine payroll.
2. Governance dashboard untuk monitoring lintas tenant subscribe oleh global admin.

Detail keputusan ada di [DECISION.md](DECISION.md).

## Ruang Lingkup Domain Pajak

Fitur ini mencakup dua domain pajak berbeda yang harus dipisah tegas:

1. **Pajak tenant (statutory tax)**
  - Dipakai tenant untuk payroll/company tax domain mereka sendiri.
  - Tenant dapat audit dan report per company secara mandiri.

2. **Pajak layanan aplikasi (platform billing tax)**
  - Dipakai penyelenggara platform untuk pajak atas revenue layanan aplikasi.
  - Perhitungan mengikuti skema paket billing (monthly, yearly, custom).

Keduanya dipantau dari governance global, tetapi tidak boleh bercampur ledger dan kewajiban legalnya.

## Pola Umum Industri Yang Dipakai

Riset benchmark lintas praktik SaaS menunjukkan pola yang paling stabil untuk domain sensitif pajak:

1. Pisahkan control plane (authoring/publish) dari data plane runtime (calculation).
2. Gunakan effective-dated versioning, bukan edit in-place untuk rule legal.
3. Terapkan approval workflow + publication state sebelum rule aktif.
4. Simpan audit event immutable untuk setiap perubahan policy.
5. Sediakan dashboard governance lintas tenant sebagai read-model/projection, bukan query langsung ke engine hitung.
6. Terapkan server-side RBAC/ABAC dan least privilege untuk aksi lintas tenant.
7. Gunakan idempotent/event-outbox untuk sinkronisasi perubahan rule ke projection.
8. Pakai UUID sebagai identifier eksternal untuk entitas sensitif.

## Akses

- Web: `/tax-rates` berada di middleware `hcm.web.admin`; non-admin diarahkan keluar dari surface admin.
- API: endpoint tax governance aktif berada di namespace `/v1/hcm/tax-governance` untuk compliance snapshot, self-audit export, platform billing policy, dan platform billing reports.
- Runtime tax mutation payroll tenant tetap terjadi lewat endpoint employee dan salary component yang sudah ada.

## UI Aktif

- Halaman aktif: `/tax-rates` (`backend/resources/views/tax-rates.blade.php`).
- Status UI saat ini:
  - compliance summary tenant + recommended actions + anomaly register;
  - policy event history;
  - export tenant self-audit JSON/PDF;
  - panel platform billing tax master (list + create policy untuk global admin);
  - panel platform billing tax report per bulan lintas tenant.
- Halaman ini sudah memiliki JS runtime (`frontend/resources/js/tax-governance-dashboard.js`) yang memanggil API tax governance secara langsung.
- Consumer runtime yang benar-benar aktif justru berada di:
  - `/employees` dan flow import employee untuk tax status/NPWP.
  - `/salary-component-master` untuk flag PPh21 TER/rekonsiliasi.
  - `/payroll-run` saat draft payroll dihitung.

## Flow Bisnis End-to-End

1. HCM Admin menyiapkan data identitas pajak karyawan melalui data employee, termasuk `taxStatus` dan NPWP.
2. HCM Admin memastikan master komponen gaji sudah benar, terutama komponen yang harus masuk bruto TER atau annual reconciliation.
3. Saat payroll draft bulanan dihitung, engine payroll membangun taxable gross dari komponen yang di-flag masuk TER.
4. Engine payroll membaca `employee_tax_profiles.tax_status`; jika kosong, sistem fallback ke `TK0` dan menandai anomaly `missingTaxProfile` di metadata payroll line serta ringkasan anomaly run.
5. Payroll line `pph21_ter` dibuat otomatis sebagai deduction dengan `meta.source = pph21_ter_lookup`.
6. Operator payroll meninjau hasil draft, anomaly missing tax profile, dan hanya sesudah itu melanjutkan finalize/disburse sesuai flow payroll run.

## Flow Billing Tax Layanan Aplikasi

1. Tenant memilih atau di-assign package subscription (monthly, yearly, atau custom).
2. Saat invoice dibuat, sistem menyimpan snapshot billing context (package, pricing basis, cycle type, discount, add-on).
3. Engine platform billing tax menghitung taxable base dan tax amount sesuai policy aktif pada tanggal invoice.
4. Hasil tax disimpan sebagai snapshot invoice agar histori tidak berubah saat policy baru dipublish.
5. Global admin memantau ringkasan pajak layanan aplikasi lintas tenant subscribe lewat dashboard governance.

## Lifecycle Dan Keputusan Bisnis

- Input tax identity: dikelola di data employee, bukan di `/tax-rates`.
- Input tax basis: dikelola di master komponen gaji, bukan di `/tax-rates`.
- Perhitungan tax deduction: dikelola di payroll runtime, bukan di `/tax-rates`.
- `/tax-rates`: saat ini lebih tepat diperlakukan sebagai placeholder/legacy shell daripada panel kontrol pajak aktif.
- Keputusan operasional saat ini: jangan gunakan `/tax-rates` sebagai bukti bahwa tarif atau taxonomy pajak sudah bisa dikelola penuh di aplikasi.

## Integrasi

- Employee master:
  - tax status dan NPWP masuk melalui flow employee edit/import lalu disimpan ke `employee_tax_profiles`.
- Salary component master:
  - flag `includePph21TerGross` menentukan komponen addition mana yang menambah bruto TER.
  - flag `includePph21AnnualReconciliation` sudah tersedia sebagai metadata master, tetapi kontrol rekonsiliasi tahunannya belum dipusatkan ke modul governance pajak.
- Payroll runs:
  - `PayrollDraftBuilder` menghitung PPh21 bulanan dari taxable gross dan tax status.
  - hasilnya muncul sebagai payroll line `pph21_ter` dengan metadata audit seperti `taxStatusSource`, `pph21TerCategory`, dan `missingTaxProfile`.
- Employee import bulk:
  - validasi import menolak tax status di luar enum yang diizinkan, tetapi alias kompatibilitas lama (`TK`/`K`) masih diterima dan dinormalisasi.
- Reporting/audit:
  - anomaly `missingTaxProfileUserIds` pada payroll run menjadi sinyal audit cepat, tetapi belum ada dashboard tax governance khusus.
- SaaS billing:
  - domain platform billing tax mengambil konteks package/subscription dan invoice lifecycle dari modul subscription + trial billing dashboard.
  - wajib mendukung perhitungan otomatis untuk package monthly, yearly, dan custom contract.

## Kontrak API

- Kontrak API tax governance sudah tersedia di:
  - `docs/api/tax-governance-api.md`
  - `docs/api/openapi.yaml` (path `/hcm/tax-governance/**`)
- Kontrak API yang berdampak ke domain pajak tersebar di:
  - `docs/api/hcm-employees-api.md` untuk data employee/tax profile.
  - `docs/api/hcm-salary-components-api.md` untuk flag komponen terkait PPh21.
  - `docs/api/hcm-payroll-api.md` untuk kalkulasi PPh21 di payroll run.
- Target implementasi baru: kontrak tax governance wajib UUID-only (lihat [DECISION.md](DECISION.md)).

## Dampak Ke Modul Lain

- Payroll monthly: perubahan tax status atau flag komponen langsung memengaruhi draft payroll berikutnya atau draft yang dihitung ulang.
- Payslip: potongan pajak yang salah akan tampil langsung di slip karyawan.
- THR/PKWT/off-cycle payroll: selama memakai line item dan component policy yang sama, ada risiko interpretasi tax basis tidak konsisten bila governance tidak dipusatkan.
- Employee onboarding/import: kesalahan tax status saat onboarding akan terbawa ke payroll sampai diperbaiki.
- Audit dan compliance: ketiadaan source of truth terpusat membuat penjelasan ke auditor harus lintas tabel dan lintas modul, bukan cukup menunjukkan satu halaman admin.
- Trial and Billing Dashboard: perlu menampilkan tax summary layanan aplikasi per tenant/per paket/per periode.
- Subscriptions and Packages: perubahan cycle atau kontrak custom harus memicu perhitungan billing tax yang konsisten melalui snapshot invoice.

## Laporan Minimum Yang Wajib

1. **Tenant self-audit pack**
  - tax policy version yang aktif per periode;
  - daftar perubahan policy (who/when/what/reason);
  - ringkasan payroll tax per periode;
  - anomaly pack (`missingTaxProfile`, fallback profile, policy mismatch).

2. **Global governance pack**
  - compliance posture lintas tenant subscribe;
  - tenant risk list (missing profile, stale policy, publish failure);
  - drift dashboard antara policy intended vs runtime outcome.

3. **Platform billing tax pack**
  - tax revenue by cycle (monthly/yearly/custom);
  - tax by package and tenant segment;
  - invoice-level tax snapshot and reconciliation trail.

## Anomali Yang Perlu Diwaspadai

- `missingTaxProfile`: payroll fallback ke `TK0` saat tax profile karyawan belum lengkap.
- Static-control illusion: admin melihat `/tax-rates` dan mengira tarif sudah terkelola, padahal runtime tidak memakai data dari halaman itu.
- Gross basis drift: komponen gaji berubah tetapi flag `includePph21TerGross` tidak ikut dikoreksi.
- Input mismatch: tax status employee benar, tetapi salary component salah flag sehingga bruto TER tetap keliru.
- Annual reconciliation gap: metadata rekonsiliasi tahunan ada di master komponen, tetapi belum ada panel governance untuk memastikan kapan dan bagaimana rekonsiliasi dijalankan.
- Legacy alias risk: import masih menerima alias `TK`/`K` untuk kompatibilitas; ini memudahkan migrasi, tetapi bisa menyamarkan kualitas data jika tidak dimonitor.

## Negative Scenario

- Admin memperbarui data di `/tax-rates` dan menganggap payroll bulan berikutnya otomatis ikut berubah, padahal engine tidak membaca halaman itu.
- Karyawan baru masuk tanpa tax profile; payroll tetap jalan dengan fallback `TK0`, sehingga potongan pajak berpotensi under/over deduct.
- Komponen tunjangan baru dibuat tanpa `includePph21TerGross`, sehingga bruto TER terlalu rendah dan PPh21 terpotong lebih kecil dari seharusnya.
- Komponen deduction atau irregular allowance salah ditandai masuk bruto TER, sehingga potongan pajak berlebihan.
- Operator memperbaiki tax status setelah payroll finalized tetapi tidak melakukan void/recalculate, sehingga slip dan audit trail periode tersebut tetap memakai status lama.
- Auditor meminta bukti master tarif/aturan pajak terpusat; tim hanya menunjukkan `/tax-rates`, padahal halaman itu tidak punya backing data runtime.

## Existing Vs Target

### Existing runtime yang sudah aktif

- Web guard `/tax-rates` sudah server-side dan tenant-admin only.
- `/tax-rates` sudah menampilkan runtime tenant compliance + self-audit export sebagai governance evidence.
- `/tax-rates` sudah menampilkan panel platform billing tax policy/report untuk global admin (permission-aware).
- Employee tax profile sudah tersimpan terpisah dan dipakai engine payroll.
- Salary component master sudah punya flag yang relevan untuk basis PPh21 TER.
- Payroll run sudah mengeluarkan anomaly missing tax profile dan metadata tax calculation yang cukup untuk audit teknis.

### Gap yang masih terbuka

- Dashboard governance lintas tenant sudah tersedia, namun insight lintas modul (tax profile coverage, taxable component drift, readiness rekonsiliasi tahunan) masih perlu pendalaman metrik.
- Negative-path authorization sudah diperketat di web route + API lifecycle, tetapi cakupan test misuse lintas role masih perlu diperluas.

### Target yang disarankan

- Keputusan sudah final: implementasi harus menghadirkan runtime control plane dan governance dashboard sekaligus.
- `/tax-rates` dipertahankan sebagai entry point, tetapi harus ditopang model data, API, audit trail, tenant scope, dan wiring runtime resmi.
- Governance dashboard wajib bisa menampilkan seluruh tenant yang subscribe untuk global admin dengan batas authorization yang ketat.
- Semua entitas tax governance baru wajib UUID-only pada kontrak publik.
- Tenant harus bisa generate laporan audit company sendiri langsung dari aplikasi.
- Platform harus bisa mengelola dan memonitor pajak biaya layanan aplikasi secara otomatis untuk package monthly, yearly, dan custom.

## Status

- Status implementation: **in progress (phase 1-9 done, hardening lanjutan ongoing)**
- Tracker: [tracker.md](tracker.md)
- UI/UX plan: [UI-UX-PLAN.md](UI-UX-PLAN.md)
- Decision log: [DECISION.md](DECISION.md)
- Snapshot saat ini: runtime pajak payroll existing sudah ada, keputusan arsitektur produk sudah final, UI/UX planning sudah ter-wire ke route dedicated (`/tax-rates/*`), lifecycle policy interaktif (draft-save-submit-approve-reject-publish) sudah aktif, dan guard RBAC web-route untuk global vs tenant screen sudah dipisahkan.