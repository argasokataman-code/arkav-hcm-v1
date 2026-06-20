# Payroll: Payroll Items (Catalog)

Katalog halaman Payroll Items — baris katalog slip/payroll (boleh mirror master atau kustom).

## `hcm_payroll_items`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `hcm_salary_component_id BIGINT UNSIGNED NULL UNIQUE` (FK `hcm_salary_components`, null on delete) — jika null = item tanpa taut master
- `code VARCHAR(64) NOT NULL`
- `name VARCHAR(200) NOT NULL`
- `kind VARCHAR(32) NOT NULL` — `addition`, `deduction`
- `category VARCHAR(64) NOT NULL`
- `notes TEXT NULL`
- `sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (hcm_salary_component_id) REFERENCES hcm_salary_components(id) ON DELETE SET NULL`
- `UNIQUE KEY hcm_payroll_items_salary_component_unique (hcm_salary_component_id)` — satu payroll item per salary component

Index:
- `KEY hcm_payroll_items_company_id_idx (company_id)`
- `KEY hcm_payroll_items_kind_idx (kind)`
- `KEY hcm_payroll_items_is_active_idx (is_active)`

**Note:** Seed migrasi mengisi taut ke `upah_pokok`, `tunjangan_tetap_transport`, `upah_lembur` serta satu contoh kustom.

---

## Related Documentation

- **Salary Components:** `docs/database/payroll/salary-components.md`
- **Payroll Periods:** `docs/database/payroll/payroll-periods.md`
- **Feature Docs:** `docs/features/payroll-items/`
- **API:** `docs/api/hcm-payroll-items-api.md`
