import { test, expect } from "@playwright/test";
import { credentials, loginViaUi, logoutIfNeeded } from "../helpers/auth.js";

function salaryRows(page) {
  return page.locator("[data-hcm-employee-salary-body] tr:visible");
}

async function gotoEmployeeSalary(page) {
  await page.goto("/employee-salary", { waitUntil: "domcontentloaded" });
}

test.describe("Employee salary UI role coverage", () => {
  test.afterEach(async ({ page }) => {
    await logoutIfNeeded(page);
  });

  test("admin can search, filter, and edit compensation", async ({ page }, testInfo) => {
    await loginViaUi(page, credentials.admin);
    await gotoEmployeeSalary(page);

    await expect(page.getByRole("heading", { name: "Gaji karyawan", exact: true })).toBeVisible();
    await expect(salaryRows(page).first()).toBeVisible();

    const firstRow = salaryRows(page).first();
    const firstName = (await firstRow.locator("td").nth(1).innerText()).trim();
    await expect(firstName.length).toBeGreaterThan(0);

    await page.locator("[data-hcm-employee-salary-search]").fill(firstName);
    await expect(salaryRows(page).first().locator("td").nth(1)).toContainText(firstName);

    await page.locator("[data-hcm-employee-salary-search]").fill("");
    await page.locator("[data-hcm-employee-salary-status]").selectOption("active");
    await expect(salaryRows(page).first()).toBeVisible();

    const editButton = salaryRows(page).first().locator("[data-hcm-employee-salary-edit]");
    await expect(editButton).toBeVisible();
    await editButton.click();

    const modal = page.locator("#arcav_employee_salary_compensation_modal");
    await expect(modal).toBeVisible();

    const baseField = modal.locator('[data-hcm-field="baseSalary"]');
    const oldBase = Number((await baseField.inputValue()) || "0");
    const newBase = Math.max(0, oldBase + 1000);
    await baseField.fill(String(newBase));

    await modal.locator("[data-hcm-employee-salary-submit]").click();
    await expect(page.locator(".alert-success", { hasText: "Data gaji disimpan." }).first()).toBeVisible();
    await expect(modal).toBeHidden();

    // Verify persisted value via reopening the same row modal.
    await editButton.click();
    await expect(modal).toBeVisible();
    await expect(baseField).toHaveValue(String(newBase));

    // Roll back to original salary to keep test data stable across reruns.
    await baseField.fill(String(oldBase));
    await modal.locator("[data-hcm-employee-salary-submit]").click();
    await expect(page.locator(".alert-success", { hasText: "Data gaji disimpan." }).first()).toBeVisible();
    await expect(modal).toBeHidden();

    await page.screenshot({ path: testInfo.outputPath("employee-salary-admin.png"), fullPage: true });
  });

  test("company user is redirected from employee salary page", async ({ page }, testInfo) => {
    await loginViaUi(page, credentials.company, {
      companyMode: true,
      companyCode: credentials.company.companyCode,
    });
    await gotoEmployeeSalary(page);

    await page.waitForURL(/\/employee-dashboard$/, { timeout: 15000 });
    await expect(page).toHaveURL(/\/employee-dashboard$/);
    await page.screenshot({ path: testInfo.outputPath("employee-salary-company-redirect.png"), fullPage: true });
  });
});