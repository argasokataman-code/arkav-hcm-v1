# HCM — Tax Governance API (Phase 9 Done + Revenue Capture Layer Active)

Prefix utama: `/v1/hcm/tax-governance`  
Prefix platform billing: `/v1/hcm/tax-governance/platform-billing`  
Prefix government layer: `/v1/hcm/tax-governance/platform-tax-compliance`  
Middleware: `api.token` + tenant scope resolver + server-side RBAC guardrails

Status dokumen: `phase-3 locked (contract baseline), phase-4 done (runtime tenant lifecycle), phase-5 done (governance dashboard + anomaly observability), phase-7 done (audit evidence pack), phase-8 closed (UUID-only runtime), phase-9 done (platform billing tax runtime + tenant compliance snapshot), revenue-capture-layer active (2026-04-27), gap-closure update done (2026-05-03)`.

## Runtime Clarification (2026-05-02)

1. `GET /platform-tax-compliance/reports` menganggap `policy_configured=true` hanya jika ada policy `status=active` untuk bulan terpilih. Policy `draft` tidak dipakai untuk perhitungan liability.
2. Snapshot policy compliance government (`transaction_tax_rate`) untuk report hanya mengambil policy `active`.
3. `GET /platform-tax-compliance/policies` pada mode global mengembalikan history global yang sudah di-dedup dari keseluruhan dataset hasil filter (bukan hanya subset page yang sedang diminta), sehingga histori versi lama tetap terbaca saat `per_page` kecil.

## Revenue Capture Layer (Internal Event Architecture — No Public Endpoint)

Revenue capture bukan endpoint publik API. Capture terjadi secara otomatis via event listeners yang di-trigger dari domain events.

### Event → Listener → Table

| Domain Event | Listener | Table | Idempotency Key Pattern |
|---|---|---|---|
| `SubscriptionCreated` | `CaptureSubscriptionRevenue` | `platform_revenue_transactions` | `subscription_created:{id}` |
| `AddonPurchased` | `CaptureAddonRevenue` | `platform_revenue_transactions` | `addon_purchased:{id}` |

### Runtime Guarantees

- **Atomicity**: setiap capture dibungkus `DB::transaction()` — partial write tidak mungkin terjadi.
- **Idempotency**: `idempotency_key` unique index mencegah double-capture dari redelivered event.
- **Retry policy**: `ShouldQueue`, `tries=3`, `backoff=[30s, 120s, 300s]`.
- **Backpressure monitoring**: `QueueBackpressureGuard` mencatat rolling 1-minute event count dan men-log warning `tax_governance.queue_backpressure_alert` bila melebihi threshold (default 200/menit).
- **Source reference integrity**: `RevenueSourceReferenceValidator` memvalidasi type, id, uuid, dan company_id sebelum capture ditulis.
- **Failure observability**: `failed()` callback men-log `tax_governance.revenue_capture_failed` dengan full context untuk alerting.

### Clearing State Machine

`platform_revenue_transactions.clearing_status`:
- `uncleared` → default saat capture terjadi.
- `cleared` → di-set oleh `ClearRevenueTransactionsJob` (nightly scheduled) setelah 2-day grace window.
- `disputed` → manual update via admin flow.
- `reversed` → manual update via admin flow.

Report aggregation (`/platform-billing/reports`) hanya menggunakan baris dengan `clearing_status = cleared` untuk revenue calculation final.

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
   - endpoint runtime tenant lifecycle terpasang untuk `GET/POST /policies`, `GET/PATCH /policies/{policyRef}`, `POST /submit`, `POST /approve`, `POST /reject`, `POST /publish`;
   - baseline guardrail server-side untuk `AUTH_FORBIDDEN`, `TAX_POLICY_SOD_VIOLATION`, `TAX_POLICY_NOT_FOUND`, `TAX_POLICY_INVALID_STATE_TRANSITION`, `TAX_POLICY_VERSION_CONFLICT`;
   - migration persistence policy + event immutable;
   - model HcmTaxGovernancePolicy + HcmTaxGovernancePolicyEvent;
   - baseline feature test untuk lifecycle + SoD + tenant boundary.
2. Lingkup phase 4:
   - runtime control plane foundation (policy authoring, approval, publication, effective-dated);
   - server-side enforcement RBAC, SoD, tenant scope;

1. Selesai (phase-3 done):
   - kontrak endpoint UUID-only untuk policy lifecycle (`list/create/detail/update/submit/approve/reject/publish`);
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

Referensi keputusan permission taxonomy: [../features/tax-governance/IMPLEMENTATION.md](../features/tax-governance/IMPLEMENTATION.md).

| Endpoint | Action | Permission minimum | Scope |
|---|---|---|---|
| `GET /policies` | List tax policy tenant | `tax.tenant.policy.view` | Tenant sendiri |
| `POST /policies` | Buat draft policy | `tax.tenant.policy.draft.manage` | Tenant sendiri |
| `GET /policies/{policyRef}` | Detail policy | `tax.tenant.policy.view` | Tenant sendiri |
| `PATCH /policies/{policyRef}` | Ubah draft policy | `tax.tenant.policy.draft.manage` | Tenant sendiri |
| `POST /policies/{policyRef}/submit` | Workflow submit | `tax.tenant.policy.draft.manage` | Tenant owner / global admin |
| `POST /policies/{policyRef}/approve` | Workflow approve | `tax.tenant.policy.draft.manage` | Tenant owner / global admin |
| `POST /policies/{policyRef}/reject` | Workflow reject back to draft | `tax.tenant.policy.draft.manage` | Tenant owner / global admin |
| `POST /policies/{policyRef}/publish` | Workflow publish | `tax.tenant.policy.draft.manage` | Tenant owner / global admin |
| `GET /policies/{policyRef}/events` | Event history immutable | `tax.tenant.policy.view` | Tenant sendiri |
| `GET /reports/tenant-self-audit` | Read tenant self-audit enhanced snapshot | `tax.tenant.policy.view` | Tenant sendiri / Global admin |
| `GET /reports/tenant-self-audit-export` | Export enhanced tenant self-audit (`json|pdf`) | `tax.tenant.report.export` | Tenant sendiri |
| `GET /reports/tenant-compliance-status` | Snapshot compliance tenant statutory + billing | `tax.tenant.report.export` | Tenant sendiri / Global admin |
| `GET /governance/dashboard` | Dashboard lintas tenant subscribe | `tax.governance.dashboard.view_all` | Global observability |
| `GET /governance/anomalies` | Anomaly lintas tenant | `tax.governance.anomaly.view_all` | Global observability |
| `POST /governance/break-glass/requests` | Request break-glass | `tax.governance.break_glass.request` | Global privileged flow |
| `POST /governance/break-glass/requests/{requestUuid}/approve` | Approve break-glass | `tax.governance.break_glass.approve` | Global privileged flow |
| `GET /platform-billing/policies` | List policy platform billing tax | `tax.platform.policy.view` | Platform domain |
| `POST /platform-billing/policies` | Buat/ubah policy platform billing | `tax.platform.policy.manage` | Platform domain |
| `GET /platform-billing/reports` | Lihat report billing tax lintas tenant | `tax.platform.report.view_all` | Platform domain |
| `GET /platform-billing/invoices` | Invoice snapshot billing tax | `tax.platform.report.export_all` | Platform domain |
| `GET /platform-tax-compliance/policies` | List kebijakan tax & compliance government layer | `tax.platform.policy.view` | Platform domain |
| `POST /platform-tax-compliance/policies` | Simpan kebijakan tax & compliance government layer | `tax.platform.policy.manage` | Platform domain |
| `GET /platform-tax-compliance/reports` | Lihat laporan tax payable & net profit platform | `tax.platform.report.view_all` | Platform domain |

## Lifecycle Policy Statutory Tax Tenant

State minimum (temporary owner-direct mode):
1. `draft`
2. `published`
3. `superseded`
4. `void`

Guardrails wajib:
1. Owner-first authoring: perubahan policy tenant dilakukan oleh tenant owner (global admin override tetap tersedia).
2. Object scope: `resource.tenant_id == actor.active_tenant_id`.
3. Semua perubahan policy menghasilkan immutable audit events.
4. Endpoint workflow `submit/approve/reject/publish` aktif pada mode owner-direct; approval chain multi-party formal belum diterapkan.

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
5. `draftKey` (string, nullable, idempotency key untuk draft authoring tenant)
6. `rules` (object)
7. `rateSchedules` (array)

Aturan payload statutory saat ini:
1. `rules.scheme` menerima `STATUTORY_PPH21` atau legacy `TER`; runtime akan dinormalisasi ke skema statutory tenant.
2. `rules.currency` hanya `IDR`.
3. `rules.regulationReference` / `rules.regulation_reference` menyimpan rujukan regulasi aktif, misalnya `PP 58/2023 & PMK 168/PMK.03/2023`.
4. `rules.regulationSourceType` menerima `ministry_regulation`, `directorate_guideline`, `company_policy_reference`, atau kode valid lain yang di-whitelist runtime.
5. `rateSchedules[]` menerima bentuk statutory seperti `category`, `lookupTableCode`, `calculationMode`, `effectiveStartDate`, `effectiveEndDate`, `regulationReference`, `regulationSourceType`; field legacy `bracket` + `rate` masih diterima untuk normalisasi transisi.

Response:
1. `201` untuk draft baru.
2. `200` bila `draftKey` menunjuk draft tenant yang sudah ada, sehingga create bersifat idempotent.
3. `data.draftKey` mengembalikan fingerprint draft yang tersimpan.

### `GET /v1/hcm/tax-governance/policies/{policyRef}`

Path:
1. `policyRef` menerima UUID saja.

Response `200`:
- detail policy + publication summary + audit summary.

### `PATCH /v1/hcm/tax-governance/policies/{policyRef}`

Aturan:
1. Hanya status `draft` yang bisa diubah.
2. Optimistic lock via `version` wajib.
3. Payload mengikuti kontrak statutory yang sama dengan endpoint create, termasuk `draftKey`, metadata regulasi, dan statutory `rateSchedules`.
4. `policyRef` menerima UUID saja.

Response `200`:
- policy draft terbaru, termasuk `draftKey`, `rules`, `rateSchedules`, dan timestamp audit ringkas.

### `POST /v1/hcm/tax-governance/policies/{policyRef}/submit`

Behavior runtime:
1. Hanya policy `draft` yang bisa disubmit.
2. Response `200` dengan status baru `submitted`.

### `POST /v1/hcm/tax-governance/policies/{policyRef}/approve`

Behavior runtime:
1. Hanya policy `submitted` yang bisa diapprove.
2. Response `200` dengan status baru `approved`.

### `POST /v1/hcm/tax-governance/policies/{policyRef}/reject`

Behavior runtime:
1. Policy `submitted` atau `approved` bisa dikembalikan ke `draft`.
2. Response `200` dengan status baru `draft`.

### `POST /v1/hcm/tax-governance/policies/{policyRef}/publish`

Behavior runtime:
1. Policy `draft`, `submitted`, atau `approved` bisa dipublish oleh tenant owner / global admin.
2. Response `200` dengan status baru `published`.

### `GET /v1/hcm/tax-governance/policies/{policyRef}/events`

Response `200`:
1. history event immutable dengan field `policy_uuid` dan `event_uuid`.
2. Path event history menerima UUID policy.

### `GET /v1/hcm/tax-governance/governance/dashboard`

Query opsional:
1. `risk_level_filter`: `green|yellow|red`
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
- request break-glass status `requested` dan disimpan di `hcm_tax_governance_break_glass_requests`.

### `POST /v1/hcm/tax-governance/governance/break-glass/requests/{requestUuid}/approve`

Body minimum:
1. `approvalNote`
2. `expiresAt`

Response `200`:
- request break-glass status `approved` dengan expiry.

### `GET /v1/hcm/tax-governance/reports/tenant-self-audit`

Query opsional:
1. `company_id` (global admin dapat override; tenant user tetap tenant aktif)

Response `200` (tambahan smart-detection detail):
1. `data.compliance_status.employee_pph21_compliance.non_compliant_employees[]`
   - `user_id`, `user_uuid`, `full_name`, `email`
   - `issues[]` dengan `code`, `label`, dan opsional `current_value`
2. Field ini digunakan untuk menampilkan detail karyawan yang menyebabkan checklist belum patuh (mis. NPWP kosong/invalid, PTKP belum valid).
3. Snapshot `employee_pph21_compliance` hanya menghitung user aktif non-owner (`company_users.role != owner`) agar owner tenant tidak ikut scope payroll default.
2. `period_start` (date)
3. `period_end` (date)

Response `200`:
- payload tenant self-audit enhanced (JSON) berisi policy snapshot, change history, compliance checklist, dan billing tax compliance summary.

### `GET /v1/hcm/tax-governance/platform-billing/policies`

Query opsional:
1. `billing_month`: `YYYY-MM`
2. `status`: `draft|active|inactive`
3. `global_mode`: `true|false` (default `false`, `true` untuk output agregasi versi global)
4. `per_page`

Response `200`:
- `data.items`: payload kompatibilitas legacy per company.
- `data.items_global`: agregasi kebijakan global berisi `version`, `subscription_tax_rate`, `addon_markup_rate`, `status`, `created_at`, `effective_from`.

### `POST /v1/hcm/tax-governance/platform-billing/policies`

Body minimum:
1. **Mode global (direkomendasikan untuk menu Platform Revenue):**
   - `subscription_tax_rate` (0..100)
   - `addon_markup_rate` (0..100)
   - `status` (opsional)
   - `billing_month` (opsional, default bulan berjalan)
   - `effective_from` (opsional, default hari ini)
   - `notes` (opsional)
2. **Mode legacy per company (tetap didukung):**
   - `company_id`, `billing_month`, `billing_cycle_type`, `tax_rate_percentage`, `base_calculation_method`, `effective_from`

Response `201`:
- mode global: mengembalikan snapshot kebijakan global + `affected_company_count`.
- mode legacy: mengembalikan policy company-level seperti sebelumnya.

### `GET /v1/hcm/tax-governance/platform-billing/reports`

Query minimum:
1. `month` dengan format `YYYY-MM`

Response `200`:
- summary report lintas tenant untuk billing month.
- payload `summary` mencakup agregasi clearing-aware revenue:
   - `total_taxable_revenue_amount` (basis pajak utama; cleared revenue, fallback invoice total saat belum ada capture runtime)
   - `total_cleared_revenue_amount`
   - `total_uncleared_revenue_amount`
   - `total_disputed_revenue_amount`
   - `total_reversed_revenue_amount`
- setiap row tenant mencakup field clearing-aware paralel: `taxable_revenue_amount`, `cleared_revenue_amount`, `uncleared_revenue_amount`, `disputed_revenue_amount`, `reversed_revenue_amount`.
- tambahan payload `summary_global` untuk UI global:
   - `total_subscription_revenue`
   - `total_addon_revenue`
   - `total_gross_revenue`
   - `total_tax_due`
   - `total_net_revenue`
   - `effective_tax_rate`
- konsistensi fallback runtime:
   - jika capture stream runtime pada bulan berjalan belum tersedia, `total_gross_revenue` mengikuti `total_taxable_revenue_amount` (invoice fallback) agar tidak terjadi kondisi `gross=0` tetapi `tax_due>0`.
   - pada kondisi fallback yang sama, `total_net_revenue` dihitung dari `total_gross_revenue - total_tax_due`.
- tambahan payload `tenants_global[]` untuk tabel global:
   - `tenant`, `plan`, `subscription_revenue`, `addon_revenue`, `gross_revenue`, `tax_amount_due`, `net_revenue`

### `GET /v1/hcm/tax-governance/platform-tax-compliance/policies`

Query opsional:
1. `billing_month`: `YYYY-MM`
2. `status`
3. `global_mode`
4. `per_page`

Response `200`:
- struktur payload sama dengan `/platform-billing/policies`.
- tambahan alias context compliance:
   - `data.view_context = government_tax_compliance`
   - setiap `items_global[]` menambahkan:
      - `government_tax_rate`
      - `addon_component_rate`

### `POST /v1/hcm/tax-governance/platform-tax-compliance/policies`

Body minimum:
1. `subscription_tax_rate`
2. `addon_markup_rate`
3. `billing_cycle_type` (opsional: `monthly|yearly|custom`, default `monthly`)

Catatan runtime saat ini:
- endpoint ini memakai mode propagasi global yang menulis policy government layer per tenant untuk `billing_month` terpilih.
- `billing_cycle_type` sekarang bisa dikonfigurasi dari form Government Tax & Compliance dan dipropagasikan ke snapshot policy per tenant.

Response `201`:
- snapshot kebijakan government layer + jumlah tenant scope terdampak.

### `GET /v1/hcm/tax-governance/platform-tax-compliance/reports`

Query minimum:
1. `month` dengan format `YYYY-MM`

Response `200`:
- struktur payload sama dengan `/platform-billing/reports`.
- dipakai khusus dashboard Government Layer untuk `Pajak Terutang` dan `Net Profit Platform`.
- tambahan alias context compliance:
   - `data.view_context = government_tax_compliance`
   - `data.summary_compliance`:
      - `total_taxable_revenue`
      - `total_collected_tax_liability`
      - `total_addon_component`
      - `total_tax_payable`
      - `total_net_revenue`
      - `effective_tax_rate`
   - `data.tenants_global[]` untuk UI global compliance menambahkan:
      - `billing_month`
      - `billing_cycle_type`
      - `next_renewal_month`
      - `collected_tax_liability`
   - `data.tenants_compliance[]`:
      - `taxable_revenue`
      - `collected_tax_liability`
      - `addon_component`
      - `total_tax_payable`

### `GET /v1/hcm/tax-governance/platform-billing/invoices`

Query minimum:
1. `period_start`
2. `period_end`
3. tidak ada format export; response berisi `invoice_snapshots` untuk billing month.

Response `200`:
- snapshot invoice billing tax lintas tenant untuk evidence dan rekonsiliasi.

### `GET /v1/hcm/tax-governance/reports/tenant-self-audit-export`

Query opsional:
1. `company_id` (global admin dapat override; tenant user tetap tenant aktif)
2. `period_start` (date)
3. `period_end` (date)
4. `format`: `json|pdf` (default `json`)

Response `200`:
- `json`: payload report tenant self-audit enhanced.
- `pdf`: file attachment report tenant self-audit.

### `GET /v1/hcm/tax-governance/reports/tenant-compliance-status`

Query opsional:
1. `company_id` (hanya global admin)

Response `200`:
- snapshot `statutory_tax_compliance`, `billing_tax_compliance`, `overall_status`, dan `recommended_actions`.
- section `compliance_status.billing_tax_compliance` sekarang mencakup metrik clearing-aware:
   - `taxable_revenue_amount`
   - `cleared_revenue_amount`
   - `uncleared_revenue_amount`
   - `disputed_revenue_amount`
   - `reversed_revenue_amount`
- section `compliance_status.employee_pph21_compliance` mencakup kualitas data pajak karyawan aktif:
   - `active_employees`
   - `profiles_available`
   - `complete_profiles`
   - `missing_npwp`
   - `invalid_npwp_format`
   - `missing_ptkp_status`
   - `completion_rate`

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

Status transisi domain tax governance: **UUID-only untuk policy path runtime**.  
Numeric fallback mengirim header deprecation dan sunset, serta ditracking via telemetry sampai cutoff migration.

## Sinkronisasi Wajib

1. OpenAPI endpoint draft ada di [openapi.yaml](openapi.yaml).
2. Decision source tetap di [../features/tax-governance/IMPLEMENTATION.md](../features/tax-governance/IMPLEMENTATION.md).
3. Tracking fase tetap di [../features/tax-governance/tracker.md](../features/tax-governance/tracker.md).