# Governance: Remaining Tables

Additional governance tables (low-frequency, non-blocking).

## `hcm_bpjs_governance_policies`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `bpjs_type VARCHAR(20) NOT NULL` — `kesehatan`, `ketenagakerjaan`
- `employer_rate_percent DECIMAL(8,4) NOT NULL`
- `employee_rate_percent DECIMAL(8,4) NOT NULL`
- `max_wage_base DECIMAL(15,2) NULL`
- `effective_from DATE NOT NULL`
- `effective_to DATE NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Index:
- `KEY hcm_bpjs_governance_policies_company_id_idx (company_id)`
- `KEY hcm_bpjs_governance_policies_bpjs_type_idx (bpjs_type)`
- `KEY hcm_bpjs_governance_policies_effective_idx (effective_from, effective_to)`

---

## `hcm_subscription_change_requests`

Tenant-initiated subscription plan change requests (upgrade/downgrade/cancel).

- `id CHAR(36) PK` — UUID primary key
- `company_uuid CHAR(36) NOT NULL` (index)
- `user_uuid CHAR(36) NOT NULL` (index)
- `current_subscription_uuid CHAR(36) NULL` (index)
- `from_package_uuid CHAR(36) NULL` (index)
- `to_package_uuid CHAR(36) NULL` (index)
- `action VARCHAR(20) NOT NULL` — `upgrade`, `downgrade`, `cancel`
- `status VARCHAR(20) NOT NULL DEFAULT 'pending'` — `pending`, `approved`, `rejected`, `applied`, `cancelled`
- `preview JSON NULL` — prorated cost preview
- `notes VARCHAR(500) NULL`
- `effective_at TIMESTAMP NULL`
- `decided_at TIMESTAMP NULL`
- `decided_by_user_uuid CHAR(36) NULL`
- `applied_at TIMESTAMP NULL`
- `created_at`, `updated_at`

Index:
- `KEY hcm_subscription_change_requests_company_status_idx (company_uuid, status)`
- `KEY hcm_subscription_change_requests_status_created_idx (status, created_at)`

**Note:** Documented here for completeness. Main subscription events flow: `docs/database/saas-platform/subscription-events.md`.

---

## `employee_biometric_consents`

Consent untuk attendance selfie/GPS tracking (UU PDP compliance).

- `id BIGINT UNSIGNED PK`
- `employee_uuid CHAR(36) NOT NULL`
- `company_id BIGINT UNSIGNED NOT NULL` (index)
- `selfie_consent BOOLEAN NOT NULL DEFAULT 0`
- `gps_consent BOOLEAN NOT NULL DEFAULT 0`
- `consent_given_at TIMESTAMP NULL`
- `consent_withdrawn_at TIMESTAMP NULL`
- `consent_ip VARCHAR(45) NULL`
- `created_at`, `updated_at`

Constraint:
- `UNIQUE KEY employee_biometric_consents_employee_company_unique (employee_uuid, company_id)`

Index:
- `KEY employee_biometric_consents_company_id_idx (company_id)`

**Note:** Main data privacy tables: `docs/database/governance/data-privacy.md`.

---

## `package_addon_assignments`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `subscription_id BIGINT UNSIGNED NOT NULL` (FK `subscriptions`, cascade on delete)
- `addon_id BIGINT UNSIGNED NOT NULL` (FK `package_addons`, cascade on delete)
- `quantity INT UNSIGNED NOT NULL DEFAULT 1`
- `status VARCHAR(30) NOT NULL DEFAULT 'active'` — `active`, `cancelled`
- `assigned_at TIMESTAMP NULL`
- `cancelled_at TIMESTAMP NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE`
- `FOREIGN KEY (addon_id) REFERENCES package_addons(id) ON DELETE CASCADE`
- `UNIQUE KEY package_addon_assignments_subscription_addon_unique (subscription_id, addon_id)`

Index:
- `KEY package_addon_assignments_subscription_id_idx (subscription_id)`
- `KEY package_addon_assignments_addon_id_idx (addon_id)`
- `KEY package_addon_assignments_status_idx (status)`

**Note:** Main packages documentation: `docs/database/saas-platform/packages.md`.

---

## Related Documentation

- **BPJS Governance:** Main tables in `docs/database/governance/tax-bpjs.md`
- **Subscription Events:** `docs/database/saas-platform/subscription-events.md`
- **Data Privacy:** `docs/database/governance/data-privacy.md`
- **Packages:** `docs/database/saas-platform/packages.md`
