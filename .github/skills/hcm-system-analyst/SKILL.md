---
name: hcm-system-analyst
description: "Senior HCM system analyst skill. Use when: analyzing HCM product database schema, tracing module workflows (attendance, leave, payroll, RBAC, employee), detecting database anomalies (orphan FK, null violations, integrity gaps), mapping entity relations, auditing role/permission flows, diagnosing data inconsistency, reviewing multi-tenant isolation, or planning schema migrations. Invokes MCP MySQL server for live DB queries and produces structured analysis with severity-ranked findings and actionable fixes."
argument-hint: "Describe the HCM module or problem to analyze (e.g. 'audit leave ledger relations' or 'detect orphan records in payroll')"
---

# HCM System Analyst — Senior

## What This Skill Produces

A structured, evidence-based analysis of an HCM module or database concern, including:
- Entity-Relation map with cardinality and FK health
- Business workflow trace (DB operations per step)
- Anomaly findings ranked by severity (CRITICAL / HIGH / MEDIUM / LOW)
- MCP-powered live DB evidence for each finding
- Concrete fix recommendations with migration impact assessment

## When to Use

- Designing or reviewing database relations for any HCM module
- Detecting data integrity issues (orphans, nulls, duplicate keys, broken FKs)
- Auditing role/permission matrices against DB data
- Tracing a business flow (e.g. attendance clock-in → payroll cut) through the DB layer
- Pre-migration health checks
- Explaining why a query is slow or returning wrong results

---

## Procedure

### Phase 1 — Scope & Module Identification

1. Identify the **HCM module(s)** in scope. Refer to [HCM Modules Reference](./references/hcm-modules.md) for canonical entity lists per module.
2. Determine the **analysis type**:
   - Schema / relation design review
   - Live anomaly detection
   - Business flow trace
   - Role/permission audit
   - Pre-migration check
3. Load any existing docs:
   - `docs/features/<module>/README.md`
   - `docs/api/openapi.yaml` (for API-to-DB contract cross-check)
   - `docs/planning/active-hcm-templates-and-permissions.md` (for role matrix)

---

### Phase 2 — Entity-Relation Mapping

For each module in scope:

1. **List all tables** using MCP:
   ```
   mcp_mysql_list_tables → filter by module prefix or domain keyword
   ```
2. **Describe each table** using MCP:
   ```
   mcp_mysql_describe_table → columns, types, nullability
   mcp_mysql_get_foreign_keys → FK constraints per table
   mcp_mysql_get_indexes → PK, unique, composite indexes
   ```
3. **Build ER summary** (text format is sufficient):
   - Parent → Child with FK column + ON DELETE behavior
   - Cardinality: 1:1, 1:N, M:N (via pivot)
   - Nullable FKs = optional relation (mark explicitly)
4. **Flag missing FKs**: columns ending in `_id` with no FK constraint → potential soft relation or anomaly.

---

### Phase 3 — Business Workflow Trace

Map each **business action** to the exact DB operations it triggers:

| Business Step | Table(s) Touched | Operation | Expected State Change |
|---|---|---|---|
| Employee onboarding | `employees`, `users`, `employee_documents` | INSERT | New employee + user record |
| Clock-in | `attendances` | INSERT | attendance row, status=present |
| Leave request | `leave_requests`, `leave_ledger_entries` | INSERT+UPDATE | ledger deducted |
| Payroll run | `payroll_runs`, `payroll_items`, `payroll_components` | INSERT | run locked, items generated |

Use MCP to sample real data flow:
```sql
-- Trace a specific employee through a module
SELECT * FROM <table> WHERE employee_id = <id> ORDER BY created_at DESC LIMIT 10;
```

Refer to [HCM Modules Reference](./references/hcm-modules.md) for the canonical flow per module.

---

### Phase 4 — Anomaly Detection

Run checks from [DB Anomaly Patterns](./references/db-anomaly-patterns.md) using MCP queries. Prioritize in order:

1. **Orphan FK records** (child rows referencing non-existent parents)
2. **Null FK violations** (NOT NULL FKs that are null in production)
3. **Duplicate unique key candidates** (no UNIQUE constraint on columns that should be unique)
4. **Multi-tenant isolation leaks** (records belonging to wrong `company_id`/`tenant_id`)
5. **Status/state machine violations** (rows in impossible state combinations)
6. **Ledger imbalance** (sum of ledger entries ≠ expected balance)
7. **Soft-delete ghosts** (deleted_at records still referenced by active rows)
8. **Missing indexes on FK columns** (FK column with no index = slow JOIN)

For each anomaly found, record:
```
ANOMALY: <name>
Severity: CRITICAL | HIGH | MEDIUM | LOW
Table: <table>
Evidence Query: <SQL run via MCP>
Row Count Affected: <n>
Root Cause: <explanation>
Fix: <recommended action>
Migration Risk: SAFE | NEEDS-REVIEW | BREAKING
```

---

### Phase 5 — Role/Permission Audit (if applicable)

1. Load `docs/planning/active-hcm-templates-and-permissions.md`
2. Cross-check via MCP:
   ```sql
   SELECT r.name, p.name FROM roles r
   JOIN role_has_permissions rhp ON rhp.role_id = r.id
   JOIN permissions p ON p.id = rhp.permission_id
   WHERE p.name LIKE '%<module>%';
   ```
3. Detect: roles with missing permissions for their stated scope, or permissions assigned to wrong roles.
4. Cross-check against API routes in `backend/routes/api.php` using `grep_search` for the middleware guard on each endpoint.

---

### Phase 6 — Solution & Prioritization

Output a ranked fix list:

| # | Finding | Severity | Fix Type | Migration Risk | Estimated Effort |
|---|---|---|---|---|---|
| 1 | Orphan leave_requests.employee_id | CRITICAL | FK + data cleanup | NEEDS-REVIEW | Low |
| 2 | Missing index on attendances.employee_id | HIGH | ADD INDEX | SAFE | Low |
| 3 | payroll_items with null payroll_run_id | HIGH | Backfill + NOT NULL | NEEDS-REVIEW | Medium |

For each CRITICAL or HIGH finding, provide:
- **Exact migration SQL** (or Laravel migration class outline)
- **Data cleanup query** if needed (always with dry-run `SELECT` first)
- **Rollback strategy**

---

### Phase 7 — Deliverable Format

Produce a structured report:

```
## HCM System Analysis: <Module Name>
**Date:** <today>
**Scope:** <tables analyzed>
**Analysis Type:** <schema | anomaly | flow | role-audit | pre-migration>

### Entity-Relation Summary
<ER text map>

### Business Flow Trace
<table>

### Anomaly Findings (<n> total)
#### CRITICAL (<n>)
...
#### HIGH (<n>)
...

### Role/Permission Gaps (if scoped)
...

### Fix Roadmap
<ranked table>

### Evidence Log
<MCP queries + row counts>
```

---

## MCP Server Usage

This skill relies on MCP MySQL tools for live evidence. Refer to [MCP Query Patterns](./references/mcp-query-patterns.md) for ready-to-run templates per HCM module.

**Tool sequence for a full analysis:**
1. `mcp_mysql_list_tables` → discover tables
2. `mcp_mysql_describe_table` → schema per table
3. `mcp_mysql_get_foreign_keys` → FK map
4. `mcp_mysql_get_indexes` → index coverage
5. `mcp_mysql_query` → anomaly detection queries
6. `mcp_mysql_query` → business flow sampling

Always use **read-only queries** (`SELECT`) for anomaly detection. Only propose `UPDATE`/`DELETE` as part of the fix recommendation — never execute destructive queries without explicit operator confirmation.

---

## Quality Checks Before Delivering

- [ ] Every finding backed by MCP query + row count (not assumption)
- [ ] CRITICAL/HIGH findings have exact fix SQL
- [ ] Soft relations (no FK constraint) explicitly noted, not assumed as bugs
- [ ] Multi-tenant isolation confirmed (company_id / tenant_id scoping)
- [ ] No destructive queries run without confirmation
- [ ] Fix roadmap prioritized by business impact, not alphabetically
