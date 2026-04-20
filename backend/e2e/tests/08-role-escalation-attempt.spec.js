import { test, expect } from '@playwright/test';

test.describe('8. ROLE ESCALATION ATTEMPT', () => {
  const budiCredentials = {
    email: 'budi@majujaya-test.com',
    password: 'TestPass123!'
  };

  test('Regular user cannot create admin roles', async ({ page }) => {
    // Login as regular user (Budi - Payroll Staff)
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Try to access Settings → Roles (should not be visible)
    const settingsLink = page.locator('nav a:has-text("Settings")');

    // Option 1: Settings menu not visible
    await expect(settingsLink).not.toBeVisible();

    // Option 2: Settings visible but Roles not accessible
    // await settingsLink.click();
    // await expect(page.locator('a:has-text("Roles")')).not.toBeVisible();

    // Try direct URL access (should redirect or show error)
    await page.goto('/roles');
    await expect(page.locator('.error-message')).toContainText('Access denied');

    // Try API access directly
    const rolesResponse = await page.request.get('/api/v1/hcm/user-management/roles');
    expect(rolesResponse.status()).toBe(403);

    const errorData = await rolesResponse.json();
    expect(errorData.error.code).toBe('PERMISSION_DENIED');
  });

  test('Regular user cannot assign themselves admin roles', async ({ page }) => {
    // Login as regular user
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Try to access user management (should not be able to assign roles to themselves)
    // This assumes there's a profile page where users can manage their own roles

    const profileLink = page.locator('a:has-text("Profile")');
    if (await profileLink.isVisible()) {
      await profileLink.click();

      // Should not see role assignment options
      await expect(page.locator('select[name="role"]')).not.toBeVisible();
      await expect(page.locator('button:has-text("Assign Role")')).not.toBeVisible();
    }

    // Try API call to assign role to themselves
    const userProfileResponse = await page.request.get('/api/v1/hcm/user/profile');
    expect(userProfileResponse.ok()).toBeTruthy();
    const userData = await userProfileResponse.json();
    const userId = userData.data.id;

    // Try to assign HR Manager role to themselves
    const assignResponse = await page.request.post(`/api/v1/hcm/user-management/users/${userId}/roles`, {
      data: {
        role_code: 'hr_manager'
      }
    });
    expect(assignResponse.status()).toBe(403);

    const errorData = await assignResponse.json();
    expect(errorData.error.code).toBe('PERMISSION_DENIED');
  });

  test('Regular user cannot modify existing roles', async ({ page }) => {
    // Login as regular user
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Try to modify roles via API
    const rolesResponse = await page.request.get('/api/v1/hcm/user-management/roles');
    if (rolesResponse.ok()) {
      const rolesData = await rolesResponse.json();

      if (rolesData.data.length > 0) {
        const roleId = rolesData.data[0].id;

        // Try to update role
        const updateResponse = await page.request.put(`/api/v1/hcm/user-management/roles/${roleId}`, {
          data: {
            name: 'Hacked Role Name',
            permission_codes: ['employee.view', 'payroll.run', 'user.manage'] // Try to add admin permission
          }
        });
        expect(updateResponse.status()).toBe(403);

        // Try to sync permissions
        const syncResponse = await page.request.post(`/api/v1/hcm/user-management/roles/${roleId}/permissions:sync`, {
          data: {
            permission_codes: ['super_admin']
          }
        });
        expect(syncResponse.status()).toBe(403);
      }
    }
  });

  test('Regular user cannot create roles with elevated permissions', async ({ page }) => {
    // Login as regular user
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Try to create role via API
    const createRoleResponse = await page.request.post('/api/v1/hcm/user-management/roles', {
      data: {
        name: 'Evil Admin Role',
        permission_codes: ['user.manage', 'role.manage', 'super_admin']
      }
    });
    expect(createRoleResponse.status()).toBe(403);

    const errorData = await createRoleResponse.json();
    expect(errorData.error.code).toBe('PERMISSION_DENIED');
  });

  test('Regular user cannot delete roles', async ({ page }) => {
    // Login as regular user
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Try to get roles and delete one
    const rolesResponse = await page.request.get('/api/v1/hcm/user-management/roles');
    if (rolesResponse.ok()) {
      const rolesData = await rolesResponse.json();

      // Find a non-system role to try to delete
      const deletableRole = rolesData.data.find(role => !role.is_system);

      if (deletableRole) {
        const deleteResponse = await page.request.delete(`/api/v1/hcm/user-management/roles/${deletableRole.id}`);
        expect(deleteResponse.status()).toBe(403);

        const errorData = await deleteResponse.json();
        expect(errorData.error.code).toBe('PERMISSION_DENIED');
      }
    }
  });

  test('Super admin bypass works correctly', async ({ page }) => {
    // This test verifies that super admin (owner) CAN perform role management
    const ownerCredentials = {
      email: 'admin@majujaya-test.com',
      password: 'StrongPass123!'
    };

    // Login as owner (super admin)
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Verify owner can access role management
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');

    // Should be able to create roles
    await expect(page.locator('button:has-text("Create Role")')).toBeVisible();

    // API access should work
    const rolesResponse = await page.request.get('/api/v1/hcm/user-management/roles');
    expect(rolesResponse.ok()).toBeTruthy();

    // Should be able to create role
    const createResponse = await page.request.post('/api/v1/hcm/user-management/roles', {
      data: {
        name: 'Super Admin Test Role',
        permission_codes: ['user.manage']
      }
    });
    expect(createResponse.ok()).toBeTruthy();
  });
});