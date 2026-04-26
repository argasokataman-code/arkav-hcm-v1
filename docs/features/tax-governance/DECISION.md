# Tax Governance Product Decision (Locked)

## Status

- Decision date: 2026-04-26
- Decision owner: product + engineering
- Status: locked for implementation

## Final Product Decision

Tax governance diputuskan memakai **dua plane sekaligus**:

1. **Runtime control plane (authoritative for calculation)**
   - Menjadi source of truth untuk aturan/tarif pajak yang dipakai engine payroll.
   - Menyediakan lifecycle policy: draft, review, approve, publish, effective window, superseded.

2. **Governance dashboard (cross-tenant observability)**
   - Menjadi command-center audit untuk tenant yang subscribe.
   - Menampilkan compliance posture lintas tenant: readiness, anomaly, drift, failed publication, missing tax profile, dan coverage evidence.

Keduanya wajib aktif; dashboard tidak boleh menjadi sumber hitung runtime, dan runtime tidak boleh mengabaikan governance evidence.

## Domain Pajak Yang Wajib Dipisah

Selain dual-plane, domain pajak juga dipisah menjadi dua kewajiban legal yang berbeda:

1. **Tenant statutory tax domain**
   - Pajak payroll/operasional milik tenant (company subscriber).
   - Pelaporan resmi ke pemerintah tetap per entitas tenant masing-masing.
   - Tenant wajib punya self-audit dan self-reporting di ruang data tenant sendiri.

2. **Platform billing tax domain**
   - Pajak atas pendapatan layanan aplikasi (subscription, service fee, add-on, custom contract).
   - Dikelola oleh penyelenggara platform dan dipantau di global admin.
   - Tidak boleh tercampur ledger atau reporting dengan pajak payroll tenant.

Keputusan mandatory: governance global boleh memantau lintas tenant, tetapi kewajiban lapor pemerintah tidak digabung antar entitas tenant.

## RBAC Decision

1. Tenant HCM Admin
   - Kelola konfigurasi pajak tenant sendiri.
   - Tidak bisa melihat detail tenant lain.

2. Platform Global HCM Admin
   - Bisa melihat seluruh tenant subscribe untuk fungsi governance dan support operasional.
   - Mutasi lintas tenant untuk action sensitif harus tercatat sebagai privileged action dengan audit reason.

3. Payroll Operator (tenant)
   - Menjalankan payroll dan review hasil, tanpa hak ubah policy baseline.

4. Auditor role
   - Read-only evidence per tenant/periode.

5. Platform Billing Admin
   - Mengelola policy pajak layanan aplikasi (service tax) untuk paket bulanan, tahunan, dan custom.
   - Memantau invoice tax outcome lintas tenant di dashboard global.

## RBAC Scope Matrix (Detailed)

Matrix ini menjadi acuan implementasi server-side authorization (bukan visibilitas UI saja).

| Action | Tenant HCM Admin | Payroll Operator (Tenant) | Tenant Auditor | Platform Global HCM Admin | Platform Billing Admin |
|---|---|---|---|---|---|
| Lihat tax policy tenant sendiri | Allow | Read-only | Read-only | Allow | Deny |
| Buat draft tax policy tenant | Allow | Deny | Deny | Deny* | Deny |
| Submit draft untuk approval | Allow | Deny | Deny | Deny* | Deny |
| Approve tax policy tenant | Allow** | Deny | Deny | Deny* | Deny |
| Publish tax policy tenant | Allow** | Deny | Deny | Deny* | Deny |
| Void/supersede tax policy tenant | Allow** | Deny | Deny | Deny* | Deny |
| Jalankan payroll dengan policy published | Allow | Allow | Deny | Allow (support-only read/run) | Deny |
| Lihat tenant self-audit reports | Allow | Read-only | Read-only | Allow | Deny |
| Lihat governance dashboard lintas tenant subscribe | Deny | Deny | Deny | Allow | Allow (billing scope only) |
| Lihat anomaly tenant statutory tax lintas tenant | Deny | Deny | Deny | Allow | Deny |
| Kelola policy pajak layanan aplikasi (monthly/yearly/custom) | Deny | Deny | Deny | Allow | Allow |
| Lihat billing tax reports lintas tenant | Deny | Deny | Deny | Allow | Allow |
| Edit data tenant lain secara langsung | Deny | Deny | Deny | Deny*** | Deny |

Keterangan:
- `Deny*`: platform global admin default tidak boleh mutasi policy statutory tax tenant; hanya observability/support.
- `Allow**`: aksi approval/publish/void tetap wajib melewati maker-checker dan SoD check.
- `Deny***`: hanya boleh lewat break-glass flow terkontrol, ter-audit, dan time-bound.

## RBAC Guardrails (Mandatory)

1. Object-level scope check
   - Semua endpoint tenant statutory tax wajib `resource.tenant_id == actor.active_tenant_id` kecuali role global yang explicit.

2. Segregation of duties (SoD)
   - Actor yang membuat draft policy tidak boleh menjadi approver/publisher policy yang sama.

3. Break-glass policy
   - Mutasi lintas tenant oleh platform role hanya boleh lewat flow break-glass dengan reason code, approval, expiry, dan audit trail.

4. Reporting ownership
   - Tenant hanya bisa generate report domain tenant sendiri.
   - Global reports bersifat observability platform, bukan penggabungan kewajiban setor pajak tenant.

## Permission Code Taxonomy (Implementation Target)

1. Tenant statutory tax permissions
   - `tax.tenant.policy.view`
   - `tax.tenant.policy.draft.manage`
   - `tax.tenant.policy.approve`
   - `tax.tenant.policy.publish`
   - `tax.tenant.report.export`

2. Governance/global permissions
   - `tax.governance.dashboard.view_all`
   - `tax.governance.anomaly.view_all`
   - `tax.governance.break_glass.request`
   - `tax.governance.break_glass.approve`

3. Platform billing tax permissions
   - `tax.platform.policy.view`
   - `tax.platform.policy.manage`
   - `tax.platform.report.view_all`
   - `tax.platform.report.export_all`

## Identifier Decision

- **Target kontrak publik: UUID-only** untuk semua entitas tax governance baru dan endpoint baru.
- Numeric ID legacy tidak boleh dipublikasikan di API baru tax governance.
- Untuk entitas existing yang masih hybrid, dipakai fase transisi sampai seluruh FK/kontrak pindah ke UUID.

## UUID Migration Strategy (Mandatory)

1. Tambah kolom `uuid` pada entitas existing yang masih numeric/hybrid.
2. Backfill seluruh data lama dan pasang unique + not-null constraint.
3. Endpoint tax governance baru hanya menerima/mengembalikan UUID.
4. Internal bridge mapping numeric->uuid dipakai sementara untuk kompatibilitas runtime lama.
5. Migrasi FK bertahap ke UUID shadow columns.
6. Telemetry penggunaan numeric endpoint dicatat.
7. Setelah traffic legacy nol, tutup numeric path.

## Target Domain Boundary

1. Runtime domain objects
   - `tax_policy_snapshots`
   - `tax_rate_schedules`
   - `tax_rule_versions`
   - `tax_evaluation_traces`

2. Governance domain objects
   - `tax_policy_definitions`
   - `tax_policy_approval_workflows`
   - `tax_policy_publications`
   - `tax_policy_change_events`
   - `tax_governance_projections`
   - `tax_audit_logs`

3. Platform billing tax objects
   - `platform_billing_tax_policies`
   - `platform_billing_tax_policy_versions`
   - `platform_billing_tax_evaluations`
   - `platform_billing_tax_invoice_snapshots`
   - `platform_billing_tax_audit_logs`

Boundary rule:
- runtime hanya baca snapshot yang sudah publish;
- governance tidak mengubah hasil payroll secara in-place;
- sinkronisasi antar-plane lewat event/outbox, bukan coupling transaksi langsung.
- tenant statutory tax dan platform billing tax dipisah secara data model, workflow, dan reporting ownership.

## Reporting Ownership Decision

1. Tenant reporting
   - Tenant dapat mengekspor laporan pajak company sendiri untuk audit internal dan kesiapan pelaporan pemerintah.
   - Scope laporan tenant dibatasi ketat pada data tenant tersebut.

2. Global admin reporting
   - Global admin dapat melihat observability lintas tenant subscribe untuk monitoring compliance platform.
   - Laporan ini bersifat governance/oversight dan bukan penggabungan kewajiban setor pajak tenant.

3. Platform billing tax reporting
   - Global admin mendapatkan laporan pajak biaya layanan aplikasi berdasarkan invoice revenue platform.
   - Wajib mendukung segmentasi per skema paket: monthly, yearly, custom.

## Anti-Pattern Yang Dilarang

1. Menjadikan `/tax-rates` sebagai UI kosmetik tanpa backing model/API runtime.
2. Mengubah rule/tarif legal secara in-place tanpa versioning efektif.
3. Menggabungkan query governance lintas tenant ke service hitung payroll online.
4. Mengandalkan visibilitas UI saja tanpa server-side authorization.
5. Membuka API baru tax governance dengan numeric ID prediktif.

## Implementation Order

1. Finalisasi kontrak API UUID-only untuk tax governance.
2. Buat model runtime + governance dengan effective-dated versioning.
3. Tambah workflow approval + publication.
4. Wiring engine payroll agar konsumsi snapshot published.
5. Rilis governance dashboard lintas tenant subscribe (global admin).
6. Jalankan migration cleanup numeric legacy path.
7. Rilis reporting packs tenant self-audit + global governance + platform billing tax.