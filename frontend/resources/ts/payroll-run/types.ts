export type ApiError = { message?: string; code?: string };
export type ApiResponse<T> = { success: boolean; data: T; error?: ApiError };

export type PayrollLine = {
    userId: number;
    userName?: string | null;
    kind: string;
    componentName?: string | null;
    componentCode?: string | null;
    category?: string | null;
    amount: number;
    sortOrder?: number;
    affectsNetPay?: boolean;
    paymentStatus?: string;
    paidAt?: string | null;
    gatewayReference?: string | null;
    meta?: {
        userName?: string;
        missingTaxProfile?: boolean;
        taxStatusSource?: string;
        taxPolicyId?: number;
        taxPolicyUuid?: string;
        taxPolicyCode?: string;
        taxPolicyVersion?: number;
        ratePercent?: number;
        rateSource?: string;
        basisAmount?: number;
        salaryCap?: number;
        capApplied?: boolean;
        riskCategory?: number;
        wageBaseCode?: string;
        taxRateApplied?: number;
        monthlyTaxableGross?: number;
        pph21TerCategory?: string;
        taxStatusUsed?: string;
    };
};

export type PayrollTaxAnomalies = {
    missingTaxProfileUserCount: number;
    missingTaxProfileUserIds: number[];
};

export type TaxGovernancePolicySnapshot = {
    id?: number | null;
    uuid?: string | null;
    policyCode?: string | null;
    version?: number | null;
    effectiveStartDate?: string | null;
    effectiveEndDate?: string | null;
    status?: string | null;
};

export type TenantContextSnapshot = {
    companyId: number | null;
    companyName: string | null;
};

export type PayrollSettings = {
    paydayDay: number;
    cutoffOffsetDays: number;
    payrollTimezone: string;
    disburseBeforePaydayAllowed: boolean;
    paydayHolidayStrategy: "previous_working_day" | "next_working_day" | "exact_calendar_day";
};

export type PayrollPolicySnapshot = PayrollSettings & {
    resolvedPaydayDate?: string | null;
    resolvedCutoffDate?: string | null;
    draftDataAsOfDate?: string | null;
};

export type PayrollRun = {
    id: number;
    status?: string;
    paymentStatus?: string;
    finalizedAt?: string | null;
    period?: { periodYear: number; periodMonth: number; status: string };
    policySnapshot?: PayrollPolicySnapshot | null;
    taxGovernancePolicy?: TaxGovernancePolicySnapshot | null;
};

export type WorkflowBadge = {
    label: string;
    badgeClass: string;
};

export type SpecialRecipients = {
    thrUserIds?: number[];
    compensationUserIds?: number[];
};

export type EmployeeRow = {
    userId: number;
    name: string;
    gross: number;
    overtime: number;
    deductions: number;
    net: number;
    receivesThr: boolean;
    receivesCompensation: boolean;
    isEligible: boolean;
    lineCount: number;
    paymentStatus: string;
    paidAt: string | null;
    gatewayReference: string | null;
    lines: PayrollLine[];
};

export type PayrollWorkProfile = {
    id: number;
    code: string;
    name: string;
    arrangementMode: string;
    defaultDayType: string;
    weeklyWorkDays: number;
    isDefault: boolean;
};

export type PayrollWorkArrangement = {
    id: number;
    userId: number;
    userName: string;
    profileId: number | null;
    profileCode?: string | null;
    profileName?: string | null;
    arrangementMode: string;
    defaultDayType?: string | null;
    weeklyWorkDays?: number | null;
    effectiveFrom?: string | null;
    effectiveTo?: string | null;
};

export function deriveRunLifecycleStatus(run: unknown): string {
    if (!run || typeof run !== "object") {
        return "";
    }
    const record = run as Record<string, unknown>;
    const raw = record.status ?? record.runStatus ?? record.run_status;
    if (raw !== null && raw !== undefined) {
        const status = String(raw).trim().toLowerCase();
        if (status) {
            return status;
        }
    }
    const finalizedAt = record.finalizedAt ?? record.finalized_at;
    if (finalizedAt) {
        return "finalized";
    }
    return "draft";
}