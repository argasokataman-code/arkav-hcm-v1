import { test, expect } from '@playwright/test';

test.describe('10. DELETE ROLE IN USE', () => {
  const ownerCredentials = {
    email: 'admin@majujaya-test.com',
    password: 'StrongPass123!'
  };

  test('Cannot delete role that is assigned to users', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Navigate to roles
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');

    // Find the Payroll Staff role (which should be assigned to Budi)
    const payrollStaffRole = page.locator('.roles-list tr, .roles-table tr').filter({
      hasText: 'Payroll Staff'
    });

    // Click delete button (might be in a dropdown menu)
    await payrollStaffRole.locator('button:has-text("Delete"), .dropdown button').click();

    // Should show warning modal
    await expect(page.locator('.modal, .confirmation-dialog')).toBeVisible();
    await expect(page.locator('.modal, .confirmation-dialog')).toContainText('assigned to users');

    // Should show options: Reassign users or Cancel
    await expect(page.locator('.modal')).toContainText(/reassign|cancel/i);

    // Try to confirm delete anyway (should fail or show error)
    const confirmButton = page.locator('.modal button:has-text("Delete"), .modal button:has-text("Confirm")');
    if (await confirmButton.isVisible()) {
      await confirmButton.click();

      // Should show error or prevent deletion
      await expect(page.locator('.error-message')).toContainText('cannot delete.*assigned');
    }
  });

  test('API prevents deletion of role in use', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Get roles via API
    const rolesResponse = await page.request.get('/api/v1/hcm/user-management/roles');
    expect(rolesResponse.ok()).toBeTruthy();
    const rolesData = await rolesResponse.json();

    // Find Payroll Staff role
    const payrollStaffRole = rolesData.data.find(role => role.name === 'Payroll Staff');
    expect(payrollStaffRole).toBeTruthy();

    // Try to delete it
    const deleteResponse = await page.request.delete(`/api/v1/hcm/user-management/roles/${payrollStaffRole.id}`);
    expect(deleteResponse.status()).toBe(409); // Conflict

    const errorData = await deleteResponse.json();
    expect(errorData.error.code).toBe('ROLE_IN_USE');
    expect(errorData.error.message).toContain('assigned to users');
  });

  test('Can delete role after reassigning users', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Create a new role to reassign users to
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');
    await page.click('button:has-text("Create Role")');

    await page.fill('input[name="name"]', 'Temporary Role');
    await page.check('input[type="checkbox"][value="employee.view"]');
    await page.click('button[type="submit"]:has-text("Save")');

    // Now try to delete Payroll Staff role
    const payrollStaffRow = page.locator('.roles-list tr, .roles-table tr').filter({
      hasText: 'Payroll Staff'
    });

    await payrollStaffRow.locator('button:has-text("Delete")').click();

    // In the warning modal, choose to reassign users
    await expect(page.locator('.modal')).toContainText('assigned to users');

    // Select the new role for reassignment
    await page.selectOption('.modal select', 'Temporary Role');

    // Confirm reassignment and deletion
    await page.click('.modal button:has-text("Reassign & Delete")');

    // Should succeed
    await expect(page.locator('.success-message')).toContainText('Role deleted');

    // Verify role is gone from list
    await expect(page.locator('.roles-list')).not.toContainText('Payroll Staff');
  });

  test('System roles cannot be deleted', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Try to delete a system role (like HR Manager)
    const hrManagerRow = page.locator('.roles-list tr, .roles-table tr').filter({
      hasText: 'HR Manager'
    });

    // Delete button should not be visible or disabled
    const deleteButton = hrManagerRow.locator('button:has-text("Delete")');
    await expect(deleteButton).toBeDisabled();

    // API verification
    const rolesResponse = await page.request.get('/api/v1/hcm/user-management/roles');
    const rolesData = await rolesResponse.json();
    const hrManagerRole = rolesData.data.find(role => role.name === 'HR Manager');

    if (hrManagerRole) {
      const deleteResponse = await page.request.delete(`/api/v1/hcm/user-management/roles/${hrManagerRole.id}`);
      expect(deleteResponse.status()).toBe(403);

      const errorData = await deleteResponse.json();
      expect(errorData.error.code).toBe('SYSTEM_ROLE_CANNOT_DELETE');
    }
  });

  test('Cancel deletion works correctly', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Try to delete Payroll Staff role
    const payrollStaffRow = page.locator('.roles-list tr').filter({
      hasText: 'Payroll Staff'
    });

    await payrollStaffRow.locator('button:has-text("Delete")').click();

    // In warning modal, click Cancel
    await page.click('.modal button:has-text("Cancel")');

    // Modal should close, role should still exist
    await expect(page.locator('.modal')).not.toBeVisible();
    await expect(page.locator('.roles-list')).toContainText('Payroll Staff');
  });
});