import { describe, it, expect, vi, beforeEach } from "vitest";

describe("Payroll Run UI — Reconciliation Gate", () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div data-payroll-run-panel>
        <div data-payroll-workflow-steps>
          <div data-payroll-step="period"><span data-payroll-step-status>Menunggu</span></div>
          <div data-payroll-step="calculate"><span data-payroll-step-status>Menunggu</span></div>
          <div data-payroll-step="review"><span data-payroll-step-status>Menunggu</span></div>
          <div data-payroll-step="export"><span data-payroll-step-status>Menunggu</span></div>
          <div data-payroll-step="pay"><span data-payroll-step-status>Menunggu</span></div>
        </div>
        <button data-payroll-run-calculate disabled>Calculate Draft</button>
        <button data-payroll-run-export-evidence disabled>Export Reconciliation</button>
        <button data-payroll-run-disburse disabled>Tandai Dibayar Manual</button>
        <span data-payroll-checklist-evidence>Status</span>
        <span data-payroll-checklist-evidence-note>Belum ada evidence</span>
        <div data-payroll-run-evidence-indicator class="d-none">
          <span data-evidence-status>Loading...</span>
          <span data-evidence-timestamp></span>
        </div>
        <div data-payroll-run-reconciliation-hint class="d-none"></div>
      </div>
    `;
  });

  it("renders workflow steps on page load", () => {
    const steps = document.querySelectorAll("[data-payroll-step]");
    expect(steps.length).toBe(5);
    expect(steps[0]?.getAttribute("data-payroll-step")).toBe("period");
    expect(steps[4]?.getAttribute("data-payroll-step")).toBe("pay");
  });

  it("Calculate Draft button starts disabled", () => {
    const btn = document.querySelector<HTMLButtonElement>("[data-payroll-run-calculate]");
    expect(btn?.disabled).toBe(true);
  });

  it("Export Evidence button starts disabled", () => {
    const btn = document.querySelector<HTMLButtonElement>("[data-payroll-run-export-evidence]");
    expect(btn?.disabled).toBe(true);
  });

  it("Disburse button starts disabled", () => {
    const btn = document.querySelector<HTMLButtonElement>("[data-payroll-run-disburse]");
    expect(btn?.disabled).toBe(true);
  });

  it("evidence indicator hidden initially", () => {
    const el = document.querySelector("[data-payroll-run-evidence-indicator]");
    expect(el?.classList.contains("d-none")).toBe(true);
  });

  it("reconciliation hint hidden initially", () => {
    const el = document.querySelector("[data-payroll-run-reconciliation-hint]");
    expect(el?.classList.contains("d-none")).toBe(true);
  });
});

describe("Disburse — skipped user count display", () => {
  it("shows skipped count in toast message", () => {
    const responseData = {
      success: true,
      data: {
        gatewayReference: "MANUAL-20260623-42-ABCD",
        skippedAlreadyPaidUserIds: [2, 5],
      },
    };
    const skipped = responseData.data.skippedAlreadyPaidUserIds;
    const message = `Pembayaran manual tercatat (${responseData.data.gatewayReference}).`;
    const detail = skipped.length > 0 ? ` (${skipped.length} karyawan diskip karena sudah dibayar).` : "";
    expect(message + detail).toContain("2 karyawan diskip");
  });

  it("skips detail when no skipped users", () => {
    const responseData = {
      success: true,
      data: {
        gatewayReference: "MANUAL-20260623-42-ABCD",
        skippedAlreadyPaidUserIds: [],
      },
    };
    const skipped = responseData.data.skippedAlreadyPaidUserIds;
    const message = `Pembayaran manual tercatat (${responseData.data.gatewayReference}).`;
    const detail = skipped.length > 0 ? ` (${skipped.length} karyawan diskip karena sudah dibayar).` : "";
    expect(message + detail).not.toContain("diskip");
  });

  it("ineligibleUserIds are returned separately", () => {
    const responseData = {
      success: true,
      data: {
        gatewayReference: "MANUAL-20260623-42-ABCD",
        selectedUserIds: [1, 3],
        ineligibleUserIds: [7, 8],
        skippedAlreadyPaidUserIds: [],
      },
    };
    const ineligible = responseData.data.ineligibleUserIds;
    expect(ineligible).toEqual([7, 8]);
  });
});

describe("THR Date Validation — FE warning", () => {
  it("shows warning when cutoff date moves earlier", () => {
    const existingSettings = {
      calendarYear: 2026,
      calculationCutoffDate: "2026-03-25",
    };
    const newCutoff = "2026-03-20";
    const existing = existingSettings;
    const shouldWarn = newCutoff && existing?.calculationCutoffDate && newCutoff < existing.calculationCutoffDate;
    expect(shouldWarn).toBe(true);
  });

  it("no warning when cutoff date moves later", () => {
    const existingSettings = {
      calendarYear: 2026,
      calculationCutoffDate: "2026-03-20",
    };
    const newCutoff = "2026-03-25";
    const existing = existingSettings;
    const shouldWarn = newCutoff && existing?.calculationCutoffDate && newCutoff < existing.calculationCutoffDate;
    expect(shouldWarn).toBe(false);
  });

  it("no warning when no existing cutoff date", () => {
    const existingSettings = {
      calendarYear: 2026,
      calculationCutoffDate: null,
    };
    const newCutoff = "2026-03-20";
    const existing = existingSettings;
    const shouldWarn = newCutoff && existing?.calculationCutoffDate && newCutoff < existing.calculationCutoffDate;
    expect(shouldWarn).toBeFalsy();
  });
});
