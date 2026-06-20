# Attendance: Schema

Tabel untuk attendance records, shifts, dan schedule timings.

## `attendance_records`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `user_id BIGINT UNSIGNED NOT NULL` (FK `users`)
- `uuid CHAR(36) NULL UNIQUE`
- `work_date DATE NOT NULL`
- `check_in_at TIMESTAMP NULL`
- `check_out_at TIMESTAMP NULL`
- GPS coordinates: `check_in_latitude DECIMAL(10,7) NULL`, `check_in_longitude DECIMAL(10,7) NULL`, `check_out_latitude DECIMAL(10,7) NULL`, `check_out_longitude DECIMAL(10,7) NULL`
- `category VARCHAR(30) NULL` — regular, correction, manual
- `correction_status VARCHAR(30) NULL` — `pending`, `approved`, `rejected`
- `correction_reason TEXT NULL`
- `corrected_by_user_id BIGINT UNSIGNED NULL`
- `break_duration_minutes INT UNSIGNED NULL`
- `overtime_minutes INT UNSIGNED NULL`
- `source VARCHAR(30) NULL` — `selfie`, `web`, `admin`
- `notes TEXT NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (user_id) REFERENCES users(id)`
- `UNIQUE KEY attendance_records_user_work_date_unique (user_id, work_date)` — satu record per user per hari

Index:
- `KEY attendance_records_company_id_idx (company_id)`
- `KEY attendance_records_work_date_idx (work_date)`
- `KEY attendance_records_user_date_idx (user_id, work_date)`
- `KEY attendance_records_check_in_at_idx (check_in_at)`

**Note:** Skema aktual di migration lebih kaya (break fields, correction status, GPS). Lihat `backend/database/migrations/*attendance*`.

---

## `hcm_shifts`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `code VARCHAR(64) NOT NULL UNIQUE`
- `name VARCHAR(255) NOT NULL`
- `start_time TIME NOT NULL`
- `end_time TIME NOT NULL`
- `description VARCHAR(500) NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0`
- `created_at`, `updated_at`

Index:
- `KEY hcm_shifts_company_id_idx (company_id)`
- `KEY hcm_shifts_is_active_idx (is_active)`

---

## `hcm_schedule_timings`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index) — tenant scope
- `user_id BIGINT UNSIGNED NOT NULL UNIQUE` (FK `users`) — satu baris per user
- `hcm_shift_id BIGINT UNSIGNED NULL` (FK `hcm_shifts`, null on delete) — jika terisi, jam efektif mengikuti shift
- `start_time TIME NULL`
- `end_time TIME NULL`
- `source VARCHAR(30) NULL`
- `updated_by_user_id BIGINT UNSIGNED NULL`
- `created_at`, `updated_at`

Constraint:
- `FOREIGN KEY (user_id) REFERENCES users(id)`
- `FOREIGN KEY (hcm_shift_id) REFERENCES hcm_shifts(id) ON DELETE SET NULL`

Index:
- `KEY hcm_schedule_timings_company_id_idx (company_id)`
- `KEY hcm_schedule_timings_shift_id_idx (hcm_shift_id)`

---

## Related Documentation

- **Leave Management:** `docs/database/leave-attendance/leave-management.md`
- **Overtime:** `docs/database/leave-attendance/overtime.md`
- **Feature Docs:** `docs/features/attendance/`
- **API:** `docs/api/hcm-attendance-api.md`
