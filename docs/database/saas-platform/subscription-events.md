# SaaS Platform: Subscription Events & Change Requests

## `subscription_events`

Event log untuk lifecycle subscription (renewal, payment, anomali).

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `company_id BIGINT UNSIGNED NULL` (index) — FK soft (tanpa FK constraint untuk kompatibilitas schema mixed)
- `company_uuid CHAR(36) NULL` (index)
- `subscription_id BIGINT UNSIGNED NULL` (index)
- `subscription_uuid CHAR(36) NULL` (index)
- `invoice_id BIGINT UNSIGNED NULL` (index)
- `invoice_uuid CHAR(36) NULL` (index)
- `payment_id BIGINT UNSIGNED NULL` (index)
- `payment_uuid CHAR(36) NULL` (index)
- `renewal_period_key VARCHAR(128) NULL` (index) — format: `{subscription_id}:{period}` (contoh: `42:2026-05`)
- `event_type VARCHAR(64) NOT NULL` (index) — `renewal_success`, `renewal_failed`, `payment_received`
- `reason_code VARCHAR(64) NULL` (index) — `XENDIT_DOWN`, `DUPLICATE_RENEWAL_BLOCKED`
- `reason_message VARCHAR(255) NULL`
- `payload JSON NULL`
- `occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` (index)
- `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`

Index:
- `KEY subscription_events_company_idx (company_id)`
- `KEY subscription_events_subscription_idx (subscription_id)`
- `KEY subscription_events_renewal_key_idx (renewal_period_key)`
- `KEY subscription_events_event_type_idx (event_type)`
- `KEY subscription_events_occurred_idx (occurred_at)`

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

---

## Related Documentation

- **Companies/Subscriptions:** `docs/database/saas-platform/companies-subscriptions.md`
- **Packages:** `docs/database/saas-platform/packages.md`
- **Invoices:** `docs/database/saas-platform/invoices.md`
- **Feature Docs:** `docs/features/auto-renewal/`
- **API:** `docs/api/saas-renewal-monitoring-api.md`
