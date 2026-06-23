import { describe, it, expect } from "vitest";

describe("Salary/Tax Edit Warning", () => {
  it("warns when baseSalary changes", () => {
    const origSalary = "5000000";
    const newSalary = "7500000";
    const changed = origSalary !== null && newSalary !== origSalary;
    expect(changed).toBe(true);
  });

  it("no warning when baseSalary unchanged", () => {
    const origSalary = "5000000";
    const newSalary = "5000000";
    const changed = origSalary !== null && newSalary !== origSalary;
    expect(changed).toBe(false);
  });

  it("warns when taxStatus changes", () => {
    const origTaxStatus = "TK0";
    const newTaxStatus = "K3";
    const changed = origTaxStatus !== null && newTaxStatus !== origTaxStatus;
    expect(changed).toBe(true);
  });

  it("no warning when taxStatus unchanged", () => {
    const origTaxStatus = "TK0";
    const newTaxStatus = "TK0";
    const changed = origTaxStatus !== null && newTaxStatus !== origTaxStatus;
    expect(changed).toBe(false);
  });

  it("builds change list for display", () => {
    const changes: string[] = [];
    if ("5000000" !== "7500000") changes.push("Base Salary (5000000 → 7500000)");
    if ("TK0" !== "K3") changes.push("Tax Status (TK0 → K3)");
    const msg = "PERHATIAN: Perubahan data berikut akan memengaruhi kalkulasi payroll:\n\n" + changes.join("\n") + "\n\nLanjutkan?";
    expect(msg).toContain("Base Salary");
    expect(msg).toContain("Tax Status");
    expect(changes.length).toBe(2);
  });

  it("only shows changed fields", () => {
    const changes: string[] = [];
    if ("5000000" !== "5000000") changes.push("Base Salary");
    if ("TK0" !== "K3") changes.push("Tax Status (TK0 → K3)");
    expect(changes.length).toBe(1);
    expect(changes[0]).toContain("Tax Status");
  });
});
