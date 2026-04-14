import { test, expect } from "@playwright/test";
import { credentials, loginViaUi, logoutIfNeeded } from "../helpers/auth.js";

async function gotoSubscriptions(page) {
  await page.goto("/saas/subscriptions", { waitUntil: "domcontentloaded" });
  await expect(page.locator("[data-saas-subscriptions-page]")).toBeVisible();
  await expect(page.getByRole("heading", { name: "SaaS Subscriptions", exact: true })).toBeVisible();
  await expect(page.locator("[data-subscriptions-list-container] table")).toBeVisible();
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
    await expect(subscriptionRows(page).first()).toBeVisible();

    const firstRow = subscriptionRows(page).first();
    const firstCompany = (await firstRow.locator("td").nth(0).textContent())?.trim() || "";
    await expect(firstRow).toBeVisible();

    await page.locator("#search_subscriptions").fill(firstCompany);
    await expect(subscriptionRows(page).first().locator("td").nth(0)).toContainText(firstCompany);

    await page.locator("#search_subscriptions").fill("");
    await page.locator("#filter_status").selectOption("cancelled");
    await expect(
      page.locator("[data-subscriptions-list-container] tbody tr td:nth-child(3) span", { hasText: "cancelled" }).first()
    ).toBeVisible();

    await page.locator("#btn_reset_filters").click();
    await page.locator("#filter_cycle").selectOption("yearly");
    await expect(subscriptionRows(page).first()).toBeVisible();
    await page.locator("#btn_reset_filters").click();
    await page.locator("#search_subscriptions").fill("");
    await expect(subscriptionRows(page).first()).toBeVisible();

    await page.locator("#btn_add_subscription").click();
    await expect(page.locator("#subscriptionModal")).toBeVisible();
    await page.locator("#input_subscription_company").selectOption({ index: 1 });
    await page.locator("#input_subscription_package").selectOption({ index: 1 });
    await page.locator("#input_subscription_start").fill("2026-04-13");
    await page.locator("#input_subscription_cycle").selectOption("monthly");
    await page.locator("#subscriptionForm button[type='submit']").click();
    await expect(page.locator(".alert-success", { hasText: "Subscription created successfully" }).first()).toBeVisible();
    await expect(subscriptionRows(page).first().locator("td").nth(2)).toContainText("active");

    await subscriptionRows(page).first().locator("[data-edit-subscription]").click();
    await expect(page.locator("#subscriptionModalTitle")).toContainText("Edit Subscription");
    await page.locator("#input_subscription_cycle").selectOption("yearly");
    await page.locator("#subscriptionForm button[type='submit']").click();
    await expect(page.locator(".alert-success", { hasText: "Subscription updated successfully" }).first()).toBeVisible();

    await subscriptionRows(page).first().locator("[data-edit-subscription]").click();
    await expect(page.locator("#input_subscription_cycle")).toHaveValue("yearly");
    await page.locator("#subscriptionModal .btn-secondary").click();

    page.once("dialog", async (dialog) => {
      await dialog.accept();
    });
    await subscriptionRows(page).first().locator("[data-view-subscription]").click();

    await expect(page.locator("[data-cancel-subscription]").first()).toBeVisible();
    await page.locator("[data-cancel-subscription]").first().click();
    await confirmArcavDialog(page);
    await expect(page.locator(".alert-success", { hasText: "Subscription cancelled successfully" }).first()).toBeVisible();
    await expect(subscriptionRows(page).first().locator("td").nth(2)).toContainText("cancelled");

    await subscriptionRows(page).first().locator("[data-delete-subscription]").click();
    await confirmArcavDialog(page);
    await expect(page.locator(".alert-success", { hasText: "Subscription deleted successfully" }).first()).toBeVisible();

    await page.screenshot({ path: testInfo.outputPath("subscriptions-admin-final.png"), fullPage: true });
  });

  test("company user sees read-only subscriptions UI and blocked mutations", async ({ page }, testInfo) => {
    await loginViaUi(page, credentials.company);
    await gotoSubscriptions(page);

    await expect(page.locator("[data-subscription-readonly-notice]")).toBeVisible();
    await expect(page.locator("#btn_add_subscription")).toBeHidden();
    await expect(page.locator("[data-edit-subscription]")).toHaveCount(0);
    await expect(page.locator("[data-cancel-subscription]")).toHaveCount(0);
    await expect(page.locator("[data-delete-subscription]")).toHaveCount(0);
    await expect(page.locator("[data-view-subscription]").first()).toBeVisible();

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
    expect(apiResult.code).toBe("ADMIN_REQUIRED");
    await page.screenshot({ path: testInfo.outputPath("subscriptions-company-readonly.png"), fullPage: true });
  });
});