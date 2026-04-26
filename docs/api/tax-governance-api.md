# HCM — Tax Governance API (Phase 5 In Progress)

Prefix utama: `/v1/hcm/tax-governance`  
Prefix platform billing: `/v1/hcm/tax-governance/platform-billing`  
Middleware: `api.token` + tenant scope resolver + server-side RBAC guardrails

Status dokumen: `phase-3 locked (contract baseline), phase-4 done (runtime tenant lifecycle), phase-5 in-progress (governance dashboard + anomaly observability)`.

## Progress Phase 5 (Governance Dashboard Lintas Tenant)

1. Sudah mulai (in-progress):
   - endpoint `GET /governance/dashboard` untuk global-admin observability dengan risk heatmap + tenant metrics;
   - endpoint `GET /governance/anomalies` untuk anomaly registry lintas tenant subscribe;
   - endpoint `GET /reports/tenant-self-audit` enhanced dengan compliance checklist + change history + payroll impact;
   - model projection (`HcmTaxGovernanceProjection`) dan anomaly registry (`HcmTaxGovernanceAnomaly`);
   - event listener untuk sync projection saat policy transitions;
   - baseline test coverage untuk authorization + dashboard metrics + self-audit report.
2. Lanjutan phase 5:
   - frontend UI dashboard pages (dashboard summary, anomaly registry, self-audit modal);
   - route registration dan navigation wiring di frontend;
   - dokumentasi lengkap OpenAPI fase 5;
   - edge case test coverage (projection consistency, anomaly lifecycle).

## Progress Phase 4 (Runtime Baseline)

1. Selesai (phase-4 done):
   - endpoint runtime tenant lifecycle terpasang untuk `GET/POST /policies`, `GET/PATCH /policies/{policyUuid}`, `POST /submit`, `POST /approve`, `POST /publish`;
   - baseline guardrail server-side untuk `AUTH_FORBIDDEN`, `TAX_POLICY_SOD_VIOLATION`, `TAX_POLICY_NOT_FOUND`, `TAX_POLICY_INVALID_STATE_TRANSITION`, `TAX_POLICY_VERSION_CONFLICT`;
   - migration persistence policy + event immutable;
   - model HcmTaxGovernancePolicy + HcmTaxGovernancePolicyEvent;
   - baseline feature test untuk lifecycle + SoD + tenant boundary.
2. Lingkup phase 4:
   - runtime control plane foundation (policy authoring, approval, publication, effective-dated);
   - server-side enforcement RBAC, SoD, tenant scope;

1. Selesai (phase-3 done):
   - kontrak endpoint UUID-only untuk policy lifecycle (`list/create/detail/update/submit/approve/publish`);
   - kontrak governance observability (`dashboard` + `anomalies`);
   - kontrak privileged flow (`break-glass request/approve`);
   - kontrak reporting (`tenant self-audit`, `platform billing reports`, `platform billing export`);
   - sinkronisasi OpenAPI untuk seluruh endpoint phase 3;
   - mapping endpoint -> permission code.
2. Dipindahkan ke phase 4 (runtime implementation):
   - wiring route/controller runtime;
   - validasi server-side RBAC/SoD/tenant boundary pada eksekusi nyata;
   - backend test implementation terhadap kontrak.

## Contract Lock (Phase 3 Exit Evidence)

1. Endpoint catalog UUID-only sudah ditetapkan.
2. Permission matrix per endpoint sudah ditetapkan.
3. Error contract minimum (403/404/409/422) sudah ditetapkan.
4. OpenAPI dan feature API doc sudah sinkron.
5. Runtime wiring sengaja menjadi scope phase 4 agar tracking tetap terpisah antara design-contract dan execution-runtime.

## Prinsip Kontrak

1. Semua identifier publik pada domain tax governance menggunakan UUID.
2. Numeric legacy tidak boleh diekspos pada endpoint baru tax governance.
3. Visibility UI tidak menggantikan authorization server-side.
4. Envelope respons standar: `{ success, data?, error? }`.

## RBAC dan Permission Code

Referensi keputusan permission taxonomy: [../features/tax-governance/DECISION.md](../features/tax-governance/DECISION.md).

| Endpoint | Action | Permission minimum | Scope |
|---|---|---|---|
| `GET /policies` | List tax policy tenant | `tax.tenant.policy.view` | Tenant sendiri |
| `POST /policies` | Buat draft policy | `tax.tenant.policy.draft.manage` | Tenant sendiri |
| `GET /policies/{policyUuid}` | Detail policy | `tax.tenant.policy.view` | Tenant sendiri |
| `PATCH /policies/{policyUuid}` | Ubah draft policy | `tax.tenant.policy.draft.manage` | Tenant sendiri |
| `POST /policies/{policyUuid}/submit` | Submit approval | `tax.tenant.policy.draft.manage` | Tenant sendiri |
| `POST /policies/{policyUuid}/approve` | Approve policy | `tax.tenant.policy.approve` | Tenant sendiri + SoD |
| `POST /policies/{policyUuid}/publish` | Publish policy | `tax.tenant.policy.publish` | Tenant sendiri + SoD |
| `GET /reports/tenant-self-audit` | Export tenant self-audit | `tax.tenant.report.export` | Tenant sendiri |
| `GET /governance/dashboard` | Dashboard lintas tenant subscribe | `tax.governance.dashboard.view_all` | Global observability |
| `GET /governance/anomalies` | Anomaly lintas tenant | `tax.governance.anomaly.view_all` | Global observability |
| `POST /governance/break-glass/requests` | Request break-glass | `tax.governance.break_glass.request` | Global privileged flow |
| `POST /governance/break-glass/requests/{requestUuid}/approve` | Approve break-glass | `tax.governance.break_glass.approve` | Global privileged flow |
| `GET /platform-billing/policies` | List policy platform billing tax | `tax.platform.policy.view` | Platform domain |
| `POST /platform-billing/policies` | Buat/ubah policy platform billing | `tax.platform.policy.manage` | Platform domain |
| `GET /platform-billing/reports` | Lihat report billing tax lintas tenant | `tax.platform.report.view_all` | Platform domain |
| `GET /platform-billing/reports/export` | Export report billing tax | `tax.platform.report.export_all` | Platform domain |

## Lifecycle Policy Statutory Tax Tenant

State minimum:
1. `draft`
2. `submitted`
3. `approved`
4. `published`
5. `superseded`
6. `void`

Guardrails wajib:
1. Maker-checker (SoD): actor pembuat draft tidak boleh approve/publish item yang sama.
2. Object scope: `resource.tenant_id == actor.active_tenant_id`.
3. Semua perubahan policy menghasilkan immutable audit events.

## Draft Endpoint Contract

### `GET /v1/hcm/tax-governance/policies`

Query opsional:
1. `status`: `draft|submitted|approved|published|superseded|void`
2. `effective_from`: tanggal `YYYY-MM-DD`
3. `effective_to`: tanggal `YYYY-MM-DD`
4. `page`: default `1`
5. `per_page`: default `20`, maksimum `100`

Response `200`:
- `data.items[]` berisi policy summary.
- `data.meta` berisi pagination.

### `POST /v1/hcm/tax-governance/policies`

Body minimum:
1. `policyCode` (string)
2. `name` (string)
3. `effectiveStartDate` (date)
4. `effectiveEndDate` (date, nullable)
5. `rules` (array)
6. `rateSchedules` (array)

Response `201`:
- `data` policy object status `draft`.

### `GET /v1/hcm/tax-governance/policies/{policyUuid}`

Path:
1. `policyUuid` format UUID.

Response `200`:
- detail policy + publication summary + audit summary.

### `PATCH /v1/hcm/tax-governance/policies/{policyUuid}`

Aturan:
1. Hanya status `draft` yang bisa diubah.
2. Optimistic lock via `version` wajib.

Response `200`:
- policy draft terbaru.

### `POST /v1/hcm/tax-governance/policies/{policyUuid}/submit`

Body opsional:
1. `submissionNote` (string, max 1000)

Response `200`:
- status berubah menjadi `submitted`.

### `POST /v1/hcm/tax-governance/policies/{policyUuid}/approve`

Body minimum:
1. `approvalNote` (string)

Response `200`:
- status berubah menjadi `approved`.

### `POST /v1/hcm/tax-governance/policies/{policyUuid}/publish`

Body minimum:
1. `publishReason` (string)
2. `effectiveStartDate` (date)

Response `200`:
- status berubah menjadi `published`.

### `GET /v1/hcm/tax-governance/governance/dashboard`

Query opsional:
1. `risk_level`: `low|medium|high|critical`
2. `page`
3. `per_page`

Response `200`:
- ringkasan posture lintas tenant subscribe.

### `GET /v1/hcm/tax-governance/governance/anomalies`

Query opsional:
1. `severity`: `low|medium|high|critical`
2. `page`
3. `per_page`

Response `200`:
- daftar anomaly lintas tenant sesuai scope permission global.

### `POST /v1/hcm/tax-governance/governance/break-glass/requests`

Body minimum:
1. `targetTenantUuid`
2. `reasonCode`
3. `reason`

Response `201`:
- request break-glass status `requested`.

### `POST /v1/hcm/tax-governance/governance/break-glass/requests/{requestUuid}/approve`

Body minimum:
1. `approvalNote`
2. `expiresAt`

Response `200`:
- request break-glass status `approved` dengan expiry.

### `GET /v1/hcm/tax-governance/reports/tenant-self-audit`

Query minimum:
1. `period_year` (int)
2. `period_month` (int)
3. `format`: `json|csv|xlsx|pdf`

Response `200`:
- audit pack tenant sesuai format.

### `GET /v1/hcm/tax-governance/platform-billing/policies`

Query opsional:
1. `billing_cycle`: `monthly|yearly|custom`
2. `status`

Response `200`:
- daftar policy platform billing tax.

### `POST /v1/hcm/tax-governance/platform-billing/policies`

Body minimum:
1. `policyCode`
2. `billingCycle`
3. `rateSchedules`
4. `effectiveStartDate`

Response `201`:
- policy platform billing tax aktif/draft sesuai mode.

### `GET /v1/hcm/tax-governance/platform-billing/reports`

Query minimum:
1. `period_start`
2. `period_end`
3. `group_by`: `cycle|package|tenant_segment`

Response `200`:
- summary tax by cycle, package, segment.

### `GET /v1/hcm/tax-governance/platform-billing/reports/export`

Query minimum:
1. `period_start`
2. `period_end`
3. `format`: `json|csv|xlsx|pdf`

Response `200`:
- artefak export report billing tax lintas tenant.

## Negative/Forbidden Contract (Mandatory)

1. `403 AUTH_FORBIDDEN`
   - permission code tidak memenuhi endpoint.
2. `403 TAX_POLICY_SOD_VIOLATION`
   - maker mencoba approve/publish policy sendiri.
3. `403 TENANT_FORBIDDEN`
   - actor mencoba akses object tenant lain.
4. `404 TAX_POLICY_NOT_FOUND`
   - UUID tidak ditemukan pada scope tenant yang sah.
5. `409 TAX_POLICY_VERSION_CONFLICT`
   - optimistic lock mismatch.
6. `422 TAX_POLICY_INVALID_STATE_TRANSITION`
   - transisi lifecycle tidak valid.

## Catatan Transisi Identifier

Status transisi domain tax governance: **UUID-only untuk endpoint baru**.  
Jika runtime lama masih butuh numeric bridge internal, bridge tetap private di service layer dan tidak dipublikasikan ke API response/request.

## Sinkronisasi Wajib

1. OpenAPI endpoint draft ada di [openapi.yaml](openapi.yaml).
2. Decision source tetap di [../features/tax-governance/DECISION.md](../features/tax-governance/DECISION.md).
3. Tracking fase tetap di [../features/tax-governance/tracker.md](../features/tax-governance/tracker.md).