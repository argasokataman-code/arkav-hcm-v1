import { test, expect } from '@playwright/test';

test.describe('11. AUDIT VISIBILITY', () => {
  const ownerCredentials = {
    email: 'admin@majujaya-test.com',
    password: 'StrongPass123!'
  };

  const budiCredentials = {
    email: 'budi@majujaya-test.com',
    password: 'TestPass123!'
  };

  test('Audit log shows role assignment actions', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Perform role assignment action
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Users")');

    // Find Budi's user row
    const budiRow = page.locator('.users-list tr, .users-table tr').filter({
      hasText: 'Budi Santoso'
    });

    // Click to manage roles (assuming there's a button to assign roles)
    await budiRow.locator('button:has-text("Manage Roles"), button:has-text("Assign")').click();

    // Assign an additional role (e.g., Employee role if not already assigned)
    await page.check('input[type="checkbox"][value="employee"]');
    await page.click('button[type="submit"]:has-text("Save")');

    // Navigate to audit logs
    await page.click('nav a:has-text("Audit"), a:has-text("Logs")');

    // Verify audit entry exists
    await expect(page.locator('.audit-log, .audit-entries')).toContainText('Role assigned');
    await expect(page.locator('.audit-log')).toContainText('Budi Santoso');
    await expect(page.locator('.audit-log')).toContainText('Employee');

    // Verify actor information
    await expect(page.locator('.audit-log')).toContainText(ownerCredentials.email);
  });

  test('Audit log shows role creation', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Create a new role
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');
    await page.click('button:has-text("Create Role")');

    await page.fill('input[name="name"]', 'Audit Test Role');
    await page.fill('input[name="description"]', 'Role for audit testing');
    await page.check('input[type="checkbox"][value="employee.view"]');
    await page.click('button[type="submit"]:has-text("Save")');

    // Check audit logs
    await page.click('nav a:has-text("Audit")');

    await expect(page.locator('.audit-log')).toContainText('Role created');
    await expect(page.locator('.audit-log')).toContainText('Audit Test Role');
    await expect(page.locator('.audit-log')).toContainText(ownerCredentials.email);
  });

  test('Audit log shows permission changes', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Edit a role to change permissions
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');
    await page.click('text=Payroll Staff');

    // Add a permission
    await page.check('input[type="checkbox"][value="leave.view"]');
    await page.click('button[type="submit"]:has-text("Save")');

    // Check audit logs
    await page.click('nav a:has-text("Audit")');

    await expect(page.locator('.audit-log')).toContainText('Role permissions updated');
    await expect(page.locator('.audit-log')).toContainText('Payroll Staff');
    await expect(page.locator('.audit-log')).toContainText('leave.view');
  });

  test('Regular users cannot access audit logs', async ({ page }) => {
    // Login as regular user
    await page.goto('/login');
    await page.fill('input[name="email"]', budiCredentials.email);
    await page.fill('input[name="password"]', budiCredentials.password);
    await page.click('button[type="submit"]');

    // Try to access audit logs
    const auditLink = page.locator('nav a:has-text("Audit")');

    // Option 1: Audit link not visible
    await expect(auditLink).not.toBeVisible();

    // Option 2: Link visible but access denied
    // await auditLink.click();
    // await expect(page.locator('.error-message')).toContainText('Access denied');

    // API verification
    const auditResponse = await page.request.get('/api/v1/hcm/audit/logs');
    expect(auditResponse.status()).toBe(403);

    const errorData = await auditResponse.json();
    expect(errorData.error.code).toBe('PERMISSION_DENIED');
  });

  test('Audit logs maintain chronological order', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Perform multiple actions in sequence
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Users")');
    await page.click('button:has-text("Create User")');

    await page.fill('input[name="name"]', 'Audit User 1');
    await page.fill('input[name="email"]', 'audit1@test.com');
    await page.fill('input[name="password"]', 'TestPass123!');
    await page.click('button[type="submit"]:has-text("Save")');

    // Create another user
    await page.click('button:has-text("Create User")');
    await page.fill('input[name="name"]', 'Audit User 2');
    await page.fill('input[name="email"]', 'audit2@test.com');
    await page.fill('input[name="password"]', 'TestPass123!');
    await page.click('button[type="submit"]:has-text("Save")');

    // Check audit logs
    await page.click('nav a:has-text("Audit")');

    // Get all audit entries
    const auditEntries = page.locator('.audit-log, .audit-entry');

    // Verify chronological order (most recent first)
    const firstEntry = auditEntries.first();
    const lastEntry = auditEntries.last();

    // First entry should be more recent than last entry
    const firstEntryText = await firstEntry.textContent();
    const lastEntryText = await lastEntry.textContent();

    // Should contain the most recent action (second user creation)
    await expect(firstEntry).toContainText('Audit User 2');
    await expect(lastEntry).toContainText('Audit User 1');
  });

  test('Audit logs show detailed change information', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Create a role with specific permissions
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Roles")');
    await page.click('button:has-text("Create Role")');

    await page.fill('input[name="name"]', 'Detailed Audit Role');
    await page.check('input[type="checkbox"][value="employee.view"]');
    await page.check('input[type="checkbox"][value="payroll.view"]');
    await page.click('button[type="submit"]:has-text("Save")');

    // Check audit logs for detailed information
    await page.click('nav a:has-text("Audit")');

    const auditEntry = page.locator('.audit-log').filter({
      hasText: 'Detailed Audit Role'
    });

    // Should show what was created
    await expect(auditEntry).toContainText('Role created');
    await expect(auditEntry).toContainText('employee.view');
    await expect(auditEntry).toContainText('payroll.view');

    // Should show timestamp
    await expect(auditEntry).toContainText(/\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{4}/);

    // Should show IP or session info if available
    // await expect(auditEntry).toContainText(/IP|Session/);
  });

  test('Audit logs are tamper-proof', async ({ page }) => {
    // Login as owner
    await page.goto('/login');
    await page.fill('input[name="email"]', ownerCredentials.email);
    await page.fill('input[name="password"]', ownerCredentials.password);
    await page.click('button[type="submit"]');

    // Perform an action
    await page.click('nav a:has-text("Settings")');
    await page.click('a:has-text("Users")');
    await page.click('button:has-text("Create User")');

    await page.fill('input[name="name"]', 'Tamper Test User');
    await page.fill('input[name="email"]', 'tamper@test.com');
    await page.fill('input[name="password"]', 'TestPass123!');
    await page.click('button[type="submit"]:has-text("Save")');

    // Check audit log
    await page.click('nav a:has-text("Audit")');
    await expect(page.locator('.audit-log')).toContainText('Tamper Test User');

    // Try to modify audit log via API (should fail)
    const auditResponse = await page.request.get('/api/v1/hcm/audit/logs');
    expect(auditResponse.ok()).toBeTruthy();

    const auditData = await auditResponse.json();
    if (auditData.data.length > 0) {
      const auditId = auditData.data[0].id;

      // Try to delete audit entry
      const deleteResponse = await page.request.delete(`/api/v1/hcm/audit/logs/${auditId}`);
      expect(deleteResponse.status()).toBe(403);

      // Try to modify audit entry
      const updateResponse = await page.request.put(`/api/v1/hcm/audit/logs/${auditId}`, {
        data: { action: 'modified_action' }
      });
      expect(updateResponse.status()).toBe(403);
    }
  });
});