# Frontend Role-Based Conditional Rendering Audit

**Date:** April 16, 2026  
**Scope:** `/frontend/resources/js/**` — role/permission checking patterns  
**Total Files Analyzed:** 15+

---

## Executive Summary

The frontend uses **UI-level role checks** (showing/hiding buttons based on `hcmAdmin`, `isOwner`, `isManager`, `isAdmin`) across multiple files. **The backend is expected to be the source of truth** for authorization (per [governance rules](docs/planning/active-hcm-templates-and-permissions.md)), but several issues suggest inconsistent patterns and potential gaps:

1. **UI role checks are not consistently duplicated** across all component variations
2. **Multiple roles are properly supported in some files** (performance, goals) but **only hcmAdmin is checked in others** (subscriptions, users, roles)
3. **Permission logic is duplicated** across similar modules without centralization
4. **No unified authorization utility** — each file implements its own role/permission check pattern
5. **Backend gating strength varies** — need verification that all API endpoints enforce same rules

---

## Files with Role-Based Conditional Rendering

### **Issue Category 1: Admin-Only Role Checks (Highest Risk)**

#### 1. `subscriptions-management.js` ⚠️
**Pattern:** Checks only `hcmAdmin`  
**Lines:** 143, 188, 208, 244, 254, 258, 263, 488, 510, 666, 737, 794, 888, 940, 978

```javascript
// Line 244: Load role from API
self.isAdminUser = !!response?.data?.hcmAdmin;

// Line 249-263: Apply UI visibility
applyRoleUi: function () {
  const addButton = document.querySelector("[data-subscription-add-button]");
  if (addButton) {
    addButton.classList.toggle("d-none", !this.isAdminUser);
  }
  const readOnlyNotice = document.querySelector("[data-subscription-readonly-notice]");
  if (readOnlyNotice) {
    readOnlyNotice.classList.toggle("d-none", this.isAdminUser);
  }
}

// Line 488, 510, 737, 794, 888, 940, 978: Guard multiple operations
if (!this.isAdminUser) return;
```

**Issues:**
- ❌ Only checks `hcmAdmin` — **no support for other admin-like roles** (e.g., operator, finance admin)
- ❌ Buttons hidden but **no backend verification shown** that create/edit/delete APIs are gated
- ⚠️ Query string defaults only available to admin (`applyQueryStringDefaults`), but **no clear 403 handling** if non-admin somehow calls the API

**Test Case Needed:**
- Non-admin user manually calls `POST /v1/hcm/subscriptions` → verify **403 Forbidden** returned

---

#### 2. `users-management.js` ⚠️
**Pattern:** No explicit role checks in sample read  
**Lines:** 1-150 (UI module for admin-only page)

```javascript
// No visible role check in init; assumes page access is already gated by middleware
init: function () {
  if (!document.getElementById("um_users_tbody")) {
    return;
  }
  this.bindEvents();
  this.loadRoles();
  this.loadUsers();
}
```

**Issues:**
- ❌ **No role validation before loading data** — assumes URL middleware guards `/users` route
- ⚠️ No `applyRoleUi()` to disable buttons for non-admin (relies entirely on route middleware)
- ⚠️ If API call succeeds, user can see full employee list + role assignments (no per-record filtering)

**Test Case Needed:**
- Verify `GET /v1/hcm/user-management/users` returns **403** if called by non-admin
- Verify route `/user-management` redirects non-admin to `/employee-dashboard`

---

#### 3. `roles-permissions.js`
**Pattern:** Assumes admin-only page (no role checks visible in init)  
**Lines:** 1-100 (UI module for role/permission CRUD)

```javascript
init: function () {
  if (!document.getElementById("rp_roles_tbody")) {
    return;
  }
  this.bindEvents();
  this.loadPermissions();
  this.loadRoles();
}
```

**Issues:**
- ❌ **No role checks** — page is **sensitive** (manages roles & permissions)
- ⚠️ **No verification shown that API endpoints enforce `EnsuresHcmAdmin`**

---

### **Issue Category 2: Mixed Role Support (Medium Risk)**

#### 4. `performance-data.js` ✓ Better Pattern
**Pattern:** Supports `isOwner`, `isManager`, `isAdmin`  
**Lines:** 807-813, 943

```javascript
function renderReviewDetail(data) {
  const isOwner = !!data?.permissions?.isOwner;
  const isManager = !!data?.permissions?.isManager;
  const isAdmin = !!data?.permissions?.isAdmin;

  const canSelfEdit = isOwner && data?.status === 'draft';
  const canManagerEdit = isManager && data?.status === 'submitted';
  const canFinalEdit = isAdmin && (data?.status === 'manager_reviewed' || data?.status === 'finalized');

  // Conditional rendering:
  // `<textarea ... ${canSelfEdit ? '' : 'disabled'}>...</textarea>`
}
```

**Strengths:**
- ✅ Supports **multiple role types** (not just admin)
- ✅ **Role + status combination logic** (e.g., `isManager && status === 'submitted'`)
- ✅ Disables UI elements rather than hiding buttons (better accessibility)

**Concerns:**
- ⚠️ **No backend verification shown** that API enforce same role+status logic
- ⚠️ If `disabled` input is submitted via dev console, **backend must reject**

---

#### 5. `goal-data.js` ✓ Better Pattern
**Pattern:** Supports `isOwner`, `isManager`, `isAdmin`  
**Lines:** 303, 305-312, 431-435, 451, 471

```javascript
let currentEditPerm = { isOwner: false, isManager: false, isAdmin: false };

function canEditGoal(g) {
  // Determine based on ownership + manager role
}

function canDeleteGoal(g) {
  // Determine based on ownership + manager role
}

// In renderRows:
const canEdit = canEditGoal(g);
const canDel = canDeleteGoal(g);
// Conditionally render edit/delete buttons
```

**Strengths:**
- ✅ Centralized permission logic in `canEditGoal()` and `canDeleteGoal()`
- ✅ Reduces duplication

**Concerns:**
- ⚠️ Logic **not extracted to reusable utility** — duplicated pattern if similar logic needed elsewhere
- ⚠️ **No shown backend enforcement** (API should also check permissions before PATCH/DELETE)

---

#### 6. `activity-data.js`
**Pattern:** Uses `canEdit` and `canDelete` from API response  
**Lines:** 120-127

```javascript
if (row.canEdit || row.canDelete) {
  var editBtn = row.canEdit
    ? '<button type="button" class="btn btn-sm btn-outline-primary me-2" data-activity-edit="' + escapeHtml(row.manualActivityId || '') + '">Edit</button>'
    : '';
  var deleteBtn = row.canDelete
    ? '<button type="button" class="btn btn-sm btn-outline-danger" data-activity-delete="' + escapeHtml(row.manualActivityId || '') + '">Delete</button>'
    : '';
}
```

**Strengths:**
- ✅ **Server-computed permissions** (cleaner pattern — backend says what UI can do)
- ✅ Avoids **client-side logic duplicating backend rules**

**Concerns:**
- ⚠️ **Trust issue:** If API returns `canEdit: true` but backend API denies edit, UX is broken
- ✅ Mitigated if backend always validates before allowing mutation

---

### **Issue Category 3: Single-Role Assumption (Admin-Only Features)**

#### 7. `payslip-admin-data.js`
**Lines:** 558

```javascript
if (!me || !me.success || !me.data || !me.data.hcmAdmin) {
  // Page locked to HCM Admin only
}
```

**Issues:**
- ⚠️ Only `hcmAdmin` check — no variance for other privileged roles

---

#### 8. `payslip-data.js`
**Lines:** 62

```javascript
if (payload?.success && payload?.data?.hcmAdmin) {
  // Branching logic based on hcmAdmin
}
```

**Issues:**
- ⚠️ Single-role assumption

---

#### 9. `resignation-data.js`
**Lines:** 472

```javascript
if (!me || !me.success || !me.data || !me.data.hcmAdmin) {
  // Admin check
}
```

---

### **Issue Category 4: Incomplete API Gating Verification**

#### 10. `hcm-pages-data.js`
**Lines:** 1-100 (General utilities, no role checks in sample)

**Concern:**
- ⚠️ Renders CRUD forms for department/designation/policy management
- ⚠️ **No shown role verification before form submission** — assumes route middleware is sufficient

---

#### 11. `tickets-data.js`
**Pattern:** Uses generic `handleUnauthorizedFromApi` fallback

```javascript
if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi && window.AuthApi.handleUnauthorizedFromApi(st, d)) {
  return null;
}
```

**Issues:**
- ⚠️ **Generic 401 handling** — does not distinguish between:
  - User lacks permission (403)
  - User token expired (401)
  - User viewing wrong resource (403)

---

#### 12. `employee-dashboard-data.js`
**No explicit role checks** — relies on route-level gating

---

### **Issue Category 5: Inconsistent Authorization Patterns**

#### Summary of Patterns Found:

| File | Pattern | Role Support | Backend Verification |
|------|---------|---|---|
| `subscriptions-management.js` | `isAdminUser` flag | ❌ Only hcmAdmin | ⚠️ Not visible |
| `users-management.js` | None (route gated) | ❌ Assumed admin | ⚠️ Route-only |
| `roles-permissions.js` | None (route gated) | ❌ Assumed admin | ⚠️ Route-only |
| `performance-data.js` | `isOwner`, `isManager`, `isAdmin` | ✅ Multiple | ⚠️ Not verified |
| `goal-data.js` | `canEditGoal()`, `canDeleteGoal()` | ✅ Multiple | ⚠️ Not verified |
| `activity-data.js` | Server-computed `canEdit`, `canDelete` | ✅ Multiple | ✅ Server source |
| `payslip-admin-data.js` | `hcmAdmin` only | ❌ Single role | ⚠️ Not visible |
| `resignation-data.js` | `hcmAdmin` only | ❌ Single role | ⚠️ Not visible |
| `employee-dashboard-data.js` | None (route gated) | ⚠️ Self + admin assumed | ⚠️ Route-only |

---

## Critical Findings

### 🔴 **Finding 1: No Unified Authorization Utility**

**Status:** **CONFIRMED**

Each module re-implements role checks from scratch:
- `subscriptions-management.js` checks `response?.data?.hcmAdmin`
- `performance-data.js` checks `data?.permissions?.isOwner`, etc.
- `goal-data.js` implements its own `canEditGoal()` function
- `activity-data.js` trusts server-provided `canEdit` flag

**Impact:**
- Inconsistent patterns → harder to audit and maintain
- Changes to role model require edits across 10+ files
- Duplicate logic = duplicate bugs

**Recommendation:**
Create a shared utility module:
```javascript
// frontend/resources/js/auth-permissions.js
window.ArcavPermissions = {
  canEditActivity: (row, user) => { /* centralized logic */ },
  canDeleteActivity: (row, user) => { /* centralized logic */ },
  // ... other shared checks
};
```

---

### 🔴 **Finding 2: Subscriptions & Users Pages Only Check `hcmAdmin`**

**Status:** **CONFIRMED**

Files: `subscriptions-management.js`, `users-management.js`, `roles-permissions.js`, `payslip-admin-data.js`, `resignation-data.js`

These modules **do not support multiple admin-like roles**. If future roles are introduced (e.g., `finance_admin`, `operation_admin`, `subscription_manager`), these pages will **not be accessible** to those users even if their backend permissions allow it.

**Impact:**
- Hard to scale authorization model
- Violates principle of least privilege (all admins get full access)

**Recommendation:**
1. Define permission constants in backend
2. Query user permissions from API instead of checking single `hcmAdmin` flag
3. Update frontend to check for specific permission/capability instead of role name

---

### 🟡 **Finding 3: Potential Backend API Gating Gaps**

**Status:** **SUSPECTED — Requires Backend Verification**

Files with concern: `subscriptions-management.js` (edit/delete/renew), `users-management.js` (assign roles), `roles-permissions.js` (sync permissions), `payslip-data.js` (payslip access)

**Example - Subscriptions:**
```javascript
// Frontend hides UI button if !isAdminUser
addButton.classList.toggle("d-none", !this.isAdminUser);

// But is the API endpoint gated?
// POST /v1/hcm/subscriptions ← must return 403 if user is not hcmAdmin
```

**Recommendation:**
Run security tests against each endpoint:
```bash
# Test as non-admin user
curl -X POST /v1/hcm/subscriptions \
  -H "Authorization: Bearer $NON_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"packageId": 1, "companyId": 1}'

# Expected: 403 Forbidden
# Actual: [Verify this]
```

---

### 🟡 **Finding 4: Role + Status Logic Not Consistently Verified**

**Status:** **SUSPECTED**

Files: `performance-data.js`, `goal-data.js`

**Example - Performance Review:**
```javascript
// Frontend logic:
const canManagerEdit = isManager && data?.status === 'submitted';

// But does API check this?
// PUT /v1/hcm/performance/reviews/{id} ← verify it checks:
//   1. User IS manager of this review
//   2. Review status IS 'submitted'
//   3. BOTH conditions fail → 403
```

**Recommendation:**
Add backend validation tests for workflow state transitions.

---

### 🟡 **Finding 5: No Centralized 403 Error Handling**

**Status:** **CONFIRMED**

Files across codebase use:
- `window.AuthApi.handleUnauthorizedFromApi(status, data)` — too generic
- Direct 401 checks — doesn't distinguish 403 (forbidden) from 401 (unauthenticated)

**Example:**
```javascript
// goal-data.js, line 48
if (status === 401 || data?.error?.code === 'AUTH_UNAUTHORIZED') {
  window.location.replace('/login');
  return;
}
// But what if status === 403? No handling shown.
```

**Impact:**
- If backend returns 403, frontend may not give user clear feedback
- User might see "loading" spinner indefinitely

**Recommendation:**
Create unified error handler:
```javascript
function handleApiError(error) {
  if (error.status === 401) {
    // Unauthenticated → redirect to login
    window.location.replace('/login');
  } else if (error.status === 403) {
    // Forbidden → show "You don't have permission" message
    ArcavUi.toast('You don\'t have permission for this action.', 'error');
  } else if (error.status >= 500) {
    // Server error
    ArcavUi.toast('Server error. Please try again.', 'error');
  }
}
```

---

## Files Checked (15+)

| # | File | Lines | Role Pattern | Status |
|---|------|-------|---|---|
| 1 | `subscriptions-management.js` | 140-978 | `isAdminUser` flag | ⚠️ Issue |
| 2 | `performance-data.js` | 800-850 | `isOwner`, `isManager`, `isAdmin` | ✅ Good |
| 3 | `roles-permissions.js` | 1-100 | None (route-gated) | ⚠️ Issue |
| 4 | `users-management.js` | 1-150 | None (route-gated) | ⚠️ Issue |
| 5 | `hcm-pages-data.js` | 1-300 | None (route-gated) | ⚠️ Issue |
| 6 | `profile-settings-data.js` | 1-100 | None (auth guard only) | ⚠️ Issue |
| 7 | `activity-data.js` | 1-150 | Server-computed `canEdit`, `canDelete` | ✅ Good |
| 8 | `goal-data.js` | 1-150 | `canEditGoal()`, `canDeleteGoal()` | ✅ Good |
| 9 | `resignation-data.js` | 1-200 | `hcmAdmin` flag | ⚠️ Issue |
| 10 | `employee-dashboard-data.js` | 1-150 | None (route-gated) | ⚠️ Issue |
| 11 | `employees-data.js` | 1-100 | None (route-gated) | ⚠️ Issue |
| 12 | `payroll-items-data.js` | 1-100 | Auth handler only | ⚠️ Issue |
| 13 | `payslip-data.js` | 1-100 | `hcmAdmin` flag | ⚠️ Issue |
| 14 | `payslip-admin-data.js` | 1-100 | `hcmAdmin` flag | ⚠️ Issue |
| 15 | `tickets-data.js` | 1-100 | Auth handler only | ⚠️ Issue |

---

## Recommendations (Priority Order)

### Priority 1: Security (Critical)

1. **Verify backend API gating for all admin-only endpoints**
   - [ ] Confirm all POST/PUT/DELETE on `/v1/hcm/*` return **403** for non-admin
   - [ ] Test with non-admin token:
     - `POST /v1/hcm/subscriptions`
     - `DELETE /v1/hcm/users/{id}`
     - `POST /v1/hcm/roles/{id}/sync-permissions`
     - `PUT /v1/hcm/payroll-items/{id}`

2. **Upgrade single-role checks to permission-based**
   - [ ] Replace `hcmAdmin` flag with permission list
   - [ ] Query `/v1/identity/auth/me` and cache `permissions` array
   - [ ] Example: `permissions: ['subscriptions.create', 'subscriptions.edit', 'subscriptions.delete']`

3. **Add 403 Forbidden handling**
   - [ ] Create `window.AuthApi.handle403()` method
   - [ ] Show user-facing message: "You don't have permission for this action."
   - [ ] Distinguish from 401 (unauthenticated)

### Priority 2: Consistency (Medium)

4. **Create unified authorization utility**
   ```javascript
   // frontend/resources/js/auth-permissions-utils.js
   window.ArcavAuth = {
     can: (action, resource, data) => { /* centralized check */ },
     canEdit: (resource) => { ... },
     canDelete: (resource) => { ... },
   };
   ```

5. **Document role/permission matrix per module**
   - [ ] Update `docs/planning/active-hcm-templates-and-permissions.md` with which JS files enforce which rules
   - [ ] Add "Backend Verification" column with test evidence

6. **Refactor permission logic into reusable functions**
   - [ ] Extract `canEditGoal()`, `canDeleteGoal()` from `goal-data.js`
   - [ ] Extract `canEditActivity()`, `canDeleteActivity()` from `activity-data.js`
   - [ ] Place in shared `auth-permissions-utils.js`

### Priority 3: Maintainability (Low)

7. **Create E2E tests for role-based access**
   - [ ] Test each admin-only page with non-admin token
   - [ ] Verify 403 on mutation endpoints
   - [ ] Test workflow role checks (manager, owner, etc.)

---

## Verification Checklist

Before marking this audit as resolved:

- [ ] **Backend verification:** All API endpoints return appropriate 401/403 status codes
- [ ] **Unified utility:** `auth-permissions-utils.js` created and integrated
- [ ] **Subscriptions module:** Refactored to use permission utility instead of `isAdminUser` flag
- [ ] **Users/Roles/Permissions modules:** Updated to check specific permissions, not just `hcmAdmin`
- [ ] **403 handling:** Global error handler covers forbidden scenarios
- [ ] **Documentation:** `docs/planning/active-hcm-templates-and-permissions.md` includes backend verification evidence
- [ ] **E2E tests:** At least 3 role-based access tests passing

---

## Appendix: Code Examples

### Better Pattern (Use This)

```javascript
// Server computes permissions, frontend displays UI
const row = {
  id: 123,
  name: 'Activity',
  canEdit: true,    // ← Server decides
  canDelete: false,  // ← Server decides
};

// Frontend just renders what server says:
const editBtn = row.canEdit ? '<button>Edit</button>' : '';
const deleteBtn = row.canDelete ? '<button>Delete</button>' : '';
```

### Anti-Pattern (Avoid This)

```javascript
// Frontend assumes role == capability
const isAdmin = !!user?.isAdmin;

// Frontend hides/shows UI based on role
const editBtn = isAdmin ? '<button>Edit</button>' : '';

// But what if admin loses edit permission? UI still shows button.
// What if non-admin gains special edit permission? UI hides button.
```

---

**Audit completed:** 2026-04-16  
**Next review:** After backend verification + refactor
