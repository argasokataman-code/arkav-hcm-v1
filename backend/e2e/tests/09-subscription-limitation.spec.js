import { test, expect } from '@playwright/test';

test.describe('9. SUBSCRIPTION LIMITATION', () => {
  const ownerCredentials = {
    email: 'admin@majujaya-test.com',
    password: 'StrongPass123!'
  };

  const budiCredentials = {
    email: 'budi@majujaya-test.com',
    password: 'TestPass123!'
  };

  test('Expired subscription blocks user creation', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Simulate expired subscription (this would be set in test database)
    // For this test, we'll assume the subscription is expired

    // Try to create user
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Users")');
    await page.click('button:has-text("Create User")');

    await page.fill('input[name="name"]', 'Blocked User');
    await page.fill('input[name="email"]', 'blocked@test.com');
    await page.fill('input[name="password"]', 'TestPass123!');

    await page.click('button[type="submit"]:has-text("Save")');

    // Should show subscription expired message
    await expect(page.locator('.error-message, .alert')).toContainText('subscription has expired');

    // API verification
    const createResponse = await page.request.post('/api/v1/hcm/user-management/users', {
      data: {
        name: 'Blocked User API',
        email: 'blocked-api@test.com',
        password: 'TestPass123!'
      }
    });
    expect(createResponse.status()).toBe(403);

    const errorData = await createResponse.json();
    expect(errorData.error.code).toBe('SUBSCRIPTION_EXPIRED');
  });

  test('Expired subscription blocks payroll run', async ({ page }) => {
    // Login as user with payroll permissions
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Navigate to payroll
    await page.click('nav a:has-text("Payroll")');

    // Try to run payroll
    const runPayrollButton = page.locator('button:has-text("Run Payroll")');
    if (await runPayrollButton.isVisible()) {
      await runPayrollButton.click();

      // Should show subscription expired banner
      await expect(page.locator('.subscription-banner, .alert')).toContainText('subscription has expired');

      // Payroll should not execute
      await expect(page.locator('.success-message')).not.toBeVisible();
    }

    // API verification
    const payrollResponse = await page.request.post('/api/v1/hcm/payroll/run');
    expect(payrollResponse.status()).toBe(403);

    const errorData = await payrollResponse.json();
    expect(errorData.error.code).toBe('SUBSCRIPTION_EXPIRED');
  });

  test('Subscription banner visible on all pages when expired', async ({ page }) => {
    // Login as any user when subscription is expired
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Check dashboard
    await expect(page.locator('.subscription-banner')).toContainText('subscription has expired');

    // Check other pages
    await page.click('nav a:has-text("Employee")');
    await expect(page.locator('.subscription-banner')).toContainText('subscription has expired');

    await page.click('nav a:has-text("Payroll")');
    await expect(page.locator('.subscription-banner')).toContainText('subscription has expired');
  });

  test('Subscription renewal prompt shown', async ({ page }) => {
    // Login when subscription expired
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Should show renewal prompt/button
    await expect(page.locator('button, a')).toContainText(/renew|upgrade|billing/);

    // Click renewal button should redirect to billing/subscription page
    const renewalButton = page.locator('button:has-text(/renew|upgrade/)').first();
    if (await renewalButton.isVisible()) {
      await renewalButton.click();
      await expect(page).toHaveURL(/billing|subscription|payment/);
    }
  });

  test('Read-only access allowed when subscription expired', async ({ page }) => {
    // Login when subscription expired
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Should be able to view data (read-only)
    await page.click('nav a:has-text("Employee")');
    await expect(page.locator('.employee-list, .employee-table')).toBeVisible();

    // But create/edit buttons should be disabled or hidden
    await expect(page.locator('button:has-text("Add Employee")')).toBeDisabled();
    await expect(page.locator('button:has-text("Edit")')).toBeDisabled();

    // API verification - read should work
    const employeesResponse = await page.request.get('/api/v1/hcm/employees');
    expect(employeesResponse.ok()).toBeTruthy();

    // Write operations should fail
    const createResponse = await page.request.post('/api/v1/hcm/employees', {
      data: {
        name: 'Test Employee',
        email: 'test@employee.com'
      }
    });
    expect(createResponse.status()).toBe(403);
  });
});