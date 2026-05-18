import {
    EmployeeRow,
    PayrollLine,
    PayrollPolicySnapshot,
    PayrollSettings,
    PayrollTaxAnomalies,
    PayrollWorkArrangement,
    PayrollWorkProfile,
    TaxGovernancePolicySnapshot,
    TenantContextSnapshot,
} from "./types";

export const workConfigState: {
    profiles: PayrollWorkProfile[];
    arrangements: PayrollWorkArrangement[];
    users: Array<{ id: number; name: string; email?: string | null }>;
} = {
    profiles: [],
    arrangements: [],
    users: [],
};

export const payrollRunState: {
    currentPeriodId: number | null;
    currentRunId: number | null;
    currentRunStatus: string | null;
    currentPolicySnapshot: PayrollPolicySnapshot | null;
    currentTaxGovernancePolicy: TaxGovernancePolicySnapshot | null;
    activeTenantContext: TenantContextSnapshot;
    currentRows: EmployeeRow[];
    currentTaxAnomalies: PayrollTaxAnomalies;
    loading: boolean;
    reconciliationDownloadedForRunId: number | null;
} = {
    currentPeriodId: null,
    currentRunId: null,
    currentRunStatus: null,
    currentPolicySnapshot: null,
    currentTaxGovernancePolicy: null,
    activeTenantContext: {
        companyId: null,
        companyName: null,
    },
    currentRows: [],
    currentTaxAnomalies: {
        missingTaxProfileUserCount: 0,
        missingTaxProfileUserIds: [],
    },
    loading: false,
    reconciliationDownloadedForRunId: null,
};

export const payrollSettingsState: {
    settings: PayrollSettings | null;
} = {
    settings: null,
};

export const POST_CUTOFF_REVIEW_ONLY_HINT = "Mode post-cutoff saat ini bersifat review-only. Calculate Draft tetap boleh untuk cek data, tetapi export dan penandaan pembayaran manual menunggu tenggat payday sesuai policy run aktif.";

export function currentRunStatus(): string {
    return String(payrollRunState.currentRunStatus || "").toLowerCase();
}

export function normalizeTaxAnomalies(raw: unknown): PayrollTaxAnomalies {
    const source = (raw && typeof raw === "object") ? raw as Record<string, unknown> : {};
    const missingIds = Array.isArray(source.missingTaxProfileUserIds)
        ? source.missingTaxProfileUserIds
            .map((value) => Number(value))
            .filter((value) => Number.isFinite(value) && value > 0)
        : [];
    const rawCount = Number(source.missingTaxProfileUserCount || 0);
    const missingCount = Number.isFinite(rawCount) && rawCount >= 0 ? Math.max(Math.trunc(rawCount), missingIds.length) : missingIds.length;

    return {
        missingTaxProfileUserCount: missingCount,
        missingTaxProfileUserIds: Array.from(new Set(missingIds)),
    };
}

export function deriveTaxAnomaliesFromLines(lines: PayrollLine[] | null): PayrollTaxAnomalies {
    if (!Array.isArray(lines) || lines.length === 0) {
        return {
            missingTaxProfileUserCount: 0,
            missingTaxProfileUserIds: [],
        };
    }

    const missingIds = lines
        .filter((line) => {
            const code = String(line.componentCode || line.category || "").toLowerCase();
            if (code !== "pph21_ter") {
                return false;
            }
            return line.meta?.missingTaxProfile === true;
        })
        .map((line) => Number(line.userId))
        .filter((value) => Number.isFinite(value) && value > 0);

    const uniqueIds = Array.from(new Set(missingIds));
    return {
        missingTaxProfileUserCount: uniqueIds.length,
        missingTaxProfileUserIds: uniqueIds,
    };
}

export function missingTaxProfileCount(): number {
    return Math.max(0, Number(payrollRunState.currentTaxAnomalies.missingTaxProfileUserCount || 0));
}

export function hasMissingTaxProfileAnomaly(): boolean {
    return missingTaxProfileCount() > 0;
}

export function hasDownloadedReconciliationForCurrentRun(): boolean {
    return (
        payrollRunState.currentRunId !== null &&
        payrollRunState.reconciliationDownloadedForRunId !== null &&
        payrollRunState.reconciliationDownloadedForRunId === payrollRunState.currentRunId
    );
}

export function clearReconciliationDownloaded(): void {
    payrollRunState.reconciliationDownloadedForRunId = null;
}

export function markReconciliationDownloadedForCurrentRun(): void {
    if (payrollRunState.currentRunId) {
        payrollRunState.reconciliationDownloadedForRunId = payrollRunState.currentRunId;
    }
}

export function getPayrollRunRoot(): HTMLElement | null {
    return document.querySelector<HTMLElement>("[data-payroll-run-panel]");
}

export function getWorkConfigRoot(): HTMLElement | null {
    const root = document.querySelector<HTMLElement>("[data-payroll-work-config-panel]");
    if (!root || root.classList.contains("d-none")) {
        return null;
    }
    return root;
}

export function getPayrollSettingsRoot(): HTMLElement | null {
    return document.querySelector<HTMLElement>("[data-payroll-settings-panel]");
}

export function getSelectedUserIds(): number[] {
    const root = getPayrollRunRoot();
    if (!root) return [];
    return Array.from(root.querySelectorAll<HTMLInputElement>("[data-payroll-run-row-check]:checked"))
        .map((element) => Number(element.value))
        .filter((value) => Number.isFinite(value) && value > 0);
}

export function readActiveTenantContext(): TenantContextSnapshot {
    const authApi = (window as unknown as { AuthApi?: { getTenantContext?: () => unknown } }).AuthApi;
    if (!authApi || typeof authApi.getTenantContext !== "function") {
        return { companyId: null, companyName: null };
    }

    const raw = authApi.getTenantContext() as Record<string, unknown> | null;
    if (!raw || typeof raw !== "object") {
        return { companyId: null, companyName: null };
    }

    const candidateId = raw.activeCompanyId ?? raw.companyId ?? raw.id ?? null;
    const parsedId = Number(candidateId);
    const companyId = Number.isFinite(parsedId) && parsedId > 0 ? parsedId : null;
    const rawName = raw.activeCompanyName ?? raw.companyName ?? raw.name ?? null;
    const companyName = typeof rawName === "string" && rawName.trim() ? rawName.trim() : null;

    return { companyId, companyName };
}

export function extractTaxGovernancePolicyFromLines(lines: PayrollLine[] | null): TaxGovernancePolicySnapshot | null {
    if (!Array.isArray(lines) || lines.length === 0) {
        return null;
    }

    const taxLine = lines.find((line) => line.componentCode === "pph21_ter" && line.meta && (line.meta.taxPolicyId || line.meta.taxPolicyUuid || line.meta.taxPolicyCode));
    if (!taxLine || !taxLine.meta) {
        return null;
    }

    return {
        id: Number.isFinite(Number(taxLine.meta.taxPolicyId)) ? Number(taxLine.meta.taxPolicyId) : null,
        uuid: taxLine.meta.taxPolicyUuid || null,
        policyCode: taxLine.meta.taxPolicyCode || null,
        version: Number.isFinite(Number(taxLine.meta.taxPolicyVersion)) ? Number(taxLine.meta.taxPolicyVersion) : null,
    };
}