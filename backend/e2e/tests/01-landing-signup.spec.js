import { test, expect } from '@playwright/test';

test.describe('1. LANDING → SIGN UP (CREATE COMPANY)', () => {
  const testCompany = {
    name: 'PT Maju Jaya Test',
    email: 'admin@majujaya-test.com',
    password: 'StrongPass123!',
    package: 'basic'
  };

  test('Happy Path - Valid company registration', async ({ page }) => {
    // Step 1: Navigate to registration page
    await page.goto('/register');

    // Verify form elements are present
    await expect(page.locator('input[name="company_name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('select[name="package"]')).toBeVisible();

    // Step 2: Fill form with valid data
    await page.fill('input[name="company_name"]', testCompany.name);
    await page.fill('input[name="email"]', testCompany.email);
    await page.fill('input[name="password"]', testCompany.password);
    await page.selectOption('select[name="package"]', testCompany.package);

    // Step 3: Submit form
    await page.click('button[type="submit"]');

    // Step 4: Verify success
    await expect(page).toHaveURL(/dashboard|onboarding/);
    await expect(page.locator('.success-message')).toContainText('Company successfully created');
  });

  test('Negative Case - Empty required fields', async ({ page }) => {
    await page.goto('/register');

    // Leave fields empty and submit
    await page.click('button[type="submit"]');

    // Verify inline validation
    await expect(page.locator('input[name="company_name"] + .error')).toContainText('This field is required');
    await expect(page.locator('input[name="email"] + .error')).toContainText('This field is required');
    await expect(page.locator('input[name="password"] + .error')).toContainText('This field is required');
  });
});