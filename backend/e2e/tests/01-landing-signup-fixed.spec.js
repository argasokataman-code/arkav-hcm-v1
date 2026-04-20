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

    // Verify we're on the registration page
    await expect(page.locator('text=Sign Up')).toBeVisible();

    // Step 2: Fill form with valid data using positional selectors
    const inputs = page.locator('input[type="text"], input[type="password"]');
    const nameInput = inputs.nth(0); // Name field
    const emailInput = inputs.nth(1); // Email field
    const passwordInput = page.locator('input.pass-input'); // Password field
    const confirmPasswordInput = page.locator('input.pass-inputs'); // Confirm password field

    await nameInput.fill(testCompany.name);
    await emailInput.fill(testCompany.email);
    await passwordInput.fill(testCompany.password);
    await confirmPasswordInput.fill(testCompany.password);

    // Step 3: Submit form
    await page.click('button[type="submit"], button:has-text("Sign Up")');

    // Step 4: Verify success - wait for navigation or success message
    await page.waitForTimeout(2000); // Wait for potential redirect

    // Check if we're redirected to dashboard or still on register page with success message
    const currentURL = page.url();
    if (currentURL.includes('dashboard') || currentURL.includes('onboarding')) {
      console.log('✅ Registration successful - redirected to:', currentURL);
    } else {
      // Check for success message on current page
      const successMessage = page.locator('text=successfully, .alert-success, .success-message');
      await expect(successMessage.or(page.locator('text=Welcome')).or(page.locator('text=created'))).toBeVisible();
    }
  });

  test('Negative Case - Empty required fields', async ({ page }) => {
    await page.goto('/register');

    // Verify we're on the registration page
    await expect(page.locator('text=Sign Up')).toBeVisible();

    // Leave fields empty and submit
    await page.click('button[type="submit"], button:has-text("Sign Up")');

    // Verify validation errors appear
    await page.waitForTimeout(1000);
    const errorMessages = page.locator('.error, .invalid-feedback, text=required, text=This field');
    await expect(errorMessages.first()).toBeVisible();
  });
});