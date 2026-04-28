# DB Anomaly Patterns for HCM

## How to Use This File
Each pattern includes:
1. **What it detects** — the anomaly type
2. **Detection query** — paste into `mcp_mysql_query`
3. **Severity rule** — how to classify based on row count
4. **Fix pattern** — migration-safe resolution approach

---

## Pattern 1: Orphan FK Records (CRITICAL)

Child rows referencing a parent that no longer exists.

```sql
-- Template: replace <child_table>, <fk_column>, <parent_table>
SELECT c.id, c.<fk_column>
FROM <child_table> c
LEFT JOIN <parent_table> p ON p.id = c.<fk_column>
WHERE c.<fk_column> IS NOT NULL
  AND p.id IS NULL
LIMIT 100;
```

**HCM instances to always check:**
```sql
-- Orphan attendances
SELECT COUNT(*) FROM attendances a
LEFT JOIN employees e ON e.id = a.employee_id
WHERE e.id IS NULL;

-- Orphan leave_requests
SELECT COUNT(*) FROM leave_requests lr
LEFT JOIN employees e ON e.id = lr.employee_id
WHERE e.id IS NULL;

-- Orphan payroll_items
SELECT COUNT(*) FROM payroll_items pi
LEFT JOIN payroll_runs pr ON pr.id = pi.payroll_run_id
WHERE pr.id IS NULL;

-- Orphan leave_ledger_entries
SELECT COUNT(*) FROM leave_ledger_entries lle
LEFT JOIN employees e ON e.id = lle.employee_id
WHERE e.id IS NULL;
```

**Severity:** > 0 rows = CRITICAL if FK is core identity (employee_id), HIGH if secondary.

**Fix:** Add ON DELETE CASCADE or ON DELETE SET NULL depending on business rule. Clean orphans first with DELETE WHERE NOT EXISTS.

---

## Pattern 2: Null FK Violations (HIGH)

FKs that are `NOT NULL` in business logic but null in actual data.

```sql
-- Template
SELECT COUNT(*) FROM <table>
WHERE <fk_column> IS NULL
  AND <condition_that_should_require_fk>;
```

**HCM instances:**
```sql
-- Attendances with no employee
SELECT COUNT(*) FROM attendances WHERE employee_id IS NULL;

-- Payroll items with no run
SELECT COUNT(*) FROM payroll_items WHERE payroll_run_id IS NULL;

-- Leave requests with no leave type
SELECT COUNT(*) FROM leave_requests WHERE leave_type_id IS NULL;

-- model_has_roles with null role_id
SELECT COUNT(*) FROM model_has_roles WHERE role_id IS NULL;
```

**Fix:** Backfill or DELETE invalid rows, then add `NOT NULL` constraint via migration.

---

## Pattern 3: Ledger Balance Imbalance (CRITICAL)

Reported balance ≠ sum of transactions.

```sql
-- Leave balance vs ledger mismatch
SELECT
  lb.employee_id,
  lb.leave_type_id,
  lb.year,
  lb.used AS reported_used,
  COALESCE(SUM(CASE WHEN lle.type = 'debit' THEN lle.amount ELSE 0 END), 0) AS actual_used,
  (lb.used - COALESCE(SUM(CASE WHEN lle.type = 'debit' THEN lle.amount ELSE 0 END), 0)) AS gap
FROM leave_balances lb
LEFT JOIN leave_ledger_entries lle
  ON lle.employee_id = lb.employee_id
  AND lle.leave_type_id = lb.leave_type_id
  AND YEAR(lle.created_at) = lb.year
GROUP BY lb.employee_id, lb.leave_type_id, lb.year
HAVING ABS(gap) > 0
LIMIT 50;
```

```sql
-- Payroll gross vs component sum mismatch
SELECT
  pi.id,
  pi.employee_id,
  pi.gross AS reported_gross,
  SUM(CASE WHEN pc.type = 'earning' THEN pid.amount ELSE 0 END) AS computed_gross,
  (pi.gross - SUM(CASE WHEN pc.type = 'earning' THEN pid.amount ELSE 0 END)) AS gap
FROM payroll_items pi
JOIN payroll_item_details pid ON pid.payroll_item_id = pi.id
JOIN payroll_components pc ON pc.id = pid.component_id
GROUP BY pi.id
HAVING ABS(gap) > 0.01
LIMIT 50;
```

**Severity:** Any mismatch = CRITICAL.

**Fix:** Recalculate and reconcile via job/command. Add DB trigger or application-layer guard to keep in sync going forward.

---

## Pattern 4: Duplicate Business Keys (HIGH)

Multiple rows for what should be a unique combination.

```sql
-- Duplicate attendance per employee per date
SELECT employee_id, DATE(clock_in) AS date, COUNT(*) AS cnt
FROM attendances
GROUP BY employee_id, DATE(clock_in)
HAVING cnt > 1
LIMIT 50;

-- Duplicate leave balance per employee+type+year
SELECT employee_id, leave_type_id, year, COUNT(*) AS cnt
FROM leave_balances
GROUP BY employee_id, leave_type_id, year
HAVING cnt > 1;

-- Duplicate payroll item per employee per run
SELECT payroll_run_id, employee_id, COUNT(*) AS cnt
FROM payroll_items
GROUP BY payroll_run_id, employee_id
HAVING cnt > 1;
```

**Fix:** Add UNIQUE constraint after deduplication. Merge or archive duplicates based on created_at.

---

## Pattern 5: Multi-Tenant Isolation Leak (CRITICAL)

Records accessible across company boundaries.

```sql
-- Users assigned to roles from different company
SELECT u.id, u.company_id AS user_company, r.company_id AS role_company
FROM users u
JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type = 'App\\Models\\User'
JOIN roles r ON r.id = mhr.role_id
WHERE u.company_id != r.company_id
  AND r.company_id IS NOT NULL;

-- Employees belonging to wrong company departments
SELECT e.id, e.company_id AS emp_company, d.company_id AS dept_company
FROM employees e
JOIN departments d ON d.id = e.department_id
WHERE e.company_id != d.company_id;
```

**Severity:** Any mismatch = CRITICAL (security/data privacy risk).

**Fix:** Correct company_id or reassign FK. Add WHERE company_id scoping to all queries at application layer.

---

## Pattern 6: Impossible State Combinations (HIGH)

Rows in logically impossible status combinations.

```sql
-- Attendance with clock_out before clock_in
SELECT id, employee_id, clock_in, clock_out
FROM attendances
WHERE clock_out IS NOT NULL AND clock_out < clock_in;

-- Approved leave requests with no ledger entry
SELECT lr.id, lr.employee_id, lr.status, lr.start_date
FROM leave_requests lr
LEFT JOIN leave_ledger_entries lle ON lle.reference_id = lr.id
WHERE lr.status = 'approved'
  AND lle.id IS NULL;

-- Locked payroll run with draft payroll items
SELECT pr.id, pr.status, pi.id AS item_id
FROM payroll_runs pr
JOIN payroll_items pi ON pi.payroll_run_id = pr.id
WHERE pr.status = 'locked'
  AND pi.status = 'draft'
LIMIT 20;
```

**Fix:** Data correction + add CHECK constraint or application-layer validation on status transitions.

---

## Pattern 7: Soft-Delete Ghosts (MEDIUM)

Soft-deleted records still referenced by active rows.

```sql
-- Template (for models using deleted_at)
SELECT c.*
FROM <child_table> c
JOIN <parent_table> p ON p.id = c.<fk_column>
WHERE p.deleted_at IS NOT NULL
  AND c.deleted_at IS NULL
LIMIT 50;
```

**HCM instances:**
```sql
-- Active attendances for soft-deleted employees
SELECT a.id, a.employee_id
FROM attendances a
JOIN employees e ON e.id = a.employee_id
WHERE e.deleted_at IS NOT NULL
  AND a.deleted_at IS NULL
LIMIT 50;
```

**Fix:** Cascade soft delete at application layer (SoftDeletes boot method or observer). Clean existing ghosts with selective soft-delete.

---

## Pattern 8: Missing Indexes on FK Columns (MEDIUM)

FK columns with no index cause slow JOINs at scale.

```sql
-- Find FK columns that have no index (MySQL information_schema)
SELECT
  kcu.TABLE_NAME,
  kcu.COLUMN_NAME,
  kcu.REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE kcu
LEFT JOIN information_schema.STATISTICS idx
  ON idx.TABLE_SCHEMA = kcu.TABLE_SCHEMA
  AND idx.TABLE_NAME = kcu.TABLE_NAME
  AND idx.COLUMN_NAME = kcu.COLUMN_NAME
WHERE kcu.TABLE_SCHEMA = DATABASE()
  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
  AND idx.INDEX_NAME IS NULL;
```

**Fix:** `ALTER TABLE <table> ADD INDEX idx_<column> (<column>);` — safe, no data change.

---

## Pattern 9: Orphan Pivot/Junction Records (MEDIUM)

Pivot table rows where one side no longer exists.

```sql
-- role_has_permissions with missing permission
SELECT rhp.role_id, rhp.permission_id
FROM role_has_permissions rhp
LEFT JOIN permissions p ON p.id = rhp.permission_id
WHERE p.id IS NULL;

-- model_has_roles with missing role
SELECT mhr.model_id, mhr.role_id
FROM model_has_roles mhr
LEFT JOIN roles r ON r.id = mhr.role_id
WHERE r.id IS NULL;
```

**Fix:** DELETE orphan pivot rows. Add ON DELETE CASCADE to pivot FK constraints.

---

## Severity Classification Summary

| Severity | Trigger | Response |
|---|---|---|
| CRITICAL | Data loss, wrong financials, security leak, ledger imbalance | Block deploy, fix before next run |
| HIGH | Data corruption risk, duplicate keys, null violations | Fix in current sprint |
| MEDIUM | Performance risk, soft ghosts, missing indexes | Fix in next sprint |
| LOW | Code smell, missing audit columns | Backlog |
