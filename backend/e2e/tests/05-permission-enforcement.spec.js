import { test, expect } from '@playwright/test';

test.describe('5. PERMISSION ENFORCEMENT (REAL USAGE)', () => {
  const ownerCredentials = {
    email: 'admin@majujaya-test.com',
    password: 'StrongPass123!'
  };

  const budiCredentials = {
    email: 'budi@majujaya-test.com',
    password: 'TestPass123!'
  };

  test('Happy Path - User with permission can access allowed features', async ({ page }) => {
    // Login as Budi (Payroll Staff)
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Verify login successful
    await expect(page).toHaveURL(/dashboard/);

    // Navigate to Payroll section
    await page.click('nav a:has-text("Payroll")');

    // Verify can access Run Payroll page
    await expect(page).toHaveURL(/payroll/);

    // Verify Run Payroll button is visible and enabled
    const runPayrollButton = page.locator('button:has-text("Run Payroll")');
    await expect(runPayrollButton).toBeVisible();
    await expect(runPayrollButton).toBeEnabled();

    // Click Run Payroll (should succeed)
    await runPayrollButton.click();

    // Verify success message or processing started
    await expect(page.locator('.success-message, .processing')).toBeVisible();
  });

  test('Negative Case - User without permission cannot access restricted features', async ({ page }) => {
    // Login as Budi (Payroll Staff - should NOT have employee.delete permission)
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Navigate to Employee section
    await page.click('nav a:has-text("Employee")');

    // Try to find Delete button - should not be visible or disabled
    const deleteButton = page.locator('button:has-text("Delete")').first();

    // Option 1: Button not visible
    await expect(deleteButton).not.toBeVisible();

    // OR Option 2: Button visible but disabled
    // await expect(deleteButton).toBeDisabled();

    // Try to force delete via API call (simulate direct API access)
    const employeesResponse = await page.request.get('/api/v1/hcm/employees');
    expect(employeesResponse.ok()).toBeTruthy();
    const employeesData = await employeesResponse.json();

    if (employeesData.data.length > 0) {
      const employeeId = employeesData.data[0].id;

      // Try to delete employee (should fail with 403)
      const deleteResponse = await page.request.delete(`/api/v1/hcm/employees/${employeeId}`);
      expect(deleteResponse.status()).toBe(403);

      const errorData = await deleteResponse.json();
      expect(errorData.error.code).toBe('PERMISSION_DENIED');
    }
  });

  test('Verify permission-based UI rendering', async ({ page }) => {
    // Login as Budi
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Check which menu items are visible based on permissions
    const nav = page.locator('nav');

    // Should see Payroll (has permission)
    await expect(nav).toContainText('Payroll');

    // Should see Employee (has view permission)
    await expect(nav).toContainText('Employee');

    // Should NOT see Settings (no admin permission)
    await expect(nav).not.toContainText('Settings');

    // Should NOT see Reports (no report permission)
    await expect(nav).not.toContainText('Reports');
  });

  test('Dynamic permission changes take effect immediately', async ({ page, browser }) => {
    // This test requires two browser contexts - one for admin, one for user

    // Create new browser context for admin
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();

    // Admin login and modify permissions
    await adminPage.goto('/login');
    await adminPage.fill('input[name="email"]', ownerCredentials.email);
    await adminPage.fill('input[name="password"]', ownerCredentials.password);
    await adminPage.click('button[type="submit"]');

    // Admin removes payroll.run permission from Payroll Staff role
    await adminPage.click('nav a:has-text("Settings")');
    await adminPage.click('a:has-text("Roles")');
    await adminPage.click('text=Payroll Staff'); // Edit role
    await adminPage.uncheck('input[type="checkbox"][value="payroll.run"]');
    await adminPage.click('button[type="submit"]:has-text("Save")');

    // Now test user access in original page context
    await page.reload(); // Refresh user page

    // Navigate to Payroll - should no longer see Run Payroll button
    await page.click('nav a:has-text("Payroll")');
    const runPayrollButton = page.locator('button:has-text("Run Payroll")');
    await expect(runPayrollButton).not.toBeVisible();

    // Cleanup
    await adminContext.close();
  });
});