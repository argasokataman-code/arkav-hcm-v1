# Feature Map: Tax Governance & BPJS

## 1. Entry Points (API)
| Method | Path | Controller | Permission |
|--------|------|------------|------------|
| GET/POST | `/v1/hcm/tax-governance` | `HcmTaxGovernanceController` | `tax.manage` |
| GET | `/v1/hcm/tax-governance/audit` | `HcmTaxGovernanceController` | `tax.audit` |
| GET/POST | `/v1/hcm/bpjs-governance` | `HcmBpjsGovernanceController` | `bpjs.manage` |
| GET/POST | `/v1/hcm/allowance-governance` | `HcmEmployeeAllowanceGovernanceController` | `allowance.manage` |
| GET/POST | `/v1/hcm/spt-masa` | `HcmSptMasaController` | `spt.manage` |
| GET | `/v1/hcm/platform-tax-summary` | `PlatformTaxSummaryController` | (super admin) |

## 2. Controllers
- `backend/app/Http/Controllers/Api/TaxGovernance/HcmTaxGovernanceController.php`
- `backend/app/Http/Controllers/Api/TaxGovernance/PlatformTaxSummaryController.php`
- `backend/app/Http/Controllers/Api/BpjsGovernance/HcmBpjsGovernanceController.php`
- `backend/app/Http/Controllers/Api/AllowanceGovernance/HcmEmployeeAllowanceGovernanceController.php`
- `backend/app/Http/Controllers/Api/SptMasa/HcmSptMasaController.php`

## 3. Controller Concerns
- `HandlesTaxPolicyCrud` — Policy CRUD
- `HandlesTaxAnomalyManagement` — Anomaly detection
- `HandlesTaxAuditReports` — Audit reports
- `HandlesTaxBreakGlass` — Emergency override
- `HandlesTaxSharedUtilities` — Shared helpers
- `HandlesPlatformTaxGovernance` — Platform-level tax
- `HandlesBpjsCrud` — BPJS CRUD
- `HandlesBpjsReports` — BPJS reports
- `HandlesAllowanceAssignments` — Allowance assignment
- `HandlesAllowancePolicies` — Allowance policies
- `HandlesAllowanceReports` — Allowance reports

## 4. Models
- `App\Models\HcmTaxGovernancePolicy` — Tax policy
- `App\Models\HcmTaxGovernancePolicyEvent` — Policy events
- `App\Models\HcmTaxGovernanceProjection` — Projections
- `App\Models\HcmTaxGovernanceAnomaly` — Anomalies
- `App\Models\HcmTaxGovernanceBreakGlassRequest` — Emergency overrides
- `App\Models\HcmBillingTaxPolicy` — Billing tax policy
- `App\Models\HcmBpjsGovernancePolicy` — BPJS policy
- `App\Models\HcmBpjsGovernancePolicyHistory` — BPJS history
- `App\Models\HcmBpjsGovernanceRateBaseline` — Rate baseline
- `App\Models\HcmEmployeeAllowancePolicy` — Allowance policy
- `App\Models\HcmEmployeeAllowancePolicyHistory` — History
- `App\Models\HcmEmployeeAllowanceAssignment` — Assignment
- `App\Models\HcmEmployeeAllowanceAssignmentHistory` — Assignment history
- `App\Models\HcmSptMasaHeader` — SPT Masa header
- `App\Models\HcmSptMasaDetail` — SPT Masa details

## 5. Services
- `backend/app/Services/TaxGovernanceReportingService.php` — Tax reporting
- `backend/app/Services/Hcm/BpjsContributionCalculator.php` — BPJS calculation
- `backend/app/Support/SptMasaGenerationService.php` — SPT Masa generation
- `backend/app/Support/SptMasaValidationService.php` — SPT Masa validation
- `backend/app/Support/SptMasaExportService.php` — SPT Masa export

## 6. Listeners
- `TaxGovernancePolicyEventListener` — React to policy changes

## 7. Key Relations
```
HcmTaxGovernancePolicy -> Company (N:1)
HcmBpjsGovernancePolicy -> Company (N:1)
HcmEmployeeAllowanceAssignment -> EmployeeProfile (N:1)
HcmSptMasaHeader -> HcmSptMasaDetail (1:N)
```
