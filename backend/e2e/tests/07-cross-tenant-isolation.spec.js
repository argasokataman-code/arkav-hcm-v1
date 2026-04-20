import { test, expect } from '@playwright/test';

test.describe('7. CROSS TENANT ISOLATION', () => {
  const companyAOwner = {
    email: 'admin@majujaya-test.com',
    password: 'StrongPass123!'
  };

  const companyBOwner = {
    name: 'PT Maju Lain',
    email: 'admin@maju-lain.com',
    password: 'OtherPass123!'
  };

  test('Critical - Company A user cannot access Company B data', async ({ page, browser }) => {
    // Create two browser contexts for cross-tenant testing
    const companyAContext = await browser.newContext();
    const companyAPage = await companyAContext.newPage();

    const companyBContext = await browser.newContext();
    const companyBPage = await companyBContext.newPage();

    try {
      // First, create Company B (if not exists)
      await companyBPage.goto('/register');
      await companyBPage.waitForLoadState('networkidle');

      // Fill form using positional selectors (no name attributes)
      const bInputs = companyBPage.locator('input[type="text"], input[type="password"]');
      const bNameInput = bInputs.nth(0);
      const bEmailInput = bInputs.nth(1);
      const bPasswordInput = companyBPage.locator('input.pass-input');
      const bConfirmPasswordInput = companyBPage.locator('input.pass-inputs');

      await bNameInput.fill(companyBOwner.name);
      await bEmailInput.fill(companyBOwner.email);
      await bPasswordInput.fill(companyBOwner.password);
      await bConfirmPasswordInput.fill(companyBOwner.password);

      await companyBPage.click('button[type="submit"], button:has-text("Sign Up")');
      await companyBPage.waitForTimeout(3000);

      // Company A login (assuming Company A already exists from previous tests)
      await companyAPage.goto('/login');
      await companyAPage.waitForLoadState('networkidle');

      // Fill login form using positional selectors
      const aInputs = companyAPage.locator('input[type="text"], input[type="password"]');
      const aEmailInput = aInputs.nth(0);
      const aPasswordInput = aInputs.nth(1);

      await aEmailInput.fill(companyAOwner.email);
      await aPasswordInput.fill(companyAOwner.password);
      await companyAPage.click('button[type="submit"], button:has-text("Login")');
      await companyAPage.waitForTimeout(3000);

      // Company A tries to access Company B data via API
      // Get Company B's company ID (this would normally be hidden)
      const companyBProfile = await companyBPage.request.get('/api/v1/hcm/company/current');
      const companyBData = await companyBProfile.json();
      const companyBId = companyBData.data.id;

      // Company A tries to access Company B's users
      const crossTenantResponse = await companyAPage.request.get(`/api/v1/hcm/user-management/users?company_id=${companyBId}`);
      expect(crossTenantResponse.status()).toBe(403);

      const errorData = await crossTenantResponse.json();
      expect(errorData.error.code).toBe('TENANT_ISOLATION_VIOLATION');

      // Try accessing specific user from Company B
      const companyBUsers = await companyBPage.request.get('/api/v1/hcm/user-management/users');
      const companyBUsersData = await companyBUsers.json();

      if (companyBUsersData.data.length > 0) {
        const companyBUserId = companyBUsersData.data[0].id;

        // Company A tries to access Company B's user
        const crossAccessResponse = await companyAPage.request.get(`/api/v1/hcm/user-management/users/${companyBUserId}`);
        expect(crossAccessResponse.status()).toBe(404); // Should return 404, not 403 to avoid data leakage

        const crossError = await crossAccessResponse.json();
        expect(crossError.error.code).toBe('TENANT_ISOLATION_VIOLATION');
      }

    } finally {
      await companyAContext.close();
      await companyBContext.close();
    }
  });

  test('Company A cannot see Company B roles and permissions', async ({ page, browser }) => {
    const companyAContext = await browser.newContext();
    const companyAPage = await companyAContext.newPage();

    const companyBContext = await browser.newContext();
    const companyBPage = await companyBContext.newPage();

    try {
      // Company B creates a custom role
      await companyBPage.goto('/login');
      await companyBPage.fill('input[name="email"]', companyBOwner.email);
      await companyBPage.fill('input[name="password"]', companyBOwner.password);
      await companyBPage.click('button[type="submit"]');

      await companyBPage.click('nav a:has-text("Settings")');
      await companyBPage.click('a:has-text("Roles")');
      await companyBPage.click('button:has-text("Create Role")');

      await companyBPage.fill('input[name="name"]', 'Company B Exclusive Role');
      await companyBPage.check('input[type="checkbox"][value="employee.view"]');
      await companyBPage.click('button[type="submit"]:has-text("Save")');

      // Company A tries to see Company B's roles
      await companyAPage.goto('/login');
      await companyAPage.fill('input[name="email"]', companyAOwner.email);
      await companyAPage.fill('input[name="password"]', companyAOwner.password);
      await companyAPage.click('button[type="submit"]');

      await companyAPage.click('nav a:has-text("Settings")');
      await companyAPage.click('a:has-text("Roles")');

      // Should NOT see Company B's role
      await expect(companyAPage.locator('.roles-list')).not.toContainText('Company B Exclusive Role');

      // API verification
      const rolesResponse = await companyAPage.request.get('/api/v1/hcm/user-management/roles');
      expect(rolesResponse.ok()).toBeTruthy();
      const rolesData = await rolesResponse.json();

      // Should not contain Company B's role
      const companyBRoles = rolesData.data.filter(role => role.name === 'Company B Exclusive Role');
      expect(companyBRoles.length).toBe(0);

    } finally {
      await companyAContext.close();
      await companyBContext.close();
    }
  });

  test('URL manipulation cannot bypass tenant isolation', async ({ page }) => {
    // Login as Company A user
    await page.goto('/login');
    await page.fill('input[name="email"]', companyAOwner.email);
    await page.fill('input[name="password"]', companyAOwner.password);
    await page.click('button[type="submit"]');

    // Try to manipulate URL to access other tenant data
    // This simulates a user trying to guess URLs or manipulate parameters

    // Try accessing users with manipulated company_id
    await page.goto('/users?company_id=999'); // Non-existent company
    await expect(page.locator('.error-message')).toContainText('Access denied');

    // Try direct API call with manipulated company_id
    const manipulatedResponse = await page.request.get('/api/v1/hcm/user-management/users?company_id=999');
    expect(manipulatedResponse.status()).toBe(403);

    // Try accessing roles with invalid company context
    const rolesResponse = await page.request.get('/api/v1/hcm/user-management/roles?company_id=999');
    expect(rolesResponse.status()).toBe(403);
  });

  test('Audit logs maintain tenant isolation', async ({ page, browser }) => {
    const companyAContext = await browser.newContext();
    const companyAPage = await companyAContext.newPage();

    const companyBContext = await browser.newContext();
    const companyBPage = await companyBContext.newPage();

    try {
      // Company A performs some action that creates audit log
      await companyAPage.goto('/login');
      await companyAPage.fill('input[name="email"]', companyAOwner.email);
      await companyAPage.fill('input[name="password"]', companyAOwner.password);
      await companyAPage.click('button[type="submit"]');

      await companyAPage.click('nav a:has-text("Settings")');
      await companyAPage.click('a:has-text("Users")');
      await companyAPage.click('button:has-text("Create User")');

      await companyAPage.fill('input[name="name"]', 'Audit Test User');
      await companyAPage.fill('input[name="email"]', 'audit-test@majujaya-test.com');
      await companyAPage.fill('input[name="password"]', 'TestPass123!');
      await companyAPage.click('button[type="submit"]:has-text("Save")');

      // Company B tries to access Company A's audit logs
      await companyBPage.goto('/login');
      await companyBPage.fill('input[name="email"]', companyBOwner.email);
      await companyBPage.fill('input[name="password"]', companyBOwner.password);
      await companyBPage.click('button[type="submit"]');

      // Try to access audit logs (assuming there's an audit endpoint)
      const auditResponse = await companyBPage.request.get('/api/v1/hcm/audit/user-actions');
      expect(auditResponse.status()).toBe(403);

      // Should not see Company A's audit entries
      if (auditResponse.ok()) {
        const auditData = await auditResponse.json();
        const companyAAudits = auditData.data.filter(entry =>
          entry.details.includes('majujaya-test.com')
        );
        expect(companyAAudits.length).toBe(0);
      }

    } finally {
      await companyAContext.close();
      await companyBContext.close();
    }
  });
});