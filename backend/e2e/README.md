# Multi-Tenant RBAC HCM - E2E UI Test Suite

**Status:** ✅ **READY TO RUN** - Complete E2E test suite for multi-tenant RBAC validation
**Framework:** Playwright
**Test Coverage:** 11 critical user scenarios

## Overview

This test suite validates the complete multi-tenant Role-Based Access Control (RBAC) implementation for the Arcav HCM SaaS platform. All tests are designed to ensure:

- ✅ **Strict tenant isolation** - Zero cross-tenant data leakage
- ✅ **Permission-based access control** - Dynamic permission enforcement
- ✅ **Security validation** - Prevention of escalation attacks
- ✅ **User experience** - Proper UI state management
- ✅ **Audit compliance** - Complete action tracking

## Test Scenarios

### 1. **LANDING → SIGN UP** (`01-landing-signup.spec.js`)
- ✅ Valid company registration
- ❌ Email already exists
- ❌ Weak password validation
- ❌ Empty field validation

### 2. **FIRST LOGIN (OWNER)** (`02-first-login-owner.spec.js`)
- ✅ Owner login with full access
- ❌ Wrong password
- ❌ Account inactive

### 3. **ROLE MANAGEMENT (CREATE)** (`03-role-management.spec.js`)
- ✅ Create role with permissions
- ❌ Empty role name
- ❌ Duplicate role name
- ❌ Invalid permission selection

### 4. **USER MANAGEMENT (CREATE)** (`04-user-management.spec.js`)
- ✅ Create user and assign role
- ❌ Duplicate email
- ❌ Invalid role assignment

### 5. **PERMISSION ENFORCEMENT** (`05-permission-enforcement.spec.js`)
- ✅ User can access allowed features
- ❌ User blocked from restricted features
- ✅ Dynamic permission changes

### 6. **EDIT ROLE (DYNAMIC)** (`06-edit-role-dynamic.spec.js`)
- ✅ Permission removal affects users immediately
- ❌ Warning for removing all permissions
- ✅ Audit trail for changes

### 7. **CROSS TENANT ISOLATION** (`07-cross-tenant-isolation.spec.js`)
- ❌ Company A cannot access Company B data
- ❌ Cannot see other tenant roles
- ❌ URL manipulation bypass prevention

### 8. **ROLE ESCALATION ATTEMPT** (`08-role-escalation-attempt.spec.js`)
- ❌ Regular user cannot create admin roles
- ❌ Cannot assign self admin roles
- ❌ Cannot modify existing roles
- ✅ Super admin bypass verification

### 9. **SUBSCRIPTION LIMITATION** (`09-subscription-limitation.spec.js`)
- ❌ User creation blocked when expired
- ❌ Payroll blocked when expired
- ✅ Renewal prompts shown
- ✅ Read-only access allowed

### 10. **DELETE ROLE IN USE** (`10-delete-role-in-use.spec.js`)
- ❌ Cannot delete role assigned to users
- ✅ Can delete after reassigning users
- ❌ System roles cannot be deleted
- ✅ Cancel deletion works

### 11. **AUDIT VISIBILITY** (`11-audit-visibility.spec.js`)
- ✅ Role assignment actions logged
- ✅ Role creation logged
- ✅ Permission changes logged
- ❌ Regular users cannot access audit
- ✅ Chronological order maintained
- ✅ Detailed change information
- ✅ Tamper-proof audit logs

## Prerequisites

### 1. Environment Setup
```bash
# Install dependencies
cd backend
npm install

# Start Laravel server
php artisan serve

# Start frontend (if separate)
npm run dev
```

### 2. Database Setup
```bash
# Run migrations
php artisan migrate

# Seed base data
php artisan db:seed --class=HcmPermissionsSeeder
php artisan db:seed --class=HcmRolesSeeder

# Initialize tenant RBAC
php artisan hcm:initialize-tenant-rbac 1
```

### 3. Test Data Setup
The tests expect these users to exist:
- **Owner:** `admin@majujaya-test.com` / `StrongPass123!`
- **Employee:** `budi@majujaya-test.com` / `TestPass123!`

## Running Tests

### Run All Tests
```bash
# From backend directory
npx playwright test
```

### Run Specific Test Scenario
```bash
# Run only signup tests
npx playwright test 01-landing-signup.spec.js

# Run only permission enforcement tests
npx playwright test 05-permission-enforcement.spec.js

# Run cross-tenant isolation tests
npx playwright test 07-cross-tenant-isolation.spec.js
```

### Run with Different Browsers
```bash
# Run on Chromium only
npx playwright test --project=chromium

# Run on mobile viewport
npx playwright test --project=mobile-chromium
```

### Debug Mode
```bash
# Run with browser visible
npx playwright test --headed

# Run specific test in debug mode
npx playwright test 01-landing-signup.spec.js --debug
```

### Generate Report
```bash
# Run tests and generate HTML report
npx playwright test

# Open report
npx playwright show-report
```

## Test Configuration

### Environment Variables
```bash
# Set base URL for tests
export PLAYWRIGHT_BASE_URL=http://127.0.0.1:8000

# Run tests
npx playwright test
```

### Parallel Execution
```javascript
// In playwright.config.js
export default defineConfig({
  workers: 4, // Run tests in parallel
  // ... other config
});
```

## Test Results Interpretation

### ✅ PASS Criteria
- All security validations pass
- UI correctly reflects permissions
- No cross-tenant data access
- Proper error messages displayed
- Audit logs capture all actions

### ❌ FAIL Indicators
- Permission bypass possible
- Cross-tenant data leakage
- UI shows unauthorized options
- Missing error messages
- Audit logs incomplete

## Troubleshooting

### Common Issues

**Tests fail with "page not found"**
```bash
# Check if Laravel server is running
php artisan serve

# Verify base URL
echo $PLAYWRIGHT_BASE_URL
```

**Authentication fails**
```bash
# Ensure test users exist in database
php artisan tinker
>>> User::where('email', 'admin@majujaya-test.com')->first()
```

**Permission checks fail**
```bash
# Verify RBAC is properly seeded
php artisan hcm:initialize-tenant-rbac 1
```

### Debug Commands
```bash
# List all tests
npx playwright test --list

# Run with detailed output
npx playwright test --reporter=line

# Capture screenshots on failure
npx playwright test --screenshot=only-on-failure
```

## CI/CD Integration

### GitHub Actions Example
```yaml
- name: Run E2E Tests
  run: |
    cd backend
    npm install
    npx playwright install
    npx playwright test
```

### Docker Setup
```dockerfile
FROM mcr.microsoft.com/playwright:v1.40.0

COPY . /app
WORKDIR /app/backend

RUN npm install
RUN npx playwright install

CMD ["npx", "playwright", "test"]
```

## Maintenance

### Adding New Tests
1. Create new `.spec.js` file in `backend/e2e/tests/`
2. Follow naming convention: `NN-description.spec.js`
3. Use descriptive test names
4. Include both positive and negative test cases

### Updating Test Data
- Modify test constants at top of each file
- Ensure database is reset between test runs
- Use unique identifiers to avoid conflicts

## Security Validation Checklist

After running all tests, verify:

- [ ] No cross-tenant data access possible
- [ ] All permission checks enforced
- [ ] Role escalation prevented
- [ ] Audit trail complete
- [ ] UI state matches backend permissions
- [ ] Error messages don't leak information
- [ ] Subscription limits enforced
- [ ] System roles protected

---

## 🎯 Final Result

**If all 11 test scenarios PASS:**
✅ **System is SECURE** - Multi-tenant isolation working  
✅ **UX is PREDICTABLE** - UI correctly reflects permissions  
✅ **Error handling is CLEAR** - Meaningful messages displayed  
✅ **Audit trail is COMPLETE** - All actions tracked  
✅ **Ready for PRODUCTION** - Enterprise-grade RBAC validated  

**Total Tests:** 11 scenarios × ~3-5 test cases each = **35+ individual validations**