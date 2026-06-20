# Payroll: Salary Components

Tabel master komponen gaji (Indonesia-oriented).

## `hcm_salary_components`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope (wave tenantization payroll)
- `code VARCHAR(64) NOT NULL UNIQUE`
- `name VARCHAR(200) NOT NULL`
- `description TEXT NULL`
- `kind VARCHAR(32) NOT NULL` — `addition` | `deduction`
- `category VARCHAR(64) NOT NULL` — subset per `kind` (lihat model `HcmSalaryComponent`)
- `category_uuid CHAR(36) NULL` (index) — FK opsional ke `hcm_salary_component_categories.uuid` (null on delete) untuk canonical category mapping berbasis UUID, sementara kolom `category` tetap dipertahankan untuk kompatibilitas legacy
- `legal_basis VARCHAR(500) NULL`
- `legal_notes TEXT NULL`
- `default_percent DECIMAL(8,4) NULL` — persen default (mis. 1.0000 = 1%); NULL = nominal per siklus gaji
- `percent_basis VARCHAR(64) NULL` — dasar perhitungan jika `default_percent` terisi (`basic_wage`, `wage_bpjs_health`, `wage_bpjs_tk`, `gross_monthly_ter`, `thr_calculation_base`); keduanya harus null bersamaan untuk komponen nominal
- Flag boolean: `include_bpjs_health_wage_base`, `include_bpjs_tk_wage_base`, `include_thr_calculation_base`, `include_pph21_ter_gross`, `include_pph21_annual_reconciliation`, `subject_overtime_regulation`, `affects_net_pay`, `employer_cost_line`
- `is_system_locked BOOLEAN NOT NULL DEFAULT 0` — baris seed; tidak boleh dihapus
- `sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Constraint:
- `UNIQUE KEY hcm_salary_components_kind_category_name_unique (kind, category, name)` — mencegah duplikasi nama komponen dalam kombinasi kind+category yang sama

Index:
- `KEY hcm_salary_components_company_id_idx (company_id)`
- `KEY hcm_salary_components_kind_idx (kind)`
- `KEY hcm_salary_components_category_idx (category)`
- `KEY hcm_salary_components_category_uuid_idx (category_uuid)`
- `KEY hcm_salary_components_is_active_idx (is_active)`

**Note:** Seed sistem aktif mencakup komponen integrasi payroll penting seperti `upah_pokok`, `tunjangan_tetap`, `upah_lembur`, `thr`, dan `kompensasi_pkwt`.

---

## Related Documentation

- **Payroll Periods:** `docs/database/payroll/payroll-periods.md`
- **Payroll Items:** `docs/database/payroll/payroll-items.md`
- **Feature Docs:** `docs/features/payroll-salary-components/`
- **API:** `docs/api/hcm-salary-components-api.md`
