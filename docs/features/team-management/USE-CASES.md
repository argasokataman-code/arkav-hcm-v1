# Team Management: Use Cases & Actor Flows

**Document Purpose:** Define detailed business scenarios per actor, data flows, and decision points. Complement to README.md technical spec.

**Actors:**
- **HCM Admin**: manage teams, assign employee, report, delegate (future)
- **Team Lead**: view team, report team-scoped data (future)
- **Employee**: see own team, colleague team members (future)
- **System**: auto-create/update team via import, archive old teams (future)

---

## UC-1: Create New Team (HCM Admin)

**Actor:** HCM Admin (PT Maju Jaya tenant)  
**Trigger:** New workgroup needed in org  
**Precondition:** HCM Admin logged in, department master already set up

### Flow

1. **Navigate to Teams**
   - Admin opens menu: Employees → Teams (or direct `/teams`)
   - System displays team list page (empty or existing teams)

2. **Initiate Create**
   - Admin clicks "Create Team" button
   - System shows form modal with fields:
     - Team Name (required, text field)
     - Primary Department (optional, dropdown from departments)
     - Team Lead (optional, employee select with search)
     - Status (Active/Inactive radio, default Active)

3. **Fill Form**
   - Team Name: "Customer Service Tier 1"
   - Primary Department: "Operations" (dept_id = 10)
   - Team Lead: (search "Budi") → select "Budi Santoso" (user_id = 456)
   - Status: Active

4. **Validate & Submit**
   - System validates:
     - Team Name not empty ✓
     - Team Name not duplicate in this company ✓
     - Department exists ✓
     - Team Lead user_id valid ✓
   - Admin clicks "Save" button

5. **Backend Process (API)**
   - `POST /v1/hcm/teams` with payload:
     ```json
     {
       "name": "Customer Service Tier 1",
       "department_id": 10,
       "team_lead_id": 456,
       "is_active": true
     }
     ```
   - Backend:
     - Insert into `teams` table (auto-generate uuid)
     - Bind to active company (`company_id` from context)
     - Return created team object

6. **Success**
   - Modal closes
   - List refreshes, new team appears at top
   - Toast: "Team created successfully"
   - Admin can now assign employee to this team

### Alternative Flows

**UC-1a: Duplicate Name Error**
- Step 4: Team name "Customer Service Tier 1" already exists
- System shows error: "Team name already exists in this company"
- Admin must pick different name

**UC-1b: Invalid Team Lead**
- Step 4: Team Lead dropdown closed without selection
- Admin tries create anyway
- Backend: Team created with `team_lead_id = NULL`
- System allows (team_lead optional)

---

## UC-2: Assign Employee to Team (HCM Admin)

**Actor:** HCM Admin  
**Trigger:** New employee hired, or org restructure  
**Precondition:** Employee exists, team exists

### Flow A: Direct Employee Form

1. **Navigate to Employee**
   - Admin opens `/employees` list
   - Searches or selects existing employee "Andi Wijaya"
   - OR clicks "Create Employee"

2. **Open Employee Form**
   - Modal: "Add/Edit Employee"
   - Form has fields including **Team** dropdown (new)

3. **Select Team**
   - Admin clicks Team dropdown
   - System fetches active teams from `/v1/hcm/teams?active=true`
   - Dropdown shows: "Customer Service Tier 1", "Engineering Core", "Finance Receivables", etc.
   - Admin selects "Customer Service Tier 1"

4. **Separate Department Selection**
   - Form also has **Department** dropdown (unchanged)
   - Admin can select Department: "Operations" OR
   - Leave blank (system auto-inherit from team? NO — optional, admin choose)
   - **Key decision:** Department & Team are INDEPENDENT
     - Employee can be Dept=Finance, Team=Customer Service (cross-dept team)
     - Department NOT auto-changed when team changes

5. **Save Employee**
   - Admin fills other employee data (name, email, salary, etc.)
   - Admin clicks "Save"
   - `PUT /v1/hcm/employees/{id}` with payload:
     ```json
     {
       "name": "Andi Wijaya",
       "email": "andi@mail.com",
       "team_id": 1,
       "department_id": 10,
       ...
     }
     ```

6. **Confirm**
   - Backend validates team_id FK
   - Update employee record: `employee_profiles.team_id = 1`
   - Also update `employee_assignments.team_id = 1` (for history)
   - Response: "Employee updated successfully"

### Flow B: Bulk Assign via Import

1. **Download Template**
   - Admin: Employees → "Download Bulk Template"
   - `GET /v1/hcm/employees/bulk-template` returns Excel

2. **Template Sheets**
   - Main sheet: `employee_bulk_data`
     - Columns: employee_no, name, email, **team_id**, department_id, ...
   - Reference sheet: `ref_teams`
     - Columns: team_id, department_id, department_name, team_name
     - Data: all active teams listed for admin reference

3. **Fill Data**
   - Admin fills rows with employee data:
     - Row 2: Name="Ani", team_id=1 (Customer Service Tier 1)
     - Row 3: Name="Budi", team_id=2 (Engineering Core)
   - Or use team_name column, system resolves to team_id

4. **Upload**
   - Admin uploads via "Bulk Upload Employee"
   - `POST /v1/hcm/employees/bulk-upload` with Excel file

5. **Backend Validation**
   - System reads team_id column, validates against teams master
   - If team_id not found: ERROR "Row X: team_id tidak ditemukan pada master teams"
   - If valid: proceed with employee creation/update
   - Transactional: if any row error, entire import rollback

6. **Success**
   - Import completes, X employees assigned to teams
   - Log: "100 employees processed, 98 succeeded, 2 failed"

### Postcondition
- Employee now assigned to team ✓
- Employee department unchanged (independent) ✓
- Employee can shift schedule can be team-scoped (in next feature) ✓

---

## UC-3: View & Filter Employee by Team (HCM Admin)

**Actor:** HCM Admin  
**Trigger:** Want to see all members of a specific team  
**Precondition:** Teams exist, employees assigned

### Flow

1. **Open Employee List**
   - Navigate to `/employees`
   - See all employees list (default, unsorted by team)

2. **Add Team Filter** (future enhancement)
   - Dropdown "Filter by Team": select "Customer Service Tier 1"
   - System filters API request: `GET /v1/hcm/employees?team_id=1`
   - List refreshes, shows only 30 employees in CS team

3. **View Member Stats**
   - Info card: "Team: Customer Service Tier 1"
     - Members: 30
     - Department breakdown: Operations (15), Finance (8), HR (7)
     - Active: 28, Inactive: 2
     - On Leave: 3 (live count from attendance)

4. **Click Team Name → Team Detail** (future, `/teams/{id}`)
   - Dedicated page showing:
     - Team info: name, department, team lead
     - Member list table: name, department, designation, hire_date, shift
     - Quick actions: reassign member, add to team, remove from team

### Postcondition
- Admin can quickly assess team composition ✓
- Can drill-down into team member details ✓

---

## UC-4: Bulk Reassign Team (HCM Admin)

**Actor:** HCM Admin  
**Trigger:** Org restructure, 10 employee move from old team to new team  
**Precondition:** Both teams exist, employee assigned to old team

### Flow A: Manual One-by-One (Current)

1. Admin opens each employee form
2. Change Team field from "Old Team" to "New Team"
3. Save 10x times
4. Total time: ~5 min per employee

### Flow B: Bulk Reassign (Future, Phase 1.5)

1. **Employee List**
   - Checkbox select 10 employees: "Andi", "Budi", ... "Zahra"
   - Right-click context menu: "Actions"

2. **Bulk Action**
   - Select "Reassign to Team"
   - Dropdown: select "Customer Service Tier 2"
   - Confirmation dialog: "Move 10 employee from 'CS Tier 1' to 'CS Tier 2'?"

3. **Execute**
   - System: `POST /v1/hcm/employees/bulk-action` with payload:
     ```json
     {
       "action": "assign_team",
       "employee_ids": [1, 2, ..., 10],
       "team_id": 2
     }
     ```
   - Backend loops, updates 10 employees, transaction
   - Audit log (phase 2): "Admin X reassigned 10 employees to team Y"

4. **Result**
   - Toast: "10 employees reassigned to CS Tier 2"
   - List refreshes

### Postcondition
- 10 employees now in new team ✓
- Total time: ~30 sec (vs 50 min manual) ✓

---

## UC-5: Delete Team (HCM Admin)

**Actor:** HCM Admin  
**Trigger:** Team no longer needed, old team being retired  
**Precondition:** Team exists

### Flow: Safe Delete with Validation

1. **Team List**
   - Admin views `/teams`
   - Sees "Old CS Team" (0 members, all reassigned)
   - Clicks delete icon

2. **Pre-Delete Check**
   - System queries: `SELECT COUNT(*) FROM employee_assignments WHERE team_id = X`
   - If member_count = 0: proceed
   - If member_count > 0: ERROR, show warning dialog

3. **Error Case: Cannot Delete**
   - Dialog: "Cannot delete team with active members"
   - Button: "View Members" → filtered employee list
   - Admin must reassign members first (UC-4)

4. **Success Case: Confirm Delete**
   - Team has 0 members
   - Dialog: "Delete team 'Old CS Team'? This cannot be undone."
   - Admin clicks "Confirm Delete"

5. **Backend Delete**
   - `DELETE /v1/hcm/teams/{id}`
   - System checks member_count again (guard)
   - If 0: proceed with delete
   - If > 0: respond with error 409 "Team still has members"

6. **Success**
   - Toast: "Team deleted"
   - Team removed from list

### Alternative: Soft Delete (Future)

- Instead of hard delete, mark `is_active = false`
- Team hidden from dropdowns but data preserved
- Admin can reactivate via edit form

### Postcondition
- Team removed ✓
- No orphan employee (all reassigned first) ✓
- Data integrity maintained ✓

---

## UC-6: Team-Aware Shift Scheduling (HCM Admin → Smart Planner)

**Actor:** HCM Admin using Smart Attendance Planner  
**Trigger:** Need to schedule shifts for "Customer Service Team" (30 people, 24-hour rotating shift)  
**Precondition:** Team exists, employees assigned, shift master ready

### Flow

1. **Open Smart Planner**
   - Navigate to `/schedule-timing` → "Smart Attendance Planner" section

2. **Select Scope** (new feature, future)
   - Radio button: Generate schedule for...
     - "All Employees" (default)
     - "By Department" → select dept
     - **"By Team"** → select team (new!)

3. **Scope: By Team**
   - Admin selects "By Team"
   - Dropdown: select "Customer Service 24h"
   - System load: 30 employees in this team

4. **Configure Planner**
   - Shift template: "CS Rotating 8h" (3 shifts, daily rotation)
   - Constraints:
     - Min 6 employee per shift (coverage requirement)
     - Max 5 consecutive days per employee
     - Fairness: rotation rank-based on work streak
   - Horizon: "Generate for next 4 weeks"

5. **Generate Draft**
   - Click "Generate"
   - Backend: `POST /v1/hcm/smart-attendance-shifting/generate`
   - System:
     - Filter employee: `WHERE team_id = 1`
     - Compute optimal schedule respecting constraints
     - Return 28-day roster draft

6. **Review & Publish**
   - Calendar view: visual preview of shift assignment
   - Check: min coverage met, no excessive consecutive days
   - Publish: apply draft to team member's `schedule_timing`

### Postcondition
- Team shift schedule generated ✓
- All 30 team member assigned to shift ✓
- Fair rotation respected ✓
- Ready for attendance tracking ✓

---

## UC-7: Team-Scoped Attendance Report (HCM Admin)

**Actor:** HCM Admin  
**Trigger:** Need attendance report for "Customer Service Team" only  
**Precondition:** Attendance data exists, team assigned

### Flow

1. **Open Attendance Report**
   - Navigate to `/attendance-admin` or reporting dashboard

2. **Filter by Team**
   - Filter section (new): Team dropdown → select "Customer Service 24h"
   - Date range: April 1-30

3. **Report Generated**
   - Table: 30 employee, attendance per day
   - Columns: Name, Dept, Total Days, Present, Late, Absent, On Leave
   - Summary stats:
     - Attendance rate: 95% (27/30 team members on-time)
     - Late incidents: 2
     - Absence rate: 3%

4. **Export**
   - Admin clicks "Export to Excel"
   - File: `CS_Team_Attendance_Apr2026.xlsx`
   - Data grouped by team, ready for payroll

### Postcondition
- Team-scoped attendance visible ✓
- Easy export for payroll/audit ✓

---

## UC-8: Permission Control — Team Lead Read-Only (Phase 2)

**Actor:** Team Lead (e.g., Budi, team lead of "CS Tier 1")  
**Trigger:** Team lead granted role, logs into system  
**Precondition:** Budi assigned as team_lead_id in team master

### Flow

1. **Login**
   - Budi logs in (user permission: role=`team.lead`, team_id=1)

2. **Navigation** (new)
   - Sidebar: "My Team" section (new menu item)
   - Can access: Team Members list, attendance filter by team, report by team

3. **View Team Members**
   - Navigate to: `/teams/1/members` (new page)
   - See only 30 employees in "CS Tier 1" team
   - Columns: name, department, designation, hire_date, last_attendance_date

4. **Permission Gate**
   - Can view: team member detail, attendance, performance score
   - Cannot: create new employee, delete employee, change team assignment
   - Cannot: access other teams data

5. **Team Attendance Report**
   - Report page auto-filter by `team_id = 1`
   - Cannot change filter to other team (permission denied)

### Postcondition
- Team lead isolated to own team data ✓
- Reduces cognitive load (focus only on team) ✓

---

## Data Flow Summary

```
HCM Admin Action              → API Endpoint            → DB Update              → Downstream Effect
─────────────────────────────────────────────────────────────────────────────────────────────────
Create Team                   → POST /v1/hcm/teams      → INSERT teams           → Team dropdown populated
Assign Employee to Team       → PUT /v1/hcm/employees   → UPDATE employee_       → Team-aware report/schedule
                              → {id}                    → profiles.team_id       →
Bulk Upload with Team         → POST /v1/hcm/employees  → BATCH INSERT           → 100+ employee assigned
                              → /bulk-upload            → employee_profiles.team → Schedule ready
Filter by Team                → GET /v1/hcm/employees   → SELECT * WHERE         → Filtered list view
                              → ?team_id=X              → team_id = X            →
Generate Team Schedule        → POST /smart-attendance  → SELECT employee WHERE  → 30-person roster
                              → /generate               → team_id + compute      →
Delete Team (no member)       → DELETE /teams/{id}      → DELETE teams           → Dropdown refreshed
                              →                         → team no longer listed  →
```

---

## Key Decision Points

| Decision | Rationale | Implementation |
|----------|-----------|-----------------|
| Team != Department | Teams cross-dept, Dept vertical hierarchy | Employee can Dept ≠ Team |
| Team optional field | Not all employee need team | `team_id` nullable, default NULL |
| Team lead optional | Phase 1 MVP, phase 2 role delegation | `team_lead_id` nullable |
| Soft cascading | Don't cascade team update to employee | Explicit bulk reassign action |
| Safe delete | Prevent orphan, data integrity | Error if member exists |
| Bulk action | Efficiency, admin UX improvement | Defer to phase 1.5 |

---

## Open Questions & Assumptions

| Q | Assumption | Status |
|---|-----------|--------|
| Can 1 employee have multiple teams? | No, 1 team max | ✅ Design as 1:many (team → employee) |
| Can team span multiple companies? | No, team scoped to company | ✅ FK company_id enforced |
| Auto-create team on bulk import? | No, admin must pre-create master | ✅ Validate against teams, error if not found |
| Rename team → update employee? | No, team name is label only | ✅ No cascade, just update team.name |
| Delete team → set employee team_id NULL? | Option: error + manual reassign | ✅ Error selected for safety |
| Report team in payroll run? | Yes, aggregate salary per team | ✅ Future feature, payroll ready |
