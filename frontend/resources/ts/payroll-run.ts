type ApiError = { message?: string; code?: string };
type ApiResponse<T> = { success: boolean; data: T; error?: ApiError };
type PayrollLine = {
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
        taxPolicyId?: number;
        taxPolicyUuid?: string;
        taxPolicyCode?: string;
        taxPolicyVersion?: number;
    };
};

type TaxGovernancePolicySnapshot = {
    id?: number | null;
    uuid?: string | null;
    policyCode?: string | null;
    version?: number | null;
    effectiveStartDate?: string | null;
    effectiveEndDate?: string | null;
    status?: string | null;
};

type TenantContextSnapshot = {
    companyId: number | null;
    companyName: string | null;
};

type PayrollRun = {
    id: number;
    status?: string;
    paymentStatus?: string;
    finalizedAt?: string | null;
    platformServiceFeeRate?: number;
    platformServiceFeeBase?: number;
    platformServiceFeeAmount?: number;
    platformServiceFeeBillingMonth?: string | null;
    totals?: {
        platformServiceFeeRate?: number;
        platformServiceFeeBase?: number;
        platformServiceFeeAmount?: number;
        platformServiceFeeBillingMonth?: string | null;
    };
    period?: { periodYear: number; periodMonth: number; status: string };
    policySnapshot?: PayrollPolicySnapshot | null;
    taxGovernancePolicy?: TaxGovernancePolicySnapshot | null;
};

type PayrollSettings = {
    paydayDay: number;
    cutoffOffsetDays: number;
    payrollTimezone: string;
    disburseBeforePaydayAllowed: boolean;
    paydayHolidayStrategy: "previous_working_day" | "next_working_day" | "exact_calendar_day";
};

type PayrollPolicySnapshot = PayrollSettings & {
    resolvedPaydayDate?: string | null;
    resolvedCutoffDate?: string | null;
    draftDataAsOfDate?: string | null;
};

type WorkflowBadge = {
    label: string;
    badgeClass: string;
};

/** Normalisasi status run dari payload API (varian key / null) + infer aman bila `status` kosong. */
function deriveRunLifecycleStatus(run: unknown): string {
    if (!run || typeof run !== "object") {
        return "";
    }
    const r = run as Record<string, unknown>;
    const raw = r.status ?? r.runStatus ?? r.run_status;
    if (raw !== null && raw !== undefined) {
        const s = String(raw).trim().toLowerCase();
        if (s) {
            return s;
        }
    }
    const fin = r.finalizedAt ?? r.finalized_at;
    if (fin) {
        return "finalized";
    }
    return "draft";
}
type SpecialRecipients = {
    thrUserIds?: number[];
    compensationUserIds?: number[];
};
type EmployeeRow = {
    userId: number;
    name: string;
    gross: number;
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

type PayrollWorkProfile = {
    id: number;
    code: string;
    name: string;
    arrangementMode: string;
    defaultDayType: string;
    weeklyWorkDays: number;
    isDefault: boolean;
};

type PayrollWorkArrangement = {
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

const _workConfigState: {
    profiles: PayrollWorkProfile[];
    arrangements: PayrollWorkArrangement[];
    users: Array<{ id: number; name: string; email?: string | null }>;
} = {
    profiles: [],
    arrangements: [],
    users: [],
};

function formatIdr(n: number): string {
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(n);
}

function toast(msg: string, danger: boolean): void {
    const Ui = (window as unknown as { ArcavUi?: { showToast?: (m: string, t: string) => void } }).ArcavUi;
    if (Ui && Ui.showToast) {
        Ui.showToast(msg, danger ? "danger" : "success");
        return;
    }

    const fallback = document.querySelector<HTMLElement>("[data-payroll-run-error]");
    if (fallback) {
        fallback.classList.remove("d-none", "alert-danger", "alert-success");
        fallback.classList.add(danger ? "alert-danger" : "alert-success");
        fallback.textContent = msg;
        window.setTimeout(() => {
            fallback.classList.add("d-none");
        }, 3200);
        return;
    }

    if (danger) {
        // Avoid native browser alert; keep UX consistent with template.
        // If no toast helper and no inline alert container, fallback to console only.
        // (UI should provide either ArcavUi.showToast or [data-payroll-run-error].)
        console.warn(msg);
    }
}

function apiRequest(method: string, url: string, data?: unknown): Promise<unknown> {
    const AuthApi = (window as unknown as { AuthApi?: { request?: (m: string, p: string, d?: unknown) => Promise<unknown> } }).AuthApi;
    if (AuthApi && AuthApi.request) {
        const path = url.replace(/^\/v1/, "");
        return AuthApi.request(method, path, data).then((res: any) => {
            // AuthApi may return an axios-like object ({ data, status }); unwrap to API payload.
            if (res && typeof res === "object" && "data" in res) {
                return res.data;
            }
            return res;
        });
    }

    const axios = (window as unknown as { axios?: (c: object) => Promise<{ data: unknown }> }).axios;
    if (axios) {
        return axios({ method, url, data, withCredentials: true }).then((r) => r.data).catch((e: any) => {
            if (e.response && e.response.data) {
                return e.response.data;
            }
            throw e;
        });
    }

    return fetch(url, {
        method: method.toUpperCase(),
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        credentials: "same-origin",
        body: data ? JSON.stringify(data) : undefined,
    }).then((res) => res.json().catch(() => ({})).then((payload) => {
        if (!res.ok) {
            const error = new Error("Request failed") as any;
            error.response = { data: payload, status: res.status };
            throw error;
        }
        return payload;
    }));
}

function reconciliationExportFileName(filePath: string | undefined | null, evidenceId: number): string {
    if (filePath && typeof filePath === "string") {
        const parts = filePath.split("/").filter(Boolean);
        const last = parts[parts.length - 1];
        if (last) {
            return last;
        }
    }
    return `reconciliation-export-${evidenceId}.csv`;
}

async function downloadReconciliationEvidenceFile(evidenceId: number, filePath?: string | null): Promise<void> {
    const AuthApi = (window as unknown as { AuthApi?: { downloadV1Binary?: (path: string, filename?: string) => Promise<void> } }).AuthApi;
    if (!AuthApi || typeof AuthApi.downloadV1Binary !== "function") {
        throw new Error("AuthApi.downloadV1Binary tidak tersedia");
    }
    const name = reconciliationExportFileName(filePath ?? undefined, evidenceId);
    await AuthApi.downloadV1Binary(`/reconciliation/exports/${evidenceId}/download`, name);
}

function formatApiError(res: unknown, fallbackStatus: number): string {
    const r = res as { error?: { message?: string; code?: string }; message?: string };
    const reconciliationMessages: Record<string, string> = {
        EXPORT_RECON_REQUIRED: "Sebelum lanjut pembayaran, lakukan export reconciliation terbaru untuk payroll run ini.",
        EXPORT_RECON_EXPIRED: "Evidence reconciliation sudah kedaluwarsa. Silakan export ulang data terbaru.",
        EXPORT_RECON_SCOPE_MISMATCH: "Evidence reconciliation tidak sesuai scope run saat ini. Gunakan evidence yang cocok.",
        EXPORT_RECON_STALE_DATA: "Data payroll berubah sejak export terakhir. Silakan export ulang lalu lanjutkan.",
    };
    const code = r?.error?.code;
    if (code && reconciliationMessages[code]) {
        return reconciliationMessages[code];
    }
    if (r?.error?.message) {
        return r.error.message;
    }
    if (r?.message) {
        return r.message;
    }
    return `Terjadi kesalahan (Kode: ${fallbackStatus})`;
}

const _state: {
    currentPeriodId: number | null;
    currentRunId: number | null;
    /** Run status from API (`draft` | `finalized` | …); dipakai untuk tombol Calculate vs Export. */
    currentRunStatus: string | null;
    currentPolicySnapshot: PayrollPolicySnapshot | null;
    currentTaxGovernancePolicy: TaxGovernancePolicySnapshot | null;
    activeTenantContext: TenantContextSnapshot;
    currentRows: EmployeeRow[];
    currentRunServiceFeeAmount: number;
    loading: boolean;
    /** Set after user completes CSV download for `currentRunId` (gate Pay via Gateway). */
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
    currentRunServiceFeeAmount: 0,
    loading: false,
    reconciliationDownloadedForRunId: null,
};

const POST_CUTOFF_REVIEW_ONLY_HINT = "Mode post-cutoff saat ini bersifat review-only. Calculate Draft tetap boleh untuk cek data, tetapi export/disburse menunggu tenggat payday sesuai policy run aktif.";

const _payrollSettingsState: {
    settings: PayrollSettings | null;
} = {
    settings: null,
};

function currentRunStatus(): string {
    return String(_state.currentRunStatus || "").toLowerCase();
}

function setBadgeState(element: HTMLElement | null, state: WorkflowBadge): void {
    if (!element) {
        return;
    }

    element.className = `badge ${state.badgeClass}`;
    element.textContent = state.label;
}

function toneButton(button: HTMLButtonElement | null, activeClass: string, inactiveClass: string, isActive: boolean): void {
    if (!button) {
        return;
    }

    button.classList.remove(...activeClass.split(" "), ...inactiveClass.split(" "));
    button.classList.add(...(isActive ? activeClass : inactiveClass).split(" "));
}

function applyWorkflowStepState(stepKey: string, state: WorkflowBadge, highlight: boolean): void {
    const root = _getRoot();
    const stepEl = root?.querySelector<HTMLElement>(`[data-payroll-step="${stepKey}"]`);
    const badgeEl = stepEl?.querySelector<HTMLElement>("[data-payroll-step-status]") || null;
    if (!stepEl) {
        return;
    }

    stepEl.classList.toggle("border-primary", highlight);
    stepEl.classList.toggle("bg-primary-subtle", highlight);
    stepEl.classList.toggle("bg-white", !highlight);
    setBadgeState(badgeEl, state);
}

function renderPayrollWorkflow(): void {
    const root = _getRoot();
    if (!root) {
        return;
    }

    const hasPeriod = !!_state.currentPeriodId;
    const hasRun = !!_state.currentRunId;
    const hasRows = _state.currentRows.length > 0;
    const selectedCount = getSelectedUserIds().length;
    const runStatus = currentRunStatus();
    const hasPaidRows = _state.currentRows.some((row) => row.paymentStatus === "paid");
    const tenantReady = !!_state.activeTenantContext.companyId;
    const policyReady = !_state.currentRunId || !!_state.currentTaxGovernancePolicy || !!_state.currentPolicySnapshot;
    const reviewOnly = isPostCutoffReviewOnlyMode();
    const evidenceDownloaded = hasDownloadedReconciliationForCurrentRun();
    const canExport = hasRun && hasRows && runStatus === "draft" && !reviewOnly;
    const canPayWindow = hasRun && hasRows && (runStatus === "draft" || runStatus === "finalized") && !reviewOnly;

    let stageTitle = "Langkah berikutnya: Calculate Draft";
    let stageDescription = "Mulai atau refresh draft payroll untuk periode aktif sebelum operator meninjau rincian payroll.";
    let stageBadge: WorkflowBadge = { label: "BUTUH TINDAKAN", badgeClass: "bg-primary" };
    let primaryActionTitle = "Hitung draft payroll periode aktif";
    let primaryActionNote = "Gunakan Calculate Draft untuk membuat atau me-refresh draft sebelum review payroll.";
    let primaryActionState: WorkflowBadge = { label: "PRIMARY ACTION", badgeClass: "bg-primary" };
    let guidance = "Gunakan Calculate Draft untuk memulai run payroll aktif.";
    let readinessBadge: WorkflowBadge = { label: "PERLU TINDAKAN", badgeClass: "bg-warning text-dark" };

    if (!hasRun) {
        stageTitle = "Langkah berikutnya: Calculate Draft";
        stageDescription = "Belum ada run aktif. Operator perlu menghitung draft terlebih dahulu sebelum review atau payment.";
        primaryActionTitle = "Hitung draft payroll periode aktif";
        primaryActionNote = "Draft dapat direfresh ulang selama run masih draft atau finalized tetapi belum paid.";
        guidance = "Setelah draft tersedia, operator akan lanjut ke review payroll lalu export evidence.";
        readinessBadge = { label: hasPeriod ? "DRAFT BELUM DIBUAT" : "MENUNGGU PERIODE", badgeClass: hasPeriod ? "bg-warning text-dark" : "bg-secondary" };
    } else if (!hasRows) {
        stageTitle = "Review setup payroll";
        stageDescription = "Run aktif ada, tetapi belum menghasilkan baris payroll yang bisa diproses. Cek setup kompensasi, eligibility, atau hitung ulang draft.";
        stageBadge = { label: "PERLU REVIEW", badgeClass: "bg-warning text-dark" };
        primaryActionTitle = "Review setup lalu Calculate Draft ulang";
        primaryActionNote = "Tidak ada baris payroll yang eligible. Operator perlu koreksi setup sebelum lanjut.";
        primaryActionState = { label: "REVIEW", badgeClass: "bg-warning text-dark" };
        guidance = "Kalau run finalized tetapi belum paid, operator masih bisa void lalu calculate ulang sesuai kebutuhan.";
        readinessBadge = { label: "DRAFT KOSONG", badgeClass: "bg-warning text-dark" };
    } else if (reviewOnly) {
        stageTitle = "Mode post-cutoff: review-only";
        stageDescription = "Operator masih bisa meninjau draft payroll, tetapi export evidence dan payment menunggu payday sesuai snapshot policy run aktif.";
        stageBadge = { label: "REVIEW-ONLY", badgeClass: "bg-warning text-dark" };
        primaryActionTitle = "Review payroll hingga payday tiba";
        primaryActionNote = POST_CUTOFF_REVIEW_ONLY_HINT;
        primaryActionState = { label: "DITAHAN POLICY", badgeClass: "bg-warning text-dark" };
        guidance = "Gunakan periode ini untuk audit hasil draft, tenant aktif, serta snapshot policy sebelum window disburse terbuka.";
        readinessBadge = { label: "WAITING PAYDAY", badgeClass: "bg-warning text-dark" };
    } else if (!evidenceDownloaded) {
        stageTitle = "Langkah berikutnya: Export Reconciliation";
        stageDescription = "Draft payroll sudah siap ditinjau. Operator wajib membuat dan mengunduh evidence reconciliation sebelum payment.";
        stageBadge = { label: "EXPORT EVIDENCE", badgeClass: "bg-secondary" };
        primaryActionTitle = "Buat dan unduh evidence reconciliation";
        primaryActionNote = "Evidence harus diunduh dengan sukses. Tanpa unduhan sukses, Pay via Gateway tetap terkunci.";
        primaryActionState = { label: "WAJIB EXPORT", badgeClass: "bg-secondary" };
        guidance = "Urutan operasional: review payroll → export reconciliation → unduh CSV → payment.";
        readinessBadge = { label: "SIAP EXPORT", badgeClass: "bg-info text-dark" };
    } else if (!hasPaidRows) {
        stageTitle = "Langkah berikutnya: Pay via Gateway";
        stageDescription = selectedCount > 0
            ? "Evidence sudah terunduh dan employee sudah dipilih. Operator bisa lanjut ke batch payment gateway."
            : "Evidence sudah terunduh. Pilih employee yang akan dibayar lalu lanjut ke gateway payment.";
        stageBadge = { label: "SIAP BAYAR", badgeClass: "bg-success" };
        primaryActionTitle = "Lanjutkan payment via gateway";
        primaryActionNote = "Batch transfer hanya terbuka setelah evidence payroll run ini terunduh dengan sukses.";
        primaryActionState = { label: "READY TO PAY", badgeClass: "bg-success" };
        guidance = selectedCount > 0
            ? "Review selection sudah siap. Buka modal gateway untuk memproses batch payment." 
            : "Pilih minimal satu employee eligible pada tabel payroll untuk memulai payment.";
        readinessBadge = { label: "PAYMENT READY", badgeClass: "bg-success" };
    } else {
        stageTitle = "Run payment sedang berjalan / selesai";
        stageDescription = "Sebagian atau seluruh employee pada run ini sudah dibayar. Operator dapat audit hasil atau lanjutkan payment untuk baris yang masih unpaid.";
        stageBadge = { label: "PAYMENT ONGOING", badgeClass: "bg-success" };
        primaryActionTitle = "Audit hasil payment atau lanjutkan batch tersisa";
        primaryActionNote = "Jika masih ada baris unpaid, pilih employee yang tersisa lalu lanjutkan payment dengan evidence terbaru.";
        primaryActionState = { label: "AUDIT / CONTINUE", badgeClass: "bg-success" };
        guidance = "Setelah payment atau reset DEV, evidence perlu dibuat ulang sebelum batch berikutnya.";
        readinessBadge = { label: "PAYMENT RECORDED", badgeClass: "bg-success" };
    }

    const titleEl = root.querySelector<HTMLElement>("[data-payroll-run-stage-title]");
    const descEl = root.querySelector<HTMLElement>("[data-payroll-run-stage-description]");
    const stageBadgeEl = root.querySelector<HTMLElement>("[data-payroll-run-stage-badge]");
    const actionTitleEl = root.querySelector<HTMLElement>("[data-payroll-run-primary-action-title]");
    const actionNoteEl = root.querySelector<HTMLElement>("[data-payroll-run-primary-action-note]");
    const actionStateEl = root.querySelector<HTMLElement>("[data-payroll-run-primary-action-state]");
    const actionGuidanceEl = root.querySelector<HTMLElement>("[data-payroll-run-action-guidance]");
    const readinessBadgeEl = root.querySelector<HTMLElement>("[data-payroll-run-readiness-badge]");

    if (titleEl) titleEl.textContent = stageTitle;
    if (descEl) descEl.textContent = stageDescription;
    if (actionTitleEl) actionTitleEl.textContent = primaryActionTitle;
    if (actionNoteEl) actionNoteEl.textContent = primaryActionNote;
    if (actionGuidanceEl) actionGuidanceEl.textContent = guidance;
    setBadgeState(stageBadgeEl, stageBadge);
    setBadgeState(actionStateEl, primaryActionState);
    setBadgeState(readinessBadgeEl, readinessBadge);

    applyWorkflowStepState("period", hasPeriod ? { label: "READY", badgeClass: "bg-success" } : { label: "WAITING", badgeClass: "bg-secondary" }, !hasRun);
    applyWorkflowStepState("calculate", hasRun ? { label: "DONE", badgeClass: "bg-success" } : { label: "ACTIVE", badgeClass: "bg-primary" }, !hasRun && hasPeriod);
    applyWorkflowStepState("review", !hasRun ? { label: "WAITING", badgeClass: "bg-secondary" } : hasRows ? { label: evidenceDownloaded || hasPaidRows ? "DONE" : "ACTIVE", badgeClass: evidenceDownloaded || hasPaidRows ? "bg-success" : "bg-primary" } : { label: "CHECK", badgeClass: "bg-warning text-dark" }, hasRun && hasRows && !evidenceDownloaded && !hasPaidRows);
    applyWorkflowStepState("export", !hasRun || !hasRows ? { label: "WAITING", badgeClass: "bg-secondary" } : reviewOnly ? { label: "BLOCKED", badgeClass: "bg-warning text-dark" } : evidenceDownloaded ? { label: "DONE", badgeClass: "bg-success" } : canExport ? { label: "ACTIVE", badgeClass: "bg-primary" } : { label: "LOCKED", badgeClass: "bg-secondary" }, canExport && !evidenceDownloaded);
    applyWorkflowStepState("pay", !hasRun || !hasRows ? { label: "WAITING", badgeClass: "bg-secondary" } : reviewOnly ? { label: "BLOCKED", badgeClass: "bg-warning text-dark" } : hasPaidRows ? { label: "IN PROGRESS", badgeClass: "bg-success" } : evidenceDownloaded ? { label: selectedCount > 0 ? "ACTIVE" : "READY", badgeClass: selectedCount > 0 ? "bg-primary" : "bg-success" } : { label: "WAITING", badgeClass: "bg-secondary" }, evidenceDownloaded && !reviewOnly);

    const tenantNote = root.querySelector<HTMLElement>("[data-payroll-checklist-tenant-note]");
    const policyNote = root.querySelector<HTMLElement>("[data-payroll-checklist-policy-note]");
    const evidenceNote = root.querySelector<HTMLElement>("[data-payroll-checklist-evidence-note]");
    const disburseNote = root.querySelector<HTMLElement>("[data-payroll-checklist-disburse-note]");
    const tenantBadge = root.querySelector<HTMLElement>("[data-payroll-checklist-tenant]");
    const policyBadge = root.querySelector<HTMLElement>("[data-payroll-checklist-policy]");
    const evidenceBadge = root.querySelector<HTMLElement>("[data-payroll-checklist-evidence]");
    const disburseBadge = root.querySelector<HTMLElement>("[data-payroll-checklist-disburse]");

    if (tenantNote) {
        tenantNote.textContent = tenantReady
            ? `${_state.activeTenantContext.companyName || "Tenant aktif"} (ID ${_state.activeTenantContext.companyId}) terdeteksi dari sesi login.`
            : "Tenant aktif belum terdeteksi dari sesi login. Global super admin perlu memastikan tenant sudah dipilih dengan benar.";
    }
    if (policyNote) {
        policyNote.textContent = policyReady
            ? (_state.currentTaxGovernancePolicy?.policyCode
                ? `Snapshot policy ${_state.currentTaxGovernancePolicy.policyCode}${_state.currentTaxGovernancePolicy.version ? ` v${_state.currentTaxGovernancePolicy.version}` : ""} terpasang pada run aktif.`
                : "Snapshot payroll policy untuk run aktif tersedia dan dapat dipakai sebagai referensi operasional.")
            : "Run aktif belum menyimpan snapshot policy yang jelas. Review policy tenant sebelum calculate ulang atau payment.";
    }
    if (evidenceNote) {
        evidenceNote.textContent = evidenceDownloaded
            ? "Evidence reconciliation payroll run ini sudah terunduh. Payment dapat dibuka sesuai selection employee."
            : canExport
                ? "Evidence belum terunduh. Operator wajib export lalu menyelesaikan unduhan CSV sebelum payment."
                : "Evidence belum siap karena draft belum lengkap atau window payment belum terbuka.";
    }
    if (disburseNote) {
        disburseNote.textContent = reviewOnly
            ? POST_CUTOFF_REVIEW_ONLY_HINT
            : canPayWindow
                ? (selectedCount > 0 ? `${selectedCount} employee dipilih untuk payment berikutnya.` : "Window disburse terbuka. Pilih employee eligible dari tabel payroll untuk lanjut.")
                : "Window disburse masih menunggu draft/evidence atau run belum berada pada status yang bisa dibayar.";
    }

    setBadgeState(tenantBadge, tenantReady ? { label: "OK", badgeClass: "bg-success" } : { label: "CHECK", badgeClass: "bg-warning text-dark" });
    setBadgeState(policyBadge, policyReady ? { label: "READY", badgeClass: "bg-success" } : { label: "MISSING", badgeClass: "bg-warning text-dark" });
    setBadgeState(evidenceBadge, evidenceDownloaded ? { label: "DOWNLOADED", badgeClass: "bg-success" } : canExport ? { label: "PENDING", badgeClass: "bg-primary" } : { label: "WAITING", badgeClass: "bg-secondary" });
    setBadgeState(disburseBadge, reviewOnly ? { label: "BLOCKED", badgeClass: "bg-warning text-dark" } : canPayWindow ? { label: "OPEN", badgeClass: "bg-success" } : { label: "WAITING", badgeClass: "bg-secondary" });

    const calculateBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-calculate]");
    const exportBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-export-evidence]");
    const disburseBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-disburse]");

    toneButton(calculateBtn, "btn-primary", "btn-outline-primary", !hasRun || (!hasRows && !reviewOnly));
    toneButton(exportBtn, "btn-secondary", "btn-outline-secondary", canExport && !evidenceDownloaded);
    toneButton(disburseBtn, "btn-success", "btn-outline-success", evidenceDownloaded && !reviewOnly);
}

function canVoidCurrentRun(): boolean {
    return !!_state.currentRunId && currentRunStatus() === "finalized" && _state.currentRows.some((row) => row.paymentStatus !== "paid");
}

function hasDownloadedReconciliationForCurrentRun(): boolean {
    return (
        _state.currentRunId !== null &&
        _state.reconciliationDownloadedForRunId !== null &&
        _state.reconciliationDownloadedForRunId === _state.currentRunId
    );
}

function clearReconciliationDownloaded(): void {
    _state.reconciliationDownloadedForRunId = null;
}

function markReconciliationDownloadedForCurrentRun(): void {
    if (_state.currentRunId) {
        _state.reconciliationDownloadedForRunId = _state.currentRunId;
    }
}

function _getRoot(): HTMLElement | null {
    return document.querySelector<HTMLElement>("[data-payroll-run-panel]");
}

function readActiveTenantContext(): TenantContextSnapshot {
    const AuthApi = (window as unknown as { AuthApi?: { getTenantContext?: () => unknown } }).AuthApi;
    if (!AuthApi || typeof AuthApi.getTenantContext !== "function") {
        return { companyId: null, companyName: null };
    }

    const raw = AuthApi.getTenantContext() as Record<string, unknown> | null;
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

function extractTaxGovernancePolicyFromLines(lines: PayrollLine[] | null): TaxGovernancePolicySnapshot | null {
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

function getWorkConfigRoot(): HTMLElement | null {
    const root = document.querySelector<HTMLElement>("[data-payroll-work-config-panel]");
    if (!root || root.classList.contains("d-none")) {
        return null;
    }
    return root;
}

function showWorkConfigError(message: string): void {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }
    const errorEl = root.querySelector<HTMLElement>("[data-payroll-work-error]");
    if (!errorEl) {
        return;
    }
    if (!message) {
        errorEl.classList.add("d-none");
        errorEl.textContent = "";
        return;
    }
    errorEl.classList.remove("d-none");
    errorEl.textContent = message;
}

function arrangementModeLabel(mode: string): string {
    return mode === "shift_worker" ? "Shift Worker" : "Office Hour";
}

function dayTypeLabel(dayType: string | null | undefined): string {
    if (!dayType) {
        return "Auto";
    }

    switch (dayType) {
    case "public_holiday":
        return "Public Holiday";
    case "weekly_rest_day":
        return "Weekly Rest Day";
    case "weekly_rest_day_short":
        return "Weekly Rest Day Short";
    default:
        return "Workday";
    }
}

function parseIntOrNull(value: string | null | undefined): number | null {
    const parsed = Number.parseInt(String(value || "").trim(), 10);
    return Number.isFinite(parsed) ? parsed : null;
}

function getPayrollSettingsRoot(): HTMLElement | null {
    return document.querySelector<HTMLElement>("[data-payroll-settings-panel]");
}

function formatIsoDateLabel(value: string | null | undefined, timeZone: string): string {
    if (!value) {
        return "-";
    }

    const parsed = new Date(`${value}T12:00:00`);
    return new Intl.DateTimeFormat("id-ID", {
        timeZone,
        day: "2-digit",
        month: "long",
        year: "numeric",
    }).format(parsed);
}

function resolvePayrollPolicyPreview(settings: PayrollSettings, periodYear: number | null, periodMonth: number | null): PayrollPolicySnapshot | null {
    if (!periodYear || !periodMonth) {
        return null;
    }

    const lastDay = new Date(periodYear, periodMonth, 0).getDate();
    const paydayDay = Math.max(1, Math.min(settings.paydayDay, lastDay));
    const payday = new Date(Date.UTC(periodYear, periodMonth - 1, paydayDay));
    const resolvedPayday = applyHolidayStrategyPreview(payday, settings.paydayHolidayStrategy);
    const cutoff = new Date(resolvedPayday.getTime());
    cutoff.setUTCDate(cutoff.getUTCDate() - Math.max(0, settings.cutoffOffsetDays));

    const toIso = (date: Date): string => {
        const year = date.getUTCFullYear();
        const month = String(date.getUTCMonth() + 1).padStart(2, "0");
        const day = String(date.getUTCDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    };

    return {
        ...settings,
        resolvedPaydayDate: toIso(resolvedPayday),
        resolvedCutoffDate: toIso(cutoff),
    };
}

function applyHolidayStrategyPreview(payday: Date, strategy: PayrollSettings["paydayHolidayStrategy"]): Date {
    if (strategy === "exact_calendar_day") {
        return payday;
    }

    const candidate = new Date(payday.getTime());
    let guard = 0;
    while (isWeekendUtc(candidate)) {
        if (strategy === "next_working_day") {
            candidate.setUTCDate(candidate.getUTCDate() + 1);
        } else {
            candidate.setUTCDate(candidate.getUTCDate() - 1);
        }

        guard += 1;
        if (guard > 14) {
            break;
        }
    }

    return candidate;
}

function isWeekendUtc(value: Date): boolean {
    const day = value.getUTCDay();
    return day === 0 || day === 6;
}

function payrollPolicyStage(snapshot: PayrollPolicySnapshot | null): { label: string; badgeClass: string; note: string } {
    if (!snapshot?.resolvedCutoffDate || !snapshot?.payrollTimezone) {
        return {
            label: "Policy belum siap",
            badgeClass: "bg-secondary",
            note: "Lengkapi policy payroll untuk melihat resolved cutoff dan payday periode aktif.",
        };
    }

    const formatter = new Intl.DateTimeFormat("en-CA", {
        timeZone: snapshot.payrollTimezone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    });
    const today = formatter.format(new Date());

    if (today <= snapshot.resolvedCutoffDate) {
        return {
            label: "Pre-cutoff",
            badgeClass: "bg-success",
            note: "Data payroll periode aktif masih bisa direfresh. Item variabel baru yang valid masih dapat masuk ke draft periode berjalan.",
        };
    }

    return {
        label: "Post-cutoff",
        badgeClass: "bg-warning text-dark",
        note: "Perubahan variabel baru setelah cutoff sebaiknya diperlakukan sebagai input periode berikutnya dan bukan otomatis masuk ke draft berjalan.",
    };
}

function currentLocalDateIso(timeZone: string): string {
    return new Intl.DateTimeFormat("en-CA", {
        timeZone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    }).format(new Date());
}

function isPostCutoffReviewOnlyMode(): boolean {
    const snapshot = _state.currentPolicySnapshot;
    if (!snapshot?.resolvedCutoffDate || !snapshot?.resolvedPaydayDate || !snapshot?.payrollTimezone) {
        return false;
    }

    if (snapshot.disburseBeforePaydayAllowed) {
        return false;
    }

    const today = currentLocalDateIso(snapshot.payrollTimezone);
    return today > snapshot.resolvedCutoffDate && today < snapshot.resolvedPaydayDate;
}

function showPayrollSettingsFeedback(message: string, danger = true): void {
    const root = getPayrollSettingsRoot();
    const feedback = root?.querySelector<HTMLElement>("[data-payroll-settings-feedback]");
    if (!feedback) {
        if (message) {
            toast(message, danger);
        }
        return;
    }

    if (!message) {
        feedback.classList.add("d-none");
        feedback.classList.remove("alert-danger", "alert-success");
        feedback.textContent = "";
        return;
    }

    feedback.classList.remove("d-none", "alert-danger", "alert-success");
    feedback.classList.add(danger ? "alert-danger" : "alert-success");
    feedback.textContent = message;
}

function fillPayrollSettingsForm(settings: PayrollSettings): void {
    const root = getPayrollSettingsRoot();
    if (!root) {
        return;
    }

    const paydayInput = root.querySelector<HTMLInputElement>("[data-payroll-settings-payday-day]");
    const cutoffInput = root.querySelector<HTMLInputElement>("[data-payroll-settings-cutoff-offset]");
    const timezoneSelect = root.querySelector<HTMLSelectElement>("[data-payroll-settings-timezone]");
    const disburseEarlyInput = root.querySelector<HTMLInputElement>("[data-payroll-settings-disburse-early]");
    const holidayStrategySelect = root.querySelector<HTMLSelectElement>("[data-payroll-settings-holiday-strategy]");

    if (paydayInput) paydayInput.value = String(settings.paydayDay);
    if (cutoffInput) cutoffInput.value = String(settings.cutoffOffsetDays);
    if (timezoneSelect) timezoneSelect.value = settings.payrollTimezone;
    if (disburseEarlyInput) disburseEarlyInput.checked = !!settings.disburseBeforePaydayAllowed;
    if (holidayStrategySelect) holidayStrategySelect.value = settings.paydayHolidayStrategy;
}

function renderPayrollSettingsPreview(): void {
    const root = getPayrollSettingsRoot();
    const settings = _payrollSettingsState.settings;
    if (!root || !settings) {
        return;
    }

    const runRoot = _getRoot();
    const year = parseIntOrNull(runRoot?.querySelector<HTMLInputElement>("[data-payroll-run-year]")?.value || null);
    const month = parseIntOrNull(runRoot?.querySelector<HTMLSelectElement>("[data-payroll-run-month]")?.value || null);
    const preview = resolvePayrollPolicyPreview(settings, year, month);
    const stage = payrollPolicyStage(preview);

    const periodEl = root.querySelector<HTMLElement>("[data-payroll-settings-preview-period]");
    const paydayEl = root.querySelector<HTMLElement>("[data-payroll-settings-preview-payday]");
    const cutoffEl = root.querySelector<HTMLElement>("[data-payroll-settings-preview-cutoff]");
    const noteEl = root.querySelector<HTMLElement>("[data-payroll-settings-preview-note]");
    const stageEl = root.querySelector<HTMLElement>("[data-payroll-settings-stage]");

    if (periodEl) {
        periodEl.textContent = year && month ? `${String(month).padStart(2, "0")}/${year}` : "Menunggu periode aktif...";
    }
    if (paydayEl) {
        paydayEl.textContent = preview ? formatIsoDateLabel(preview.resolvedPaydayDate, settings.payrollTimezone) : "-";
    }
    if (cutoffEl) {
        cutoffEl.textContent = preview ? formatIsoDateLabel(preview.resolvedCutoffDate, settings.payrollTimezone) : "-";
    }
    if (noteEl) {
        noteEl.textContent = stage.note;
    }
    if (stageEl) {
        stageEl.className = `badge ${stage.badgeClass}`;
        stageEl.textContent = stage.label;
    }
}

async function loadPayrollSettings(): Promise<void> {
    if (!getPayrollSettingsRoot()) {
        return;
    }

    try {
        const resp = await apiRequest("get", "/v1/hcm/payroll/settings") as ApiResponse<PayrollSettings>;
        if (!resp.success) {
            showPayrollSettingsFeedback(formatApiError(resp, 400), true);
            return;
        }

        _payrollSettingsState.settings = {
            paydayDay: Number(resp.data.paydayDay || 0) || 28,
            cutoffOffsetDays: Number(resp.data.cutoffOffsetDays || 0),
            payrollTimezone: String(resp.data.payrollTimezone || "Asia/Jakarta"),
            disburseBeforePaydayAllowed: !!resp.data.disburseBeforePaydayAllowed,
            paydayHolidayStrategy: (resp.data.paydayHolidayStrategy || "previous_working_day") as PayrollSettings["paydayHolidayStrategy"],
        };
        fillPayrollSettingsForm(_payrollSettingsState.settings);
        showPayrollSettingsFeedback("", false);
        renderPayrollSettingsPreview();
    } catch (e: any) {
        showPayrollSettingsFeedback(formatApiError(e.response?.data || {}, 500), true);
    }
}

async function savePayrollSettings(): Promise<void> {
    const root = getPayrollSettingsRoot();
    if (!root) {
        return;
    }

    const saveBtn = root.querySelector<HTMLButtonElement>("[data-payroll-settings-save]");
    const paydayInput = root.querySelector<HTMLInputElement>("[data-payroll-settings-payday-day]");
    const cutoffInput = root.querySelector<HTMLInputElement>("[data-payroll-settings-cutoff-offset]");
    const timezoneSelect = root.querySelector<HTMLSelectElement>("[data-payroll-settings-timezone]");
    const disburseEarlyInput = root.querySelector<HTMLInputElement>("[data-payroll-settings-disburse-early]");
    const holidayStrategySelect = root.querySelector<HTMLSelectElement>("[data-payroll-settings-holiday-strategy]");
    if (!paydayInput || !cutoffInput || !timezoneSelect || !disburseEarlyInput || !holidayStrategySelect) {
        return;
    }

    const payload = {
        paydayDay: parseIntOrNull(paydayInput.value),
        cutoffOffsetDays: parseIntOrNull(cutoffInput.value),
        payrollTimezone: timezoneSelect.value,
        disburseBeforePaydayAllowed: disburseEarlyInput.checked,
        paydayHolidayStrategy: holidayStrategySelect.value,
    };

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = "Menyimpan...";
    }

    try {
        const resp = await apiRequest("put", "/v1/hcm/payroll/settings", payload) as ApiResponse<PayrollSettings>;
        if (!resp.success) {
            showPayrollSettingsFeedback(formatApiError(resp, 400), true);
            return;
        }

        _payrollSettingsState.settings = {
            paydayDay: Number(resp.data.paydayDay || 0) || 28,
            cutoffOffsetDays: Number(resp.data.cutoffOffsetDays || 0),
            payrollTimezone: String(resp.data.payrollTimezone || "Asia/Jakarta"),
            disburseBeforePaydayAllowed: !!resp.data.disburseBeforePaydayAllowed,
            paydayHolidayStrategy: (resp.data.paydayHolidayStrategy || "previous_working_day") as PayrollSettings["paydayHolidayStrategy"],
        };
        fillPayrollSettingsForm(_payrollSettingsState.settings);
        renderPayrollSettingsPreview();
        showPayrollSettingsFeedback("Policy payroll berhasil disimpan. Run finalized yang belum paid tetap memakai snapshot lama; void lalu Calculate Draft ulang jika policy baru harus diterapkan.", false);
    } catch (e: any) {
        showPayrollSettingsFeedback(formatApiError(e.response?.data || {}, 500), true);
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = "Simpan policy payroll";
        }
    }
}

function renderWorkProfiles(profiles: PayrollWorkProfile[]): void {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    const profileBody = root.querySelector<HTMLElement>("[data-payroll-work-profiles-body]");
    const profileSelect = root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-profile]");

    if (profileBody) {
        if (!profiles.length) {
            profileBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Belum ada profile.</td></tr>';
        } else {
            profileBody.innerHTML = profiles.map((profile) => {
                const summary = `${arrangementModeLabel(profile.arrangementMode)} · ${dayTypeLabel(profile.defaultDayType)} · ${profile.weeklyWorkDays} hari`;
                return `
                    <tr>
                        <td>
                            <div class="fw-semibold">${profile.name}</div>
                            <div class="text-muted small">${profile.code}</div>
                        </td>
                        <td class="small">${summary}</td>
                        <td class="text-center">${profile.isDefault ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Default</span>' : '<span class="text-muted">-</span>'}</td>
                    </tr>
                `;
            }).join("");
        }
    }

    if (profileSelect) {
        const current = profileSelect.value;
        profileSelect.innerHTML = '<option value="">Custom tanpa profile</option>' + profiles.map((profile) => `<option value="${profile.id}">${profile.name} (${profile.code})</option>`).join("");
        if (current && profileSelect.querySelector(`option[value="${current}"]`)) {
            profileSelect.value = current;
        }
    }
}

function renderWorkArrangements(arrangements: PayrollWorkArrangement[]): void {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    const body = root.querySelector<HTMLElement>("[data-payroll-work-arrangements-body]");
    if (!body) {
        return;
    }

    if (!arrangements.length) {
        body.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Belum ada assignment.</td></tr>';
        return;
    }

    body.innerHTML = arrangements.map((row) => {
        const profileText = row.profileName
            ? `${row.profileName} (${row.profileCode || "-"})`
            : `${arrangementModeLabel(row.arrangementMode)} · ${dayTypeLabel(row.defaultDayType)} · ${row.weeklyWorkDays || "auto"} hari`;
        const effectiveText = `${row.effectiveFrom || "-"} s/d ${row.effectiveTo || "open"}`;
        return `
            <tr>
                <td>
                    <div class="fw-semibold">${row.userName || `User #${row.userId}`}</div>
                    <div class="text-muted small">UID: ${row.userId}</div>
                </td>
                <td class="small">${profileText}</td>
                <td class="small">${effectiveText}</td>
            </tr>
        `;
    }).join("");
}

function renderEmployeeOptions(users: Array<{ id: number; name: string; email?: string | null }>): void {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    const select = root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-user]");
    if (!select) {
        return;
    }

    const current = select.value;
    select.innerHTML = '<option value="">Pilih karyawan</option>' + users.map((user) => {
        const emailSuffix = user.email ? ` · ${user.email}` : "";
        return `<option value="${user.id}">${user.name}${emailSuffix}</option>`;
    }).join("");

    if (current && select.querySelector(`option[value="${current}"]`)) {
        select.value = current;
    }
}

function isoDateFromPayrollPeriodOrToday(): string {
    const root = _getRoot();
    const year = Number(root?.querySelector<HTMLInputElement>("[data-payroll-run-year]")?.value || 0);
    const month = Number(root?.querySelector<HTMLSelectElement>("[data-payroll-run-month]")?.value || 0);
    if (Number.isFinite(year) && year >= 2000 && Number.isFinite(month) && month >= 1 && month <= 12) {
        return `${String(year).padStart(4, "0")}-${String(month).padStart(2, "0")}-01`;
    }

    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-01`;
}

function isArrangementActiveOnDate(row: PayrollWorkArrangement, isoDate: string): boolean {
    const from = String(row.effectiveFrom || "").trim();
    const to = String(row.effectiveTo || "").trim();
    if (!from) {
        return false;
    }
    if (from > isoDate) {
        return false;
    }
    if (!to) {
        return true;
    }
    return to >= isoDate;
}

async function autoGenerateWorkArrangementsFromRun(options?: { showToast?: boolean; useWorkConfigError?: boolean }): Promise<{ created: number; skipped: number }> {
    const root = getWorkConfigRoot();
    if (options?.useWorkConfigError !== false && root) {
        showWorkConfigError("");
    }

    const btn = root?.querySelector<HTMLButtonElement>("[data-payroll-work-auto-generate]") || null;
    if (btn) {
        btn.disabled = true;
        btn.textContent = "Generating...";
    }

    try {
        const effectiveFrom = isoDateFromPayrollPeriodOrToday();
        const profileResp = await apiRequest("get", "/v1/hcm/payroll/work-profiles") as ApiResponse<PayrollWorkProfile[]>;
        const arrangementResp = await apiRequest("get", "/v1/hcm/payroll/work-arrangements?perPage=25") as ApiResponse<PayrollWorkArrangement[]>;
        if (!profileResp?.success) {
            throw new Error(formatApiError(profileResp, 400));
        }
        if (!arrangementResp?.success) {
            throw new Error(formatApiError(arrangementResp, 400));
        }

        const profiles = Array.isArray(profileResp.data) ? profileResp.data : [];
        const arrangements = Array.isArray(arrangementResp.data) ? arrangementResp.data : [];

        let users = usersFromCurrentPayrollRows();
        if (!users.length) {
            users = await usersFromCurrentPayrollRunDetail();
        }
        if (!users.length) {
            if (options?.useWorkConfigError !== false && root) {
                showWorkConfigError("Belum ada user dari run aktif untuk di-auto-generate.");
            }
            return { created: 0, skipped: 0 };
        }

        const activeUserSet = new Set<number>();
        for (const arrangement of arrangements) {
            if (isArrangementActiveOnDate(arrangement, effectiveFrom)) {
                activeUserSet.add(Number(arrangement.userId));
            }
        }

        const defaultProfile = profiles.find((profile) => !!profile.isDefault) || null;
        let created = 0;
        let skipped = 0;
        for (const user of users) {
            if (activeUserSet.has(user.id)) {
                skipped += 1;
                continue;
            }

            const payload = {
                userId: user.id,
                profileId: defaultProfile ? defaultProfile.id : null,
                arrangementMode: defaultProfile ? defaultProfile.arrangementMode : "office_hour",
                defaultDayType: defaultProfile ? null : "workday",
                weeklyWorkDays: defaultProfile ? null : 5,
                effectiveFrom,
                effectiveTo: null,
            };

            const response = await apiRequest("post", "/v1/hcm/payroll/work-arrangements", payload) as ApiResponse<PayrollWorkArrangement>;
            if (!response?.success) {
                throw new Error(formatApiError(response, 400));
            }

            created += 1;
        }

        if (root) {
            await refreshWorkConfigurator();
        }
        if (options?.showToast !== false) {
            toast(`Auto-generate selesai. Created: ${created}, skipped: ${skipped}.`, false);
        }
        return { created, skipped };
    } catch (error: any) {
        if (options?.useWorkConfigError !== false && root) {
            showWorkConfigError(error?.message || "Auto-generate assignment gagal.");
        }
        return { created: 0, skipped: 0 };
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = "Auto Generate dari Run Aktif";
        }
    }
}

function usersFromCurrentPayrollRows(): Array<{ id: number; name: string; email?: string | null }> {
    const unique = new Map<number, { id: number; name: string; email?: string | null }>();
    for (const row of _state.currentRows) {
        const userId = Number(row.userId);
        if (!Number.isFinite(userId) || userId <= 0) {
            continue;
        }
        const name = String(row.name || row.userName || row.meta?.userName || `User #${userId}`).trim();
        if (!unique.has(userId)) {
            unique.set(userId, { id: userId, name: name || `User #${userId}`, email: null });
        }
    }
    return Array.from(unique.values());
}

async function usersFromCurrentPayrollRunDetail(): Promise<Array<{ id: number; name: string; email?: string | null }>> {
    if (!_state.currentRunId) {
        return [];
    }

    const response = await apiRequest("get", `/v1/hcm/payroll-runs/${_state.currentRunId}`) as ApiResponse<{ lines?: PayrollLine[] }>;
    if (!response?.success) {
        return [];
    }

    const lines = Array.isArray(response.data?.lines) ? response.data.lines : [];
    const unique = new Map<number, { id: number; name: string; email?: string | null }>();
    for (const line of lines) {
        const userId = Number(line.userId);
        if (!Number.isFinite(userId) || userId <= 0) {
            continue;
        }
        const name = String(line.userName || line.meta?.userName || `User #${userId}`).trim();
        if (!unique.has(userId)) {
            unique.set(userId, { id: userId, name: name || `User #${userId}`, email: null });
        }
    }

    return Array.from(unique.values());
}

async function refreshWorkConfigurator(): Promise<void> {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    showWorkConfigError("");
    const profilesBody = root.querySelector<HTMLElement>("[data-payroll-work-profiles-body]");
    const arrangementsBody = root.querySelector<HTMLElement>("[data-payroll-work-arrangements-body]");

    if (profilesBody) {
        profilesBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Memuat profile...</td></tr>';
    }
    if (arrangementsBody) {
        arrangementsBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Memuat assignment...</td></tr>';
    }

    try {
        const profileResp = await apiRequest("get", "/v1/hcm/payroll/work-profiles") as ApiResponse<PayrollWorkProfile[]>;
        const arrangementResp = await apiRequest("get", "/v1/hcm/payroll/work-arrangements?perPage=25") as ApiResponse<PayrollWorkArrangement[]>;
        let employeeResp: ApiResponse<Array<{ id?: number; userId?: number; name?: string; fullName?: string; email?: string | null }>> | null = null;
        try {
            employeeResp = await apiRequest("get", "/v1/hcm/employees?page=1&perPage=100") as ApiResponse<Array<{ id?: number; userId?: number; name?: string; fullName?: string; email?: string | null }>>;
        } catch (_employeeErr) {
            employeeResp = null;
        }

        if (!profileResp?.success) {
            throw new Error(formatApiError(profileResp, 400));
        }
        if (!arrangementResp?.success) {
            throw new Error(formatApiError(arrangementResp, 400));
        }

        const profiles = Array.isArray(profileResp.data) ? profileResp.data : [];
        const arrangements = Array.isArray(arrangementResp.data) ? arrangementResp.data : [];
        const employeeRows = employeeResp?.success && Array.isArray(employeeResp.data) ? employeeResp.data : [];
        const usersFromDirectory = employeeRows
            .map((item) => ({
                id: Number(item.userId ?? item.id ?? 0),
                name: String(item.fullName || item.name || ""),
                email: item.email || null,
            }))
            .filter((item) => Number.isFinite(item.id) && item.id > 0 && item.name);
        const fallbackFromState = usersFromCurrentPayrollRows();
        let fallbackFromRun: Array<{ id: number; name: string; email?: string | null }> = [];
        if (!usersFromDirectory.length && !fallbackFromState.length) {
            fallbackFromRun = await usersFromCurrentPayrollRunDetail();
        }

        const usersById = new Map<number, { id: number; name: string; email?: string | null }>();
        for (const user of [...usersFromDirectory, ...fallbackFromState, ...fallbackFromRun]) {
            if (!Number.isFinite(user.id) || user.id <= 0) {
                continue;
            }
            if (!usersById.has(user.id)) {
                usersById.set(user.id, user);
            }
        }
        const users = Array.from(usersById.values());
        _workConfigState.profiles = profiles;
        _workConfigState.arrangements = arrangements;
        _workConfigState.users = users;

        renderWorkProfiles(profiles);
        renderWorkArrangements(arrangements);
        renderEmployeeOptions(users);

        if (!users.length) {
            showWorkConfigError("Belum ada kandidat karyawan. Coba refresh setelah data payroll run bulanan dimuat.");
        }
    } catch (error: any) {
        showWorkConfigError(error?.message || "Gagal memuat konfigurasi payroll work arrangement.");
    }
}

async function submitWorkProfileForm(form: HTMLFormElement): Promise<void> {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    const code = root.querySelector<HTMLInputElement>("[data-payroll-work-profile-code]")?.value?.trim() || "";
    const name = root.querySelector<HTMLInputElement>("[data-payroll-work-profile-name]")?.value?.trim() || "";
    const arrangementMode = root.querySelector<HTMLSelectElement>("[data-payroll-work-profile-mode]")?.value || "office_hour";
    const defaultDayType = root.querySelector<HTMLSelectElement>("[data-payroll-work-profile-day-type]")?.value || "workday";
    const weeklyWorkDays = parseIntOrNull(root.querySelector<HTMLSelectElement>("[data-payroll-work-profile-weekly-days]")?.value) || 5;
    const isDefault = !!root.querySelector<HTMLInputElement>("[data-payroll-work-profile-default]")?.checked;
    const submitBtn = root.querySelector<HTMLButtonElement>("[data-payroll-work-profile-submit]");

    if (!code || !name) {
        showWorkConfigError("Kode dan nama profile wajib diisi.");
        return;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
    }

    try {
        const response = await apiRequest("post", "/v1/hcm/payroll/work-profiles", {
            code,
            name,
            arrangementMode,
            defaultDayType,
            weeklyWorkDays,
            isDefault,
        }) as ApiResponse<PayrollWorkProfile>;

        if (!response?.success) {
            showWorkConfigError(formatApiError(response, 400));
            return;
        }

        form.reset();
        const weeklySelect = root.querySelector<HTMLSelectElement>("[data-payroll-work-profile-weekly-days]");
        if (weeklySelect) {
            weeklySelect.value = "5";
        }
        toast("Profile payroll work berhasil disimpan.", false);
        await refreshWorkConfigurator();
    } catch (error: any) {
        showWorkConfigError(formatApiError(error?.response?.data || {}, 500));
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }
}

async function submitWorkArrangementForm(form: HTMLFormElement): Promise<void> {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    const userId = parseIntOrNull(root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-user]")?.value);
    const profileId = parseIntOrNull(root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-profile]")?.value);
    const arrangementMode = root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-mode]")?.value || "office_hour";
    const defaultDayTypeRaw = root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-day-type]")?.value || "";
    const weeklyWorkDays = parseIntOrNull(root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-weekly-days]")?.value);
    const effectiveFrom = root.querySelector<HTMLInputElement>("[data-payroll-work-arrangement-effective-from]")?.value || "";
    const effectiveToRaw = root.querySelector<HTMLInputElement>("[data-payroll-work-arrangement-effective-to]")?.value || "";
    const submitBtn = root.querySelector<HTMLButtonElement>("[data-payroll-work-arrangement-submit]");

    if (!userId || !effectiveFrom) {
        showWorkConfigError("Karyawan dan effective from wajib diisi.");
        return;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
    }

    try {
        const response = await apiRequest("post", "/v1/hcm/payroll/work-arrangements", {
            userId,
            profileId,
            arrangementMode,
            defaultDayType: defaultDayTypeRaw || null,
            weeklyWorkDays,
            effectiveFrom,
            effectiveTo: effectiveToRaw || null,
        }) as ApiResponse<PayrollWorkArrangement>;

        if (!response?.success) {
            showWorkConfigError(formatApiError(response, 400));
            return;
        }

        form.reset();
        toast("Assignment payroll work berhasil disimpan.", false);
        await refreshWorkConfigurator();
    } catch (error: any) {
        showWorkConfigError(formatApiError(error?.response?.data || {}, 500));
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }
}

function bindWorkConfigurator(): void {
    const root = getWorkConfigRoot();
    if (!root || root.dataset.bound === "1") {
        return;
    }
    root.dataset.bound = "1";

    const refreshBtn = root.querySelector<HTMLButtonElement>("[data-payroll-work-refresh]");
    if (refreshBtn) {
        refreshBtn.addEventListener("click", () => {
            void refreshWorkConfigurator();
        });
    }

    const autoGenerateBtn = root.querySelector<HTMLButtonElement>("[data-payroll-work-auto-generate]");
    if (autoGenerateBtn) {
        autoGenerateBtn.addEventListener("click", () => {
            void autoGenerateWorkArrangementsFromRun();
        });
    }

    const profileForm = root.querySelector<HTMLFormElement>("[data-payroll-work-profile-form]");
    if (profileForm) {
        profileForm.addEventListener("submit", (event) => {
            event.preventDefault();
            void submitWorkProfileForm(profileForm);
        });
    }

    const arrangementForm = root.querySelector<HTMLFormElement>("[data-payroll-work-arrangement-form]");
    if (arrangementForm) {
        arrangementForm.addEventListener("submit", (event) => {
            event.preventDefault();
            void submitWorkArrangementForm(arrangementForm);
        });
    }

    void refreshWorkConfigurator();
}

function showErr(msg: string): void {
    const root = _getRoot();
    if (!root) return;
    const errEl = root.querySelector<HTMLElement>("[data-payroll-run-error]");
    if (!errEl) return;
    if (!msg) {
        errEl.classList.add("d-none");
        errEl.textContent = "";
    } else {
        errEl.classList.remove("d-none");
        errEl.textContent = msg;
    }
}

function setPayrollVoidHint(message: string): void {
    const root = _getRoot();
    if (!root) return;
    const hintEl = root.querySelector<HTMLElement>("[data-payroll-run-void-hint]");
    if (!hintEl) return;
    if (!message) {
        hintEl.classList.add("d-none");
        hintEl.textContent = "";
        return;
    }
    hintEl.textContent = message;
    hintEl.classList.remove("d-none");
}

function getApiErrorCode(res: unknown): string | null {
    const r = res as { error?: { code?: string } };
    return typeof r?.error?.code === "string" ? r.error.code : null;
}

function setPayrollReconciliationHint(message: string): void {
    const root = _getRoot();
    if (!root) return;
    const hintEl = root.querySelector<HTMLElement>("[data-payroll-run-reconciliation-hint]");
    if (!hintEl) return;
    if (!message) {
        hintEl.classList.add("d-none");
        hintEl.textContent = "";
        return;
    }
    hintEl.textContent = message;
    hintEl.classList.remove("d-none");
}

function setPayrollTenantHint(message: string): void {
    const root = _getRoot();
    if (!root) return;
    const hintEl = root.querySelector<HTMLElement>("[data-payroll-run-tenant-hint]");
    if (!hintEl) return;
    if (!message) {
        hintEl.classList.add("d-none");
        hintEl.textContent = "";
        return;
    }
    hintEl.textContent = message;
    hintEl.classList.remove("d-none");
}

function setPayrollTaxPolicyHint(message: string): void {
    const root = _getRoot();
    if (!root) return;
    const hintEl = root.querySelector<HTMLElement>("[data-payroll-run-tax-policy-hint]");
    if (!hintEl) return;
    if (!message) {
        hintEl.classList.add("d-none");
        hintEl.textContent = "";
        return;
    }
    hintEl.textContent = message;
    hintEl.classList.remove("d-none");
}

function renderRunContextSummary(): void {
    const root = _getRoot();
    if (!root) {
        return;
    }

    const tenantEl = root.querySelector<HTMLElement>("[data-payroll-run-tenant-context]");
    const policyEl = root.querySelector<HTMLElement>("[data-payroll-run-tax-policy]");

    const tenantId = _state.activeTenantContext.companyId;
    const tenantName = _state.activeTenantContext.companyName;
    if (tenantEl) {
        tenantEl.textContent = tenantId
            ? `${tenantName ? `${tenantName} ` : ""}(ID ${tenantId})`
            : "Tenant tidak terdeteksi";
    }

    if (!tenantId) {
        setPayrollTenantHint("Tenant aktif tidak terdeteksi dari sesi login. Pastikan global super admin memilih tenant yang benar sebelum Calculate Draft.");
    } else {
        setPayrollTenantHint("");
    }

    const policy = _state.currentTaxGovernancePolicy;
    if (policyEl) {
        if (policy?.policyCode || policy?.version) {
            const code = policy.policyCode || "POLICY";
            const version = policy.version ? `v${policy.version}` : "v?";
            policyEl.textContent = `${code} (${version})`;
        } else {
            policyEl.textContent = "Tidak ada snapshot policy";
        }
    }

    if (_state.currentRunId && !policy) {
        setPayrollTaxPolicyHint("Run ini belum menyimpan snapshot policy tax governance. Pastikan tenant punya policy published yang efektif sebelum Calculate Draft.");
    } else {
        setPayrollTaxPolicyHint("");
    }

    renderPayrollWorkflow();
}

function showEvidenceIndicator(evidence: any): void {
    const root = _getRoot();
    if (!root) return;
    const indicatorEl = root.querySelector<HTMLElement>("[data-payroll-run-evidence-indicator]");
    if (!indicatorEl) return;

    const statusBadge = indicatorEl.querySelector<HTMLElement>("[data-evidence-status]");
    const timestampEl = indicatorEl.querySelector<HTMLElement>("[data-evidence-timestamp]");

    if (!evidence) {
        indicatorEl.classList.add("d-none");
        return;
    }

    const now = new Date().getTime();
    const expiresAt = new Date(evidence.expires_at || 0).getTime();
    let status = "valid";
    let statusClass = "bg-success";

    if (now > expiresAt) {
        status = "expired";
        statusClass = "bg-danger";
    } else if (evidence.is_stale) {
        status = "stale";
        statusClass = "bg-warning";
    }

    if (statusBadge) {
        statusBadge.textContent = status.toUpperCase();
        statusBadge.className = `badge ${statusClass}`;
    }

    if (timestampEl && evidence.exported_at) {
        const date = new Date(evidence.exported_at).toLocaleString("id-ID");
        const user = evidence.exported_by_name || evidence.exported_by_user_id || "—";
        timestampEl.textContent = `Exported: ${date} oleh ${user}`;
    }

    indicatorEl.classList.remove("d-none");
    renderPayrollWorkflow();
}

async function fetchLatestEvidence(): Promise<void> {
    if (!_state.currentRunId) return;
    try {
        const res = (await apiRequest("GET", "/v1/reconciliation/exports", {
            featureKey: "payroll_run",
            actionKey: "disburse",
            scopeRef: String(_state.currentRunId),
        })) as any;

        if (res && res.data && Array.isArray(res.data) && res.data.length > 0) {
            showEvidenceIndicator(res.data[0]);
        } else {
            showEvidenceIndicator(null);
        }
    } catch (error) {
        console.warn("Failed to fetch evidence status:", error);
        showEvidenceIndicator(null);
    }
}

async function triggerExportReconciliation(): Promise<void> {
    if (!_state.currentRunId) {
        toast("No payroll run selected", true);
        return;
    }
    if (_state.currentRows.length === 0) {
        toast("Belum ada baris payroll. Lakukan Calculate Draft terlebih dahulu.", true);
        return;
    }
    if (isPostCutoffReviewOnlyMode()) {
        setPayrollReconciliationHint(POST_CUTOFF_REVIEW_ONLY_HINT);
        toast("Periode saat ini post-cutoff review-only. Export reconciliation untuk payment menunggu payday.", true);
        return;
    }
    if (String(_state.currentRunStatus || "").toLowerCase() !== "draft") {
        toast("Export reconciliation hanya untuk payroll run berstatus draft.", true);
        return;
    }

    try {
        const filterPayload = {
            periods: _state.currentRows.filter((r) => r.lineCount > 0).map((r) => ({ userId: r.userId })),
        };

        const res = (await apiRequest("POST", "/v1/reconciliation/exports", {
            featureKey: "payroll_run",
            actionKey: "disburse",
            scopeRef: String(_state.currentRunId),
            filterPayload: filterPayload,
            fileFormat: "csv",
        })) as any;

        if (res && res.data && res.data.id) {
            toast("Export reconciliation berhasil dibuat", false);
            try {
                await downloadReconciliationEvidenceFile(Number(res.data.id), res.data.filePath);
                markReconciliationDownloadedForCurrentRun();
                setPayrollReconciliationHint("");
            } catch (dlErr) {
                console.warn("Reconciliation file download failed:", dlErr);
                clearReconciliationDownloaded();
                toast("Evidence tersimpan, tetapi unduh file gagal. Pay via Gateway tetap terkunci sampai unduhan berhasil.", true);
            }
            await fetchLatestEvidence();
            refreshSelectionSummary();
            syncExportReconciliationButton();
        } else {
            toast("Gagal membuat export reconciliation", true);
        }
    } catch (error: any) {
        const errorCode = getApiErrorCode(error);
        if (errorCode && errorCode.startsWith("EXPORT_RECON_")) {
            const msg = reconciliationMessages[errorCode as keyof typeof reconciliationMessages];
            if (msg) {
                setPayrollReconciliationHint(msg);
                return;
            }
        }
        toast(`Error: ${error?.message || "Unknown error"}`, true);
    }
}

function periodLabel(year: number, month: number): string {
    const date = new Date(year, month - 1, 1);
    return new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(date);
}

function aggregateRows(lines: PayrollLine[]): EmployeeRow[] {
    const map = new Map<number, EmployeeRow>();
    lines.forEach((line) => {
        const current = map.get(line.userId) || {
            userId: line.userId,
            name: line.userName || line.meta?.userName || `User ID ${line.userId}`,
            gross: 0,
            deductions: 0,
            net: 0,
            receivesThr: false,
            receivesCompensation: false,
            isEligible: false,
            lineCount: 0,
            paymentStatus: "unpaid",
            paidAt: null,
            gatewayReference: null,
            lines: [] as PayrollLine[],
        };
        current.lines.push(line);
        current.lineCount += 1;
        const affectsNetPay = line.affectsNetPay !== false;
        if (!affectsNetPay) {
            map.set(line.userId, current);
            return;
        }
        if (line.kind === "addition") {
            current.gross += Number(line.amount || 0);
        } else if (line.kind === "deduction") {
            current.deductions += Number(line.amount || 0);
        }
        if ((line.paymentStatus || "unpaid") === "paid") {
            current.paymentStatus = "paid";
            current.paidAt = line.paidAt || current.paidAt;
            current.gatewayReference = line.gatewayReference || current.gatewayReference;
        }
        current.net = current.gross - current.deductions;
        current.isEligible = current.net > 0;
        map.set(line.userId, current);
    });
    return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name, "id"));
}

function getSelectedUserIds(): number[] {
    const root = _getRoot();
    if (!root) return [];
    return Array.from(root.querySelectorAll<HTMLInputElement>("[data-payroll-run-row-check]:checked"))
        .map((el) => Number(el.value))
        .filter((value) => Number.isFinite(value) && value > 0);
}

function refreshSelectionSummary(): void {
    const root = _getRoot();
    if (!root) return;
    const selectedIds = getSelectedUserIds();
    const selectedCountEl = root.querySelector<HTMLElement>("[data-payroll-run-selected-count]");
    const disburseBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-disburse]");
    const resetBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-reset-payments]");
    if (selectedCountEl) {
        selectedCountEl.textContent = String(selectedIds.length);
    }
    if (disburseBtn) {
        const st = currentRunStatus();
        const reviewOnly = isPostCutoffReviewOnlyMode();
        const canDisburse = !_state.currentRunId ||
            _state.currentRows.length === 0 ||
            selectedIds.length === 0 ||
            !hasDownloadedReconciliationForCurrentRun() ||
            !(st === "draft" || st === "finalized") ||
            reviewOnly;
        console.log("[refreshSelectionSummary]", { runId: _state.currentRunId, rows: _state.currentRows.length, selected: selectedIds.length, downloaded: hasDownloadedReconciliationForCurrentRun(), status: st, disabled: canDisburse });
        disburseBtn.disabled = canDisburse;
        if (reviewOnly) {
            setPayrollReconciliationHint(POST_CUTOFF_REVIEW_ONLY_HINT);
        }
    }
    if (resetBtn) {
        resetBtn.disabled = !_state.currentRunId;
    }

    renderPayrollWorkflow();
}

function syncVoidButton(): void {
    const root = _getRoot();
    if (!root) return;
    const voidBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-void]");
    if (!voidBtn) return;

    const status = currentRunStatus();
    const hasPaidRows = _state.currentRows.some((row) => row.paymentStatus === "paid");
    const hasUnpaidRows = _state.currentRows.some((row) => row.paymentStatus !== "paid");
    const canVoid = canVoidCurrentRun();

    voidBtn.disabled = !canVoid;

    if (status === "void") {
        setPayrollVoidHint("Run ini sudah di-void. Anda bisa hitung draft ulang untuk periode aktif yang sama.");
        return;
    }

    if (status === "finalized" && hasPaidRows) {
        setPayrollVoidHint("Run sudah memiliki pembayaran berstatus paid, jadi tidak bisa di-void agar rekonsiliasi transfer tidak rancu.");
        return;
    }

    if (status === "finalized" && hasUnpaidRows) {
        setPayrollVoidHint("Run finalized ini belum dibayar, jadi masih bisa di-void bila perlu koreksi setup atau hitung ulang draft.");
        return;
    }

    setPayrollVoidHint("");
    renderPayrollWorkflow();
}

function syncExportReconciliationButton(): void {
    const root = _getRoot();
    if (!root) return;
    const exportBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-export-evidence]");
    if (exportBtn) {
        const st = String(_state.currentRunStatus || "").toLowerCase();
        const exportAllowed = !!_state.currentRunId && _state.currentRows.length > 0 && st === "draft" && !isPostCutoffReviewOnlyMode();
        console.log("[syncExportReconciliationButton]", { runId: _state.currentRunId, rows: _state.currentRows.length, status: st, exportAllowed });
        exportBtn.disabled = !exportAllowed;
    }

    renderPayrollWorkflow();
}

function syncCalculateDraftButton(): void {
    const root = _getRoot();
    if (!root) return;
    const calculateBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-calculate]");
    if (!calculateBtn) return;
    const st = String(_state.currentRunStatus || "").toLowerCase();
    const hasPaidRows = _state.currentRows.some((row) => row.paymentStatus === "paid");
    /**
     * Backend dev flow juga mengizinkan recalculation untuk finalized run yang belum dibayar.
     * UI harus mengikuti agar tombol tidak terkunci padahal endpoint calculate-draft tetap valid.
     */
    const canCalculate = !!_state.currentPeriodId && (
        !_state.currentRunId
        || st === "draft"
        || st === "void"
        || (st === "finalized" && !hasPaidRows)
    );
    console.log("[syncCalculateDraftButton]", { periodId: _state.currentPeriodId, runId: _state.currentRunId, status: st, hasPaidRows, canCalculate });
    calculateBtn.disabled = !canCalculate;
    renderPayrollWorkflow();
}

function updateRunUI(runData: PayrollRun | null, lines: PayrollLine[] | null = null, specialRecipients: SpecialRecipients | null = null): void {
    const root = _getRoot();
    if (!root) return;

    if (runData) {
        _state.currentRunStatus = deriveRunLifecycleStatus(runData);
        _state.currentPolicySnapshot = runData.policySnapshot || null;
        _state.currentTaxGovernancePolicy = runData.taxGovernancePolicy || extractTaxGovernancePolicyFromLines(lines) || null;
        const feeFromTotals = Number(runData.totals?.platformServiceFeeAmount || 0);
        const feeFromRoot = Number(runData.platformServiceFeeAmount || 0);
        _state.currentRunServiceFeeAmount = Number.isFinite(feeFromTotals) && feeFromTotals > 0 ? feeFromTotals : feeFromRoot;
    } else if (!_state.currentRunId) {
        _state.currentRunStatus = null;
        _state.currentPolicySnapshot = null;
        _state.currentTaxGovernancePolicy = null;
        _state.currentRunServiceFeeAmount = 0;
    }

    const empCountEl = root.querySelector<HTMLElement>("[data-payroll-run-emp-count]");
    const selectedCountEl = root.querySelector<HTMLElement>("[data-payroll-run-selected-count]");
    const lineCountEl = root.querySelector<HTMLElement>("[data-payroll-run-line-count]");
    const periodStatusEl = root.querySelector<HTMLElement>("[data-payroll-run-status]");
    const paymentStatusEl = root.querySelector<HTMLElement>("[data-payroll-run-payment-status]");
    const serviceFeeEl = root.querySelector<HTMLElement>("[data-payroll-run-service-fee]");
    const emptyEl = root.querySelector<HTMLElement>("[data-payroll-run-empty]");
    const gridEl = root.querySelector<HTMLElement>("[data-payroll-run-grid]");
    const tbody = gridEl?.querySelector("tbody");
    const selectAll = root.querySelector<HTMLInputElement>("[data-payroll-run-select-all]");

    if (Array.isArray(lines)) {
        _state.currentRows = aggregateRows(lines);

        const thrSet = new Set(
            (Array.isArray(specialRecipients?.thrUserIds) ? specialRecipients.thrUserIds : [])
                .map((value) => Number(value))
                .filter((value) => Number.isFinite(value) && value > 0),
        );
        const compensationSet = new Set(
            (Array.isArray(specialRecipients?.compensationUserIds) ? specialRecipients.compensationUserIds : [])
                .map((value) => Number(value))
                .filter((value) => Number.isFinite(value) && value > 0),
        );

        _state.currentRows = _state.currentRows.map((row) => ({
            ...row,
            receivesThr: thrSet.has(row.userId),
            receivesCompensation: compensationSet.has(row.userId),
        }));
    }
    if (empCountEl) empCountEl.textContent = String(_state.currentRows.length);
    if (selectedCountEl) selectedCountEl.textContent = "0";
    if (lineCountEl && Array.isArray(lines)) lineCountEl.textContent = String(lines.length);
    if (periodStatusEl) {
        const status = runData?.period?.status || "open";
        periodStatusEl.innerHTML = `<span class="badge bg-${status === "open" ? "warning" : "success"}">${String(status).toUpperCase()}</span>`;
    }
    if (paymentStatusEl) {
        const paymentStatus = runData?.paymentStatus || "unpaid";
        const badgeClass = paymentStatus === "paid" ? "success" : paymentStatus === "partial" ? "warning text-dark" : "secondary";
        paymentStatusEl.innerHTML = `<span class="badge bg-${badgeClass}">${String(paymentStatus).toUpperCase()}</span>`;
    }
    if (serviceFeeEl) {
        serviceFeeEl.textContent = formatIdr(_state.currentRunServiceFeeAmount || 0);
    }

    syncCalculateDraftButton();
    syncExportReconciliationButton();
    syncVoidButton();
    renderRunContextSummary();

    if (emptyEl && (!runData || _state.currentRows.length === 0)) {
        emptyEl.textContent = runData
            ? (currentRunStatus() === "void"
                ? "Run sebelumnya sudah di-void. Gunakan Calculate Draft untuk membuat draft baru dari setup payroll terbaru."
                : "Belum ada karyawan payroll untuk periode ini. Gunakan Calculate Draft untuk refresh data aktif.")
            : "Klik Calculate Draft untuk membuat draft payroll. Setelah itu lakukan Export Reconciliation dan unduh file CSV; Pay via Gateway aktif hanya setelah unduhan selesai.";
        emptyEl.classList.remove("d-none");
        if (gridEl) gridEl.classList.add("d-none");
        refreshSelectionSummary();
        return;
    }

    if (emptyEl) emptyEl.classList.add("d-none");
    if (!gridEl || !tbody) return;

    gridEl.classList.remove("d-none");
    tbody.innerHTML = _state.currentRows.map((row) => {
        const isPaid = row.paymentStatus === "paid";
        const isEligible = row.isEligible;
        const paymentBadgeClass = row.paymentStatus === "paid" ? "bg-success" : "bg-light text-dark";
        const paymentLabel = row.paymentStatus === "paid" ? "Paid" : "Pending";
        const payAction = isPaid
            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Telah Dibayar</span>'
            : (!isEligible
                ? '<span class="badge bg-warning-subtle text-dark border border-warning-subtle">Tidak eligible (THP <= 0)</span>'
                : `<button type="button" class="btn btn-sm btn-outline-success" data-payroll-run-pay-one="${row.userId}">Pay</button>`);
        const rowAction = `
            <div class="d-inline-flex align-items-center justify-content-end gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-primary" data-payroll-run-view-one="${row.userId}">Detail</button>
                ${payAction}
            </div>
        `;
        return `
            <tr>
                <td>
                    <div class="form-check form-check-md">
                        <input class="form-check-input" type="checkbox" value="${row.userId}" data-payroll-run-row-check ${(isPaid || !isEligible) ? "disabled" : "checked"}>
                    </div>
                </td>
                <td>
                    <div class="fw-semibold text-dark">${row.name}</div>
                    <div class="text-muted small">UID: ${row.userId}</div>
                </td>
                <td class="text-end">${formatIdr(row.gross)}</td>
                <td class="text-end">${formatIdr(row.deductions)}</td>
                <td class="text-end fw-bold">${formatIdr(row.net)}</td>
                <td class="text-center">${row.receivesThr ? '<span class="badge bg-info-subtle text-info border border-info-subtle">Ya</span>' : '<span class="text-muted">-</span>'}</td>
                <td class="text-center">${row.receivesCompensation ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Ya</span>' : '<span class="text-muted">-</span>'}</td>
                <td class="text-center">${row.lineCount}</td>
                <td>
                    <span class="badge ${paymentBadgeClass}">${paymentLabel}</span>
                    ${row.gatewayReference ? `<div class="text-muted small mt-1">${row.gatewayReference}</div>` : ""}
                </td>
                <td class="text-end">
                    ${rowAction}
                </td>
            </tr>
        `;
    }).join("");

    if (selectAll) {
        const unpaidRows = _state.currentRows.filter((row) => row.paymentStatus !== "paid" && row.isEligible);
        selectAll.checked = unpaidRows.length > 0;
        selectAll.disabled = unpaidRows.length === 0;
    }
    refreshSelectionSummary();
}

function openEmployeeDetailModal(userId: number): void {
    const row = _state.currentRows.find((item) => item.userId === userId);
    const modal = document.getElementById("payroll_detail_modal");
    if (!row || !modal || !(window as any).bootstrap?.Modal) {
        return;
    }

    const root = _getRoot();
    const year = Number(root?.querySelector<HTMLInputElement>("[data-payroll-run-year]")?.value || 0);
    const month = Number(root?.querySelector<HTMLSelectElement>("[data-payroll-run-month]")?.value || 0);
    const periodText = year > 0 && month > 0 ? periodLabel(year, month) : "—";

    const setText = (selector: string, value: string) => {
        const el = modal.querySelector<HTMLElement>(selector);
        if (el) el.textContent = value;
    };
    const setHtml = (selector: string, value: string) => {
        const el = modal.querySelector<HTMLElement>(selector);
        if (el) el.innerHTML = value;
    };

    setText("[data-payroll-detail-name]", row.name);
    setText("[data-payroll-detail-meta]", `UID: ${row.userId}`);
    setText("[data-payroll-detail-period]", periodText);
    setHtml("[data-payroll-detail-payment-status]", `Payment: <strong>${row.paymentStatus === "paid" ? "PAID" : "PENDING"}</strong>`);
    setHtml("[data-payroll-detail-eligibility]", `Status: <strong>${row.isEligible ? "Eligible" : "Tidak eligible (THP <= 0)"}</strong>`);
    setHtml("[data-payroll-detail-thr]", `THR: <strong>${row.receivesThr ? "Ya" : "-"}</strong>`);
    setHtml("[data-payroll-detail-compensation]", `Compensation: <strong>${row.receivesCompensation ? "Ya" : "-"}</strong>`);

    setText("[data-payroll-detail-gross]", formatIdr(row.gross));
    setText("[data-payroll-detail-deductions]", formatIdr(row.deductions));
    setText("[data-payroll-detail-net]", formatIdr(row.net));
    setText("[data-payroll-detail-line-count]", String(row.lineCount));

    const linesTbody = modal.querySelector<HTMLElement>("[data-payroll-detail-lines]");
    if (linesTbody) {
        const sorted = [...row.lines].sort((a, b) => {
            if ((a.sortOrder ?? 999) !== (b.sortOrder ?? 999)) {
                return (a.sortOrder ?? 999) - (b.sortOrder ?? 999);
            }
            if (a.kind !== b.kind) {
                return a.kind === "addition" ? -1 : 1;
            }
            return (a.componentName || a.componentCode || "").localeCompare((b.componentName || b.componentCode || ""), "id");
        });

        linesTbody.innerHTML = sorted.length === 0
            ? '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada data komponen.</td></tr>'
            : sorted.map((line, index) => {
                const label = line.componentName || line.componentCode || "Komponen";
                const kindLabel = line.kind === "addition" ? "Addition" : "Deduction";
                const kindClass = line.kind === "addition" ? "bg-success-subtle text-success border border-success-subtle" : "bg-danger-subtle text-danger border border-danger-subtle";
                const affectsNetPay = line.affectsNetPay !== false;
                const payLabel = (line.paymentStatus || "unpaid") === "paid" ? "PAID" : "UNPAID";
                const payClass = payLabel === "PAID" ? "bg-success-subtle text-success border border-success-subtle" : "bg-secondary-subtle text-dark border border-secondary-subtle";
                return `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <div class="fw-semibold">${label}</div>
                            <div class="text-muted small">${line.componentCode || "-"}</div>
                        </td>
                        <td><span class="badge ${kindClass}">${kindLabel}</span></td>
                        <td>${line.category || "-"}</td>
                        <td class="text-end fw-semibold">${formatIdr(line.amount || 0)}</td>
                        <td class="text-center">${affectsNetPay ? '<span class="badge bg-info-subtle text-info border border-info-subtle">Ya</span>' : '<span class="badge bg-light text-dark border">Tidak</span>'}</td>
                        <td class="text-center"><span class="badge ${payClass}">${payLabel}</span></td>
                    </tr>
                `;
            }).join("");
    }

    (window as any).bootstrap.Modal.getOrCreateInstance(modal).show();
}

async function loadRunDetails(runId: number): Promise<void> {
    try {
        const resp = await apiRequest("get", `/v1/hcm/payroll-runs/${runId}`) as ApiResponse<{ run: PayrollRun; lines: PayrollLine[]; specialRecipients?: SpecialRecipients }>;
        if (!resp.success) {
            showErr(formatApiError(resp, 400));
            return;
        }
        updateRunUI(
            resp.data.run,
            Array.isArray(resp.data.lines) ? resp.data.lines : [],
            (resp.data?.specialRecipients || null) as SpecialRecipients | null,
        );
        void fetchLatestEvidence();
    } catch (e: any) {
        showErr(formatApiError(e.response?.data || {}, 500));
    }
}

async function loadPeriod(autoCalculateMissing = true): Promise<void> {
    const root = _getRoot();
    if (!root || _state.loading) return;

    const yearInput = root.querySelector<HTMLInputElement>("[data-payroll-run-year]");
    const monthSelect = root.querySelector<HTMLSelectElement>("[data-payroll-run-month]");
    if (!yearInput || !monthSelect) return;

    _state.loading = true;
    _state.activeTenantContext = readActiveTenantContext();
    renderRunContextSummary();
    showErr("");
    console.log("[loadPeriod] Starting to load period...");

    try {
        const activeResp = await apiRequest("get", "/v1/hcm/payroll-periods/active") as ApiResponse<any>;
        if (!activeResp.success) {
            showErr(formatApiError(activeResp, 400));
            return;
        }

        const period = activeResp.data;
        console.log("[loadPeriod] Got period:", period);
        if (period && period.periodYear) {
            yearInput.value = String(period.periodYear);
        }
        if (period && period.periodMonth) {
            monthSelect.value = String(period.periodMonth);
        }
        monthSelect.disabled = true;
        yearInput.readOnly = true;

        _state.currentPeriodId = Number(period.id || 0) || null;
        console.log("[loadPeriod] Set currentPeriodId to:", _state.currentPeriodId);
        if (!_state.currentPeriodId) {
            showErr("Periode payroll tidak valid.");
            return;
        }

        const detailResp = await apiRequest("get", `/v1/hcm/payroll-periods/${_state.currentPeriodId}`) as ApiResponse<any>;
        if (!detailResp.success) {
            showErr(formatApiError(detailResp, 400));
            return;
        }

        const detailedPeriod = detailResp.data;
        const statusEl = root.querySelector<HTMLElement>("[data-payroll-run-status]");
        if (statusEl) {
            statusEl.innerHTML = `<span class="badge bg-${detailedPeriod.status === "open" ? "warning" : "success"}">${String(detailedPeriod.status).toUpperCase()}</span>`;
        }

        if (detailedPeriod.latestRun && detailedPeriod.latestRun.id) {
            clearReconciliationDownloaded();
            _state.currentRunId = Number(detailedPeriod.latestRun.id);
            _state.currentRunStatus = deriveRunLifecycleStatus(detailedPeriod.latestRun);
            console.log("[loadPeriod] Found latestRun:", { runId: _state.currentRunId, status: _state.currentRunStatus });
            await loadRunDetails(_state.currentRunId);
            return;
        }

        _state.currentRunId = null;
        _state.currentRunStatus = null;
        clearReconciliationDownloaded();
        updateRunUI(null, []);
        console.log("[loadPeriod] No latestRun, cleared runId/status");
        if (autoCalculateMissing) {
            await calculateDraft(true);
        }
    } catch (e: any) {
        showErr(formatApiError(e.response?.data || {}, 500));
    } finally {
        _state.loading = false;
        console.log("[loadPeriod] Finally block - syncing buttons...");
        syncCalculateDraftButton();
        syncExportReconciliationButton();
        refreshSelectionSummary();
        if (getWorkConfigRoot()) {
            void refreshWorkConfigurator();
        }
        renderPayrollSettingsPreview();
        console.log("[loadPeriod] Done");
    }
}

async function calculateDraft(silent = false): Promise<void> {
    const root = _getRoot();
    if (!root || !_state.currentPeriodId) return;

    const calculateBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-calculate]");
    if (calculateBtn) calculateBtn.disabled = true;

    try {
        clearReconciliationDownloaded();
        const resp = await apiRequest("post", `/v1/hcm/payroll-periods/${_state.currentPeriodId}/calculate-draft`) as ApiResponse<any>;
        if (!resp.success) {
            toast(formatApiError(resp, 400), true);
            return;
        }
        _state.currentRunId = Number(resp.data?.run?.id || 0) || null;
        if (resp.data?.run) {
            _state.currentRunStatus = deriveRunLifecycleStatus(resp.data.run);
        }
        if (!silent) {
            toast("Draft payroll berhasil direfresh.", false);
        }
        if (_state.currentRunId) {
            await loadRunDetails(_state.currentRunId);
            try {
                const autoSummary = await autoGenerateWorkArrangementsFromRun({ showToast: false, useWorkConfigError: false });
                if (autoSummary.created > 0) {
                    toast(`Auto-assignment payroll aktif: ${autoSummary.created} dibuat, ${autoSummary.skipped} dilewati.`, false);
                }
            } catch (autoError: any) {
                console.warn("[payroll-run] auto work assignment skipped", autoError?.message || autoError);
            }
        }
    } catch (e: any) {
        toast(formatApiError(e.response?.data || {}, 500), true);
    } finally {
        syncCalculateDraftButton();
        syncExportReconciliationButton();
        refreshSelectionSummary();
    }
}

async function voidCurrentRun(): Promise<void> {
    const root = _getRoot();
    if (!root || !_state.currentRunId || !canVoidCurrentRun()) {
        return;
    }

    const confirmed = (window as any).ArcavUi?.confirm
        ? await (window as any).ArcavUi.confirm(
            "Void run finalized ini? Hanya boleh jika payroll belum ditransfer/dibayar.",
            "Void Finalized Run"
        )
        : false;

    if (!confirmed) {
        return;
    }

    const voidBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-void]");
    if (voidBtn) {
        voidBtn.disabled = true;
        voidBtn.textContent = "Voiding...";
    }

    try {
        const resp = await apiRequest("post", `/v1/hcm/payroll-runs/${_state.currentRunId}/void`) as ApiResponse<any>;
        if (!resp.success) {
            toast(formatApiError(resp, 400), true);
            return;
        }

        clearReconciliationDownloaded();
        updateRunUI((resp.data || null) as PayrollRun | null, null);
        toast("Run finalized berhasil di-void. Anda bisa hitung draft ulang untuk periode aktif ini.", false);
    } catch (e: any) {
        toast(formatApiError(e.response?.data || {}, 500), true);
    } finally {
        if (voidBtn) {
            voidBtn.textContent = "Void Finalized Run";
            syncVoidButton();
            syncCalculateDraftButton();
            refreshSelectionSummary();
        }
    }
}

function populateGatewayModal(userIds: number[]): EmployeeRow[] {
    const modal = document.getElementById("payroll_gateway_modal");
    if (!modal) return [];

    const rows = _state.currentRows.filter((row) => userIds.includes(row.userId) && row.isEligible);
    const totalGross = rows.reduce((sum, row) => sum + row.gross, 0);
    const totalDeductions = rows.reduce((sum, row) => sum + row.deductions, 0);
    const totalNet = rows.reduce((sum, row) => sum + row.net, 0);
    const periodText = (() => {
        const root = _getRoot();
        const year = root?.querySelector<HTMLInputElement>("[data-payroll-run-year]")?.value || "";
        const month = Number(root?.querySelector<HTMLSelectElement>("[data-payroll-run-month]")?.value || 0);
        return year && month ? periodLabel(Number(year), month) : "—";
    })();

    const setText = (selector: string, value: string) => {
        const el = modal.querySelector<HTMLElement>(selector);
        if (el) el.textContent = value;
    };
    setText("[data-payroll-gateway-period]", periodText);
    setText("[data-payroll-gateway-count]", String(rows.length));
    setText("[data-payroll-gateway-gross]", formatIdr(totalGross));
    setText("[data-payroll-gateway-deductions]", formatIdr(totalDeductions));
    setText("[data-payroll-gateway-total]", formatIdr(totalNet));
    setText("[data-payroll-gateway-service-fee]", formatIdr(_state.currentRunServiceFeeAmount || 0));
    const statusEl = modal.querySelector<HTMLElement>("[data-payroll-gateway-status]");
    if (statusEl) {
        const hasPaid = _state.currentRows.some((row) => row.paymentStatus === "paid");
        statusEl.textContent = hasPaid ? "PARTIAL / ONGOING" : "READY TO PAY";
        statusEl.className = `badge ${hasPaid ? "bg-info text-dark border" : "bg-success"}`;
    }

    const listEl = modal.querySelector<HTMLElement>("[data-payroll-gateway-list]");
    if (listEl) {
        const renderComponents = (row: EmployeeRow): string => {
            const sorted = row.lines.filter((line) => line.affectsNetPay !== false).sort((a, b) => {
                if (a.kind !== b.kind) return a.kind === "addition" ? -1 : 1;
                return (a.sortOrder ?? 99) - (b.sortOrder ?? 99);
            });
            const additions = sorted.filter((l) => l.kind === "addition");
            const deductions = sorted.filter((l) => l.kind === "deduction");

            const renderLine = (l: PayrollLine, isDeduction: boolean): string => {
                const label = l.componentName || l.componentCode || (isDeduction ? "Potongan" : "Penghasilan");
                const sign = isDeduction ? "−" : "+";
                const cls = isDeduction ? "text-danger" : "text-success";
                return `<div class="d-flex align-items-start justify-content-between ${cls} small py-1">
                    <span>${label}</span>
                    <span class="fw-semibold ms-3 text-nowrap">${sign} ${formatIdr(l.amount)}</span>
                </div>`;
            };

            const addRows = additions.length
                ? `
                    <div class="mb-2">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">Penambah</div>
                        ${additions.map((l) => renderLine(l, false)).join("")}
                    </div>
                `
                : "";
            const dedRows = deductions.length
                ? `
                    <div>
                        <div class="small text-muted text-uppercase fw-semibold mb-1">Pengurang</div>
                        ${deductions.map((l) => renderLine(l, true)).join("")}
                    </div>
                `
                : "";

            return `${addRows}${addRows && dedRows ? '<div class="border-top pt-2 mt-2"></div>' : ""}${dedRows}`;
        };

        listEl.innerHTML = rows.map((row) => `
            <div class="list-group-item py-3 px-3">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2">
                    <div>
                        <div class="fw-semibold text-dark">${row.name}</div>
                        <div class="text-muted small">UID: ${row.userId}</div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Take Home Pay</div>
                        <div class="fw-bold text-dark">${formatIdr(row.net)}</div>
                    </div>
                </div>
                <div class="border-start border-3 ps-3">
                    ${renderComponents(row)}
                </div>
            </div>
        `).join("");
    }

    const payBtn = modal.querySelector<HTMLButtonElement>("[data-payroll-gateway-pay]");
    if (payBtn) {
        payBtn.dataset.userIds = userIds.join(",");
        payBtn.disabled = rows.length === 0 || !_state.currentRunId || !hasDownloadedReconciliationForCurrentRun();
    }

    return rows;
}

function openDisburseModal(userIds?: number[]): void {
    const selectedIds = Array.isArray(userIds) && userIds.length ? userIds : getSelectedUserIds();
    if (!_state.currentRunId || selectedIds.length === 0) {
        toast("Pilih minimal satu karyawan untuk dibayar melalui gateway.", true);
        return;
    }
    if (isPostCutoffReviewOnlyMode()) {
        setPayrollReconciliationHint(POST_CUTOFF_REVIEW_ONLY_HINT);
        toast("Periode saat ini post-cutoff review-only. Pembayaran menunggu payday sesuai policy run aktif.", true);
        return;
    }
    if (!hasDownloadedReconciliationForCurrentRun()) {
        setPayrollReconciliationHint(
            "Urutan wajib: Calculate Draft → Export Reconciliation → unduh file CSV → Pay via Gateway.",
        );
        toast("Selesaikan Export Reconciliation dan unduh file CSV terlebih dahulu.", true);
        return;
    }
    const modal = document.getElementById("payroll_gateway_modal");
    if (!modal || !(window as any).bootstrap?.Modal) {
        return;
    }
    populateGatewayModal(selectedIds);
    (window as any).bootstrap.Modal.getOrCreateInstance(modal).show();
}

async function disburseSelected(): Promise<void> {
    const modal = document.getElementById("payroll_gateway_modal");
    if (!modal || !_state.currentRunId) return;

    if (isPostCutoffReviewOnlyMode()) {
        setPayrollReconciliationHint(POST_CUTOFF_REVIEW_ONLY_HINT);
        toast("Periode saat ini post-cutoff review-only. Pembayaran menunggu payday sesuai policy run aktif.", true);
        return;
    }

    const payBtn = modal.querySelector<HTMLButtonElement>("[data-payroll-gateway-pay]");
    const ids = String(payBtn?.dataset.userIds || "")
        .split(",")
        .map((value) => Number(value))
        .filter((value) => Number.isFinite(value) && value > 0);

    if (ids.length === 0) {
        toast("Tidak ada karyawan yang dipilih untuk pembayaran gateway.", true);
        return;
    }

    if (payBtn) {
        payBtn.disabled = true;
        payBtn.textContent = "Processing...";
    }

    try {
        const hosted = await apiRequest(
            "post",
            `/v1/hcm/payroll-runs/${_state.currentRunId}/mock-hosted-checkout`,
            { userIds: ids },
        ) as ApiResponse<any>;

        if (!hosted.success) {
            toast(formatApiError(hosted, 400), true);
            return;
        }

        const hostedCheckoutUrl = String((hosted as any)?.flow?.hostedCheckoutUrl || "").trim();
        if (hostedCheckoutUrl.length === 0) {
            toast("Gagal membuka hosted payment gateway.", true);
            return;
        }

        window.location.assign(hostedCheckoutUrl);
        return;
    } catch (e: any) {
        const code = getApiErrorCode(e.response?.data || {});
        if (code && code.startsWith("EXPORT_RECON_")) {
            setPayrollReconciliationHint(formatApiError(e.response?.data || {}, 500));
        }
        toast(formatApiError(e.response?.data || {}, 500), true);
    } finally {
        if (payBtn) {
            const canPay = !!_state.currentRunId && ids.length > 0 && hasDownloadedReconciliationForCurrentRun();
            payBtn.disabled = !canPay;
            payBtn.textContent = "Pay now";
        }
    }
}

async function settleMockHostedReturnAndDisburse(): Promise<void> {
    const params = new URLSearchParams(window.location.search);
    const status = String(params.get("payroll_mock_payment_status") || "").trim().toLowerCase();
    const runId = Number(params.get("payroll_run_id") || 0);
    const callbackToken = String(params.get("callback_token") || "").trim();
    const selectedCsv = String(params.get("selected_user_ids") || "").trim();

    if (!status || !runId) {
        return;
    }

    const clearHostedParams = (): void => {
        const next = new URL(window.location.href);
        [
            "payroll_mock_payment_status",
            "payroll_run_id",
            "callback_token",
            "selected_user_ids",
            "mock_payment_status",
            "payment_uuid",
            "invoice_uuid",
            "settled_at",
        ].forEach((key) => next.searchParams.delete(key));
        window.history.replaceState({}, document.title, next.pathname + next.search + next.hash);
    };

    if (status !== "completed") {
        if (status === "failed") {
            toast("Pembayaran mock gateway belum berhasil. Silakan ulangi proses Pay via Gateway.", true);
        } else {
            toast("Pembayaran mock gateway belum selesai.", true);
        }
        clearHostedParams();
        return;
    }

    const ids = selectedCsv
        .split(",")
        .map((value) => Number(value))
        .filter((value) => Number.isFinite(value) && value > 0);

    if (!callbackToken || ids.length === 0) {
        toast("Sesi hosted payment tidak lengkap. Silakan ulangi pembayaran.", true);
        clearHostedParams();
        return;
    }

    try {
        const confirmResp = await apiRequest(
            "post",
            `/v1/hcm/payroll-runs/${runId}/mock-hosted-checkout/confirm`,
            {
                callbackToken,
                userIds: ids,
            },
        ) as ApiResponse<any>;

        if (!confirmResp.success) {
            toast(formatApiError(confirmResp, 400), true);
            clearHostedParams();
            return;
        }

        const resp = await apiRequest(
            "post",
            `/v1/hcm/payroll-runs/${runId}/disburse`,
            {
                userIds: ids,
                mockApprovalToken: callbackToken,
            },
        ) as ApiResponse<any>;

        if (!resp.success) {
            toast(formatApiError(resp, 400), true);
            clearHostedParams();
            return;
        }

        setPayrollReconciliationHint("");
        clearReconciliationDownloaded();
        toast(`Pembayaran gateway selesai (${resp.data?.gatewayReference || "OK"}).`, false);
        clearHostedParams();
        await loadPeriod(false);
    } catch (e: any) {
        toast(formatApiError(e.response?.data || {}, 500), true);
        clearHostedParams();
    }
}

async function resetPayments(): Promise<void> {
    const root = _getRoot();
    if (!root || !_state.currentRunId) return;

    const confirmed = (window as any).ArcavUi?.confirm
        ? await (window as any).ArcavUi.confirm(
            "Reset seluruh metadata pembayaran payroll run ini? Aksi ini khusus helper development.",
            "Reset Payments"
        )
        : false;
    if (!confirmed) {
        return;
    }

    const resetBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-reset-payments]");
    if (resetBtn) {
        resetBtn.disabled = true;
        resetBtn.textContent = "Resetting...";
    }

    try {
        const resp = await apiRequest("post", `/v1/hcm/payroll-runs/${_state.currentRunId}/reset-payments`) as ApiResponse<any>;
        if (!resp.success) {
            toast(formatApiError(resp, 400), true);
            return;
        }

        _state.currentRows = _state.currentRows.map((row) => ({
            ...row,
            paymentStatus: "unpaid",
            paidAt: null,
            gatewayReference: null,
        }));

        clearReconciliationDownloaded();
        updateRunUI((resp.data?.run || null) as PayrollRun | null, null);
        syncExportReconciliationButton();
        toast(`Reset pembayaran selesai (${String(resp.data?.resetLineCount || 0)} line direset).`, false);
    } catch (e: any) {
        toast(formatApiError(e.response?.data || {}, 500), true);
    } finally {
        if (resetBtn) {
            resetBtn.textContent = "Reset Pembayaran (DEV)";
            refreshSelectionSummary();
        }
    }
}

function bindEvents(): void {
    const root = _getRoot();
    if (!root || root.dataset.bound === "1") return;
    root.dataset.bound = "1";

    const yearInput = root.querySelector<HTMLInputElement>("[data-payroll-run-year]");
    const monthSelect = root.querySelector<HTMLSelectElement>("[data-payroll-run-month]");
    if (monthSelect && !monthSelect.value) {
        monthSelect.value = String(new Date().getMonth() + 1);
    }

    if (yearInput) {
        yearInput.readOnly = true;
    }
    if (monthSelect) {
        monthSelect.disabled = true;
    }

    root.addEventListener("click", (event) => {
        const calculateBtn = (event.target as HTMLElement).closest("[data-payroll-run-calculate]");
        if (calculateBtn) {
            event.preventDefault();
            void calculateDraft(false);
            return;
        }
        const exportBtn = (event.target as HTMLElement).closest("[data-payroll-run-export-evidence]") as HTMLElement | null;
        if (exportBtn) {
            event.preventDefault();
            void triggerExportReconciliation();
            return;
        }
        const disburseBtn = (event.target as HTMLElement).closest("[data-payroll-run-disburse]");
        if (disburseBtn) {
            event.preventDefault();
            openDisburseModal();
            return;
        }

        const voidBtn = (event.target as HTMLElement).closest("[data-payroll-run-void]");
        if (voidBtn) {
            event.preventDefault();
            void voidCurrentRun();
            return;
        }

        const resetBtn = (event.target as HTMLElement).closest("[data-payroll-run-reset-payments]");
        if (resetBtn) {
            event.preventDefault();
            void resetPayments();
            return;
        }

        const payOneBtn = (event.target as HTMLElement).closest("[data-payroll-run-pay-one]") as HTMLElement | null;
        if (payOneBtn) {
            event.preventDefault();
            const userId = Number(payOneBtn.getAttribute("data-payroll-run-pay-one") || 0);
            if (userId > 0) {
                root.querySelectorAll<HTMLInputElement>("[data-payroll-run-row-check]").forEach((checkbox) => {
                    checkbox.checked = Number(checkbox.value) === userId;
                });
                refreshSelectionSummary();
                openDisburseModal([userId]);
            }
            return;
        }

        const detailBtn = (event.target as HTMLElement).closest("[data-payroll-run-view-one]") as HTMLElement | null;
        if (detailBtn) {
            event.preventDefault();
            const userId = Number(detailBtn.getAttribute("data-payroll-run-view-one") || 0);
            if (userId > 0) {
                openEmployeeDetailModal(userId);
            }
            return;
        }
    });

    root.addEventListener("change", (event) => {
        const selectAll = (event.target as HTMLElement).closest("[data-payroll-run-select-all]") as HTMLInputElement | null;
        if (selectAll) {
            root.querySelectorAll<HTMLInputElement>("[data-payroll-run-row-check]").forEach((checkbox) => {
                if (!checkbox.disabled) {
                    checkbox.checked = selectAll.checked;
                }
            });
            refreshSelectionSummary();
            return;
        }

        const rowCheck = (event.target as HTMLElement).closest("[data-payroll-run-row-check]");
        if (rowCheck) {
            const checks = Array.from(root.querySelectorAll<HTMLInputElement>("[data-payroll-run-row-check]"));
            const selectAllInput = root.querySelector<HTMLInputElement>("[data-payroll-run-select-all]");
            if (selectAllInput) {
                selectAllInput.checked = checks.length > 0 && checks.every((checkbox) => checkbox.checked);
            }
            refreshSelectionSummary();
        }
    });

    const modal = document.getElementById("payroll_gateway_modal");
    modal?.querySelector<HTMLButtonElement>("[data-payroll-gateway-pay]")?.addEventListener("click", () => void disburseSelected());

    const settingsRoot = getPayrollSettingsRoot();
    settingsRoot?.querySelector<HTMLFormElement>("[data-payroll-settings-form]")?.addEventListener("submit", (event) => {
        event.preventDefault();
        void savePayrollSettings();
    });
    settingsRoot?.addEventListener("input", () => {
        const paydayInput = settingsRoot.querySelector<HTMLInputElement>("[data-payroll-settings-payday-day]");
        const cutoffInput = settingsRoot.querySelector<HTMLInputElement>("[data-payroll-settings-cutoff-offset]");
        const timezoneSelect = settingsRoot.querySelector<HTMLSelectElement>("[data-payroll-settings-timezone]");
        const disburseEarlyInput = settingsRoot.querySelector<HTMLInputElement>("[data-payroll-settings-disburse-early]");
        const holidayStrategySelect = settingsRoot.querySelector<HTMLSelectElement>("[data-payroll-settings-holiday-strategy]");
        if (!paydayInput || !cutoffInput || !timezoneSelect || !disburseEarlyInput || !holidayStrategySelect) {
            return;
        }
        _payrollSettingsState.settings = {
            paydayDay: parseIntOrNull(paydayInput.value) || 28,
            cutoffOffsetDays: parseIntOrNull(cutoffInput.value) || 0,
            payrollTimezone: timezoneSelect.value || "Asia/Jakarta",
            disburseBeforePaydayAllowed: disburseEarlyInput.checked,
            paydayHolidayStrategy: (holidayStrategySelect.value || "previous_working_day") as PayrollSettings["paydayHolidayStrategy"],
        };
        renderPayrollSettingsPreview();
    });
    settingsRoot?.addEventListener("change", () => {
        renderPayrollSettingsPreview();
    });

    bindWorkConfigurator();

    void settleMockHostedReturnAndDisburse();

    void loadPayrollSettings();
    void loadPeriod(false);
}

(window as any).payrollRunLoadPeriod = () => loadPeriod(false);
(window as any).payrollRunCalculateDraft = () => calculateDraft(false);
(window as any).payrollRunVoid = () => voidCurrentRun();
(window as any).payrollRunDisburse = () => openDisburseModal();

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindEvents);
} else {
    bindEvents();
}

