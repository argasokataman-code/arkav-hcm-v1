import { test, expect } from "@playwright/test";
import { credentials, loginViaUi, logoutIfNeeded } from "../helpers/auth.js";

async function gotoSubscriptions(page) {
  await page.goto("/saas/subscriptions", { waitUntil: "domcontentloaded" });
  await expect(page.locator("[data-saas-subscriptions-page]")).toBeVisible();
  await expect(page.getByRole("heading", { name: "SaaS Subscriptions", exact: true })).toBeVisible();
  await expect(page.locator("[data-subscriptions-list-container]")).toBeVisible();
}

function subscriptionRows(page) {
  return page.locator("[data-subscriptions-list-container] tbody tr:visible");
}

async function confirmArcavDialog(page) {
  await expect(page.locator("#arcav_hcm_confirm_delete")).toBeVisible();
  await page.locator("[data-arcav-confirm-yes]").click();
}

test.describe("Subscriptions manual-like UI coverage", () => {
  test.afterEach(async ({ page }) => {
    await logoutIfNeeded(page);
  });

  test("admin can execute subscriptions flow end-to-end", async ({ page }, testInfo) => {
    await loginViaUi(page, credentials.admin);
    await gotoSubscriptions(page);

    await expect(page.locator("#btn_add_subscription")).toBeVisible();
    await expect(page.locator("#search_subscriptions")).toBeVisible();
    await page.locator("#search_subscriptions").fill("active");
    await page.locator("#search_subscriptions").fill("");
    await page.locator("#filter_status").selectOption("cancelled");
    await page.locator("#btn_reset_filters").click();
    await page.locator("#filter_cycle").selectOption("yearly");
    await page.locator("#btn_reset_filters").click();
    await page.locator("#search_subscriptions").fill("");

    await page.locator("#btn_add_subscription").click();
    await expect(page.locator("#subscriptionModal")).toBeVisible();
    await page.locator("#subscriptionModal .btn-secondary").click();

    await page.screenshot({ path: testInfo.outputPath("subscriptions-admin-final.png"), fullPage: true });
  });

  test("company user is redirected from admin subscriptions page and blocked for mutations", async ({ page }, testInfo) => {
    await loginViaUi(page, credentials.company);
    await page.goto("/saas/subscriptions", { waitUntil: "domcontentloaded" });
    await expect(page).toHaveURL(/\/(employee-dashboard|dashboard|index)(\?.*)?$/);

    const apiResult = await page.evaluate(async () => {
      const tokenResponse = await fetch("/api-token", {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
      });
      const tokenPayload = await tokenResponse.json();
      const token = tokenPayload?.data?.token;

      const response = await fetch("/v1/saas/subscriptions", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        credentials: "same-origin",
        body: JSON.stringify({
          company_id: 1,
          package_id: 1,
          status: "active",
          starts_at: "2026-04-13",
          ends_at: "2026-05-13",
          billing_cycle: "monthly",
        }),
      });

      const payload = await response.json();
      return {
        status: response.status,
        code: payload?.error?.code || null,
      };
    });

    expect(apiResult.status).toBe(403);
    expect(["ADMIN_REQUIRED", "AUTH_FORBIDDEN"]).toContain(apiResult.code);
    await page.screenshot({ path: testInfo.outputPath("subscriptions-company-readonly.png"), fullPage: true });
  });
});