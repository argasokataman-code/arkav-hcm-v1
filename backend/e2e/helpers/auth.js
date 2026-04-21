import { expect } from "@playwright/test";

const runId = process.env.PW_RUN_ID || Date.now().toString(36);

export const credentials = {
  admin: {
    email: process.env.PW_ADMIN_EMAIL || "qa.login@example.com",
    password: process.env.PW_ADMIN_PASSWORD || "StrongPass1",
  },
  company: {
    email: process.env.PW_COMPANY_EMAIL || `company.viewer.${runId}@example.com`,
    password: process.env.PW_COMPANY_PASSWORD || "StrongPass1",
    companyCode: process.env.PW_COMPANY_CODE || "demo_co_01",
  },
};

async function ensureRegularUserExists(page, user) {
  await page.request.post("/v1/identity/auth/register", {
    data: {
      name: user.name || "Eee User",
      email: user.email,
      password: user.password,
      confirmPassword: user.password,
    },
  });
}

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

  const homeRegex = /\/(index|dashboard|employee-dashboard)(\?.*)?$/;
  const expectedUrlRegex = options.expectedUrlRegex || homeRegex;
  const reachedExpectedPageAfterFirstTry = await page.waitForURL(expectedUrlRegex, { timeout: 5000 }).then(() => true).catch(() => false);

  const invalidCredentials = page.locator("#login-error:not(.d-none)");
  if (!reachedExpectedPageAfterFirstTry && !options.companyMode && (await invalidCredentials.isVisible().catch(() => false))) {
    await ensureRegularUserExists(page, user);
    await page.locator("#login-email").fill(user.email);
    await page.locator("#login-password").fill(user.password);
    await page.locator("#login-submit").click();
  }

  await page.waitForURL(expectedUrlRegex, { timeout: 20000 });
  await expect(page).toHaveURL(expectedUrlRegex);
}

export async function logoutIfNeeded(page) {
  await page.context().clearCookies();
}