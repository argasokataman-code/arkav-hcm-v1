import { expect } from "@playwright/test";

export class EmployeePage {
  constructor(page) {
    this.page = page;
    this.searchInput = page.locator("[data-employees-search]");
    this.listBody = page.locator("[data-employees-list-body]");
    this.addModal = page.locator("#add_employee");
    this.editModal = page.locator("#edit_employee");
    this.addForm = page.locator("[data-employee-add-form]");
    this.editForm = page.locator("[data-employee-edit-form]");
  }

  async goto() {
    await this.page.goto("/employees", { waitUntil: "networkidle", timeout: 45000 });
    await expect(this.searchInput).toBeVisible({ timeout: 15000 });
    await expect(this.listBody).toBeVisible({ timeout: 15000 });
  }

  async openAddModal() {
    await this.page.getByRole("link", { name: /add employee/i }).click();
    await expect(this.addModal).toBeVisible({ timeout: 10000 });
  }

  async createEmployee(data) {
    await this.fillCreateStep1(data);
    await this.nextStepAndWait(1);

    await this.fillCreateStep2(data);
    await this.nextStepAndWait(2);

    await this.fillCreateStep3(data);
    await this.nextStepAndWait(3);

    await this.fillCreateStep4(data);
    await this.nextStepAndWait(4);

    await this.fillCreateStep5(data);

    const createRespPromise = this.page.waitForResponse((response) => {
      return response.url().includes("/v1/hcm/employees") && response.request().method() === "POST";
    }, { timeout: 20000 });

    await this.addForm.locator("[data-employee-step-submit]").click();
    const createResp = await createRespPromise;

    if (createResp.status() !== 201) {
      let payload = null;
      try {
        payload = await createResp.json();
      } catch (_error) {
        payload = null;
      }
      throw new Error(
        `Create employee failed (${createResp.status()}): ${JSON.stringify(payload || {})}`,
      );
    }

    await expect(this.addModal).toBeHidden({ timeout: 15000 });
  }

  async searchByKeyword(keyword) {
    await expect(this.searchInput).toBeVisible({ timeout: 10000 });
    await this.searchInput.fill(keyword);
    await this.page.waitForTimeout(800);
  }

  rowByText(text) {
    return this.page.locator("[data-employees-list-body] tr", { hasText: text }).first();
  }

  async expectRowVisible(text) {
    await expect(this.rowByText(text)).toBeVisible({ timeout: 15000 });
  }

  async openEditFromRow(text) {
    const row = this.rowByText(text);
    await expect(row).toBeVisible({ timeout: 15000 });
    await row.locator("[data-employee-edit-open]").click();
    await expect(this.editModal).toBeVisible({ timeout: 10000 });
  }

  async updateEmployeeTeamAndStatus(update) {
    await this.editForm.locator("[data-employee-step-trigger='1']").click();
    await this.editForm.locator('[data-employee-edit-field="team"]').fill(update.team);
    await this.editForm.locator('[data-employee-edit-field="employmentStatus"]').selectOption(update.employmentStatus);

    await this.editForm.locator("[data-employee-step-trigger='4']").click();

    const updateRespPromise = this.page.waitForResponse((response) => {
      return response.url().includes("/v1/hcm/employees/") && response.request().method() === "PUT";
    }, { timeout: 20000 });

    await this.editForm.locator("[data-employee-step-submit]").click();
    const updateResp = await updateRespPromise;
    expect(updateResp.status(), "Update employee API should succeed").toBe(200);
    await expect(this.editModal).toBeHidden({ timeout: 15000 });
  }

  async openDetailFromRow(text) {
    const row = this.rowByText(text);
    await expect(row).toBeVisible({ timeout: 15000 });
    await row.locator("[data-employee-detail-link]").first().click();
    await this.page.waitForURL(/\/employee-details\?id=\d+/, { timeout: 15000 });
  }

  async expectDetailContainsEmail(email) {
    await expect(this.page.locator("[data-employee-email]")).toContainText(email, { timeout: 15000 });
  }

  async fillCreateStep1(data) {
    await this.addForm.locator('[data-employee-add-field="name"]').fill(data.fullName);
    await this.addForm.locator('[data-employee-add-field="email"]').fill(data.email);
    await this.addForm.locator('[data-employee-add-field="password"]').fill(data.password);
    await this.addForm.locator('[data-employee-add-field="confirmPassword"]').fill(data.password);
    await this.addForm.locator('[data-employee-add-field="phone"]').fill(data.phone);
    await this.addForm.locator('[data-employee-add-field="nik"]').fill(data.nik);
    await this.addForm.locator('[data-employee-add-field="placeOfBirth"]').fill(data.placeOfBirth);
    await this.addForm.locator('[data-employee-add-field="dateOfBirth"]').fill(data.dateOfBirth);
    await this.addForm.locator('[data-employee-add-field="gender"]').selectOption(data.gender);
    await this.addForm.locator('[data-employee-add-field="maritalStatus"]').selectOption(data.maritalStatus);
    await this.addForm.locator('[data-employee-add-field="religion"]').selectOption(data.religion);

    const provinceSelect = this.addForm.locator("[data-employee-wilayah-province]");
    const regencySelect = this.addForm.locator("[data-employee-wilayah-regency]");
    const districtSelect = this.addForm.locator("[data-employee-wilayah-district]");
    const villageSelect = this.addForm.locator("[data-employee-wilayah-village]");

    const provinceValue = await this.selectFirstNonEmpty(provinceSelect, "province");
    await expect(regencySelect).toBeEnabled({ timeout: 10000 });
    const regencyValue = await this.selectFirstNonEmpty(regencySelect, `regency for province ${provinceValue}`);
    await expect(districtSelect).toBeEnabled({ timeout: 10000 });
    const districtValue = await this.selectFirstNonEmpty(districtSelect, `district for regency ${regencyValue}`);
    await expect(villageSelect).toBeEnabled({ timeout: 10000 });
    await this.selectFirstNonEmpty(villageSelect, `village for district ${districtValue}`);
  }

  async fillCreateStep2(data) {
    await this.addForm.locator('[data-employee-add-field="team"]').fill(data.team);

    const departmentSelect = this.addForm.locator('[data-employee-add-field="departmentId"]');
    const designationSelect = this.addForm.locator('[data-employee-add-field="designationId"]');
    const departmentValue = await this.selectFirstNonEmpty(departmentSelect, "department");
    await expect(designationSelect).toBeEnabled({ timeout: 10000 });
    await this.selectFirstNonEmpty(designationSelect, `designation for department ${departmentValue}`);

    await this.addForm.locator('[data-employee-add-field="employeeType"]').selectOption(data.employeeType);
  }

  async fillCreateStep3(data) {
    await this.addForm.locator('[data-employee-add-field="baseSalary"]').fill(data.baseSalary);
    await this.addForm.locator('[data-employee-add-field="fixedAllowance"]').fill(data.fixedAllowance);
    await this.addForm.locator('[data-employee-add-field="salaryType"]').selectOption(data.salaryType);
    await this.addForm.locator('[data-employee-add-field="contractType"]').selectOption(data.contractType);
    await this.addForm.locator('[data-employee-add-field="contractStartDate"]').fill(data.contractStartDate);
    await this.addForm.locator('[data-employee-add-field="contractStatus"]').selectOption(data.contractStatus);
  }

  async fillCreateStep4(data) {
    await this.selectFirstNonEmpty(this.addForm.locator('[data-employee-add-field="bankName"]'), "bankName");
    await this.addForm.locator('[data-employee-add-field="bankAccountNo"]').fill(data.bankAccountNo);
    await this.addForm.locator('[data-employee-add-field="bankAccountHolderName"]').fill(data.bankAccountHolderName);
  }

  async fillCreateStep5(data) {
    const firstEmergencyRow = this.addForm.locator('[data-employee-repeatable="emergencyContacts"] [data-repeat-row]').first();
    await expect(firstEmergencyRow).toBeVisible({ timeout: 10000 });
    await firstEmergencyRow.locator('[data-repeat-key="name"]').fill(data.emergencyName);
    await firstEmergencyRow.locator('[data-repeat-key="relationship"]').fill(data.emergencyRelationship);
    await firstEmergencyRow.locator('[data-repeat-key="phone"]').fill(data.emergencyPhone);
  }

  async nextStepAndWait(expectedIndex) {
    await this.addForm.locator("[data-employee-step-next]").click();
    await expect(this.addForm).toHaveAttribute("data-employee-step-index", String(expectedIndex), { timeout: 10000 });
  }

  async selectFirstNonEmpty(selectLocator, contextLabel = "select field") {
    await expect
      .poll(async () => {
        return selectLocator.locator("option").count();
      }, { timeout: 20000 })
      .toBeGreaterThan(1);

    const value = await selectLocator.locator("option").nth(1).getAttribute("value");
    expect(value && value !== "", `Expected non-empty option value for ${contextLabel}`).toBeTruthy();
    await selectLocator.selectOption(value);
    return value;
  }
}
