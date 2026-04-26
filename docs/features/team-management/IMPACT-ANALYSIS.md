# Team Management Feature — Complete Impact Analysis

**Last Updated:** 2026-04-26  
**Phase:** 1 (MVP CRUD + employee form integration)  
**Objective:** Identify ALL files that must be created, modified, or documented for Team Master CRUD feature.  
**Scope:** Excludes Phase 2+ (team lead delegation, analytics, payroll grouping, attendance team-filtering).

---

## Executive Summary

**Total components affected:** 23 primary + 7 secondary  
**New files to create:** 8  
**Existing files to modify:** 15  
**Documentation-only files:** 7  
**Estimated effort:** 9–11 days (Phase 1 MVP)

**Risk Level:** LOW (pattern mirrors department/designation CRUD, no novel integration)

---

## Existing UI/UX Affected (User Perspective)

**Summary:** 3 major existing UI flows will change. ALL user-facing changes are **additive** (no removal), **backward compatible** (old flows still work).

| Existing Flow | Current Behavior | Phase 1 Change | User Impact | Risk |
|---------------|------------------|----------------|------------|------|
| **Sidebar menu** | Shows: Employees, Departments, Designations, Policies, etc. | **Add:** "Teams" item under "Organization Master" section | User sees new menu link to `/teams` page; can navigate to team CRUD | NONE — pure addition |
| **Employee Create Form** | Modal with: Name, Email, Dept dropdown, Designation, Employment Type, Salary, Contract, etc. | **Add:** Team dropdown (optional field, appears after Department) | User optionally selects team when creating employee; field auto-loads department teams or all active teams | LOW — optional field, non-breaking |
| **Employee Edit Form** | Modal with all employee fields pre-filled | **Add:** Team dropdown pre-filled with current team (if exists) | User can change employee team; update persists to DB | LOW — optional field, non-breaking |
| **Employee List/Grid** | Columns: Name, Email, Dept, Designation, Salary, Status | **No change Phase 1** | User sees no visual difference (team column deferred to Phase 2) | NONE |
| **Bulk Employee Import** | Template: ref_departments, ref_designations, employee data sheet | **No breaking change** (ref_teams already exists in template) | User can optionally assign teams via bulk import (same as before) | NONE — backward compat |
| **Existing Tests** (PHPUnit + Vitest) | 700+ tests: employees CRUD, departments, designations, leave, payroll, attendance | **Add:** 12+ new team tests; run alongside existing | Tests still pass; new tests isolated | NONE — regression caught |

**Verdict:**  
- ✅ Zero **removal** of existing UI elements  
- ✅ Zero **breaking changes** to existing user flows  
- ✅ All changes **purely additive** (new menu + optional form field)  
- ✅ All changes **backward compatible** (legacy data flows preserved)

---

## Phase 1 UI/UX Regression Prevention

To ensure existing UI/UX is NOT regressed, the following will be validated **before merge to main**:

### Pre-Deployment Validation (QA Checklist)

| Check | How | Pass Criteria |
|-------|-----|--------------|
| **Sidebar renders correctly** | Open browser, login as HCM admin, check sidebar | "Teams" menu item visible + clickable; other menus unchanged |
| **Employee create still works** | Navigate to `/employees`, click Create, open form | Form loads; team dropdown appears; form submits without error; employee created |
| **Employee edit still works** | On `/employees`, click edit on any employee, open form | Form pre-fills; team dropdown visible; can change team; update saves |
| **Employee list unchanged** | Check `/employees` page | Columns same (no team column added); grid renders normally; pagination works |
| **Bulk import unchanged** | GET `/v1/hcm/employees/bulk-template` → download → check sheets | ref_teams sheet exists (same as before); employee data sheet unchanged |
| **Department/Designation CRUD unchanged** | Navigate to `/departments` and `/designations` | Pages load; CRUD operations work; no new errors |
| **All existing tests pass** | Run `bash scripts/local-test-gate.sh` | PHPUnit: 700+ tests pass; Vitest: 150+ tests pass; NO FAILURES |
| **No console JS errors** | Open browser DevTools → Console | Zero JS errors related to team dropdown or new code |
| **Permission guards intact** | As non-admin user, try accessing `/teams` | Page redirects or shows 403; non-admin cannot see menu item |
| **Tenant scoping intact** | Switch companies in multi-tenant scenario | Teams scoped to active company; no cross-tenant leakage |

**Responsibility:** QA will run this checklist post-development, before sign-off.  
**Gate:** If ANY check fails, feature returns to dev for fixes (no merge until all pass).

---

## Part A: MUST CHANGE (Phase 1 Blocking)

Changes that **block Phase 1 MVP**. All must be completed and tested before:
- Shift to Phase 1.5 (permission scope hardening)
- Demo to business stakeholders
- Merge to `main` and deploy

### A.1 Backend API Controller

**File:** `backend/app/Http/Controllers/Api/HcmTeamController.php`  
**Type:** NEW  
**Status:** ❌ Not started  
**Effort:** 3–4 hours

**Why:** Central CRUD endpoint for team master data.

**Implementation checklist:**
- [ ] Class `HcmTeamController extends Controller`
- [ ] Guard decorator: `#[Authorize('team.manage')]` (fallback `EnsuresHcmAdmin` until permission scope ready in Phase 1.5)
- [ ] Method `index(Request $req)`:
  - Query params: `page`, `perPage`, `search` (by name, department), `status` (`active|inactive|all`)
  - Tenant-scoped via middleware `tenant.context`
  - Response: paginated list with columns: `id`, `uuid`, `name`, `department_id`, `departmentName`, `team_lead_id`, `teamLeadName`, `member_count`, `is_active`, `created_at`
  - Left join `departments` + count `employee_assignments` grouped by `team_id`
- [ ] Method `store(Request $req)`:
  - Validation: `name` (required, unique per company+dept+name), `department_id` (required, exists in dept master), `team_lead_id` (optional, exists in users), `is_active` (default true)
  - Create `Team` model instance with tenant `company_id` auto-inferred
  - Response: `201` with created team + `id`, `uuid`
- [ ] Method `show(int|string $id)`:
  - Lookup by id or uuid
  - Response: full team object + `member_count`
- [ ] Method `update(int|string $id, Request $req)`:
  - Same validation as store (name uniqueness check excludes self)
  - Update `Team` model
  - Response: `200` with updated team
- [ ] Method `destroy(int|string $id)`:
  - Safety check: if `employee_assignments` exist for this team, return `409` with message "Cannot delete team with active members. Reassign members first."
  - Delete `Team` model
  - Response: `204` no content
- [ ] Error handling: `TeamNotFoundException`, `TeamDeletionBlockedError`
- [ ] Tests: `backend/tests/Feature/HcmTeamApiTest.php` (min 6 scenarios: CRUD + safety checks)

**API Contract (OpenAPI):**
```yaml
/v1/hcm/teams:
  get:
    summary: List teams
    parameters: [page, perPage, search, status]
    responses:
      200:
        content:
          application/json:
            schema:
              properties:
                data:
                  type: array
                  items: { $ref: '#/components/schemas/Team' }
                meta: { $ref: '#/components/schemas/Pagination' }
  post:
    summary: Create team
    requestBody:
      required: true
      content:
        application/json:
          schema: { $ref: '#/components/schemas/TeamCreate' }
    responses:
      201: { description: Team created }
      422: { description: Validation error }

/v1/hcm/teams/{id}:
  get:
    summary: Get team detail
    responses:
      200: { content: { application/json: { schema: { $ref: '#/components/schemas/Team' } } } }
      404: { description: Team not found }
  put:
    summary: Update team
    responses:
      200: { content: { application/json: { schema: { $ref: '#/components/schemas/Team' } } } }
      422: { description: Validation error }
      404: { description: Team not found }
  delete:
    summary: Delete team
    responses:
      204: { description: Team deleted }
      409: { description: Cannot delete team with members }
      404: { description: Team not found }
```

---

### A.2 Backend Route Registration

**File:** `backend/routes/api.php`  
**Type:** MODIFY  
**Status:** ❌ Not started  
**Effort:** 0.5 hours

**Why:** Register `/v1/hcm/teams` routes.

**Implementation checklist:**
- [ ] Add route group under `/v1/hcm` namespace:
  ```php
  Route::apiResource('teams', HcmTeamController::class)
       ->middleware(['auth:sanctum', 'tenant.context'])
       ->names('hcm.teams');
  ```
  Or explicit routes:
  ```php
  Route::middleware(['auth:sanctum', 'tenant.context'])->prefix('/v1/hcm')->group(function () {
      Route::get('/teams', [HcmTeamController::class, 'index'])->name('hcm.teams.index');
      Route::post('/teams', [HcmTeamController::class, 'store'])->name('hcm.teams.store');
      Route::get('/teams/{id}', [HcmTeamController::class, 'show'])->name('hcm.teams.show');
      Route::put('/teams/{id}', [HcmTeamController::class, 'update'])->name('hcm.teams.update');
      Route::delete('/teams/{id}', [HcmTeamController::class, 'destroy'])->name('hcm.teams.destroy');
  });
  ```
- [ ] Ensure guard `hcm.web.admin` (or future `team.manage` permission) applied via controller decorator
- [ ] No breaking changes to existing routes

---

### A.3 Web Route & Menu Registration

**File:** `backend/routes/web.php`  
**Type:** MODIFY  
**Status:** ❌ Not started  
**Effort:** 0.5 hours

**Why:** Register `/teams` web route for UI page.

**Implementation checklist:**
- [ ] Add route entry after `/designations` line (~line 467):
  ```php
  Route::get('/teams', function () {
      return view(view: 'teams');
  })->middleware('hcm.web.admin')->name('teams');
  ```
- [ ] Ensure it mirrors department/designation pattern

**Sidebar/Menu (Blade layout):**  
**File:** `backend/resources/views/layouts/sidebar.blade.php` or main layout  
**Type:** MODIFY  
**Status:** ⚠️ Check current  
**Effort:** 0.5 hours

- [ ] Add menu item under "HCM > Organization Master" or "HCM > Settings" section:
  ```blade
  @if ($isHcmAdmin)
      <li>
          <a href="{{ route('teams') }}" class="{{ Route::is('teams') ? 'active' : '' }}">
              Teams
          </a>
      </li>
  @endif
  ```
- [ ] Or add to secondary menu list (check current structure in existing sidebar)
- [ ] Verify permission guard matches route `hcm.web.admin`

---

### A.4 Blade View (UI Page)

**File:** `backend/resources/views/teams.blade.php`  
**Type:** NEW  
**Status:** ❌ Not started  
**Effort:** 2–3 hours

**Why:** Render teams CRUD page.

**Implementation checklist:**
- [ ] Copy structure from `resources/views/departments.blade.php` as template
- [ ] Page structure:
  - [ ] Header + breadcrumb
  - [ ] Search bar + active/inactive filter
  - [ ] List table with columns: `id` (hidden), `name`, `department`, `member_count`, `team_lead`, `is_active`, `actions` (edit/delete buttons)
  - [ ] Create button → opens modal form
  - [ ] Edit button → populates modal with existing team data
  - [ ] Delete button → confirmation dialog
  - [ ] Pagination controls
- [ ] Modal forms:
  - Create form:
    ```html
    <form data-hcm-form="team-add">
        <label>Team Name <input name="name" required max="100"></label>
        <label>Department <select name="department_id" data-hcm-field="team-department" required></select></label>
        <label>Team Lead <select name="team_lead_id" data-hcm-field="team-lead"></select></label>
        <label>Active <select name="is_active" data-hcm-field="team-active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select></label>
        <button type="submit">Create</button>
    </form>
    ```
  - Edit form: similar, with `data-hcm-form="team-edit"`
  - Delete confirmation:
    ```html
    <button data-hcm-delete="team" data-id="...">Confirm Delete</button>
    ```
- [ ] CSRF token in forms (Laravel blade provides `@csrf`)
- [ ] Load `api-client.js` + `team-master-data.js` scripts at end
- [ ] No hardcoded team data; all fetched via JS API

---

### A.5 Frontend JS Manager

**File:** `frontend/resources/js/team-master-data.js`  
**Type:** NEW  
**Status:** ❌ Not started  
**Effort:** 3–4 hours

**Why:** CRUD binding for teams page.

**Implementation checklist:**
- [ ] Structure (mirror `employees-data.js` / `hcm-pages-data.js` pattern):
  - [ ] `fetchTeams(page, perPage, search, status)` → `GET /v1/hcm/teams?page=X&perPage=Y&search=S&status=T`
  - [ ] `renderTeamGrid(data, pagination)` → render table rows + pagination UI
  - [ ] `createTeam(payload)` → `POST /v1/hcm/teams` with validation
  - [ ] `editTeam(id, payload)` → `PUT /v1/hcm/teams/{id}`
  - [ ] `deleteTeam(id)` → `DELETE /v1/hcm/teams/{id}` with error handling
  - [ ] `initTeamMasterPage()` → setup event listeners for form submissions
- [ ] Form bindings:
  - [ ] Create form `[data-hcm-form="team-add"]`: collect name, dept, lead, active → POST
  - [ ] Edit form `[data-hcm-form="team-edit"]`: prefill with existing data → PUT
  - [ ] Delete buttons `[data-hcm-delete="team"]`: confirm → DELETE
- [ ] Error handling:
  - [ ] Show toast/alert on create/edit/delete success
  - [ ] Show error message on API failure (e.g., "Team name already exists", "Cannot delete team with members")
  - [ ] Refresh grid after successful mutation
- [ ] Search + filter:
  - [ ] On-change listener for search input → debounce 300ms → refetch
  - [ ] On-change listener for status dropdown → refetch
  - [ ] Preserve pagination state on search
- [ ] Department/Team Lead dropdowns:
  - [ ] Load department master on modal open: `GET /v1/hcm/departments` or use cached list
  - [ ] Load user list for team lead: `GET /v1/hcm/user-management/users` with filter role HR/admin
  - [ ] Populate select options dynamically
- [ ] Tests: `frontend/__tests__/team-master.test.js` (min 4 scenarios: fetch, create, update, delete)

**Example function structure:**
```js
export async function fetchTeams(page = 1, perPage = 20, search = '', status = 'all') {
    const params = new URLSearchParams({ page, perPage, search, status });
    const resp = await fetch(`/v1/hcm/teams?${params}`, {
        headers: { 'Authorization': `Bearer ${getToken()}` }
    });
    if (!resp.ok) throw new Error(`Fetch failed: ${resp.status}`);
    return resp.json();
}

export async function createTeam(name, departmentId, teamLeadId = null, isActive = true) {
    const resp = await fetch('/v1/hcm/teams', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${getToken()}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ name, department_id: departmentId, team_lead_id: teamLeadId, is_active: isActive })
    });
    if (!resp.ok) throw new Error(`Create failed: ${resp.status}`);
    return resp.json();
}
// ... similar for update, delete
```

---

### A.6 Integration: Employee Form Team Dropdown

**File:** `backend/app/Http/Controllers/Api/HcmEmployeeController.php`  
**Type:** MODIFY  
**Status:** ⚠️ Partial (team field exists, dropdown binding missing)  
**Effort:** 1 hour

**Why:** Employee form must allow team selection via dropdown (currently team is text-only or imported bulk).

**Implementation checklist:**
- [ ] `store()` method: add validation for `team_id` (optional, exists in `teams.id` if provided)
  ```php
  'team_id' => 'nullable|integer|exists:teams,id',
  ```
- [ ] `update()` method: same validation
- [ ] If legacy `team` text field exists, add migration/logic to auto-create team if text provided (Phase 1 or Phase 1.5)
  - For now, accept both `team_id` (new) and `team` text (legacy) without conflict

**Employee Response Schema (API):**
- Add `team_id` + `team_name` to response:
  ```php
  'team_id' => $employee->team_id,
  'team_name' => $employee->team?->name ?? '—',
  ```

---

### A.7 Integration: Employee Form UI Binding

**File:** `frontend/resources/js/employees-data.js`  
**Type:** MODIFY  
**Status:** ⚠️ Partial (team field exists in form, dropdown missing)  
**Effort:** 2 hours

**Why:** Employee create/edit modal must show team dropdown.

**Implementation checklist:**
- [ ] In employee modal form, add field after department:
  ```html
  <label>Team
    <select name="team_id" data-hcm-field="employee-team">
        <option value="">Select Team</option>
    </select>
  </label>
  ```
- [ ] On modal open:
  - [ ] Fetch teams list: `GET /v1/hcm/teams?status=active` (or cached)
  - [ ] Populate dropdown options
  - [ ] If editing employee, pre-select their current team
- [ ] On department change:
  - [ ] Filter teams by selected department (optional enhancement; Phase 1 can show all active teams)
- [ ] On form submit:
  - [ ] Include `team_id` in payload (or `team` text field for legacy)
  - [ ] POST/PUT to `/v1/hcm/employees` with team_id field

**Pseudocode:**
```js
function onEmployeeModalOpen(employeeId = null) {
    // Load teams for dropdown
    fetchTeams().then(resp => {
        const teamSelect = form.querySelector('[data-hcm-field="employee-team"]');
        teamSelect.innerHTML = '<option value="">Select Team</option>';
        resp.data.forEach(team => {
            const opt = document.createElement('option');
            opt.value = team.id;
            opt.textContent = team.name;
            teamSelect.appendChild(opt);
        });
        // If editing, pre-select
        if (employeeId) {
            fetch(`/v1/hcm/employees/${employeeId}`).then(e => {
                teamSelect.value = e.team_id || '';
            });
        }
    });
}
```

---

### A.8 Database Migration (Optional — Schema Already Exists)

**File:** `backend/database/migrations/` (already exists, but may need UUID/FK hardening)  
**Type:** REVIEW/OPTIONAL  
**Status:** ✅ Mostly ready, review needed for UUID consistency  
**Effort:** 0.5–1 hour

**Why:** Validate teams table has correct structure; add UUID FKs if needed (align with smart planner hardening pattern).

**Implementation checklist:**
- [ ] Verify migration exists for teams table (should be in `2026_04_11_150000_harden_hcm_indonesia_consistency.php`)
- [ ] Verify columns: `id` (PK), `uuid` (unique), `company_id` (FK), `department_id` (nullable FK), `name`, `team_lead_id` (nullable FK), `is_active` (default 1), `created_at`, `updated_at`
- [ ] If UUIDs NOT yet migrated for company/department FKs:
  - [ ] Add migration `2026_04_27_000000_add_uuid_fks_to_teams.php`:
    ```php
    // Add company_uuid, department_uuid columns (optional, depends on Phase 1.5 UUID migration)
    // For now, keep numeric FKs until master migration ready
    ```
  - For Phase 1, **keep numeric FKs** (`company_id`, `department_id`, `team_lead_id`); UUID FKs deferred to Phase 1.5 UUID hardening
- [ ] Run locally: `php artisan migrate --force --env=testing` to verify no errors

---

### A.9 Test Coverage

**Backend API Test**  
**File:** `backend/tests/Feature/HcmTeamApiTest.php`  
**Type:** NEW  
**Status:** ❌ Not started  
**Effort:** 2 hours

**Implementation checklist:**
- [ ] Class `HcmTeamApiTest extends TestCase`
- [ ] Setup: create test company, department, user (HCM admin)
- [ ] Test cases (min 6):
  - [ ] `testListTeamsSuccessful()` → GET `/v1/hcm/teams` → `200` with paginated list
  - [ ] `testListTeamsFilterByDepartment()` → GET `/v1/hcm/teams?search=sales` → filtered results
  - [ ] `testCreateTeamSuccessful()` → POST `/v1/hcm/teams` with valid payload → `201`
  - [ ] `testCreateTeamValidationError()` → POST missing required fields → `422`
  - [ ] `testUpdateTeamSuccessful()` → PUT `/v1/hcm/teams/{id}` → `200`
  - [ ] `testDeleteTeamSuccessful()` → DELETE `/v1/hcm/teams/{id}` → `204`
  - [ ] `testDeleteTeamWithMembersBlocked()` → DELETE team with active members → `409` error
  - [ ] `testTeamNotFoundError()` → GET/PUT/DELETE invalid id → `404`
  - [ ] `testUnauthorizedAccessBlocked()` → non-admin user tries CRUD → `403`

**Frontend JS Test**  
**File:** `frontend/__tests__/team-master.test.js`  
**Type:** NEW  
**Status:** ❌ Not started  
**Effort:** 1.5 hours

**Implementation checklist:**
- [ ] Use Vitest + MSW (mock service worker) for API mocking
- [ ] Test cases (min 4):
  - [ ] `testFetchTeamsSuccessful()` → mock GET `/v1/hcm/teams` → renders grid
  - [ ] `testCreateTeamFlow()` → fill form, submit, API called with payload
  - [ ] `testEditTeamFlow()` → click edit, populate form, submit
  - [ ] `testDeleteTeamFlow()` → click delete, confirm, API called, grid refreshed
  - [ ] `testErrorHandling()` → API error shown as toast

---

### A.10 Local Test Gate

**Script:** `bash scripts/local-test-gate.sh` (existing)  
**Type:** VALIDATE  
**Status:** ✅ Already runs tests  
**Effort:** 0 hours (automatic)

**What happens:**
1. `composer install --no-dev`
2. `npm ci && npm run build`
3. `php artisan migrate --force --env=testing`
4. `php artisan test` — will run `HcmTeamApiTest.php`
5. `npx vitest run` — will run `team-master.test.js`

**Criteria for Phase 1 completion:**
- [ ] All 8+ backend team API tests pass
- [ ] All 4+ frontend team JS tests pass
- [ ] No regression on existing tests (employees, departments, designations, attendance, leave, payroll)

---

## Part B: SHOULD CHANGE (Phase 1 Nice-to-Have, but Recommended)

Changes that **enhance completeness** but don't block MVP. Can be deferred to Phase 1.1 if schedule tight, but recommended before demo.

### B.1 API Documentation

**File:** `docs/api/openapi.yaml`  
**Type:** MODIFY  
**Status:** ⚠️ Partial (team schema may exist)  
**Effort:** 1 hour

**Implementation checklist:**
- [ ] Add `/components/schemas/Team`:
  ```yaml
  Team:
    type: object
    properties:
      id: { type: integer }
      uuid: { type: string, format: uuid }
      company_id: { type: integer }
      name: { type: string, maxLength: 100 }
      department_id: { type: integer, nullable: true }
      departmentName: { type: string, nullable: true }
      team_lead_id: { type: integer, nullable: true }
      teamLeadName: { type: string, nullable: true }
      member_count: { type: integer }
      is_active: { type: boolean }
      created_at: { type: string, format: date-time }
      updated_at: { type: string, format: date-time }
  ```
- [ ] Add `/v1/hcm/teams` path section (copy from `/components/paths/Teams` or inline):
  - GET, POST, PUT, DELETE operations with parameters, request/response schemas
  - Examples of payloads
- [ ] Update `/v1/hcm/employees` POST/PUT schema to include `team_id` field

**Swagger UI verification:**
- [ ] Run `npm run docs` (if available) or manual Swagger render
- [ ] Test endpoints in Swagger UI with Bearer token

---

### B.2 Domain-Specific API Doc

**File:** `docs/api/hcm-masterdata-api.md` or NEW `docs/api/hcm-team-management-api.md`  
**Type:** MODIFY OR CREATE  
**Status:** ❌ Not started  
**Effort:** 1 hour

**Why:** Provide human-readable API reference for team endpoints.

**Implementation checklist:**
- [ ] If modifying `hcm-masterdata-api.md`:
  - [ ] Add "Teams" section after "Designations" section
  - [ ] Document each endpoint: GET /teams, POST /teams, PUT /teams/{id}, DELETE /teams/{id}
  - [ ] Include request/response examples
  - [ ] Note: team deletion requires no active members
- [ ] Or create new file `hcm-team-management-api.md` (optional, can consolidate into masterdata)

---

### B.3 Feature Documentation Updates

**File:** `docs/features/INTEGRATION-MAP.md`  
**Type:** MODIFY  
**Status:** ⚠️ Partial (may already reference teams)  
**Effort:** 0.5 hours

**Implementation checklist:**
- [ ] Add "Team Management" row to cross-feature dependency table
  ```
  | Team Management | Teams CRUD + employee team assignment | Attendance (team scope filtering Phase 2), Payroll (team grouping Phase 2), Reporting (team analytics Phase 2) | Master data stable; no breaking changes planned |
  ```
- [ ] Verify links to `/docs/features/team-management/README.md` are correct

---

### B.4 Employee Organization Feature Doc

**File:** `docs/features/employees-organization/README.md`  
**Type:** MODIFY  
**Status:** ⚠️ Partial (team field may be mentioned)  
**Effort:** 1 hour

**Implementation checklist:**
- [ ] In "Data Model" section:
  - [ ] Add team_id field description:
    ```
    **team_id** (integer, optional): Foreign key to teams master. 
    Links employee to a team within their department for organizational structuring.
    ```
- [ ] In "API Contract" section (`GET /v1/hcm/employees` response):
  - [ ] Add `team_id`, `team_name` fields to example response
- [ ] In "Integration" section:
  - [ ] Add Team Management as integration point:
    ```
    **Team Management**: Employees belong to teams; employee CRUD exposes team_id 
    for assignment. Team CRUD independent of employee module but used for bulk team 
    operations in future phases.
    ```
- [ ] Verify `/v1/hcm/employees` endpoint POST/PUT payload includes `team_id` optional field

---

### B.5 Permissions Matrix Update

**File:** `docs/planning/active-hcm-templates-and-permissions.md`  
**Type:** MODIFY  
**Status:** ⚠️ Partial (may list team as row)  
**Effort:** 0.5 hours

**Implementation checklist:**
- [ ] In "3. Matriks halaman aktif HCM" table, add row after `/designations`:
  ```
  | `/teams` | Master team (organizational) | `team-master-data.js` | `GET/POST/PUT/DELETE /teams` | **HCM Admin** | Semua verb **hcmAdmin** | UI + API aktif Phase 1 |
  ```
- [ ] In "Target API" column, note scope: "tenant-scoped via activeCompanyId"

---

### B.6 Feature Status Tracker

**File:** `docs/features/team-management/tracker.md`  
**Type:** MODIFY (update progress)  
**Status:** ⚠️ Partial (file exists, needs status update post-development)  
**Effort:** 0.5 hours (post-implementation)

**Implementation checklist (AFTER development complete):**
- [ ] Update status table rows to "✅ Complete" once all tasks done
- [ ] Add evidence links: "HcmTeamApiTest.php (6 passed)", "team-master.test.js (4 passed)"
- [ ] Sign-off checklist: QA verified, team lead reviewed, docs synced

---

## Part C: DEFER (Phase 2+)

Changes deferred beyond Phase 1 MVP. These have ZERO priority for current development.

| Component | Reason | Estimated Effort |
|-----------|--------|------------------|
| **Team Lead Delegation** (`team_lead_id` management UI) | Requires RBAC policy for team-scoped admin actions; defer to Phase 2 | 3–4 days |
| **Attendance Shift Scope** (smart planner "Generate by Team") | Depends on team lead delegation + UI filter changes; defer to Phase 2 | 2–3 days |
| **Payroll Team Grouping** (team-based salary component aggregation) | Requires payroll policy changes; defer to Phase 2 | 3–4 days |
| **Team Analytics Dashboard** (team composition, turnover by team, etc.) | New dashboard component; defer to Phase 2 | 3–4 days |
| **Bulk Team Reassignment** (move multiple employees to new team via checkbox) | Low priority; can be manual reassign in Phase 1 | 1–2 days |
| **Team Capacity Planning** (add `capacity`, `utilization` columns) | Data model enhancement; defer to Phase 2 | 2–3 days |
| **UUID Migration for Team FKs** (upgrade numeric FKs to UUID) | Aligns with broader UUID hardening Phase 1.5; defer | 1–2 days |

---

## Part D: Validation Checklist (Sign-Off Before Merge)

Use this checklist to verify completeness before marking Phase 1 DONE.

### D.1 Code Quality
- [ ] `HcmTeamController.php`: 50+ lines, well-structured, guard decorator applied
- [ ] `team-master-data.js`: 100+ lines, error handling, pagination support
- [ ] `teams.blade.php`: semantic HTML, accessibility attributes (labels, ARIA)
- [ ] All code follows PSR-12 (PHP) and ESLint rules (JS)

### D.2 Test Coverage
- [ ] Backend `HcmTeamApiTest.php`: min 8 test methods, all green ✅
- [ ] Frontend `team-master.test.js`: min 4 test methods, all green ✅
- [ ] Local gate `bash scripts/local-test-gate.sh`: ALL tests pass (php + vitest)
- [ ] No regression: existing department/designation/employee tests still pass

### D.3 API Contract
- [ ] OpenAPI spec updated with Team schema + /v1/hcm/teams paths
- [ ] Example requests/responses in docs match actual API behavior
- [ ] DELETE team error message clear: "Cannot delete team with active members"

### D.4 Documentation
- [ ] Feature README comprehensive: business flow, API, integrations, gaps clearly marked
- [ ] Tracker updated with final status + evidence links
- [ ] USE-CASES file complete with 8 actor scenarios
- [ ] INTEGRATION-MAP includes Team row with cross-feature links
- [ ] Permission matrix includes `/teams` web route row

### D.5 UI/UX
- [ ] `/teams` page loads without JS errors (check browser console)
- [ ] Create modal appears on button click; form submits successfully
- [ ] Edit modal pre-fills existing team data; changes save
- [ ] Delete confirmation appears; deletion removes team from grid
- [ ] Search + filter respond quickly (no lag)
- [ ] Pagination works (navigate pages, verify data changes)
- [ ] Team dropdown in employee form populated; selection persists

**Existing UI/UX Regression Checks (CRITICAL):**
- [ ] Sidebar "Teams" menu item visible for HCM admin; other menu items unchanged
- [ ] Employee Create form: team dropdown appears after department; form still submits
- [ ] Employee Edit form: team dropdown pre-filled if employee has team; edit saves
- [ ] Employee list/grid: displays same columns as before (no team column visible Phase 1)
- [ ] Bulk import template: ref_teams sheet exists; employee data sheet unchanged
- [ ] Department CRUD (`/departments`): page loads, CRUD operations work normally
- [ ] Designation CRUD (`/designations`): page loads, CRUD operations work normally
- [ ] No new console JS errors (check browser DevTools)
- [ ] Non-admin user: cannot access `/teams` page (403 or redirect)

### D.6 Employee Integration
- [ ] Employee create form includes team dropdown
- [ ] Employee edit form shows current team pre-selected
- [ ] Bulk import template includes team assignment capability
- [ ] Employee list/grid shows team column (optional, Phase 2+)

### D.7 Security
- [ ] Non-admin user cannot access `/teams` page (redirected or 403)
- [ ] Non-admin user cannot call `/v1/hcm/teams` API (403)
- [ ] Team deletion checks authorization (non-admin blocked)
- [ ] Team data scoped to active company (no cross-tenant leakage)

### D.8 Deployment Readiness
- [ ] All new files committed to git
- [ ] No `.bak` files left behind
- [ ] Migration tested locally (can roll forward/back)
- [ ] Feature docs synced with code (no orphan docs)
- [ ] `RELEASE-METADATA.txt` updated with timestamp

---

## File Change Summary (Compressed)

### NEW Files (8)
1. `backend/app/Http/Controllers/Api/HcmTeamController.php`
2. `backend/resources/views/teams.blade.php`
3. `frontend/resources/js/team-master-data.js`
4. `backend/tests/Feature/HcmTeamApiTest.php`
5. `frontend/__tests__/team-master.test.js`
6. `docs/features/team-management/README.md` ✅ (already created)
7. `docs/features/team-management/USE-CASES.md` ✅ (already created)
8. `docs/features/team-management/tracker.md` ✅ (already created)

### MODIFIED Files (15)
1. `backend/routes/api.php` — add /v1/hcm/teams routes
2. `backend/routes/web.php` — add /teams route
3. `backend/resources/views/layouts/sidebar.blade.php` — add Teams menu item
4. `backend/app/Http/Controllers/Api/HcmEmployeeController.php` — add team_id field validation
5. `frontend/resources/js/employees-data.js` — add team dropdown to employee modal
6. `docs/api/openapi.yaml` — add Team schema + endpoints
7. `docs/api/hcm-employees-api.md` — document team_id field in employee API
8. `docs/api/hcm-masterdata-api.md` OR `docs/api/hcm-team-management-api.md` — add team endpoint docs
9. `docs/features/employees-organization/README.md` — add team field to data model section
10. `docs/features/INTEGRATION-MAP.md` — add Team row to cross-feature table
11. `docs/planning/active-hcm-templates-and-permissions.md` — add /teams row to permission matrix
12. `docs/features/team-management/tracker.md` — update status post-implementation
13. `backend/phpunit.xml` — NO CHANGE (should already cover Feature tests)
14. `backend/vitest.config.js` — NO CHANGE (should already cover __tests__ folder)
15. `scripts/local-test-gate.sh` — NO CHANGE (already runs all tests)

### DOCUMENTATION-ONLY Files (7)
1. `docs/features/team-management/README.md` ✅
2. `docs/features/team-management/USE-CASES.md` ✅
3. `docs/features/team-management/tracker.md` ✅
4. `docs/api/openapi.yaml` — API spec
5. `docs/api/hcm-employees-api.md` — API docs
6. `docs/features/INTEGRATION-MAP.md` — cross-feature map
7. `docs/planning/active-hcm-templates-and-permissions.md` — permission matrix

---

## Implementation Order (Recommended Sequence)

**Week 1:**
1. Create `HcmTeamController.php` (4h)
2. Add routes to `api.php` + `web.php` (1h)
3. Write `HcmTeamApiTest.php` + validate locally (2h)
4. Create `teams.blade.php` view (3h)

**Week 2:**
5. Create `team-master-data.js` (4h)
6. Write `team-master.test.js` + validate locally (1.5h)
7. Update employee form dropdown in `employees-data.js` (2h)
8. Update `HcmEmployeeController.php` team_id validation (1h)

**Week 2 (cont'd):**
9. Add Teams menu to sidebar (0.5h)
10. Run local gate: `bash scripts/local-test-gate.sh` (0.5h, automated)
11. Update all documentation (openapi, feature docs, integration map, permissions) (3h)
12. Final sign-off + deployment prep (1h)

**Total: 9–11 days** ✅

---

## Sign-Off

**Phase 1 Team Management Feature — Complete Impact Analysis**

- **Prepared by:** AI Agent (Copilot)
- **Reviewed by:** [Pending — awaiting HCM Module Lead approval]
- **Approval date:** [Pending]

**Next steps after sign-off:**
1. Assign tasks to developers
2. Create sprint/milestone in issue tracker
3. Start Week 1 implementation
4. Daily standup to track progress
5. Run local gate daily to catch regressions early
