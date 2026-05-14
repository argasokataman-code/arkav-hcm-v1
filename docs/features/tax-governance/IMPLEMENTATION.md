# Tax Governance — Implementation Notes

## Permission Taxonomy

Keputusan desain permission code tax governance menggunakan hierarki 3-level:

```
tax.<domain>.<action>
```

Domain:
- `tenant` — scope tenant sendiri (HR admin / tenant owner)
- `governance` — scope global observability (super admin platform)
- `platform` — scope platform billing/government (platform admin)

### Tenant-Level Permissions

| Code | Deskripsi |
|---|---|
| `tax.tenant.policy.view` | Membaca list, detail, event history policy tenant; akses self-audit |
| `tax.tenant.policy.draft.manage` | Membuat dan mengedit draft policy tenant |
| `tax.tenant.report.export` | Export self-audit; akses compliance snapshot |

### Governance-Level Permissions (Global)

| Code | Deskripsi |
|---|---|
| `tax.governance.dashboard.view_all` | Dashboard observability lintas semua tenant subscribe |
| `tax.governance.anomaly.view_all` | Anomaly registry lintas tenant |
| `tax.governance.break_glass.request` | Request akses break-glass ke data tenant |
| `tax.governance.break_glass.approve` | Approve break-glass request |

### Platform-Level Permissions

| Code | Deskripsi |
|---|---|
| `tax.platform.policy.view` | Membaca policy platform billing dan government layer |
| `tax.platform.policy.manage` | Kelola policy platform billing dan government layer |
| `tax.platform.report.view_all` | Report billing tax lintas tenant |
| `tax.platform.report.export_all` | Export invoice billing tax |

## Model dan Tabel Utama

| Model | Tabel | Keterangan |
|---|---|---|
| `HcmTaxGovernancePolicy` | `hcm_tax_governance_policies` | Policy PPh 21 tenant (UUID primary) |
| `HcmTaxGovernancePolicyEvent` | `hcm_tax_governance_policy_events` | Immutable audit trail setiap transisi status |
| `HcmTaxGovernanceProjection` | `hcm_tax_governance_projections` | Projection state untuk governance dashboard |
| `HcmTaxGovernanceAnomaly` | `hcm_tax_governance_anomalies` | Registry anomali tenant |
| `EmployeeTaxProfile` | `employee_tax_profiles` | Profil pajak karyawan (NPWP, PTKP) |

## Controller

`backend/app/Http/Controllers/Api/HcmTaxGovernanceController.php`

Trait modular (platform scope) untuk memisahkan tanggung jawab controller:

`backend/app/Http/Controllers/Api/TaxGovernance/Concerns/HandlesPlatformTaxGovernance.php`

Method runtime yang aktif:
- `index()` — list policy tenant
- `store()` — buat draft policy (idempotent via `draftKey`)
- `show()` — detail policy
- `update()` — edit draft (optimistic lock)
- `submit()` / `approve()` / `publish()` — lifecycle transitions (saat ini: `409 WORKFLOW_DISABLED`)
- `policyEventHistory()` — event audit immutable
- `anomalyRegistry()` / `resolveAnomaly()` / `acknowledgeAnomaly()` — anomaly management
- `tenantSelfAuditReportEnhanced()` — enhanced self-audit report
- `tenantSelfAuditReportExport()` — export (json/pdf)
- `tenantComplianceStatus()` — compliance snapshot termasuk `employee_pph21_compliance`
- `platformBillingPolicies()` / `storePlatformBillingPolicy()` / `platformBillingReports()` / `platformBillingInvoices()` — platform billing layer
- `platformTaxCompliancePolicies()` / `storePlatformTaxCompliancePolicy()` / `platformTaxComplianceReports()` — government layer

## Revenue Capture Architecture

Revenue dari domain events di-capture secara event-driven:

| Event | Listener | Idempotency Key |
|---|---|---|
| `SubscriptionCreated` | `CaptureSubscriptionRevenue` | `subscription_created:{id}` |
| `AddonPurchased` | `CaptureAddonRevenue` | `addon_purchased:{id}` |

Jaminan runtime:
- Atomicity via `DB::transaction()`
- Idempotency via unique index `idempotency_key`
- Retry: `ShouldQueue`, `tries=3`, `backoff=[30s, 120s, 300s]`
- Backpressure monitoring: `QueueBackpressureGuard` (threshold 200 events/menit)

## NPWP Validation Logic

NPWP dianggap valid jika, setelah menghapus semua karakter `.` dan `-`, hasilnya adalah string numerik sepanjang 15 atau 16 digit.

Klasifikasi:
- `missing_npwp`: field null atau string kosong
- `invalid_npwp_format`: ada nilai tapi tidak lolos validasi 15-16 digit
- Valid: lolos validasi — masuk hitungan `complete_profiles` (jika PTKP juga terisi)

## Migration Window — UUID vs Numeric

- `policyRef` di URL menerima UUID (utama) atau numeric legacy ID (selama migration window)
- Endpoint baru tidak pernah mengembalikan numeric ID sebagai primary key publik
- Setelah seluruh client migrasi, legacy lookup akan dihapus

## Testing Coverage

File test:
- `backend/tests/Feature/HcmTaxGovernancePhase7Test.php` — 6 test (lifecycle, compliance snapshot, employee_pph21_compliance quality metrics)

Gate lokal:
```bash
php artisan test --filter=HcmTaxGovernancePhase7Test
```
