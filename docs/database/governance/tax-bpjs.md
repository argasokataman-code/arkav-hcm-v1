# Governance: Tax & BPJS Compliance

## Tax Governance Tables

### `hcm_tax_governance_policies`
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `tax_type VARCHAR(50) NOT NULL` — `pph21`, `ppn`, `pph23`, `pph_badan`
- `rate DECIMAL(8,4) NOT NULL`
- `effective_from DATE NOT NULL`, `effective_to DATE NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

### `hcm_tax_governance_policy_events`
- `id BIGINT UNSIGNED PK`
- `policy_id BIGINT UNSIGNED NOT NULL` (FK `hcm_tax_governance_policies`, cascade)
- `event_type VARCHAR(50) NOT NULL` — `created`, `updated`, `deactivated`
- `actor_user_id BIGINT UNSIGNED NULL`
- `payload JSON NULL`
- `created_at`, `updated_at`

### `hcm_tax_governance_projections`
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `period_year SMALLINT UNSIGNED`, `period_month TINYINT UNSIGNED`
- `projection_type VARCHAR(50) NOT NULL`
- `total_employees INT UNSIGNED`, `total_net_pay DECIMAL(15,2)`
- `projected_pph21 DECIMAL(15,2)`
- `created_at`, `updated_at`

### `hcm_tax_governance_anomalies`
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `employee_id BIGINT UNSIGNED NULL`
- `anomaly_type VARCHAR(50) NOT NULL`
- `severity VARCHAR(20) NOT NULL DEFAULT 'medium'`
- `description TEXT NULL`
- `is_resolved BOOLEAN NOT NULL DEFAULT 0`
- `resolved_at TIMESTAMP NULL`
- `created_at`, `updated_at`

---

## BPJS Governance Tables

### `hcm_bpjs_governance_policies`
- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `company_id BIGINT UNSIGNED NULL` (index)
- `bpjs_type VARCHAR(20) NOT NULL` — `kesehatan`, `ketenagakerjaan`
- `employer_rate_percent DECIMAL(8,4) NOT NULL`
- `employee_rate_percent DECIMAL(8,4) NOT NULL`
- `max_wage_base DECIMAL(15,2) NULL`
- `effective_from DATE NOT NULL`, `effective_to DATE NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

---

## Platform-Level Tables

### `hcm_billing_tax_policies`
- `id BIGINT UNSIGNED PK`
- `tax_type VARCHAR(50) NOT NULL` — `ppn`, `pph23`
- `rate DECIMAL(8,4) NOT NULL`
- `effective_from DATE`, `effective_to DATE`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

### `platform_monthly_financial_summaries`
- `id BIGINT UNSIGNED PK`
- `period_year SMALLINT UNSIGNED`, `period_month TINYINT UNSIGNED`
- `total_revenue DECIMAL(15,2)`, `total_tax DECIMAL(15,2)`
- `active_companies INT UNSIGNED`, `total_employees INT UNSIGNED`
- `created_at`, `updated_at`

---

## Related Documentation

- **Feature Docs:** `docs/features/tax-governance/`, `docs/features/bpjs-governance/`
- **API:** `docs/api/tax-governance-api.md`, `docs/api/bpjs-governance-api.md`
