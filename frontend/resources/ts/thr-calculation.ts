/**
 * THR calculation — mirror backend ThrProRataCalculator (Permenaker 6/2016 pro rata).
 * Money: round to 2 decimals via integer hundredths to reduce float noise.
 */

export type ThrCalcStatus = "full" | "pro_rata" | "nihil" | "invalid";

export interface ThrCalcResult {
    status: ThrCalcStatus;
    monthsOfService: number;
    multiplier: number;
    referenceMonthlyWage: number;
    thrGross: number;
}

function parseYmd(ymd: string): { y: number; m: number; d: number } | null {
    const p = ymd.trim().split("-");
    if (p.length !== 3) {
        return null;
    }
    const y = parseInt(p[0], 10);
    const m = parseInt(p[1], 10);
    const d = parseInt(p[2], 10);
    if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) {
        return null;
    }
    return { y, m, d };
}

/** UTC date compare (calendar dates only). */
function ymdToUtcMs(ymd: string): number | null {
    const p = parseYmd(ymd);
    if (!p) {
        return null;
    }
    return Date.UTC(p.y, p.m - 1, p.d);
}

export function wholeMonthsBetween(joinYmd: string, cutoffYmd: string): number {
    const j = parseYmd(joinYmd);
    const c = parseYmd(cutoffYmd);
    if (!j || !c) {
        return -1;
    }
    const jMs = Date.UTC(j.y, j.m - 1, j.d);
    const cMs = Date.UTC(c.y, c.m - 1, c.d);
    if (cMs < jMs) {
        return -1;
    }
    let months = (c.y - j.y) * 12 + (c.m - j.m);
    if (c.d < j.d) {
        months -= 1;
    }
    return Math.max(0, months);
}

export function roundMoney2(n: number): number {
    return Math.round(n * 100) / 100;
}

export function computeThr(
    joinYmd: string,
    cutoffYmd: string,
    baseMonthlySalary: number,
    fixedMonthlyAllowance: number,
): ThrCalcResult {
    const jMs = ymdToUtcMs(joinYmd);
    const cMs = ymdToUtcMs(cutoffYmd);
    const base = Math.max(0, baseMonthlySalary);
    const fixed = Math.max(0, fixedMonthlyAllowance);
    const reference = roundMoney2(base + fixed);

    if (jMs === null || cMs === null || cMs < jMs) {
        return {
            status: "invalid",
            monthsOfService: 0,
            multiplier: 0,
            referenceMonthlyWage: reference,
            thrGross: 0,
        };
    }

    const m = wholeMonthsBetween(joinYmd, cutoffYmd);
    if (m < 1) {
        return {
            status: "nihil",
            monthsOfService: m,
            multiplier: 0,
            referenceMonthlyWage: reference,
            thrGross: 0,
        };
    }
    if (m >= 12) {
        return {
            status: "full",
            monthsOfService: m,
            multiplier: 1,
            referenceMonthlyWage: reference,
            thrGross: roundMoney2(reference),
        };
    }
    const thr = roundMoney2((reference * m) / 12);
    return {
        status: "pro_rata",
        monthsOfService: m,
        multiplier: Math.round((m / 12) * 1e6) / 1e6,
        referenceMonthlyWage: reference,
        thrGross: thr,
    };
}
