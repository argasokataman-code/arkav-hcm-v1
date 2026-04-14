import { expect } from "@playwright/test";

export const credentials = {
  admin: {
    email: process.env.PW_ADMIN_EMAIL || "qa.login@example.com",
    password: process.env.PW_ADMIN_PASSWORD || "StrongPass1",
  },
  company: {
    email: process.env.PW_COMPANY_EMAIL || "demo.owner01@example.com",
    password: process.env.PW_COMPANY_PASSWORD || "StrongPass1",
    companyCode: process.env.PW_COMPANY_CODE || "demo_co_01",
  },
};

export async function loginViaUi(page, user, options = {}) {
  await page.goto("/login", { waitUntil: "domcontentloaded" });
  await expect(page.locator("#api-login-form")).toBeVisible();

  if (options.companyMode) {
    await page.locator("#login_mode_company").check();
    await page.locator("#login-company-code").fill(options.companyCode || credentials.company.companyCode);
  }

  await page.locator("#login-email").fill(user.email);
  await page.locator("#login-password").fill(user.password);
  await page.locator("#login-submit").click();

  await page.waitForURL(/\/index$/, { timeout: 15000 });
  await expect(page).toHaveURL(/\/index$/);
}

export async function logoutIfNeeded(page) {
  await page.context().clearCookies();
}