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
    meta?: { userName?: string };
};
type PayrollRun = {
    id: number;
    status?: string;
    paymentStatus?: string;
    finalizedAt?: string | null;
    period?: { periodYear: number; periodMonth: number; status: string };
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
    currentRows: EmployeeRow[];
    loading: boolean;
    /** Set after user completes CSV download for `currentRunId` (gate Pay via Gateway). */
    reconciliationDownloadedForRunId: number | null;
} = {
    currentPeriodId: null,
    currentRunId: null,
    currentRunStatus: null,
    currentRows: [],
    loading: false,
    reconciliationDownloadedForRunId: null,
};

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
        const canDisburse = !_state.currentRunId ||
            _state.currentRows.length === 0 ||
            selectedIds.length === 0 ||
            !hasDownloadedReconciliationForCurrentRun();
        console.log("[refreshSelectionSummary]", { runId: _state.currentRunId, rows: _state.currentRows.length, selected: selectedIds.length, downloaded: hasDownloadedReconciliationForCurrentRun(), disabled: canDisburse });
        disburseBtn.disabled = canDisburse;
    }
    if (resetBtn) {
        resetBtn.disabled = !_state.currentRunId;
    }
}

function syncExportReconciliationButton(): void {
    const root = _getRoot();
    if (!root) return;
    const exportBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-export-evidence]");
    if (exportBtn) {
        const st = String(_state.currentRunStatus || "").toLowerCase();
        const exportAllowed = !!_state.currentRunId && _state.currentRows.length > 0 && st === "draft";
        console.log("[syncExportReconciliationButton]", { runId: _state.currentRunId, rows: _state.currentRows.length, status: st, exportAllowed });
        exportBtn.disabled = !exportAllowed;
    }
}

function syncCalculateDraftButton(): void {
    const root = _getRoot();
    if (!root) return;
    const calculateBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-calculate]");
    if (!calculateBtn) return;
    const st = String(_state.currentRunStatus || "").toLowerCase();
    /** Backend mengizinkan hitung ulang / reuse draft (`reusedExistingDraft`) selama status `draft`. */
    const canCalculate = !!_state.currentPeriodId && (!_state.currentRunId || st === "draft");
    console.log("[syncCalculateDraftButton]", { periodId: _state.currentPeriodId, runId: _state.currentRunId, status: st, canCalculate });
    calculateBtn.disabled = !canCalculate;
}

function updateRunUI(runData: PayrollRun | null, lines: PayrollLine[] | null = null, specialRecipients: SpecialRecipients | null = null): void {
    const root = _getRoot();
    if (!root) return;

    if (runData) {
        _state.currentRunStatus = deriveRunLifecycleStatus(runData);
    } else if (!_state.currentRunId) {
        _state.currentRunStatus = null;
    }

    const empCountEl = root.querySelector<HTMLElement>("[data-payroll-run-emp-count]");
    const selectedCountEl = root.querySelector<HTMLElement>("[data-payroll-run-selected-count]");
    const lineCountEl = root.querySelector<HTMLElement>("[data-payroll-run-line-count]");
    const periodStatusEl = root.querySelector<HTMLElement>("[data-payroll-run-status]");
    const paymentStatusEl = root.querySelector<HTMLElement>("[data-payroll-run-payment-status]");
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

    syncCalculateDraftButton();
    syncExportReconciliationButton();

    if (emptyEl && (!runData || _state.currentRows.length === 0)) {
        emptyEl.textContent = runData
            ? "Belum ada karyawan payroll untuk periode ini. Gunakan Calculate Draft untuk refresh data aktif."
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
        }
    } catch (e: any) {
        toast(formatApiError(e.response?.data || {}, 500), true);
    } finally {
        syncCalculateDraftButton();
        syncExportReconciliationButton();
        refreshSelectionSummary();
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
    setText("[data-payroll-gateway-status]", (_state.currentRows.some((row) => row.paymentStatus === "paid") ? "Partial / Ongoing" : "Ready to pay"));

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
                return `<div class="d-flex justify-content-between ${cls}" style="font-size:0.78rem;padding:1px 0">
                    <span>${label}</span>
                    <span>${sign} ${formatIdr(l.amount)}</span>
                </div>`;
            };

            const addRows = additions.map((l) => renderLine(l, false)).join("");
            const dedRows = deductions.map((l) => renderLine(l, true)).join("");

            return `${addRows}${deductions.length > 0 && additions.length > 0 ? `<div style="border-top:1px dashed #dee2e6;margin:4px 0"></div>` : ""}${dedRows}`;
        };

        listEl.innerHTML = rows.map((row) => `
            <div class="list-group-item py-2">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <div class="fw-semibold">${row.name}</div>
                        <div class="text-muted" style="font-size:0.72rem">UID: ${row.userId}</div>
                    </div>
                    <strong>${formatIdr(row.net)}</strong>
                </div>
                <div class="ms-1 ps-2" style="border-left:3px solid #dee2e6">
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
        const resp = await apiRequest("post", `/v1/hcm/payroll-runs/${_state.currentRunId}/disburse`, { userIds: ids }) as ApiResponse<any>;
        if (!resp.success) {
            const code = getApiErrorCode(resp);
            if (code && code.startsWith("EXPORT_RECON_")) {
                setPayrollReconciliationHint(formatApiError(resp, 400));
            }
            toast(formatApiError(resp, 400), true);
            return;
        }

        setPayrollReconciliationHint("");
        clearReconciliationDownloaded();

        const selectedUserIds = Array.isArray(resp.data?.selectedUserIds)
            ? resp.data.selectedUserIds.map((value: unknown) => Number(value)).filter((value: number) => Number.isFinite(value) && value > 0)
            : ids;
        const paidSet = new Set<number>(selectedUserIds);
        const ineligibleCount = Array.isArray(resp.data?.ineligibleUserIds) ? resp.data.ineligibleUserIds.length : 0;
        const gatewayReference = String(resp.data?.gatewayReference || "");
        const paidAtIso = new Date().toISOString();

        _state.currentRows = _state.currentRows.map((row) => {
            if (!paidSet.has(row.userId)) {
                return row;
            }

            return {
                ...row,
                paymentStatus: "paid",
                paidAt: row.paidAt || paidAtIso,
                gatewayReference: row.gatewayReference || gatewayReference || null,
            };
        });

        updateRunUI((resp.data?.run || null) as PayrollRun | null, null);
        toast(`Pembayaran gateway selesai (${resp.data?.gatewayReference || "OK"})${ineligibleCount > 0 ? `, ${ineligibleCount} user tidak eligible dilewati.` : ""}.`, false);
        if ((window as any).bootstrap?.Modal) {
            (window as any).bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
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

    void loadPeriod(false);
}

(window as any).payrollRunLoadPeriod = () => loadPeriod(false);
(window as any).payrollRunCalculateDraft = () => calculateDraft(false);
(window as any).payrollRunDisburse = () => openDisburseModal();

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindEvents);
} else {
    bindEvents();
}

