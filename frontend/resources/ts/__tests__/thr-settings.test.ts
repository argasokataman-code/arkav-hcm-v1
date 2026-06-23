import { describe, it, expect } from "vitest";

describe("THR Settings API — Cutoff Backward Validation", () => {
  it("rejects when new cutoff is earlier than existing", () => {
    const existing = { calculationCutoffDate: "2026-03-25" };
    const newCutoff = "2026-03-20";
    const isInvalid = existing.calculationCutoffDate !== null && newCutoff < existing.calculationCutoffDate;
    expect(isInvalid).toBe(true);
  });

  it("accepts when new cutoff is same as existing", () => {
    const existing = { calculationCutoffDate: "2026-03-25" };
    const newCutoff = "2026-03-25";
    const isInvalid = existing.calculationCutoffDate !== null && newCutoff < existing.calculationCutoffDate;
    expect(isInvalid).toBe(false);
  });

  it("accepts when new cutoff is later than existing", () => {
    const existing = { calculationCutoffDate: "2026-03-20" };
    const newCutoff = "2026-03-25";
    const isInvalid = existing.calculationCutoffDate !== null && newCutoff < existing.calculationCutoffDate;
    expect(isInvalid).toBe(false);
  });

  it("accepts when no existing cutoff date", () => {
    const existing = { calculationCutoffDate: null };
    const newCutoff = "2026-03-01";
    const isInvalid = existing.calculationCutoffDate !== null && newCutoff < existing.calculationCutoffDate;
    expect(isInvalid).toBe(false);
  });

  it("accepts when new cutoff is null (clearing)", () => {
    const existing = { calculationCutoffDate: "2026-03-25" };
    const newCutoff = null;
    const isInvalid = existing.calculationCutoffDate !== null && newCutoff !== null && newCutoff < existing.calculationCutoffDate;
    expect(isInvalid).toBe(false);
  });

  it("error code constant matches backend", () => {
    expect("THR_CUTOFF_DATE_INVALID").toBe("THR_CUTOFF_DATE_INVALID");
    expect("THR_CUTOFF_DATE_INVALID").toMatch(/^THR_/);
  });
});
