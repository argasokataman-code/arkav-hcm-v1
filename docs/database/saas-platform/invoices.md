# SaaS Platform: Invoices & Billing

## `invoices`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `company_id BIGINT UNSIGNED NOT NULL` (FK `companies`, cascade on delete)
- `subscription_id BIGINT UNSIGNED NULL` (FK `subscriptions`, null on delete)
- `invoice_number VARCHAR(100) NOT NULL UNIQUE`
- `total_amount DECIMAL(15,2) NOT NULL`
- `tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0`
- `currency VARCHAR(10) NOT NULL DEFAULT 'IDR'`
- `status VARCHAR(30) NOT NULL DEFAULT 'pending'` — `pending`, `paid`, `overdue`, `cancelled`, `refunded`
- `issue_date DATE NOT NULL`
- `due_date DATE NOT NULL`
- `paid_at TIMESTAMP NULL`
- `cancelled_at TIMESTAMP NULL`
- `notes TEXT NULL`
- Subscription tracking: `renewal_period_key VARCHAR(128) NULL` (index)
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE`
- `FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL`

Index:
- `KEY invoices_company_id_idx (company_id)`
- `KEY invoices_subscription_id_idx (subscription_id)`
- `KEY invoices_status_idx (status)`
- `KEY invoices_issue_date_idx (issue_date)`
- `KEY invoices_due_date_idx (due_date)`
- `KEY invoices_renewal_period_key_idx (renewal_period_key)`

---

## `invoice_email_logs`

- `id BIGINT UNSIGNED PK`
- `invoice_id BIGINT UNSIGNED NOT NULL` (FK `invoices`, cascade on delete)
- `to_email VARCHAR(255) NOT NULL`
- `status ENUM('sent','failed') NOT NULL` (index)
- `provider_message_id VARCHAR(191) NULL`
- `error_message TEXT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE`

Index:
- `KEY invoice_email_logs_invoice_created_idx (invoice_id, created_at)`
- `KEY invoice_email_logs_status_idx (status)`

---

## Related Documentation

- **Companies/Subscriptions:** `docs/database/saas-platform/companies-subscriptions.md`
- **Subscription Events:** `docs/database/saas-platform/subscription-events.md`
- **Feature Docs:** `docs/features/purchase-transaction/`
- **API:** `docs/api/invoice-api.md`
