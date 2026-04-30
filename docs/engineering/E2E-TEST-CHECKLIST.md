# E2E UI Testing Scenario - User Management & Role Assignment
**Date:** April 18, 2026  
**Objective:** Test user login modes (Regular + Company Mode), then create/assign admin roles to verify dashboard control flow.

---

## Test Credentials

```
Email: e2e-test@arcav.test
Password: password123
Company Code (for Company Mode): e2e_test_company
```

---

## Test Scenario Flow

### Phase 1: Regular Mode Login (Employee View)

**Objective:** Verify employee can login and see employee dashboard

#### Test Case 1.1: Login in Regular Mode
1. Go to login page: `http://localhost/login`
2. Observe: **Two radio buttons should be visible**
   - ☐ "Login as Employee" (Regular Mode)
   - ☐ "Login as Company Admin" (Company Mode)
3. Select **"Login as Employee"** radio button
4. Observe: Company code field should **DISAPPEAR** (hidden)
5. Enter credentials:
   - Email: `e2e-test@arcav.test`
   - Password: `password123`
6. Click **Login**
7. ✅ **Expected:** Redirect to `/index` with employee dashboard
8. ✅ **Verify:** User context shows as **employee** (no company admin flag)

#### Test Case 1.2: Verify Employee Dashboard
1. On dashboard, check URL: Should be `/index`
2. Observe navigation menu - should show:
   - ☐ Attendance
   - ☐ Leave Management
   - ☐ Payroll
   - ☐ Employee-only features
3. Verify **Admin section is NOT visible** in sidebar
4. ✅ **Expected:** Employee cannot see "Users", "Roles", "Administration" menu items

---

### Phase 2: Logout & Company Mode Login (Admin View)

**Objective:** Test same user can login as company admin

#### Test Case 2.1: Logout from Employee Mode
1. Click **Logout** button (top right corner)
2. ✅ **Expected:** Redirect to `/login`
3. ✅ **Verify:** Auth token cleared, session ended

#### Test Case 2.2: Login in Company Mode
1. On login page, select **"Login as Company Admin"** radio button
2. Observe: Company code field should **APPEAR** (shown)
3. Enter credentials:
   - Email: `e2e-test@arcav.test`
   - Password: `password123`
   - Company Code: `e2e_test_company`
4. Click **Login**
5. ✅ **Expected:** Redirect to `/index` with company admin dashboard
6. ✅ **Verify:** User context shows as **company admin** (isHcmAdmin: true)

#### Test Case 2.3: Verify Admin Dashboard
1. On dashboard, check URL: Should be `/index`
2. Observe navigation menu - should show **Administration section**:
   - ☐ Users
   - ☐ Roles & Permissions
   - ☐ Company Settings
3. ✅ **Expected:** Admin can see full admin menu

---

### Phase 3: User Management (Admin Side)

**Objective:** Test user management CRUD operations

#### Test Case 3.1: Navigate to Users
1. Sidebar → **Administration** → **Users**
2. URL should be: `/users`
3. ✅ **Observe:** Users list page loads
4. ✅ **Verify:** E2E Test User visible in list

#### Test Case 3.2: User Details
1. Find "E2E Test User" in list
2. Click on user row to view details
3. ✅ **Observe:** User edit modal/form opens
4. ✅ **Verify:** Shows:
   - Name: "E2E Test User"
   - Email: "e2e-test@arcav.test"
   - Status: "active"

---

### Phase 4: Role Management & Assignment

**Objective:** Create admin role and assign it to test user

#### Test Case 4.1: Navigate to Roles
1. Sidebar → **Administration** → **Roles & Permissions**
2. URL should be: `/roles-permissions`
3. ✅ **Observe:** Roles list page loads
4. ✅ **Verify:** "Admin (E2E)" role visible in list

#### Test Case 4.2: View Admin Role
1. Find "Admin (E2E)" role in list
2. ✅ **Observe:** Shows:
   - Code: `ADMIN_E2E`
   - Status: `active`
   - Description: "Test admin role"

#### Test Case 4.3: Assign Permissions to Admin Role
1. On "Admin (E2E)" row, click **Manage Permissions** button
2. ✅ **Observe:** Permissions modal opens
3. Select permissions for dashboard control:
   - ☐ `user.view` (View users)
   - ☐ `user.create` (Create users)
   - ☐ `role.view` (View roles)
   - ☐ `role.update` (Update roles)
4. Click **Save Permissions**
5. ✅ **Expected:** Success message: "Role permissions updated"
6. ✅ **Verify:** Permission list updates

#### Test Case 4.4: Assign Role to Employee User
1. Sidebar → **Administration** → **Users**
2. Find "E2E Test User" in list
3. Click **Manage Roles** button (or similar)
4. ✅ **Observe:** Role assignment modal/panel opens
5. Select role:
   - Role dropdown/select: Choose `ADMIN_E2E`
   - Click **Assign Role**
6. ✅ **Expected:** Success message: "Role assigned successfully"
7. ✅ **Verify:** Role appears in user's role assignments list

#### Test Case 4.5: Verify Role Assignment
1. Refresh page or check role assignments
2. ✅ **Observe:** E2E Test User now shows role: `ADMIN_E2E`

---

### Phase 5: Dual-Role Access Verification

**Objective:** Verify same user can login in BOTH modes with different dashboards

#### Test Case 5.1: Logout from Company Mode
1. Click **Logout**
2. ✅ **Expected:** Redirect to `/login`

#### Test Case 5.2: Re-login as Employee
1. Select "Login as Employee"
2. Enter credentials (same email/password)
3. ✅ **Expected:** Login successful, employee dashboard shown
4. ✅ **Verify:** Admin menu NOT visible
5. Observation: Employee doesn't automatically see "Admin" role - they just see employee dashboard

#### Test Case 5.3: Re-login as Company Admin
1. Logout again
2. Select "Login as Company Admin"
3. Enter company code: `e2e_test_company`
4. ✅ **Expected:** Login successful, admin dashboard shown
5. ✅ **Verify:** Users/Roles/Administration menu visible
6. ✅ **Verify:** Can access user management as admin

---

## Expected Results Summary

| Scenario | Expected Outcome | Status |
|----------|------------------|--------|
| Regular Mode Login | Employee dashboard, no admin menu | ☐ |
| Company Mode Login | Admin dashboard, full menu visible | ☐ |
| Same User Both Modes | Can login both ways with different dashboards | ☐ |
| Create Admin Role | Role created with code ADMIN_E2E | ☐ |
| Assign Permissions | Permissions synced successfully | ☐ |
| Assign Role to User | User role assignment recorded | ☐ |
| Multi-Account Access | Employee view + Admin view both accessible | ☐ |

---

## Notes

- **Employee Profile:** Created automatically when user joins company
- **Dual Account:** Not a separate account, but same email used in 2 login modes
- **Permissions:** Both frontend (UX) and backend (API) enforce access control
- **Audit Trail:** Role assignments are logged in `hcm_user_role_audit` table

---

## API Endpoints Being Tested

### Users
- `GET /v1/hcm/user-management/users` - List users
- `POST /v1/hcm/user-management/users/{id}/roles` - Assign role

### Roles
- `GET /v1/hcm/user-management/roles` - List roles
- `POST /v1/hcm/user-management/roles/{id}/permissions:sync` - Sync permissions
- `GET /v1/hcm/user-management/permissions` - List permissions

### Auth
- `POST /v1/identity/auth/login` - Login endpoint
- `GET /v1/identity/auth/me` - Get current user
- `POST /v1/identity/auth/logout` - Logout

---

## Troubleshooting

**Issue:** Login fails with "Invalid credentials"
- ✓ Check email: `e2e-test@arcav.test` (with dot)
- ✓ Check password: `password123` (case-sensitive)

**Issue:** Company code field not appearing
- ✓ Refresh page
- ✓ Check browser console for errors

**Issue:** After role assignment, user still can't see admin features
- ✓ Logout and login again
- ✓ Check token is refreshed
- ✓ Verify permission codes match backend expectations

---

## Next Steps After E2E Testing

1. ✅ Document any bugs or UI issues
2. ✅ Create GitHub issue if discrepancies found
3. ✅ Update API documentation if endpoints changed
4. ✅ Add UI screenshots to test report
5. ✅ Run automated test suite for permission validation

