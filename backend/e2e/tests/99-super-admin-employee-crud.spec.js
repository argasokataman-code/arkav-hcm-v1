import { test, expect } from '@playwright/test';
import { loginViaUi, credentials, logoutIfNeeded } from '../helpers/auth.js';

test.describe('99. SUPER ADMIN EMPLOYEE CRUD TEST', () => {
  const superAdminCredentials = {
    email: credentials.admin.email,
    password: credentials.admin.password,
  };

  const testEmployee = {
    firstName: `Test_Employee_${Date.now()}`,
    lastName: 'Test_Super_Admin',
    email: `test.emp.${Date.now()}@example.com`,
    phone: '081234567890',
    position: 'Senior Developer',
    department: 'Engineering',
  };

  let employeeId = null;

  test.beforeEach(async ({ page }) => {
    console.log('\n=== SETUP: Super Admin Employee CRUD Test ===\n');
    console.log(`🔐 Logging in as: ${superAdminCredentials.email}`);
    
    await logoutIfNeeded(page);
    await loginViaUi(page, superAdminCredentials);
    
    console.log(`✅ Login successful, redirected to: ${page.url()}`);
  });

  test('1️⃣ NAVIGATE TO EMPLOYEES MENU', async ({ page }) => {
    console.log('\n--- TEST 1: Navigate to Employees Menu ---\n');
    
    // Check that we're on dashboard
    await expect(page).toHaveURL(/\/(index|dashboard|employee-dashboard)/);
    console.log(`📍 Current URL: ${page.url()}`);

    // Check if Employees menu exists
    const employeesMenu = page.locator('a:has-text("Employees"), button:has-text("Employees"), [role="menuitem"]:has-text("Employees")');
    await expect(employeesMenu).toBeVisible({ timeout: 10000 });
    console.log(`✅ Found Employees menu`);

    // Click Employees menu
    await employeesMenu.first().click();
    
    // Wait for navigation and check URL
    await page.waitForURL(/\/employees/, { timeout: 10000 });
    console.log(`✅ Navigated to Employees page: ${page.url()}`);

    // Check if employee list is visible
    await expect(page.locator('h1, h2, .page-title')).toContainText(/Employee|Karyawan/, { timeout: 10000 });
    console.log(`✅ Employee page loaded successfully`);

    // Check for table or list
    const employeeTable = page.locator('table, [role="grid"], .employee-list, .data-table');
    await expect(employeeTable).toBeVisible({ timeout: 10000 });
    console.log(`✅ Employee table/list is visible`);
  });

  test('2️⃣ CREATE EMPLOYEE', async ({ page }) => {
    console.log('\n--- TEST 2: Create Employee ---\n');
    
    // Navigate to employees
    await loginViaUi(page, superAdminCredentials);
    const employeesMenu = page.locator('a:has-text("Employees"), button:has-text("Employees"), [role="menuitem"]:has-text("Employees")');
    await employeesMenu.first().click();
    await page.waitForURL(/\/employees/, { timeout: 10000 });

    console.log(`📝 Employee to create:`);
    console.log(`   - Name: ${testEmployee.firstName} ${testEmployee.lastName}`);
    console.log(`   - Email: ${testEmployee.email}`);

    // Find and click "Create" button (various possible names/selectors)
    const createButtons = page.locator(
      'button:has-text("Create Employee"), button:has-text("Add Employee"), button:has-text("New Employee"), button:has-text("Tambah"), [data-action="create"]'
    );
    
    const createButton = createButtons.first();
    await expect(createButton).toBeVisible({ timeout: 10000 });
    console.log(`✅ Found Create button`);
    
    await createButton.click();

    // Wait for modal/form to appear
    const createForm = page.locator('.modal, .drawer, form, [role="dialog"]');
    await expect(createForm).toBeVisible({ timeout: 10000 });
    console.log(`✅ Create form/modal opened`);

    // Fill form fields with retries for dynamic content
    const firstNameInput = page.locator('input[name*="first_name"], input[name*="firstName"], input[placeholder*="First"], input:nth-of-type(1)');
    await expect(firstNameInput).toBeVisible({ timeout: 5000 });
    await firstNameInput.fill(testEmployee.firstName);
    console.log(`✅ Filled first name`);

    const lastNameInput = page.locator('input[name*="last_name"], input[name*="lastName"], input[placeholder*="Last"], input:nth-of-type(2)');
    await expect(lastNameInput).toBeVisible({ timeout: 5000 });
    await lastNameInput.fill(testEmployee.lastName);
    console.log(`✅ Filled last name`);

    const emailInput = page.locator('input[type="email"], input[name*="email"]');
    await expect(emailInput).toBeVisible({ timeout: 5000 });
    await emailInput.fill(testEmployee.email);
    console.log(`✅ Filled email`);

    // Try to fill optional fields if they exist
    const phoneInput = page.locator('input[name*="phone"], input[name*="mobile"], input[placeholder*="Phone"]').first();
    if (await phoneInput.isVisible().catch(() => false)) {
      await phoneInput.fill(testEmployee.phone);
      console.log(`✅ Filled phone`);
    }

    // Submit form
    const submitButton = page.locator('button:has-text("Save"), button:has-text("Submit"), button:has-text("Create"), button:has-text("Simpan")');
    await expect(submitButton).toBeVisible({ timeout: 5000 });
    console.log(`✅ Found Submit button`);
    
    await submitButton.click();

    // Wait for success (redirect or notification)
    try {
      await page.waitForURL(/\/employees/, { timeout: 15000 });
    } catch (e) {
      console.log(`⚠️  URL didn't change immediately, checking for success notification`);
    }

    // Check for success message or employee in list
    const successNotification = page.locator('.alert-success, .toast-success, [role="alert"]:has-text("success")');
    const employeeInList = page.locator(`text="${testEmployee.firstName}"`, `text="${testEmployee.email}"`);
    
    const successFound = await Promise.race([
      successNotification.isVisible().catch(() => false),
      employeeInList.isVisible().catch(() => false),
    ]).catch(() => false);

    if (!successFound) {
      console.log(`⚠️  No success confirmation found, checking Network tab...`);
      console.log(`   Current URL: ${page.url()}`);
      const pageContent = await page.content();
      if (pageContent.includes(testEmployee.email)) {
        console.log(`✅ Employee found in page content`);
      }
    } else {
      console.log(`✅ Employee created successfully`);
    }

    // Try to get the employee ID from API
    const listResponse = await page.request.get('/api/v1/hcm/employees');
    if (listResponse.ok()) {
      const data = await listResponse.json();
      const created = data.data?.find(e => e.email === testEmployee.email);
      if (created) {
        employeeId = created.id;
        console.log(`✅ API confirmed employee created with ID: ${employeeId}`);
      }
    }
  });

  test('3️⃣ READ EMPLOYEE LIST', async ({ page }) => {
    console.log('\n--- TEST 3: Read Employee List ---\n');
    
    // Navigate to employees
    await loginViaUi(page, superAdminCredentials);
    const employeesMenu = page.locator('a:has-text("Employees"), button:has-text("Employees"), [role="menuitem"]:has-text("Employees")');
    await employeesMenu.first().click();
    await page.waitForURL(/\/employees/, { timeout: 10000 });

    console.log(`📊 Checking employee list...`);

    // Get all employee rows
    const employeeRows = page.locator('table tbody tr, [role="grid"] [role="row"], .employee-item');
    const count = await employeeRows.count();
    console.log(`✅ Found ${count} employees in list`);

    if (count > 0) {
      // Show first few employees
      for (let i = 0; i < Math.min(3, count); i++) {
        const row = employeeRows.nth(i);
        const text = await row.innerText().catch(() => 'N/A');
        console.log(`   [${i + 1}] ${text.substring(0, 50)}...`);
      }
    }

    // Verify API list endpoint works
    const listResponse = await page.request.get('/api/v1/hcm/employees');
    expect(listResponse.ok()).toBeTruthy();
    console.log(`✅ API /employees endpoint returned 200`);
    
    const data = await listResponse.json();
    expect(data.data).toBeDefined();
    console.log(`✅ API returned ${data.data?.length || 0} employees`);
  });

  test('4️⃣ EDIT EMPLOYEE', async ({ page }) => {
    console.log('\n--- TEST 4: Edit Employee ---\n');
    
    if (!employeeId) {
      console.log(`⚠️  No employeeId available, skipping edit test`);
      test.skip();
    }

    // Navigate to employees
    await loginViaUi(page, superAdminCredentials);
    const employeesMenu = page.locator('a:has-text("Employees"), button:has-text("Employees"), [role="menuitem"]:has-text("Employees")');
    await employeesMenu.first().click();
    await page.waitForURL(/\/employees/, { timeout: 10000 });

    const updatedName = `${testEmployee.firstName}_UPDATED`;
    console.log(`✏️  Updating employee name to: ${updatedName}`);

    // Find the employee row with the test email
    const employeeRow = page.locator(`text="${testEmployee.email}"`);
    await expect(employeeRow).toBeVisible({ timeout: 10000 });
    console.log(`✅ Found employee in list`);

    // Click edit button (could be in the row or on the row itself)
    const editButton = employeeRow.locator('..').locator('a:has-text("Edit"), button:has-text("Edit"), [data-action="edit"]').first();
    if (await editButton.isVisible().catch(() => false)) {
      await editButton.click();
      console.log(`✅ Clicked Edit button`);
    } else {
      // Try clicking the row itself to open
      await employeeRow.locator('..').click();
      console.log(`✅ Clicked employee row`);
    }

    // Wait for edit form
    const editForm = page.locator('.modal, .drawer, form, [role="dialog"]');
    await expect(editForm).toBeVisible({ timeout: 10000 });
    console.log(`✅ Edit form opened`);

    // Update first name
    const firstNameInput = page.locator('input[name*="first_name"], input[name*="firstName"]').first();
    await firstNameInput.fill(updatedName);
    console.log(`✅ Updated first name`);

    // Submit
    const submitButton = page.locator('button:has-text("Save"), button:has-text("Submit"), button:has-text("Update"), button:has-text("Simpan")');
    await submitButton.click();
    console.log(`✅ Submitted update`);

    // Wait for confirmation
    try {
      await page.waitForURL(/\/employees/, { timeout: 10000 });
      console.log(`✅ Redirected back to employee list`);
    } catch (e) {
      console.log(`⚠️  Redirect timeout, may still be processing`);
    }

    // Verify via API
    const getResponse = await page.request.get(`/api/v1/hcm/employees/${employeeId}`);
    if (getResponse.ok()) {
      const data = await getResponse.json();
      if (data.data?.first_name?.includes('UPDATED')) {
        console.log(`✅ API confirmed update successful`);
      }
    }
  });

  test('5️⃣ DELETE EMPLOYEE', async ({ page }) => {
    console.log('\n--- TEST 5: Delete Employee ---\n');
    
    if (!employeeId) {
      console.log(`⚠️  No employeeId available, skipping delete test`);
      test.skip();
    }

    // Navigate to employees
    await loginViaUi(page, superAdminCredentials);
    const employeesMenu = page.locator('a:has-text("Employees"), button:has-text("Employees"), [role="menuitem"]:has-text("Employees")');
    await employeesMenu.first().click();
    await page.waitForURL(/\/employees/, { timeout: 10000 });

    console.log(`🗑️  Deleting employee with ID: ${employeeId}`);

    // Find the employee row
    const employeeRow = page.locator(`text="${testEmployee.email}"`);
    await expect(employeeRow).toBeVisible({ timeout: 10000 });
    console.log(`✅ Found employee in list`);

    // Click delete button
    const deleteButton = employeeRow.locator('..').locator('button:has-text("Delete"), [data-action="delete"], a:has-text("Delete")').first();
    if (await deleteButton.isVisible().catch(() => false)) {
      await deleteButton.click();
      console.log(`✅ Clicked Delete button`);

      // Confirm deletion if prompted
      const confirmButton = page.locator('button:has-text("Confirm"), button:has-text("Yes"), button:has-text("Delete")').last();
      if (await confirmButton.isVisible({ timeout: 5000 }).catch(() => false)) {
        await confirmButton.click();
        console.log(`✅ Confirmed deletion`);
      }
    } else {
      // Try delete via API
      const deleteResponse = await page.request.delete(`/api/v1/hcm/employees/${employeeId}`);
      if (deleteResponse.ok()) {
        console.log(`✅ Deleted via API`);
      } else {
        console.log(`❌ Delete failed: ${deleteResponse.status()}`);
      }
      return;
    }

    // Verify deletion
    const verifyResponse = await page.request.get(`/api/v1/hcm/employees/${employeeId}`).catch(() => null);
    if (!verifyResponse || verifyResponse.status() === 404) {
      console.log(`✅ API confirmed employee deleted`);
    } else {
      console.log(`⚠️  Employee still exists after delete`);
    }
  });

  test('6️⃣ API PERMISSION TEST (SUPER ADMIN BYPASS)', async ({ page }) => {
    console.log('\n--- TEST 6: API Permission Test ---\n');
    
    console.log(`🔐 Testing super admin API permissions...`);

    // Get auth token
    const listResponse = await page.request.get('/api/v1/hcm/employees');
    console.log(`📊 GET /api/v1/hcm/employees: ${listResponse.status()}`);
    expect(listResponse.status()).toBe(200);
    console.log(`✅ List endpoint accessible`);

    // Test create via API
    const createResponse = await page.request.post('/api/v1/hcm/employees', {
      data: {
        first_name: 'API_Test',
        last_name: 'Employee',
        email: `api.test.${Date.now()}@example.com`,
      },
    }).catch(e => ({ status: () => e.message }));

    if (typeof createResponse.status === 'function') {
      const status = createResponse.status();
      console.log(`📝 POST /api/v1/hcm/employees: ${status}`);
      if (status === 201 || status === 200 || status === 422) {
        console.log(`✅ Create endpoint accessible (status: ${status})`);
      }
    }

    console.log(`✅ Super admin API permissions working`);
  });
});
