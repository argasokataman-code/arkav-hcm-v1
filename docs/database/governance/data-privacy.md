# Governance: Data Privacy & Compliance

## `erasure_requests`

UU PDP compliance — request penghapusan data pribadi.

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `subject_uuid CHAR(36) NOT NULL` (index) — user requesting data erasure
- `company_id BIGINT UNSIGNED NOT NULL` (index)
- `status VARCHAR(30) NOT NULL DEFAULT 'pending'` — `pending`, `approved`, `rejected`, `completed`
- `reason TEXT NULL`
- `reviewed_by_uuid CHAR(36) NULL` — admin yang review
- `reviewed_at TIMESTAMP NULL`
- `completed_at TIMESTAMP NULL`
- `admin_notes TEXT NULL`
- `created_at`, `updated_at`

Index:
- `KEY erasure_requests_subject_uuid_idx (subject_uuid)`
- `KEY erasure_requests_company_id_idx (company_id)`
- `KEY erasure_requests_status_idx (status)`

---

## `employee_biometric_consents`

Consent untuk attendance selfie/GPS tracking (UU PDP compliance).

- `id BIGINT UNSIGNED PK`
- `employee_uuid CHAR(36) NOT NULL`
- `company_id BIGINT UNSIGNED NOT NULL` (index)
- `selfie_consent BOOLEAN NOT NULL DEFAULT 0` — consent untuk attendance selfie capture
- `gps_consent BOOLEAN NOT NULL DEFAULT 0` — consent untuk GPS location tracking
- `consent_given_at TIMESTAMP NULL`
- `consent_withdrawn_at TIMESTAMP NULL`
- `consent_ip VARCHAR(45) NULL` — IP address saat consent diberikan
- `created_at`, `updated_at`

Constraint:
- `UNIQUE KEY employee_biometric_consents_employee_company_unique (employee_uuid, company_id)`

Index:
- `KEY employee_biometric_consents_company_id_idx (company_id)`

**Note:** Consent wajib sebelum capture biometric data (selfie/GPS).

---

## Related Documentation

- **Export Audit:** `docs/database/governance/export-audit.md`
- **Approval Workflows:** `docs/database/governance/approval-workflows.md`
- **Feature Docs:** `docs/features/data-privacy/`
- **API:** `docs/api/hcm-data-privacy-api.md`
