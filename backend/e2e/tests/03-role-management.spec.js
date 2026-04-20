import { test, expect } from '@playwright/test';

test.describe('3. ROLE MANAGEMENT (CREATE ROLE)', () => {
  const ownerCredentials = {
    email: 'admin@majujaya-test.com',
    password: 'StrongPass123!'
  };

  const testRole = {
    name: 'Payroll Staff',
    description: 'Staff responsible for payroll processing',
    permissions: ['payroll.run', 'employee.view']
  };

  test.beforeEach(async ({ page }) => {
    // Login as owner first
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/dashboard/);
  });

  test('Happy Path - Create role with permissions', async ({ page }) => {
    // Step 1: Navigate to Settings → Roles
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');

    // Verify roles page loaded
    await expect(page).toHaveURL(/roles/);
    await expect(page.locator('h1')).toContainText('Roles');

    // Step 2: Click Create Role button
    await page.click('button:has-text("Create Role")');

    // Verify create role modal/form opens
    await expect(page.locator('.modal, .drawer, form')).toBeVisible();

    // Step 3: Fill role name
    await page.fill('input[name="name"]', testRole.name);
    await page.fill('textarea[name="description"]', testRole.description);

    // Step 4: Select permissions
    for (const permission of testRole.permissions) {
      await page.check(`input[type="checkbox"][value="${permission}"]`);
    }

    // Step 5: Save role
    await page.click('button[type="submit"]:has-text("Save")');

    // Step 6: Verify role appears in list
    await expect(page.locator('.roles-list, .roles-table')).toContainText(testRole.name);

    // Verify system state via API
    const rolesResponse = await page.request.get('/api/v1/hcm/user-management/roles');
    expect(rolesResponse.ok()).toBeTruthy();
    const rolesData = await rolesResponse.json();

    const createdRole = rolesData.data.find(role => role.name === testRole.name);
    expect(createdRole).toBeTruthy();
    expect(createdRole.permissions).toEqual(expect.arrayContaining(testRole.permissions));
    expect(createdRole.company_id).toBeTruthy(); // Should have company_id for tenant isolation
  });

  test('Negative Case - Empty role name', async ({ page }) => {
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');
    await page.click('button:has-text("Create Role")');

    // Leave name empty and try to save
    await page.click('button[type="submit"]:has-text("Save")');

    // Verify validation error
    await expect(page.locator('.error-message, .field-error')).toContainText('Role name is required');
  });

  test('Negative Case - Duplicate role name', async ({ page }) => {
    // First create a role
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');
    await page.click('button:has-text("Create Role")');

    await page.fill('input[name="name"]', 'Duplicate Role');
    await page.click('button[type="submit"]:has-text("Save")');

    // Wait for success
    await expect(page.locator('.roles-list')).toContainText('Duplicate Role');

    // Now try to create another role with same name
    await page.click('button:has-text("Create Role")');
    await page.fill('input[name="name"]', 'Duplicate Role');
    await page.click('button[type="submit"]:has-text("Save")');

    // Verify error
    await expect(page.locator('.error-message')).toContainText('Role already exists');
  });

  test('Negative Case - Invalid permission selection', async ({ page }) => {
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');
    await page.click('button:has-text("Create Role")');

    // Try to select a permission that should be disabled/hidden
    const hiddenPermission = page.locator('input[type="checkbox"][value="super_admin_only"]');

    // Verify it's disabled or not present
    await expect(hiddenPermission).toBeDisabled();
    // OR
    await expect(hiddenPermission).not.toBeVisible();
  });
});