# Payroll: THR (Tunjangan Hari Raya)

THR yearly settings dan batch processing.

## `hcm_thr_yearly_settings`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `calendar_year SMALLINT UNSIGNED NOT NULL` — tahun kalender perencanaan (contoh: 2026)
- `eid_date DATE NOT NULL` — tanggal Lebaran referensi
- `payment_date DATE NULL` — rencana transfer THR (biasanya terpisah dari jadwal gaji bulanan; sering 7–10 hari sebelum H)
- `calculation_cutoff_date DATE NULL` — cut-off perhitungan pro rata (mis. H-1)
- `notes TEXT NULL`
- `created_at`, `updated_at`

Constraint:
- `UNIQUE KEY hcm_thr_yearly_settings_company_year_unique (company_id, calendar_year)`

Index:
- `KEY hcm_thr_yearly_settings_company_id_idx (company_id)`
- `KEY hcm_thr_yearly_settings_calendar_year_idx (calendar_year)`

---

## `hcm_thr_batches`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `calendar_year SMALLINT UNSIGNED NOT NULL`
- `hcm_thr_yearly_setting_id BIGINT UNSIGNED NULL` (FK `hcm_thr_yearly_settings`, null on delete)
- `cutoff_date DATE NOT NULL`
- `grand_total_eligible DECIMAL(15,2) NOT NULL DEFAULT 0`
- `eligible_line_count INT UNSIGNED NOT NULL DEFAULT 0`
- `total_line_count INT UNSIGNED NOT NULL DEFAULT 0`
- `status VARCHAR(30) NOT NULL DEFAULT 'draft'` — `draft`, `assigned`
- `assigned_at TIMESTAMP NULL`
- `assigned_by_user_id BIGINT UNSIGNED NULL` (FK `users`, null on delete)
- `generated_by_user_id BIGINT UNSIGNED NULL` (FK `users`, null on delete)
- `hcm_payroll_period_id BIGINT UNSIGNED NULL` — FK opsional setelah assign
- `hcm_payroll_run_id BIGINT UNSIGNED NULL` — FK opsional setelah assign
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (hcm_thr_yearly_setting_id) REFERENCES hcm_thr_yearly_settings(id) ON DELETE SET NULL`
- `FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL`
- `FOREIGN KEY (generated_by_user_id) REFERENCES users(id) ON DELETE SET NULL`

Index:
- `KEY hcm_thr_batches_company_year_status_idx (company_id, calendar_year, status)`
- `KEY hcm_thr_batches_status_idx (status)`

---

## `hcm_thr_batch_lines`

- `id BIGINT UNSIGNED PK`
- `hcm_thr_batch_id BIGINT UNSIGNED NOT NULL` (FK `hcm_thr_batches`, cascade on delete)
- `user_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade on delete)
- `thr_slip_public_no VARCHAR(48) NOT NULL UNIQUE` — nomor slip resmi tercetak (format: `THR-{tahun}-{ULID}`; data lama: `THR-{tahun}-{id}`)
- Snapshot: `employee_name VARCHAR(255) NOT NULL`, `employee_no VARCHAR(50) NULL`
- `join_date_used DATE NULL`
- `base_salary DECIMAL(15,2) NOT NULL DEFAULT 0`
- `fixed_allowance DECIMAL(15,2) NOT NULL DEFAULT 0`
- `reference_wage DECIMAL(15,2) NOT NULL DEFAULT 0`
- `months_of_service DECIMAL(8,2) NOT NULL DEFAULT 0`
- `multiplier DECIMAL(8,4) NOT NULL DEFAULT 0`
- `thr_gross DECIMAL(15,2) NOT NULL DEFAULT 0`
- `row_status VARCHAR(20) NOT NULL DEFAULT 'full'` — `full`, `pro_rata`, `nihil`, `invalid`
- `eligible BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (hcm_thr_batch_id) REFERENCES hcm_thr_batches(id) ON DELETE CASCADE`
- `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `UNIQUE KEY hcm_thr_batch_lines_batch_user_unique (hcm_thr_batch_id, user_id)`
- `UNIQUE KEY hcm_thr_batch_lines_slip_public_no_unique (thr_slip_public_no)`

Index:
- `KEY hcm_thr_batch_lines_batch_id_idx (hcm_thr_batch_id)`
- `KEY hcm_thr_batch_lines_user_id_idx (user_id)`
- `KEY hcm_thr_batch_lines_eligible_idx (eligible)`

---

## Related Documentation

- **Payroll Periods:** `docs/database/payroll/payroll-periods.md`
- **Salary Components:** `docs/database/payroll/salary-components.md`
- **Feature Docs:** `docs/features/payroll-thr/`
- **API:** `docs/api/hcm-payroll-api.md`
