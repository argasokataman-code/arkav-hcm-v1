# Tax Governance & SaaS Financial Platform

**Status:** Production hardening in progress; platform finance government compliance runtime active; broader tax governance rollout still partial (~70% complete)  
**Decision Date:** 2026-04-27 (Locked for Phase 0-9)  
**Last Updated:** 2026-04-29

## Ringkasan Eksekutif

Tax Governance adalah bagian dari **SaaS Financial Platform** — sistem revenue management + tax compliance untuk aplikasi multi-tenant. Platform mengelola:

1. **Platform Revenue** (3 streams) — dari subscription, payroll service, dan add-on features
2. **Platform Expense Tax** — potongan pajak ke pemerintah atas total revenue
3. **Tenant Statutory Tax** — domain mandiri setiap tenant untuk payroll karyawan mereka

### Current Implementation Status

**✅ What Works:**
- Tenant statutory tax: Employee tax profiles + payroll engine calculates PPh21 TER
- Tax policy CRUD: owner-direct draft editing active, workflow endpoints (`submit/approve/reject/publish`) temporarily disabled (return `409`)
- Permission system: RBAC architecture in place

**❌ What's Missing:**
- Platform revenue capture: 0% (no subscription/payroll/addon tax auto-capture)
- SaaS Financial Platform model: 0% (8 tables + 4 automation flows missing)
- Governance dashboard: 10% (tables exist but projection never written)
- Authorization: 1 permission check missing + domain violations found
- Event dispatch: Commented out (5 locations), so projection never syncs

**Critical Issues Found:** 16 anomalies identified (4 CRITICAL, 6 HIGH, 5 MEDIUM) — see IMPLEMENTATION.md for details.

Dokumentasi ini menjelaskan **architecture, data model, E2E flows, dan authorization rules** untuk SaaS Financial Platform.

## Revenue Streams (Platform)

### Stream 1: Subscription/Package Tax
- **Trigger:** Saat tenant membeli/renew paket (monthly, yearly, custom)
- **Calculation:** `package_price × subscription_tax_rate` (e.g., 5%)
- **Storage:** `platform_revenue_transactions` dengan type='subscription'
- **Table master:** `subscription_packages` + `platform_subscription_tax_configs`

### Stream 2: Payroll Service Tax
- **Trigger:** Saat tenant menjalankan payroll run (setiap bulan)
- **Calculation:** `payroll_total × payroll_service_tax_rate` (e.g., 0.5%)
- **Storage:** `platform_revenue_transactions` dengan type='payroll_service'
- **Table master:** `platform_payroll_service_tax_config` (platform-wide singleton)

### Stream 3: Add-on Feature Tax
- **Trigger:** Saat tenant subscribe/purchase fitur tambahan (reporting, hrms, dll)
- **Calculation:** `addon_price × addon_tax_rate` (per feature)
- **Storage:** `platform_revenue_transactions` dengan type='addon_feature'
- **Table master:** `addon_features` + `platform_addon_feature_tax_configs`

### Platform Expense Tax (Government)
- **Basis:** Total dari 3 revenue streams di atas
- **Rate:** Configurable per bulan (e.g., 15% dari total revenue)
- **Tax codes:** PPh 21, PPN, PPh 22, dll — configurable
- **Storage:** `platform_expense_tax_codes` + `platform_monthly_tax_breakdowns`
- **Calculation:** Revenue × platform_tax_rate → Monthly expense

### Monthly Financial Report (Auto-generated)
```
Gross Revenue = Stream1 + Stream2 + Stream3
Platform Tax Due = Gross Revenue × Tax Rate
Net Revenue = Gross Revenue - Tax Due
```
Stored in `platform_monthly_financial_summaries` (projection/read-model).

## Domain Isolation (Critical)

Tiga domain yang **HARUS DIPISAH** untuk audit dan legal compliance:

| Domain | Owner | Scope | Ledger | Report |
|---|---|---|---|---|
| **Platform Revenue** | Global Admin | Global, all tenants | `platform_revenue_transactions` | `platform_monthly_financial_summaries` |
| **Platform Expense Tax** | Global Admin | Global, all tenants | `platform_expense_tax_codes` + `platform_monthly_tax_breakdowns` | Platform tax obligation report |
| **Tenant Statutory Tax** | Tenant Admin | Per-tenant only | `employee_tax_profiles` + payroll tax lines | Tenant self-audit report |

**Rule:** Tenant TIDAK bisa melihat platform revenue atau platform tax numbers. Tenant HANYA melihat charges yang dia tanggung (subscription fee + payroll service fee + add-on fee pada invoice mereka).

## Keputusan Produk Final

Keputusan produk final dan implikasi teknis dirangkum di IMPLEMENTATION.md (Part 2-9).

## Known Limitations & Scalability Ceiling

**Current Status:** Designed for ≤ 1000 employees per tenant; scaling issues identified at 5000+ employees.

### Critical Issues (Must Fix in Phase 0-1)

| Issue | Severity | Impact at 1K Emp | When Breaks | Fix Phase |
|-------|----------|------------------|-------------|-----------|
| No transaction atomicity on event listeners | 🔴 CRITICAL | $50-100K loss possible | Phase 1 live | Phase 1 |
| Monthly close race condition (missing payroll) | 🔴 CRITICAL | $50K revenue missing | Every month | Phase 4 |
| Payroll policy concurrency (mixed tax rates) | 🔴 CRITICAL | 1-5% employees wrong tax | Phase 3 live | Phase 3 |
| Dashboard N+1 query (anomaly count) | 🟠 HIGH | 20-30 second timeout | 500+ tenants | Phase 4 |
| Event queue backpressure (revenue loss) | 🟠 HIGH | 500 events dropped | High concurrency | Phase 1 |
| No idempotency key (2x capture) | 🟠 HIGH | Duplicate revenue | Phase 1 live | Phase 1 |
| Tenant isolation leak potential | 🟠 HIGH | Data breach | Phase 4 live | Phase 4 |
| No payment clearing status | 🔴 CRITICAL | $10-50K reconciliation gap | All phases | Phase 1 |

### Performance Benchmarks (Projected)

| Scenario | Query | Time | Status |
|----------|-------|------|--------|
| 100 employees, monthly revenue agg | 1 query | 5-10ms | ✅ Safe |
| 500 employees, dashboard anomalies | 501 queries | 5s | ⚠️ Borderline |
| 1000 employees, payroll draft | 1 query (eager load) | <1s | ✅ Safe |
| 5000 employees, dashboard | 5001 queries | 20-30s | 🔴 **TIMEOUT** |
| 10K employees, all combined | - | - | 💥 **CRASHED** |

### Scaling Ceiling

**✅ Safe to 1000 employees** (with fixes applied)  
**⚠️ Risky at 5000 employees** (needs query optimization + monitoring)  
**🔴 Broken at 10K+ employees** (fundamental architecture changes needed)

### Recommended Mitigations (Phase 0-1)

Before production launch:
1. **Phase 0:** Add all indexes + increase connection pool (10 → 50)
2. **Phase 1:** Implement atomicity + idempotency + queue monitoring
3. **Phase 3:** Implement pessimistic locking on policy reads
4. **Phase 4:** Fix N+1 query + implement advisory lock on monthly close

For detailed analysis, see IMPLEMENTATION.md Part 3 (Performance & Scalability Analysis).

---

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

- Web canonical: `/tax-employees` berada di middleware `hcm.web.admin`; non-admin diarahkan keluar dari surface admin.
- Web legacy compatibility: `/tax-rates` tetap tersedia sebagai redirect ke route canonical untuk menjaga backward compatibility bookmark/link lama.
- API: endpoint tax governance aktif berada di namespace `/v1/hcm/tax-governance` untuk compliance snapshot, self-audit export, platform billing policy, dan platform billing reports.
- Runtime tax mutation payroll tenant tetap terjadi lewat endpoint employee dan salary component yang sudah ada.

## UI Aktif

- Halaman aktif canonical: `/tax-employees` (`backend/resources/views/tax-rates.blade.php`).
- Status UI saat ini:
  - compliance summary tenant + recommended actions + anomaly register;
  - policy event history;
  - export tenant self-audit JSON/PDF;
  - panel platform billing tax master (list + create policy untuk global admin);
  - panel platform billing tax report per bulan lintas tenant.
  - panel `PPh 21 Component Mapping` di route `/tax-employees/komponen-pajak` untuk memetakan perlakuan pajak setiap salary component tanpa membuat komponen baru.
- Halaman ini sudah memiliki JS runtime (`frontend/resources/js/tax-governance-dashboard.js`) yang memanggil API tax governance secara langsung.
- Consumer runtime yang benar-benar aktif justru berada di:
  - `/employees` dan flow import employee untuk tax status/NPWP.
  - `/salary-component-master` untuk flag PPh21 TER/rekonsiliasi.
  - `/payroll-run` saat draft payroll dihitung.

## Flow Bisnis End-to-End

1. HCM Admin menyiapkan data identitas pajak karyawan melalui data employee, termasuk `taxStatus` dan NPWP.
2. HCM Admin memastikan master komponen gaji sudah benar, terutama komponen yang harus masuk bruto TER atau annual reconciliation.
3. Saat payroll draft bulanan dihitung, engine payroll membangun taxable gross dari komponen yang di-flag masuk TER.
4. Engine payroll membaca policy tax governance tenant yang statusnya `published` dan efektif pada periode draft. Jika policy menyediakan `rateSchedules` yang bisa dipetakan ke kategori TER (`A/B/C`), rate policy tersebut dipakai sebagai sumber tarif pajak run itu.
5. Jika tidak ada policy efektif atau `rateSchedules` policy tidak bisa dipakai, engine fallback ke lookup TER bawaan berbasis `employee_tax_profiles.tax_status`; jika tax status kosong, sistem fallback ke `TK0` dan menandai anomaly `missingTaxProfile` di metadata payroll line serta ringkasan anomaly run.
6. Payroll line `pph21_ter` dibuat otomatis sebagai deduction. Metadata line sekarang membedakan sumber perhitungan: `tax_governance_policy_schedule` untuk policy tenant yang aktif, atau `pph21_ter_lookup` untuk fallback lookup bawaan.
7. Operator payroll meninjau hasil draft, snapshot policy pada run, anomaly missing tax profile, dan hanya sesudah itu melanjutkan finalize/disburse sesuai flow payroll run.

## Flow Billing Tax Layanan Aplikasi

1. Tenant memilih atau di-assign package subscription (monthly, yearly, atau custom).
2. Saat invoice dibuat, sistem menyimpan snapshot billing context (package, pricing basis, cycle type, discount, add-on).
3. Engine platform billing tax menghitung taxable base dan tax amount sesuai policy aktif pada tanggal invoice.
4. Hasil tax disimpan sebagai snapshot invoice agar histori tidak berubah saat policy baru dipublish.
5. Global admin memantau ringkasan pajak layanan aplikasi lintas tenant subscribe lewat dashboard governance.

## Lifecycle Dan Keputusan Bisnis

- Input tax identity: dikelola di data employee, bukan di `/tax-employees`.
- Input tax basis: dikelola di master komponen gaji, bukan di `/tax-employees`.
- Perhitungan tax deduction: dikelola di payroll runtime, bukan di `/tax-employees`.
- `/tax-employees`: panel governance pajak karyawan tenant; `/tax-rates` diperlakukan legacy alias/redirect.
- Keputusan operasional saat ini: jangan gunakan halaman governance sebagai bukti bahwa seluruh tarif/taxonomy sudah terkelola penuh jika belum ada wiring runtime di engine payroll.

## Integrasi

- Employee master:
  - tax status dan NPWP masuk melalui flow employee edit/import lalu disimpan ke `employee_tax_profiles`.
- Salary component master:
  - route mapping `/tax-employees/komponen-pajak` menampilkan semua komponen payroll dari master salary components, termasuk BPJS, dalam satu tabel audit.
  - admin melakukan inline mapping untuk `Tax Treatment`, `Include in TER`, dan `Annual Reconciliation`; perubahan auto-save ke endpoint salary components tax flags.
  - komponen `Unmapped` ditandai prioritas tinggi (highlight merah + audit filter) untuk mencegah run payroll dengan konfigurasi pajak yang belum jelas.
- Payroll runs:
  - `PayrollDraftBuilder` memilih policy tax governance tenant yang `published` dan efektif pada `draftDataAsOfDate` run monthly.
  - jika policy menyimpan `rateSchedules` yang cocok dengan kategori TER, payroll memakai rate policy itu; jika tidak, engine fallback ke tabel TER bawaan.
  - run monthly menyimpan snapshot policy di `hcm_tax_governance_policy_id`, `hcm_tax_governance_policy_version`, dan `meta.taxGovernancePolicy` agar audit tetap stabil walau policy tenant berubah setelah draft terbentuk.
  - hasilnya muncul sebagai payroll line `pph21_ter` dengan metadata audit seperti `source`, `taxRateMode`, `taxPolicyVersion`, `taxStatusSource`, `pph21TerCategory`, dan `missingTaxProfile`.
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
- Target implementasi baru: kontrak tax governance wajib UUID-only (lihat IMPLEMENTATION.md Part 6).

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
- Static-control illusion: admin melihat `/tax-employees` dan mengira tarif sudah terkelola, padahal runtime tidak memakai semua data dari halaman itu.
- Gross basis drift: komponen gaji berubah tetapi flag `includePph21TerGross` tidak ikut dikoreksi.
- Input mismatch: tax status employee benar, tetapi salary component salah flag sehingga bruto TER tetap keliru.
- Annual reconciliation gap: metadata rekonsiliasi tahunan ada di master komponen, tetapi belum ada panel governance untuk memastikan kapan dan bagaimana rekonsiliasi dijalankan.
- Legacy alias risk: import masih menerima alias `TK`/`K` untuk kompatibilitas; ini memudahkan migrasi, tetapi bisa menyamarkan kualitas data jika tidak dimonitor.

## Negative Scenario

- Admin memperbarui data di `/tax-employees` dan menganggap payroll bulan berikutnya otomatis ikut berubah, padahal engine tidak membaca semua bagian halaman itu.
- Karyawan baru masuk tanpa tax profile; payroll tetap jalan dengan fallback `TK0`, sehingga potongan pajak berpotensi under/over deduct.
- Komponen tunjangan baru dibuat tanpa `includePph21TerGross`, sehingga bruto TER terlalu rendah dan PPh21 terpotong lebih kecil dari seharusnya.
- Komponen deduction atau irregular allowance salah ditandai masuk bruto TER, sehingga potongan pajak berlebihan.
- Operator memperbaiki tax status setelah payroll finalized tetapi tidak melakukan void/recalculate, sehingga slip dan audit trail periode tersebut tetap memakai status lama.
- Auditor meminta bukti master tarif/aturan pajak terpusat; tim hanya menunjukkan `/tax-employees`, padahal halaman itu tidak punya seluruh backing data runtime.

## Existing Vs Target

### Existing runtime yang sudah aktif

- Web guard `/tax-employees` sudah server-side dan tenant-admin only.
- `/tax-employees` sudah menampilkan runtime tenant compliance + self-audit export sebagai governance evidence.
- `/tax-employees` sudah menampilkan panel platform billing tax policy/report untuk global admin (permission-aware).
- Employee tax profile sudah tersimpan terpisah dan dipakai engine payroll.
- Salary component master sudah punya flag yang relevan untuk basis PPh21 TER.
- Payroll run sudah mengeluarkan anomaly missing tax profile dan metadata tax calculation yang cukup untuk audit teknis.

### Gap yang masih terbuka

- Dashboard governance lintas tenant sudah tersedia, namun insight lintas modul (tax profile coverage, taxable component drift, readiness rekonsiliasi tahunan) masih perlu pendalaman metrik.
- Negative-path authorization sudah diperketat di web route + API lifecycle, tetapi cakupan test misuse lintas role masih perlu diperluas.

### Target yang disarankan

- Keputusan sudah final: implementasi harus menghadirkan runtime control plane dan governance dashboard sekaligus.
- `/tax-employees` menjadi entry point canonical, didukung redirect legacy dari `/tax-rates`, dan harus tetap ditopang model data, API, audit trail, tenant scope, serta wiring runtime resmi.
- Governance dashboard wajib bisa menampilkan seluruh tenant yang subscribe untuk global admin dengan batas authorization yang ketat.
- Semua entitas tax governance baru wajib UUID-only pada kontrak publik.
- Tenant harus bisa generate laporan audit company sendiri langsung dari aplikasi.
- Platform harus bisa mengelola dan memonitor pajak biaya layanan aplikasi secara otomatis untuk package monthly, yearly, dan custom.

## Status

- Status implementation: **architecture designed; eksekusi Phase 0-9 belum dimulai penuh**
- Tracker: [tracker.md](tracker.md)
- Implementation guide: [IMPLEMENTATION.md](IMPLEMENTATION.md)
- Snapshot saat ini: runtime pajak payroll existing sudah ada, keputusan arsitektur produk sudah final, dan dokumentasi implementasi + UI/UX sudah dikonsolidasikan penuh ke 3 dokumen inti.