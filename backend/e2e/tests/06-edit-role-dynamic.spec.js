import { test, expect } from '@playwright/test';

test.describe('6. EDIT ROLE (DYNAMIC CHANGE)', () => {
  const ownerCredentials = {
    email: 'admin@majujaya-test.com',
    password: 'StrongPass123!'
  };

  const budiCredentials = {
    email: 'budi@majujaya-test.com',
    password: 'TestPass123!'
  };

  test('Happy Path - Remove permission from role affects users immediately', async ({ page, browser }) => {
    // Create two browser contexts - one for admin, one for user
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    const userPage = await browser.newContext();
    const userBrowserPage = await userPage.newPage();

    try {
      // Admin login
      await adminPage.goto('/login');
      await adminPage.fill('input[name="email"]', ownerCredentials.email);
      await adminPage.fill('input[name="password"]', ownerCredentials.password);
      await adminPage.click('button[type="submit"]');

      // User login
      await userBrowserPage.goto('/login');
      await userBrowserPage.fill('input[name="email"]', budiCredentials.email);
      await userBrowserPage.fill('input[name="password"]', budiCredentials.password);
      await userBrowserPage.click('button[type="submit"]');

      // Verify user initially has access to Run Payroll
      await userBrowserPage.click('nav a:has-text("Payroll")');
      await expect(userBrowserPage.locator('button:has-text("Run Payroll")')).toBeVisible();

      // Admin edits role and removes payroll.run permission
      await adminPage.click('nav a:has-text("Settings")');
      await adminPage.click('a:has-text("Roles")');
      await adminPage.click('text=Payroll Staff'); // Edit role

      // Uncheck payroll.run permission
      await adminPage.uncheck('input[type="checkbox"][value="payroll.run"]');
      await adminPage.click('button[type="submit"]:has-text("Save")');

      // Verify success message
      await expect(adminPage.locator('.success-message')).toContainText('Role updated');

      // User refreshes page and should lose access immediately
      await userBrowserPage.reload();
      await userBrowserPage.click('nav a:has-text("Payroll")');

      // Run Payroll button should no longer be visible
      await expect(userBrowserPage.locator('button:has-text("Run Payroll")')).not.toBeVisible();

      // Verify via API that permission is removed
      const userProfileResponse = await userBrowserPage.request.get('/api/v1/hcm/user/profile');
      expect(userProfileResponse.ok()).toBeTruthy();
      const userData = await userProfileResponse.json();

      // Should not have payroll.run permission anymore
      const hasPayrollRun = userData.data.effective_permissions.includes('payroll.run');
      expect(hasPayrollRun).toBeFalsy();

    } finally {
      // Cleanup
      await adminContext.close();
      await userPage.close();
    }
  });

  test('Negative Case - Attempt to remove all permissions shows warning', async ({ page }) => {
    // Login as admin
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Navigate to roles and create a test role with single permission
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');
    await page.click('button:has-text("Create Role")');

    await page.fill('input[name="name"]', 'Test Role Single Permission');
    await page.check('input[type="checkbox"][value="employee.view"]');
    await page.click('button[type="submit"]:has-text("Save")');

    // Now edit the role and try to remove all permissions
    await page.click('text=Test Role Single Permission');
    await page.uncheck('input[type="checkbox"][value="employee.view"]');

    // Should show warning
    await expect(page.locator('.warning-message, .alert')).toContainText('This role will have no permissions');

    // Try to save anyway
    await page.click('button[type="submit"]:has-text("Save")');

    // Should either prevent save or show confirmation dialog
    // Option 1: Save prevented
    await expect(page.locator('.error-message')).toContainText('Role must have at least one permission');

    // Option 2: Confirmation dialog appears
    // await expect(page.locator('.confirmation-dialog')).toBeVisible();
    // await expect(page.locator('.confirmation-dialog')).toContainText('no permissions');
  });

  test('Verify role changes are audited', async ({ page }) => {
    // Login as admin
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Edit a role
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');
    await page.click('text=Payroll Staff');

    // Make a change
    await page.check('input[type="checkbox"][value="leave.view"]');
    await page.click('button[type="submit"]:has-text("Save")');

    // Check audit log (assuming there's an audit section)
    await page.click('a:has-text("Audit")'); // or wherever audit logs are shown

    // Verify audit entry exists
    await expect(page.locator('.audit-log')).toContainText('Role modified');
    await expect(page.locator('.audit-log')).toContainText('Payroll Staff');
    await expect(page.locator('.audit-log')).toContainText('leave.view permission added');
  });
});