# Auxiliary Tables: Feature Classifications

## `feature_classifications`

Runtime classification fitur untuk keperluan billing/composer.

- `id BIGINT UNSIGNED PK`
- `feature_code VARCHAR(100) NOT NULL UNIQUE`
- `tier VARCHAR(16) NOT NULL DEFAULT 'addon'` — klasifikasi komersial: `default`, `mvp`, `addon` (lihat `docs/features/RUNTIME-FEATURE-CLASSIFICATION.md`)
- `created_at`, `updated_at`

Index:
- `KEY feature_classifications_tier_idx (tier)`

---

## Related Documentation

- **Feature Classification:** `docs/features/RUNTIME-FEATURE-CLASSIFICATION.md`
- **Packages:** `docs/database/saas-platform/packages.md`
