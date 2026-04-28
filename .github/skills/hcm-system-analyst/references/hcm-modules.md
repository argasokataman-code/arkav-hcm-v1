# HCM Modules Reference

## Module Map — Canonical Entities

### 1. Employee Core
| Table | Key Columns | Relations |
|---|---|---|
| `employees` | `id`, `user_id`, `company_id`, `department_id`, `position_id`, `employment_status` | Parent for most HCM data |
| `users` | `id`, `email`, `company_id` | Auth identity linked to employee |
| `departments` | `id`, `company_id`, `parent_id` | Tree structure (self-referential) |
| `positions` | `id`, `company_id`, `department_id` | Job roles |
| `employee_documents` | `id`, `employee_id`, `document_type` | 1:N to employees |
| `employee_addresses` | `id`, `employee_id`, `type` | 1:N to employees |

**Key business flows:**
- Onboarding: `users` INSERT → `employees` INSERT → department/position assignment
- Status change: `employees.employment_status` transition (active → resigned → terminated)

---

### 2. Attendance
| Table | Key Columns | Relations |
|---|---|---|
| `attendances` | `id`, `employee_id`, `company_id`, `clock_in`, `clock_out`, `status`, `date` | FK → employees |
| `attendance_locations` | `id`, `attendance_id`, `lat`, `lng`, `type` | FK → attendances |
| `attendance_overrides` | `id`, `attendance_id`, `requested_by`, `approved_by` | Override/correction |
| `work_schedules` | `id`, `company_id`, `shift_name`, `start_time`, `end_time` | Shift definitions |
| `employee_schedules` | `id`, `employee_id`, `work_schedule_id`, `effective_date` | Employee→schedule mapping |

**Key business flows:**
- Clock-in: INSERT `attendances` (status=present, clock_out=null)
- Clock-out: UPDATE `attendances` SET clock_out, calculate duration
- Override: INSERT `attendance_overrides`, approval changes `attendances.status`

**Critical integrity checks:**
- Duplicate clock-in same day same employee
- clock_out < clock_in (impossible state)
- Missing `employee_schedule` for employee with attendance records

---

### 3. Leave
| Table | Key Columns | Relations |
|---|---|---|
| `leave_types` | `id`, `company_id`, `name`, `is_paid`, `max_days` | Leave policy definitions |
| `leave_requests` | `id`, `employee_id`, `leave_type_id`, `status`, `start_date`, `end_date` | FK → employees, leave_types |
| `leave_approvals` | `id`, `leave_request_id`, `approver_id`, `status` | Approval chain |
| `leave_balances` | `id`, `employee_id`, `leave_type_id`, `year`, `balance`, `used` | Ledger summary |
| `leave_ledger_entries` | `id`, `employee_id`, `leave_type_id`, `amount`, `type` (credit/debit), `reference_id` | Audit trail |
| `leave_policies` | `id`, `company_id`, `leave_type_id`, `accrual_rule` | Company policy |

**Key business flows:**
- Request: INSERT `leave_requests` (status=pending)
- Approve: UPDATE `leave_requests.status`=approved → INSERT `leave_ledger_entries` (debit) → UPDATE `leave_balances`
- Reject: UPDATE status=rejected, no ledger impact
- Cancel: reverse ledger (INSERT credit entry) → UPDATE balance

**Critical integrity checks:**
- `leave_balances.used` ≠ SUM of debit entries in `leave_ledger_entries`
- Approved leave with no ledger entry
- leave_request spanning weekend/holiday not adjusted

---

### 4. Payroll
| Table | Key Columns | Relations |
|---|---|---|
| `payroll_runs` | `id`, `company_id`, `period_start`, `period_end`, `status`, `locked_at` | Payroll period container |
| `payroll_items` | `id`, `payroll_run_id`, `employee_id`, `gross`, `net`, `deductions` | Per-employee payslip |
| `payroll_components` | `id`, `company_id`, `name`, `type` (earning/deduction), `calculation_type` | Component definitions |
| `payroll_item_details` | `id`, `payroll_item_id`, `component_id`, `amount` | Breakdown per component |
| `tax_calculations` | `id`, `payroll_item_id`, `tax_type`, `taxable_income`, `tax_amount` | Tax breakdown |
| `salary_structures` | `id`, `employee_id`, `effective_date`, `base_salary` | Employee salary record |

**Key business flows:**
- Run generation: SELECT active employees → compute components → INSERT `payroll_items` + `payroll_item_details`
- Lock: UPDATE `payroll_runs.status`=locked (immutable after this)
- Payslip: SELECT payroll_item + details + tax for employee

**Critical integrity checks:**
- `payroll_items.gross` ≠ SUM of earning `payroll_item_details.amount`
- `payroll_items.net` ≠ gross - SUM of deduction details
- Multiple `payroll_items` for same employee in same `payroll_run_id`
- `payroll_items` with null `payroll_run_id`

---

### 5. RBAC / Multi-Tenant
| Table | Key Columns | Relations |
|---|---|---|
| `companies` | `id`, `name`, `slug`, `plan`, `status` | Tenant root |
| `roles` | `id`, `name`, `guard_name`, `company_id` | Spatie roles, scoped to company |
| `permissions` | `id`, `name`, `guard_name` | Global permission definitions |
| `model_has_roles` | `model_type`, `model_id`, `role_id` | Polymorphic role assignment |
| `role_has_permissions` | `role_id`, `permission_id` | Role→Permission matrix |

**Multi-tenant isolation rule:** Every major entity MUST have `company_id`. Queries without `WHERE company_id = ?` are tenant leaks.

**Critical integrity checks:**
- `model_has_roles` referencing non-existent `role_id`
- Roles with `company_id=null` assigned to tenant users
- Employees from company A able to see company B data (missing `company_id` scope on query)

---

## Module Dependency Graph

```
companies
  └── departments → employees → users
                  → attendances → attendance_locations
                  → leave_requests → leave_approvals
                                  → leave_ledger_entries → leave_balances
                  → payroll_items → payroll_item_details
                                  → tax_calculations
  └── payroll_runs
  └── leave_types → leave_policies
  └── payroll_components → salary_structures
  └── work_schedules → employee_schedules
  └── roles → model_has_roles
```

---

## Status/State Machine Reference

### leave_requests.status
`pending` → `approved` | `rejected`
`approved` → `cancelled`

### payroll_runs.status
`draft` → `processing` → `completed` → `locked`

### attendances.status
`present` | `absent` | `late` | `half_day` | `on_leave` | `holiday`

### employees.employment_status
`active` → `resigned` | `terminated` | `on_leave` | `suspended`
