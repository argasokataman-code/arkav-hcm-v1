# SaaS Platform: Packages & Add-ons

## `packages`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `code VARCHAR(80) NOT NULL UNIQUE` — `starter`, `business`, `enterprise`, `ultimate`, `umkm`, `unlimited`
- `name VARCHAR(150) NOT NULL`
- `description TEXT NULL`
- `price_monthly DECIMAL(15,2) NOT NULL DEFAULT 0`
- `price_yearly DECIMAL(15,2) NOT NULL DEFAULT 0`
- `max_employees INT UNSIGNED NULL` — NULL = unlimited
- `status VARCHAR(30) NOT NULL DEFAULT 'active'`
- `sort_order INT UNSIGNED NOT NULL DEFAULT 0`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Index:
- `KEY packages_status_idx (status)`
- `KEY packages_is_active_idx (is_active)`

---

## `package_features`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `package_id BIGINT UNSIGNED NOT NULL` (FK `packages`, cascade on delete)
- `feature_code VARCHAR(100) NOT NULL` — kode fitur (contoh: `employee_management`, `payroll`, `attendance`)
- `is_enabled BOOLEAN NOT NULL DEFAULT 1`
- `config JSON NULL` — limit/config per feature
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE`
- `UNIQUE KEY package_features_package_feature_unique (package_id, feature_code)`

Index:
- `KEY package_features_feature_code_idx (feature_code)`

---

## `package_addons`

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `code VARCHAR(80) NOT NULL UNIQUE`
- `name VARCHAR(150) NOT NULL`
- `description TEXT NULL`
- `price_monthly DECIMAL(15,2) NOT NULL DEFAULT 0`
- `price_yearly DECIMAL(15,2) NOT NULL DEFAULT 0`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `sort_order INT UNSIGNED NOT NULL DEFAULT 0`
- `created_at`, `updated_at`

Index:
- `KEY package_addons_is_active_idx (is_active)`

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

---

## `package_feature_archives`

- `id BIGINT UNSIGNED PK`
- `package_id BIGINT UNSIGNED NOT NULL` (FK `packages`, cascade on delete)
- `feature_code VARCHAR(100) NOT NULL`
- `is_enabled BOOLEAN NOT NULL`
- `archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`
- `archived_by_user_id BIGINT UNSIGNED NULL`
- `reason TEXT NULL`
- `config JSON NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE`

Index:
- `KEY package_feature_archives_package_feature_idx (package_id, feature_code)`
- `KEY package_feature_archives_archived_at_idx (archived_at)`

---

## Related Documentation

- **Companies/Subscriptions:** `docs/database/saas-platform/companies-subscriptions.md`
- **Subscription Events:** `docs/database/saas-platform/subscription-events.md`
- **Feature Docs:** `docs/features/packages/`
- **API:** `docs/api/packages-api.md`
