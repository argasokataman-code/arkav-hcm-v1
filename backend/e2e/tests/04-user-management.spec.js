import { test, expect } from '@playwright/test';

test.describe('4. USER MANAGEMENT (CREATE USER)', () => {
  const ownerCredentials = {
    email: 'admin@majujaya-test.com',
    password: 'StrongPass123!'
  };

  const testUser = {
    name: 'Budi Santoso',
    email: 'budi@majujaya-test.com',
    password: 'TestPass123!'
  };

  test.beforeEach(async ({ page }) => {
    // Login as owner first
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/dashboard/);
  });

  test('Happy Path - Create user and assign role', async ({ page }) => {
    // Navigate to Settings → Users
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Users")');

    // Verify users page loaded
    await expect(page).toHaveURL(/users/);
    await expect(page.locator('h1')).toContainText('Users');

    // Click Create User button
    await page.click('button:has-text("Create User")');

    // Verify create user form opens
    await expect(page.locator('.modal, .drawer, form')).toBeVisible();

    // Fill user details
    await page.fill('input[name="name"]', testUser.name);
    await page.fill('input[name="email"]', testUser.email);
    await page.fill('input[name="password"]', testUser.password);

    // Assign role (assuming Payroll Staff role exists from previous test)
    await page.selectOption('select[name="role"]', 'Payroll Staff');

    // Submit form
    await page.click('button[type="submit"]:has-text("Save")');

    // Verify user appears in list
    await expect(page.locator('.users-list, .users-table')).toContainText(testUser.name);
    await expect(page.locator('.users-list, .users-table')).toContainText(testUser.email);

    // Verify system state via API
    const usersResponse = await page.request.get('/api/v1/hcm/user-management/users');
    expect(usersResponse.ok()).toBeTruthy();
    const usersData = await usersResponse.json();

    const createdUser = usersData.data.find(user => user.email === testUser.email);
    expect(createdUser).toBeTruthy();
    expect(createdUser.name).toBe(testUser.name);
    expect(createdUser.company_id).toBeTruthy(); // Should have company_id

    // Verify role assignment
    const userRolesResponse = await page.request.get(`/api/v1/hcm/user-management/users/${createdUser.id}/roles`);
    expect(userRolesResponse.ok()).toBeTruthy();
    const rolesData = await userRolesResponse.json();
    expect(rolesData.data.length).toBeGreaterThan(0);
    expect(rolesData.data[0].role_name).toBe('Payroll Staff');
  });

  test('Negative Case - Duplicate email', async ({ page }) => {
    // First create a user
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Users")');
    await page.click('button:has-text("Create User")');

    await page.fill('input[name="name"]', 'First User');
    await page.fill('input[name="email"]', 'duplicate@test.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]:has-text("Save")');

    // Wait for success
    await expect(page.locator('.users-list')).toContainText('duplicate@test.com');

    // Now try to create another user with same email
    await page.click('button:has-text("Create User")');
    await page.fill('input[name="name"]', testUser.name);
    await page.fill('input[name="email"]', 'duplicate@test.com');
    await page.fill('input[name="password"]', testUser.password);
    await page.click('button[type="submit"]:has-text("Save")');

    // Verify error
    await expect(page.locator('.error-message')).toContainText('Email already used');
  });

  test('Negative Case - Invalid role assignment', async ({ page }) => {
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Users")');
    await page.click('button:has-text("Create User")');

    await page.fill('input[name="name"]', testUser.name);
    await page.fill('input[name="email"]', testUser.email);
    await page.fill('input[name="password"]', testUser.password);

    // Try to assign a role from different tenant (this should fail)
    // This would require setting up cross-tenant scenario
    // For now, we'll test with invalid role selection
    await page.selectOption('select[name="role"]', 'invalid-role');

    await page.click('button[type="submit"]:has-text("Save")');

    // Verify error - should reject invalid role
    await expect(page.locator('.error-message')).toContainText('Invalid role selection');
  });
});