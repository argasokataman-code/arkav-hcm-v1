import { test, expect } from "@playwright/test";
import { credentials, loginViaUi } from "../../helpers/auth.js";
import { employeeFixtures } from "../../fixtures/employee.fixture.js";
import { EmployeePage } from "../../pages/employee.page.js";

test.describe("FE Automation | Employee CRUD | Super Admin", () => {
  test("super admin can create, read, update and inactivate employee", async ({ page }) => {
    const employeePage = new EmployeePage(page);

    await loginViaUi(page, credentials.admin, {
      expectedUrlRegex: /\/(index|dashboard|employee-dashboard)(\?.*)?$/,
    });

    const authMeResponse = await page.request.get("/v1/identity/auth/me");
    expect(authMeResponse.ok()).toBeTruthy();
    const authPayload = await authMeResponse.json();

    expect(authPayload?.data?.hcmGlobalAdmin, "User must be global admin").toBeTruthy();
    expect(authPayload?.data?.permissions?.["employee.view"], "employee.view permission is required").toBeTruthy();
    expect(authPayload?.data?.permissions?.["employee.create"], "employee.create permission is required").toBeTruthy();
    const hasUpdatePermission =
      authPayload?.data?.permissions?.["employee.update"] || authPayload?.data?.permissions?.["employee.edit"];
    expect(hasUpdatePermission, "employee.update or employee.edit permission is required").toBeTruthy();

    const masterChecks = [
      { name: "departments", endpoint: "/v1/hcm/departments" },
      { name: "designations", endpoint: "/v1/hcm/designations" },
      { name: "wilayah provinces", endpoint: "/v1/hcm/wilayah/provinces" },
    ];

    for (const master of masterChecks) {
      const response = await page.request.get(master.endpoint);
      expect(response.ok(), `${master.name} endpoint must be reachable`).toBeTruthy();
      const payload = await response.json();
      expect(payload?.success, `${master.name} response success must be true`).toBeTruthy();
      expect(Array.isArray(payload?.data), `${master.name} data must be an array`).toBeTruthy();
      expect(payload.data.length, `${master.name} must have at least one record for employee create flow`).toBeGreaterThan(0);
    }

    await employeePage.goto();

    await employeePage.openAddModal();
    await employeePage.createEmployee(employeeFixtures.create);

    await employeePage.searchByKeyword(employeeFixtures.create.email);
    await employeePage.expectRowVisible(employeeFixtures.create.email);

    await employeePage.openDetailFromRow(employeeFixtures.create.email);
    await employeePage.expectDetailContainsEmail(employeeFixtures.create.email);

    await employeePage.goto();
    await employeePage.searchByKeyword(employeeFixtures.create.email);
    await employeePage.openEditFromRow(employeeFixtures.create.email);
    await employeePage.updateEmployeeTeamAndStatus(employeeFixtures.update);

    await employeePage.searchByKeyword(employeeFixtures.create.email);
    await employeePage.expectRowVisible(employeeFixtures.update.team);
    await expect(employeePage.rowByText(employeeFixtures.create.email)).toContainText(/inactive/i, { timeout: 15000 });
  });
});
