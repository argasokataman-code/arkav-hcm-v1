# MCP Query Patterns for HCM Analysis

## Quick Invocation Reference

All queries use `mcp_mysql_query` unless noted.
Always scope queries with `WHERE company_id = <id>` for tenant-safe analysis.

---

## Step 0: Database Discovery

```
// List all tables
mcp_mysql_list_tables

// Describe a specific table
mcp_mysql_describe_table → table: "employees"

// Get FKs for a table
mcp_mysql_get_foreign_keys → table: "leave_requests"

// Get indexes for a table
mcp_mysql_get_indexes → table: "attendances"
```

---

## Module: Employee Core

```sql
-- Count employees by status per company
SELECT company_id, employment_status, COUNT(*) AS cnt
FROM employees
GROUP BY company_id, employment_status;

-- Employees with no user account
SELECT e.id, e.name FROM employees e
LEFT JOIN users u ON u.id = e.user_id
WHERE u.id IS NULL;

-- Employees with no department
SELECT id, name FROM employees WHERE department_id IS NULL AND employment_status = 'active';

-- Recently onboarded (last 30 days)
SELECT id, name, created_at FROM employees
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY created_at DESC LIMIT 20;
```

---

## Module: Attendance

```sql
-- Attendance summary for date range
SELECT employee_id, status, COUNT(*) AS cnt
FROM attendances
WHERE date BETWEEN '2026-01-01' AND '2026-04-27'
GROUP BY employee_id, status
ORDER BY employee_id;

-- Missing clock-out (in-progress or forgotten)
SELECT id, employee_id, clock_in, date
FROM attendances
WHERE clock_out IS NULL AND date < CURDATE()
ORDER BY date DESC LIMIT 50;

-- Late arrivals count per employee
SELECT employee_id, COUNT(*) AS late_count
FROM attendances
WHERE status = 'late'
  AND date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
GROUP BY employee_id
ORDER BY late_count DESC LIMIT 20;

-- Duplicate attendance check
SELECT employee_id, DATE(clock_in) AS date, COUNT(*) AS cnt
FROM attendances
GROUP BY employee_id, DATE(clock_in)
HAVING cnt > 1;

-- Employees with attendance but no work schedule
SELECT DISTINCT a.employee_id
FROM attendances a
LEFT JOIN employee_schedules es ON es.employee_id = a.employee_id
WHERE es.id IS NULL;
```

---

## Module: Leave

```sql
-- Pending leave requests older than 7 days
SELECT id, employee_id, leave_type_id, created_at, DATEDIFF(NOW(), created_at) AS days_pending
FROM leave_requests
WHERE status = 'pending'
  AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY days_pending DESC;

-- Leave balance summary per employee
SELECT lb.employee_id, lt.name AS leave_type, lb.balance, lb.used, (lb.balance - lb.used) AS remaining
FROM leave_balances lb
JOIN leave_types lt ON lt.id = lb.leave_type_id
WHERE lb.year = YEAR(CURDATE())
ORDER BY lb.employee_id;

-- Approved requests with no ledger entry
SELECT lr.id, lr.employee_id, lr.status
FROM leave_requests lr
LEFT JOIN leave_ledger_entries lle ON lle.reference_id = lr.id
WHERE lr.status = 'approved' AND lle.id IS NULL;

-- Ledger vs balance reconciliation
SELECT
  lb.employee_id,
  lb.leave_type_id,
  lb.used AS reported,
  COALESCE(SUM(CASE WHEN lle.type='debit' THEN lle.amount ELSE 0 END),0) AS actual,
  (lb.used - COALESCE(SUM(CASE WHEN lle.type='debit' THEN lle.amount ELSE 0 END),0)) AS gap
FROM leave_balances lb
LEFT JOIN leave_ledger_entries lle
  ON lle.employee_id = lb.employee_id
  AND lle.leave_type_id = lb.leave_type_id
  AND YEAR(lle.created_at) = lb.year
GROUP BY lb.employee_id, lb.leave_type_id, lb.year
HAVING ABS(gap) > 0;
```

---

## Module: Payroll

```sql
-- Active payroll runs
SELECT id, company_id, period_start, period_end, status, created_at
FROM payroll_runs
ORDER BY created_at DESC LIMIT 10;

-- Payroll items count per run
SELECT pr.id, pr.period_start, pr.period_end, pr.status, COUNT(pi.id) AS item_count
FROM payroll_runs pr
LEFT JOIN payroll_items pi ON pi.payroll_run_id = pr.id
GROUP BY pr.id ORDER BY pr.created_at DESC LIMIT 10;

-- Gross vs component sum mismatch
SELECT
  pi.id, pi.employee_id, pi.gross AS reported,
  SUM(CASE WHEN pc.type='earning' THEN pid.amount ELSE 0 END) AS computed,
  (pi.gross - SUM(CASE WHEN pc.type='earning' THEN pid.amount ELSE 0 END)) AS gap
FROM payroll_items pi
JOIN payroll_item_details pid ON pid.payroll_item_id = pi.id
JOIN payroll_components pc ON pc.id = pid.component_id
GROUP BY pi.id
HAVING ABS(gap) > 0.01
LIMIT 50;

-- Net pay sanity check
SELECT
  pi.id,
  pi.gross,
  pi.net,
  SUM(CASE WHEN pc.type='deduction' THEN pid.amount ELSE 0 END) AS total_deductions,
  (pi.gross - SUM(CASE WHEN pc.type='deduction' THEN pid.amount ELSE 0 END)) AS expected_net,
  (pi.net - (pi.gross - SUM(CASE WHEN pc.type='deduction' THEN pid.amount ELSE 0 END))) AS net_gap
FROM payroll_items pi
JOIN payroll_item_details pid ON pid.payroll_item_id = pi.id
JOIN payroll_components pc ON pc.id = pid.component_id
GROUP BY pi.id
HAVING ABS(net_gap) > 0.01
LIMIT 50;

-- Employees with no salary structure
SELECT e.id, e.name
FROM employees e
LEFT JOIN salary_structures ss ON ss.employee_id = e.id
WHERE ss.id IS NULL AND e.employment_status = 'active';
```

---

## Module: RBAC / Multi-Tenant

```sql
-- All roles and their permission counts per company
SELECT r.company_id, r.name AS role, COUNT(rhp.permission_id) AS perm_count
FROM roles r
LEFT JOIN role_has_permissions rhp ON rhp.role_id = r.id
GROUP BY r.id ORDER BY r.company_id, r.name;

-- Permissions for a specific role
SELECT p.name FROM permissions p
JOIN role_has_permissions rhp ON rhp.permission_id = p.id
JOIN roles r ON r.id = rhp.role_id
WHERE r.name = '<ROLE_NAME>' AND r.guard_name = 'web';

-- Users with no roles
SELECT u.id, u.email, u.company_id
FROM users u
LEFT JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type = 'App\\Models\\User'
WHERE mhr.role_id IS NULL AND u.email != 'admin@system.com';

-- Cross-company role assignment (isolation leak)
SELECT u.id, u.company_id AS user_co, r.company_id AS role_co, r.name AS role
FROM users u
JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type = 'App\\Models\\User'
JOIN roles r ON r.id = mhr.role_id
WHERE u.company_id != r.company_id AND r.company_id IS NOT NULL;

-- Orphan model_has_roles
SELECT mhr.model_id, mhr.role_id
FROM model_has_roles mhr
LEFT JOIN roles r ON r.id = mhr.role_id
WHERE r.id IS NULL;
```

---

## Meta / Schema Health

```sql
-- Tables without company_id (multi-tenant gap check)
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE'
  AND TABLE_NAME NOT IN (
    SELECT TABLE_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'company_id'
  )
ORDER BY TABLE_NAME;

-- FK columns with no index
SELECT kcu.TABLE_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE kcu
LEFT JOIN information_schema.STATISTICS idx
  ON idx.TABLE_SCHEMA = kcu.TABLE_SCHEMA
  AND idx.TABLE_NAME = kcu.TABLE_NAME
  AND idx.COLUMN_NAME = kcu.COLUMN_NAME
WHERE kcu.TABLE_SCHEMA = DATABASE()
  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
  AND idx.INDEX_NAME IS NULL;

-- Tables with the most rows (identify high-volume tables)
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_ROWS DESC LIMIT 20;

-- Recent schema changes (if audit log available)
SELECT * FROM migrations ORDER BY batch DESC LIMIT 20;
```

---

## Execution Notes

- Always use `LIMIT` on anomaly queries unless counting.
- Use `COUNT(*)` first before fetching rows for large tables.
- Never run `UPDATE`/`DELETE` without explicit user confirmation.
- For production analysis, prefer `SELECT ... INTO OUTFILE` or sample with `LIMIT 100` to avoid locking.
- If a query times out, add `USE INDEX` hint or break into smaller date ranges.
