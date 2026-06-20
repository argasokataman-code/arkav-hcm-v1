# Auxiliary Tables: Performance, Training & Lifecycle

## Performance Tables

### `performance_cycles`
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `name VARCHAR(255) NOT NULL`
- `status VARCHAR(30) NOT NULL DEFAULT 'draft'`
- `start_date DATE NOT NULL`, `end_date DATE NOT NULL`
- `created_at`, `updated_at`

### `performance_indicator_templates`
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `name VARCHAR(255) NOT NULL`
- `type VARCHAR(50) NOT NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

### `performance_indicator_items`
- `id BIGINT UNSIGNED PK`
- `template_id BIGINT UNSIGNED NOT NULL` (FK `performance_indicator_templates`, cascade)
- `name VARCHAR(255) NOT NULL`
- `weight DECIMAL(5,2) NOT NULL DEFAULT 0`
- `created_at`, `updated_at`

### `performance_reviews`
- `id BIGINT UNSIGNED PK`
- `cycle_id BIGINT UNSIGNED NOT NULL` (FK `performance_cycles`, cascade)
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade)
- `reviewer_id BIGINT UNSIGNED NULL` (FK `users`, null)
- `status VARCHAR(30) NOT NULL DEFAULT 'draft'`
- `created_at`, `updated_at`

### `performance_review_scores`
- `id BIGINT UNSIGNED PK`
- `review_id BIGINT UNSIGNED NOT NULL` (FK `performance_reviews`, cascade)
- `indicator_item_id BIGINT UNSIGNED NOT NULL`
- `score DECIMAL(5,2) NOT NULL DEFAULT 0`
- `notes TEXT NULL`
- `created_at`, `updated_at`

---

## Training Tables

### `hcm_training_types`
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `name VARCHAR(255) NOT NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1`

### `hcm_trainings`
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `training_type_id BIGINT UNSIGNED NULL` (FK `hcm_training_types`)
- `trainer_id BIGINT UNSIGNED NULL` (FK `hcm_trainers`)
- `title VARCHAR(255) NOT NULL`, `description TEXT NULL`
- `start_date DATE`, `end_date DATE`
- `status VARCHAR(30) NOT NULL DEFAULT 'planned'`
- `created_at`, `updated_at`

### `hcm_training_participants`
- `id BIGINT UNSIGNED PK`
- `training_id BIGINT UNSIGNED NOT NULL` (FK `hcm_trainings`, cascade)
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade)
- `status VARCHAR(30) NOT NULL DEFAULT 'registered'`
- `created_at`, `updated_at`

### `hcm_trainers`
- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `name VARCHAR(255) NOT NULL`
- `specialization VARCHAR(255) NULL`
- `is_active BOOLEAN NOT NULL DEFAULT 1`
- `created_at`, `updated_at`

---

## Employee Lifecycle Tables

### `hcm_promotions`
- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade)
- `from_designation VARCHAR(150) NOT NULL`
- `to_designation VARCHAR(150) NOT NULL`
- `promotion_date DATE NOT NULL`
- `notes TEXT NULL`
- `created_at`, `updated_at`

### `hcm_resignations`
- `id BIGINT UNSIGNED PK`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade)
- `resignation_date DATE NOT NULL`
- `reason TEXT NULL`
- `status VARCHAR(30) NOT NULL DEFAULT 'pending'`
- `created_at`, `updated_at`

### `hcm_terminations`
- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NULL UNIQUE`
- `employee_id BIGINT UNSIGNED NOT NULL` (FK `users`, cascade)
- `termination_type VARCHAR(50) NOT NULL`
- `termination_date DATE NOT NULL`
- `reason TEXT NULL`
- `status VARCHAR(30) NOT NULL DEFAULT 'pending'`
- `approval_workflow_history JSON NULL`
- `settlement_evidence TEXT NULL`
- `created_at`, `updated_at`

### `hcm_termination_checklist_items`
- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `termination_id BIGINT UNSIGNED NOT NULL` (FK `hcm_terminations`, restrict on delete)
- `label VARCHAR(255) NOT NULL`
- `description TEXT NULL`
- `owner_name VARCHAR(100) NULL`
- `due_date DATE NULL`
- `mandatory BOOLEAN NOT NULL DEFAULT 0`
- `status ENUM('open','completed','skipped') NOT NULL DEFAULT 'open'`
- `completed_by BIGINT UNSIGNED NULL`
- `completed_at TIMESTAMP NULL`
- `completion_evidence TEXT NULL`
- `deleted_at TIMESTAMP NULL` — soft delete
- `created_at`, `updated_at`

Index:
- `KEY hcm_termination_checklist_termination_id_idx (termination_id)`
- `KEY hcm_termination_checklist_status_idx (status)`

---

## `hcm_manual_activities`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `user_id BIGINT UNSIGNED NOT NULL` (FK `users`)
- `activity_type VARCHAR(50) NOT NULL`
- `description TEXT NOT NULL`
- `activity_date DATE NOT NULL`
- `reference_type VARCHAR(50) NULL`
- `reference_id BIGINT UNSIGNED NULL`
- `created_at`, `updated_at`

---

## `calendar_events`

- `id BIGINT UNSIGNED PK`
- `company_id BIGINT UNSIGNED NULL` (index)
- `title VARCHAR(255) NOT NULL`
- `description TEXT NULL`
- `start_date DATE NOT NULL`
- `end_date DATE NULL`
- `type VARCHAR(50) NOT NULL DEFAULT 'event'`
- `created_at`, `updated_at`

---

## Related Documentation

- **Performance:** `docs/features/performance/`
- **Training:** `docs/features/training/`
- **Promotion:** `docs/features/promotion/`
- **Resignation:** `docs/features/resignation/`
- **Termination:** `docs/features/termination/`
