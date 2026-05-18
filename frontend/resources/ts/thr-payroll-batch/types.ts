export type BatchLine = {
    id: number;
    userId: number;
    fullName: string;
    employeeNo: string;
    bankName?: string | null;
    bankAccountNo?: string | null;
    joinDateUsed: string;
    baseSalary: number;
    fixedAllowance: number;
    referenceWage: number;
    monthsOfService: number;
    multiplier: number;
    thrGross: number;
    rowStatus: string;
    eligible: boolean;
    paymentStatus: string | null;
    paymentFailureReason: string | null;
    paymentGatewayRef: string | null;
    paidAt: string | null;
    hasSlip: boolean;
    slipGeneratedAt: string | null;
    slipNotifySentAt: string | null;
    slipNumber?: string | null;
    thrSlipPublicNo?: string | null;
    calendarYear?: number | null;
};

export type BatchMeta = {
    id: number;
    calendarYear: number;
    cutoffDate: string;
    grandTotalEligible: number;
    eligibleLineCount: number;
    totalLineCount: number;
    status: string;
    canPostToPayroll?: boolean;
};

export type ThrSettingsSnapshot = {
    calendarYear?: number;
    calculationCutoffDate?: string | null;
    eidDate?: string | null;
};

export type ThrSettingsAppliedDetail = {
    calendarYear: number | null;
    settings: ThrSettingsSnapshot | null;
};