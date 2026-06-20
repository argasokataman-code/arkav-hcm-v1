# Payroll: Periods, Runs, Lines

Siklus **actual payroll** per kalender bulan.

## `hcm_payroll_periods`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `period_year SMALLINT UNSIGNED NOT NULL`
- `period_month TINYINT UNSIGNED NOT NULL` — 1–12
- `status VARCHAR(30) NOT NULL DEFAULT 'open'` — `open`, `posted` (setelah ada run yang finalized)
- `created_at`, `updated_at`

Constraint:
- `UNIQUE KEY hcm_payroll_periods_company_year_month_unique (company_id, period_year, period_month)`

Index:
- `KEY hcm_payroll_periods_company_id_idx (company_id)`
- `KEY hcm_payroll_periods_year_month_idx (period_year, period_month)`
- `KEY hcm_payroll_periods_status_idx (status)`

---

## `hcm_payroll_runs`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `hcm_payroll_period_id BIGINT UNSIGNED NOT NULL` (FK `hcm_payroll_periods`, cascade on delete)
- `purpose VARCHAR(32) NOT NULL DEFAULT 'monthly'` — `monthly` (gaji rutin), `thr` (THR massal), `pkwt_compensation` (kompensasi PKWT standalone/off-cycle)
- `status VARCHAR(30) NOT NULL DEFAULT 'draft'` — `draft`, `finalized`
- `calculated_at TIMESTAMP NULL`
- `finalized_at TIMESTAMP NULL`
- `finalized_by_user_id BIGINT UNSIGNED NULL` (FK `users`, null on delete)
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (hcm_payroll_period_id) REFERENCES hcm_payroll_periods(id) ON DELETE CASCADE`
- `FOREIGN KEY (finalized_by_user_id) REFERENCES users(id) ON DELETE SET NULL`

Index:
- `KEY hcm_payroll_runs_company_id_idx (company_id)`
- `KEY hcm_payroll_runs_period_status_idx (hcm_payroll_period_id, status)`
- `KEY hcm_payroll_runs_period_purpose_status_idx (hcm_payroll_period_id, purpose, status)`

---

## `hcm_payroll_lines`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `hcm_payroll_run_id BIGINT UNSIGNED NOT NULL` (FK `hcm_payroll_runs`, cascade on delete)
- `user_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade on delete)
- `hcm_salary_component_id BIGINT UNSIGNED NULL` (FK `hcm_salary_components`, null on delete)
- `component_code VARCHAR(64) NOT NULL` — snapshot code
- `component_name VARCHAR(200) NOT NULL` — snapshot name
- `kind VARCHAR(32) NOT NULL` — `addition`, `deduction`
- `category VARCHAR(64) NOT NULL`
- `amount DECIMAL(15,2) NOT NULL DEFAULT 0`
- `sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0`
- `meta JSON NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (hcm_payroll_run_id) REFERENCES hcm_payroll_runs(id) ON DELETE CASCADE`
- `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `FOREIGN KEY (hcm_salary_component_id) REFERENCES hcm_salary_components(id) ON DELETE SET NULL`

Index:
- `KEY hcm_payroll_lines_company_id_idx (company_id)`
- `KEY hcm_payroll_lines_run_user_idx (hcm_payroll_run_id, user_id)`
- `KEY hcm_payroll_lines_user_id_idx (user_id)`

---

## Related Documentation

- **Salary Components:** `docs/database/payroll/salary-components.md`
- **THR:** `docs/database/payroll/thr.md`
- **Payroll Items:** `docs/database/payroll/payroll-items.md`
- **Feature Docs:** `docs/features/payroll-runs/`
- **API:** `docs/api/hcm-payroll-api.md`
- **Lifecycle:** `docs/planning/payroll-lifecycle.md`
