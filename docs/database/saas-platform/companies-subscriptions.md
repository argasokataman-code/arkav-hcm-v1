# SaaS Platform: Companies & Subscriptions

Core SaaS tables untuk multi-tenant billing & subscription management.

## `companies`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `code VARCHAR(100) NOT NULL UNIQUE` — kode unik company (contoh: `default_company`)
- `name VARCHAR(255) NOT NULL`
- `email VARCHAR(191) NULL`
- `phone VARCHAR(50) NULL`
- `address TEXT NULL`
- `default_timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Jakarta'`
- `status VARCHAR(30) NOT NULL DEFAULT 'active'` — `active`, `suspended`, `trial`, `expired`
- `created_at`, `updated_at`

Index:
- `KEY companies_status_idx (status)`

---

## `company_users`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NOT NULL` (FK `companies`, cascade on delete)
- `user_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade on delete)
- `role VARCHAR(50) NOT NULL DEFAULT 'member'` — `owner`, `admin`, `hr_admin`, `ops_admin`, `member`
- `status VARCHAR(30) NOT NULL DEFAULT 'active'`
- `joined_at TIMESTAMP NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE`
- `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `UNIQUE KEY company_users_company_user_unique (company_id, user_id)`

Index:
- `KEY company_users_user_id_idx (user_id)`
- `KEY company_users_role_idx (role)`
- `KEY company_users_status_idx (status)`

---

## `subscriptions`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `company_id BIGINT UNSIGNED NOT NULL` (FK `companies`, cascade on delete)
- `company_uuid CHAR(36) NULL` (index)
- `package_id BIGINT UNSIGNED NOT NULL` (FK `packages`, restrict on delete)
- `status VARCHAR(30) NOT NULL DEFAULT 'active'` — `active`, `trial`, `expired`, `cancelled`, `suspended`
- `trial_ends_at TIMESTAMP NULL`
- `starts_at TIMESTAMP NULL`
- `ends_at TIMESTAMP NULL`
- `cancelled_at TIMESTAMP NULL`
- `suspension_reason VARCHAR(255) NULL`
- `grace_started_at TIMESTAMP NULL`
- `grace_ends_at TIMESTAMP NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE`
- `FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT`

Index:
- `KEY subscriptions_company_id_idx (company_id)`
- `KEY subscriptions_company_uuid_idx (company_uuid)`
- `KEY subscriptions_status_idx (status)`
- `KEY subscriptions_package_id_idx (package_id)`

---

## `payments`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `company_id BIGINT UNSIGNED NOT NULL` (FK `companies`, cascade on delete)
- `invoice_id BIGINT UNSIGNED NULL` (FK `invoices`, null on delete)
- `amount DECIMAL(15,2) NOT NULL`
- `currency VARCHAR(10) NOT NULL DEFAULT 'IDR'`
- `payment_method VARCHAR(50) NULL`
- `status VARCHAR(30) NOT NULL DEFAULT 'pending'` — `pending`, `success`, `failed`, `refunded`
- `paid_at TIMESTAMP NULL`
- `external_reference VARCHAR(255) NULL`
- `notes TEXT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE`
- `FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL`

Index:
- `KEY payments_company_id_idx (company_id)`
- `KEY payments_invoice_id_idx (invoice_id)`
- `KEY payments_status_idx (status)`
- `KEY payments_paid_at_idx (paid_at)`

---

## `company_settings`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NOT NULL` (FK `companies`, cascade on delete)
- `group VARCHAR(80) NOT NULL` — `general`, `payroll`, `leave`, `attendance`, `cronjob`
- `key VARCHAR(80) NOT NULL`
- `value TEXT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE`
- `UNIQUE KEY company_settings_company_group_key_unique (company_id, group, key)`

Index:
- `KEY company_settings_group_idx (group)`
- `KEY company_settings_group_key_idx (group, key)`

---

## Related Documentation

- **Packages:** `docs/database/saas-platform/packages.md`
- **Subscription Events:** `docs/database/saas-platform/subscription-events.md`
- **Invoices:** `docs/database/saas-platform/invoices.md`
- **Feature Docs:** `docs/features/subscriptions/`
- **API:** `docs/api/subscriptions-api.md`
