import {
    RECON_ERROR_MESSAGES,
    apiRequest,
    downloadReconciliationEvidenceFile,
    formatApiError,
    formatIdr,
    formatLineAuditMeta,
    getApiErrorCode,
    isOvertimeLine,
    setPayrollReconciliationHint,
    setPayrollTaxAnomalyHint,
    showErr,
    toast,
} from "./helpers";
import {
    clearReconciliationDownloaded,
    deriveTaxAnomaliesFromLines,
    extractTaxGovernancePolicyFromLines,
    getPayrollRunRoot,
    getSelectedUserIds,
    hasDownloadedReconciliationForCurrentRun,
    hasMissingTaxProfileAnomaly,
    markReconciliationDownloadedForCurrentRun,
    missingTaxProfileCount,
    normalizeTaxAnomalies,
    payrollRunState,
    readActiveTenantContext,
} from "./shared";
import { isPostCutoffReviewOnlyMode, renderPayrollSettingsPreview } from "./settings";
import { autoGenerateWorkArrangementsFromRun, refreshWorkConfigurator } from "./work-config";
import { fetchLatestEvidence, refreshSelectionSummary, renderRunContextSummary, syncCalculateDraftButton, syncExportReconciliationButton } from "./workflow";
import { ApiResponse, EmployeeRow, PayrollLine, PayrollRun, SpecialRecipients, TenantContextSnapshot, deriveRunLifecycleStatus } from "./types";

export function periodLabel(year: number, month: number): string {
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
            overtime: 0,
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
            if (isOvertimeLine(line)) {
                current.overtime += Number(line.amount || 0);
            }
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

export function updateRunUI(runData: PayrollRun | null, lines: PayrollLine[] | null = null, specialRecipients: SpecialRecipients | null = null): void {
    const root = getPayrollRunRoot();
    if (!root) return;

    if (runData) {
        payrollRunState.currentRunStatus = deriveRunLifecycleStatus(runData);
        payrollRunState.currentPolicySnapshot = runData.policySnapshot || null;
        payrollRunState.currentTaxGovernancePolicy = runData.taxGovernancePolicy || extractTaxGovernancePolicyFromLines(lines) || null;
    } else if (!payrollRunState.currentRunId) {
        payrollRunState.currentRunStatus = null;
        payrollRunState.currentPolicySnapshot = null;
        payrollRunState.currentTaxGovernancePolicy = null;
        payrollRunState.currentTaxAnomalies = normalizeTaxAnomalies(null);
    }

    const employeeCountElement = root.querySelector<HTMLElement>("[data-payroll-run-emp-count]");
    const selectedCountElement = root.querySelector<HTMLElement>("[data-payroll-run-selected-count]");
    const lineCountElement = root.querySelector<HTMLElement>("[data-payroll-run-line-count]");
    const periodStatusElement = root.querySelector<HTMLElement>("[data-payroll-run-status]");
    const paymentStatusElement = root.querySelector<HTMLElement>("[data-payroll-run-payment-status]");
    const emptyElement = root.querySelector<HTMLElement>("[data-payroll-run-empty]");
    const gridElement = root.querySelector<HTMLElement>("[data-payroll-run-grid]");
    const tbody = gridElement?.querySelector("tbody");
    const selectAll = root.querySelector<HTMLInputElement>("[data-payroll-run-select-all]");

    if (Array.isArray(lines)) {
        payrollRunState.currentRows = aggregateRows(lines);
        payrollRunState.currentTaxAnomalies = deriveTaxAnomaliesFromLines(lines);

        const thrSet = new Set((Array.isArray(specialRecipients?.thrUserIds) ? specialRecipients.thrUserIds : []).map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0));
        const compensationSet = new Set((Array.isArray(specialRecipients?.compensationUserIds) ? specialRecipients.compensationUserIds : []).map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0));

        payrollRunState.currentRows = payrollRunState.currentRows.map((row) => ({
            ...row,
            receivesThr: thrSet.has(row.userId),
            receivesCompensation: compensationSet.has(row.userId),
        }));
    }

    if (employeeCountElement) employeeCountElement.textContent = String(payrollRunState.currentRows.length);
    if (selectedCountElement) selectedCountElement.textContent = "0";
    if (lineCountElement && Array.isArray(lines)) lineCountElement.textContent = String(lines.length);
    if (periodStatusElement) {
        const status = runData?.period?.status || "open";
        periodStatusElement.innerHTML = `<span class="badge bg-${status === "open" ? "warning" : "success"}">${String(status).toUpperCase()}</span>`;
    }
    if (paymentStatusElement) {
        const paymentStatus = runData?.paymentStatus || "unpaid";
        const badgeClass = paymentStatus === "paid" ? "success" : paymentStatus === "partial" ? "warning text-dark" : "secondary";
        paymentStatusElement.innerHTML = `<span class="badge bg-${badgeClass}">${String(paymentStatus).toUpperCase()}</span>`;
    }
    syncCalculateDraftButton();
    syncExportReconciliationButton();
    renderRunContextSummary();

    if (emptyElement && (!runData || payrollRunState.currentRows.length === 0)) {
        emptyElement.textContent = runData
            ? (String(payrollRunState.currentRunStatus || "") === "void"
                ? "Run sebelumnya sudah di-void. Gunakan Calculate Draft untuk membuat draft baru dari setup payroll terbaru."
                : "Belum ada karyawan payroll untuk periode ini. Gunakan Calculate Draft untuk refresh data aktif.")
            : "Klik Calculate Draft untuk membuat draft payroll. Setelah itu lakukan Export Reconciliation dan unduh file XLSX; penandaan pembayaran manual aktif hanya setelah unduhan selesai.";
        emptyElement.classList.remove("d-none");
        if (gridElement) gridElement.classList.add("d-none");
        refreshSelectionSummary();
        return;
    }

    if (emptyElement) emptyElement.classList.add("d-none");
    if (!gridElement || !tbody) return;

    gridElement.classList.remove("d-none");
    const canPayNow = hasDownloadedReconciliationForCurrentRun() && !isPostCutoffReviewOnlyMode() && !hasMissingTaxProfileAnomaly();
    tbody.innerHTML = payrollRunState.currentRows.map((row) => {
        const isPaid = row.paymentStatus === "paid";
        const paymentBadgeClass = row.paymentStatus === "paid" ? "bg-success" : "bg-light text-dark";
        const paymentLabel = row.paymentStatus === "paid" ? "Paid" : "Pending";
        const payAction = isPaid
            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Telah Dibayar</span>'
            : (!row.isEligible
                ? '<span class="badge bg-warning-subtle text-dark border border-warning-subtle">Tidak eligible (THP <= 0)</span>'
                : `<button type="button" class="btn btn-sm ${canPayNow ? 'btn-outline-success' : 'btn-outline-secondary'}" data-payroll-run-pay-one="${row.userId}" title="${canPayNow ? 'Tandai karyawan ini sudah dibayar manual' : 'Selesaikan Export Reconciliation dan unduh file XLSX terlebih dahulu.'}">Tandai Bayar</button>`);
        return `
            <tr>
                <td>
                    <div class="form-check form-check-md">
                        <input class="form-check-input" type="checkbox" value="${row.userId}" data-payroll-run-row-check ${(isPaid || !row.isEligible) ? "disabled" : "checked"}>
                    </div>
                </td>
                <td>
                    <div class="fw-semibold text-dark">${row.name}</div>
                    <div class="text-muted small">UID: ${row.userId}</div>
                </td>
                <td class="text-end">${formatIdr(row.gross)}</td>
                <td class="text-end text-info">${formatIdr(row.overtime)}</td>
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
                    <div class="d-inline-flex align-items-center justify-content-end gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-payroll-run-view-one="${row.userId}">Detail</button>
                        ${payAction}
                    </div>
                </td>
            </tr>
        `;
    }).join("");

    if (selectAll) {
        const unpaidRows = payrollRunState.currentRows.filter((row) => row.paymentStatus !== "paid" && row.isEligible);
        selectAll.checked = unpaidRows.length > 0;
        selectAll.disabled = unpaidRows.length === 0;
    }
    refreshSelectionSummary();
}

export function openEmployeeDetailModal(userId: number): void {
    const row = payrollRunState.currentRows.find((item) => item.userId === userId);
    const modal = document.getElementById("payroll_detail_modal");
    if (!row || !modal || !(window as any).bootstrap?.Modal) {
        return;
    }

    const root = getPayrollRunRoot();
    const year = Number(root?.querySelector<HTMLInputElement>("[data-payroll-run-year]")?.value || 0);
    const month = Number(root?.querySelector<HTMLSelectElement>("[data-payroll-run-month]")?.value || 0);
    const periodText = year > 0 && month > 0 ? periodLabel(year, month) : "—";
    const setText = (selector: string, value: string) => {
        const element = modal.querySelector<HTMLElement>(selector);
        if (element) element.textContent = value;
    };
    const setHtml = (selector: string, value: string) => {
        const element = modal.querySelector<HTMLElement>(selector);
        if (element) element.innerHTML = value;
    };

    setText("[data-payroll-detail-name]", row.name);
    setText("[data-payroll-detail-meta]", `UID: ${row.userId}`);
    setText("[data-payroll-detail-period]", periodText);
    setHtml("[data-payroll-detail-payment-status]", `Payment: <strong>${row.paymentStatus === "paid" ? "PAID" : "PENDING"}</strong>`);
    setHtml("[data-payroll-detail-eligibility]", `Status: <strong>${row.isEligible ? "Eligible" : "Tidak eligible (THP <= 0)"}</strong>`);
    setHtml("[data-payroll-detail-thr]", `THR: <strong>${row.receivesThr ? "Ya" : "-"}</strong>`);
    setHtml("[data-payroll-detail-compensation]", `Compensation: <strong>${row.receivesCompensation ? "Ya" : "-"}</strong>`);
    setText("[data-payroll-detail-gross]", formatIdr(row.gross));
    setText("[data-payroll-detail-overtime]", formatIdr(row.overtime));
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
                const isEmployerCost = line.category === "employer_cost_display";
                const kindLabel = isEmployerCost ? "Biaya Perusahaan" : (line.kind === "addition" ? "Addition" : "Deduction");
                const kindClass = isEmployerCost ? "bg-info-subtle text-info border border-info-subtle" : (line.kind === "addition" ? "bg-success-subtle text-success border border-success-subtle" : "bg-danger-subtle text-danger border border-danger-subtle");
                const affectsNetPay = line.affectsNetPay !== false;
                const overtimeBadge = isOvertimeLine(line) ? '<span class="badge bg-warning-subtle text-dark border border-warning-subtle ms-2">OT</span>' : "";
                const payLabel = (line.paymentStatus || "unpaid") === "paid" ? "PAID" : "UNPAID";
                const payClass = payLabel === "PAID" ? "bg-success-subtle text-success border border-success-subtle" : "bg-secondary-subtle text-dark border border-secondary-subtle";
                return `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <div class="fw-semibold">${label}${overtimeBadge}</div>
                            <div class="text-muted small">${line.componentCode || "-"}</div>
                            ${formatLineAuditMeta(line)}
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
        const response = await apiRequest("get", `/v1/hcm/payroll-runs/${runId}`) as ApiResponse<{ run: PayrollRun; lines: PayrollLine[]; specialRecipients?: SpecialRecipients; anomalies?: unknown }>;
        if (!response.success) {
            showErr(formatApiError(response, 400));
            return;
        }
        const lines = Array.isArray(response.data.lines) ? response.data.lines : [];
        payrollRunState.currentTaxAnomalies = normalizeTaxAnomalies(response.data?.anomalies || deriveTaxAnomaliesFromLines(lines));
        updateRunUI(response.data.run, lines, (response.data?.specialRecipients || null) as SpecialRecipients | null);
        void fetchLatestEvidence();
    } catch (error: any) {
        showErr(formatApiError(error.response?.data || {}, 500));
    }
}

export async function loadPeriod(autoCalculateMissing = true): Promise<void> {
    const root = getPayrollRunRoot();
    if (!root || payrollRunState.loading) return;

    const yearInput = root.querySelector<HTMLInputElement>("[data-payroll-run-year]");
    const monthSelect = root.querySelector<HTMLSelectElement>("[data-payroll-run-month]");
    if (!yearInput || !monthSelect) return;

    payrollRunState.loading = true;
    payrollRunState.activeTenantContext = readActiveTenantContext();
    renderRunContextSummary();
    showErr("");

    try {
        const activeResponse = await apiRequest("get", "/v1/hcm/payroll-periods/active") as ApiResponse<any>;
        if (!activeResponse.success) {
            showErr(formatApiError(activeResponse, 400));
            return;
        }

        const period = activeResponse.data;
        if (period && period.companyId) {
            const serverContext: TenantContextSnapshot = {
                companyId: Number(period.companyId) || null,
                companyName: (typeof period.companyName === "string" && period.companyName.trim())
                    ? period.companyName.trim()
                    : ((typeof period.companyCode === "string" && period.companyCode.trim()) ? period.companyCode.trim() : payrollRunState.activeTenantContext.companyName),
            };
            if (serverContext.companyId) {
                payrollRunState.activeTenantContext = serverContext;
                renderRunContextSummary();
            }
        }

        if (period?.periodYear) {
            yearInput.value = String(period.periodYear);
        }
        if (period?.periodMonth) {
            monthSelect.value = String(period.periodMonth);
        }
        monthSelect.disabled = true;
        yearInput.readOnly = true;

        payrollRunState.currentPeriodId = Number(period.id || 0) || null;
        if (!payrollRunState.currentPeriodId) {
            showErr("Periode payroll tidak valid.");
            return;
        }

        const detailResponse = await apiRequest("get", `/v1/hcm/payroll-periods/${payrollRunState.currentPeriodId}`) as ApiResponse<any>;
        if (!detailResponse.success) {
            showErr(formatApiError(detailResponse, 400));
            return;
        }

        const detailedPeriod = detailResponse.data;
        const statusElement = root.querySelector<HTMLElement>("[data-payroll-run-status]");
        if (statusElement) {
            statusElement.innerHTML = `<span class="badge bg-${detailedPeriod.status === "open" ? "warning" : "success"}">${String(detailedPeriod.status).toUpperCase()}</span>`;
        }

        if (detailedPeriod.latestRun && detailedPeriod.latestRun.id) {
            clearReconciliationDownloaded();
            payrollRunState.currentRunId = Number(detailedPeriod.latestRun.id);
            payrollRunState.currentRunStatus = deriveRunLifecycleStatus(detailedPeriod.latestRun);
            await loadRunDetails(payrollRunState.currentRunId);
            return;
        }

        payrollRunState.currentRunId = null;
        payrollRunState.currentRunStatus = null;
        clearReconciliationDownloaded();
        updateRunUI(null, []);
        if (autoCalculateMissing) {
            await calculateDraft(true);
        }
    } catch (error: any) {
        showErr(formatApiError(error.response?.data || {}, 500));
    } finally {
        payrollRunState.loading = false;
        syncCalculateDraftButton();
        syncExportReconciliationButton();
        refreshSelectionSummary();
        if (document.querySelector("[data-payroll-work-config-panel]") && !document.querySelector("[data-payroll-work-config-panel]")?.classList.contains("d-none")) {
            void refreshWorkConfigurator();
        }
        renderPayrollSettingsPreview();
    }
}

export async function calculateDraft(silent = false): Promise<void> {
    const root = getPayrollRunRoot();
    if (!root || !payrollRunState.currentPeriodId) return;

    const calculateButton = root.querySelector<HTMLButtonElement>("[data-payroll-run-calculate]");
    if (calculateButton) calculateButton.disabled = true;

    try {
        clearReconciliationDownloaded();
        const response = await apiRequest("post", `/v1/hcm/payroll-periods/${payrollRunState.currentPeriodId}/calculate-draft`) as ApiResponse<any>;
        if (!response.success) {
            toast(formatApiError(response, 400), true);
            return;
        }
        payrollRunState.currentRunId = Number(response.data?.run?.id || 0) || null;
        payrollRunState.currentTaxAnomalies = normalizeTaxAnomalies(response.data?.anomalies);
        if (response.data?.run) {
            payrollRunState.currentRunStatus = deriveRunLifecycleStatus(response.data.run);
        }
        if (payrollRunState.currentTaxAnomalies.missingTaxProfileUserCount > 0) {
            toast(`Anomali PPh21 terdeteksi pada ${payrollRunState.currentTaxAnomalies.missingTaxProfileUserCount} karyawan. Lengkapi Tax Employee Profiles sebelum export/payment.`, true);
        }
        if (!silent) {
            toast("Draft payroll berhasil direfresh.", false);
        }
        if (payrollRunState.currentRunId) {
            await loadRunDetails(payrollRunState.currentRunId);
            try {
                const summary = await autoGenerateWorkArrangementsFromRun({ showToast: false, useWorkConfigError: false });
                if (summary.created > 0) {
                    toast(`Auto-assignment payroll aktif: ${summary.created} dibuat, ${summary.skipped} dilewati.`, false);
                }
            } catch (error: any) {
                console.warn("[payroll-run] auto work assignment skipped", error?.message || error);
            }
        }
    } catch (error: any) {
        toast(formatApiError(error.response?.data || {}, 500), true);
    } finally {
        syncCalculateDraftButton();
        syncExportReconciliationButton();
        refreshSelectionSummary();
    }
}

function populateGatewayModal(userIds: number[]): EmployeeRow[] {
    const modal = document.getElementById("payroll_gateway_modal");
    if (!modal) return [];

    const rows = payrollRunState.currentRows.filter((row) => userIds.includes(row.userId) && row.isEligible);
    const totalGross = rows.reduce((sum, row) => sum + row.gross, 0);
    const totalOvertime = rows.reduce((sum, row) => sum + row.overtime, 0);
    const totalDeductions = rows.reduce((sum, row) => sum + row.deductions, 0);
    const totalNet = rows.reduce((sum, row) => sum + row.net, 0);
    const periodText = (() => {
        const root = getPayrollRunRoot();
        const year = root?.querySelector<HTMLInputElement>("[data-payroll-run-year]")?.value || "";
        const month = Number(root?.querySelector<HTMLSelectElement>("[data-payroll-run-month]")?.value || 0);
        return year && month ? periodLabel(Number(year), month) : "—";
    })();

    const setText = (selector: string, value: string) => {
        const element = modal.querySelector<HTMLElement>(selector);
        if (element) element.textContent = value;
    };
    setText("[data-payroll-gateway-period]", periodText);
    setText("[data-payroll-gateway-count]", String(rows.length));
    setText("[data-payroll-gateway-gross]", formatIdr(totalGross));
    setText("[data-payroll-gateway-overtime]", formatIdr(totalOvertime));
    setText("[data-payroll-gateway-deductions]", formatIdr(totalDeductions));
    setText("[data-payroll-gateway-total]", formatIdr(totalNet));
    const statusElement = modal.querySelector<HTMLElement>("[data-payroll-gateway-status]");
    if (statusElement) {
        const hasPaid = payrollRunState.currentRows.some((row) => row.paymentStatus === "paid");
        statusElement.textContent = hasPaid ? "PARTIAL / TERCATAT" : "SIAP DITANDAI MANUAL";
        statusElement.className = `badge ${hasPaid ? "bg-info text-dark border" : "bg-success"}`;
    }

    const listElement = modal.querySelector<HTMLElement>("[data-payroll-gateway-list]");
    if (listElement) {
        const renderComponents = (row: EmployeeRow): string => {
            const sorted = row.lines.filter((line) => line.affectsNetPay !== false).sort((a, b) => {
                if (a.kind !== b.kind) return a.kind === "addition" ? -1 : 1;
                return (a.sortOrder ?? 99) - (b.sortOrder ?? 99);
            });
            const additions = sorted.filter((line) => line.kind === "addition");
            const deductions = sorted.filter((line) => line.kind === "deduction");
            const renderLine = (line: PayrollLine, isDeduction: boolean): string => {
                const label = line.componentName || line.componentCode || (isDeduction ? "Potongan" : "Penghasilan");
                const sign = isDeduction ? "−" : "+";
                const cls = isDeduction ? "text-danger" : "text-success";
                const overtimeBadge = !isDeduction && isOvertimeLine(line) ? '<span class="badge bg-warning-subtle text-dark border border-warning-subtle ms-2">OT</span>' : "";
                return `<div class="d-flex align-items-start justify-content-between ${cls} small py-1"><span>${label}${overtimeBadge}</span><span class="fw-semibold ms-3 text-nowrap">${sign} ${formatIdr(line.amount)}</span></div>`;
            };
            const additionRows = additions.length ? `<div class="mb-2"><div class="small text-muted text-uppercase fw-semibold mb-1">Penambah</div>${additions.map((line) => renderLine(line, false)).join("")}</div>` : "";
            const deductionRows = deductions.length ? `<div><div class="small text-muted text-uppercase fw-semibold mb-1">Pengurang</div>${deductions.map((line) => renderLine(line, true)).join("")}</div>` : "";
            return `${additionRows}${additionRows && deductionRows ? '<div class="border-top pt-2 mt-2"></div>' : ""}${deductionRows}`;
        };

        listElement.innerHTML = rows.map((row) => `
            <div class="list-group-item py-3 px-3">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2">
                    <div>
                        <div class="fw-semibold text-dark">${row.name}</div>
                        <div class="text-muted small">UID: ${row.userId}</div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Overtime</div>
                        <div class="fw-semibold text-info">${formatIdr(row.overtime)}</div>
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

    const payButton = modal.querySelector<HTMLButtonElement>("[data-payroll-gateway-pay]");
    if (payButton) {
        payButton.dataset.userIds = userIds.join(",");
        payButton.disabled = rows.length === 0 || !payrollRunState.currentRunId || !hasDownloadedReconciliationForCurrentRun();
        payButton.textContent = "Simpan pembayaran manual";
    }

    return rows;
}

export function openReconciliationPreviewModal(): void {
    if (!payrollRunState.currentRunId) {
        toast("No payroll run selected", true);
        return;
    }
    if (payrollRunState.currentRows.length === 0) {
        toast("Belum ada baris payroll. Lakukan Calculate Draft terlebih dahulu.", true);
        return;
    }
    if (isPostCutoffReviewOnlyMode()) {
        setPayrollReconciliationHint("Mode post-cutoff saat ini bersifat review-only. Calculate Draft tetap boleh untuk cek data, tetapi export dan penandaan pembayaran manual menunggu tenggat payday sesuai policy run aktif.");
        toast("Periode saat ini post-cutoff review-only. Export reconciliation untuk payment menunggu payday.", true);
        return;
    }
    if (String(payrollRunState.currentRunStatus || "").toLowerCase() !== "draft") {
        toast("Export reconciliation hanya untuk payroll run berstatus draft.", true);
        return;
    }

    const modal = document.getElementById("payroll_reconciliation_preview_modal");
    if (!modal) return;

    const rows = payrollRunState.currentRows.filter((row) => row.lineCount > 0);
    const totalGross = rows.reduce((sum, row) => sum + (row.gross || 0), 0);
    const totalOvertime = rows.reduce((sum, row) => sum + (row.overtime || 0), 0);
    const totalDeductions = rows.reduce((sum, row) => sum + (row.deductions || 0), 0);
    const totalNet = rows.reduce((sum, row) => sum + (row.net || 0), 0);
    const formatValue = (value: number) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(value);

    const periodElement = modal.querySelector("[data-recon-preview-period]");
    const countElement = modal.querySelector("[data-recon-preview-count]");
    const netElement = modal.querySelector("[data-recon-preview-net]");
    const grossElement = modal.querySelector("[data-recon-preview-gross]");
    const overtimeElement = modal.querySelector("[data-recon-preview-overtime]");
    const tbody = modal.querySelector("[data-recon-preview-body]");
    const root = getPayrollRunRoot();
    const year = Number(root?.querySelector<HTMLInputElement>("[data-payroll-run-year]")?.value || 0);
    const month = Number(root?.querySelector<HTMLSelectElement>("[data-payroll-run-month]")?.value || 0);
    const periodText = year > 0 && month > 0 ? periodLabel(year, month) : "—";

    if (periodElement) periodElement.textContent = periodText;
    if (countElement) countElement.textContent = String(rows.length);
    if (netElement) netElement.textContent = formatValue(totalNet);
    if (grossElement) grossElement.textContent = formatValue(totalGross);
    if (overtimeElement) overtimeElement.textContent = formatValue(totalOvertime);

    if (tbody) {
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada baris payroll dengan komponen.</td></tr>';
        } else {
            tbody.innerHTML = rows.map((row) => `
                <tr>
                    <td>
                        <div class="fw-semibold">${row.name || "—"}</div>
                        <div class="text-muted small">${row.userId}</div>
                    </td>
                    <td class="text-end">${formatValue(row.gross || 0)}</td>
                    <td class="text-end text-info">${formatValue(row.overtime || 0)}</td>
                    <td class="text-end text-danger">${formatValue(row.deductions || 0)}</td>
                    <td class="text-end fw-semibold text-primary">${formatValue(row.net || 0)}</td>
                    <td class="text-center"><span class="badge bg-light text-dark border">${row.lineCount}</span></td>
                    <td class="text-center">${row.receivesThr ? '<span class="badge bg-success">Ya</span>' : '<span class="badge bg-light text-muted border">Tidak</span>'}</td>
                    <td class="text-center"><span class="badge bg-light text-dark border">${row.paymentStatus || "—"}</span></td>
                </tr>`).join("");
            tbody.innerHTML += `
                <tr class="table-light fw-semibold">
                    <td>Total (${rows.length} karyawan)</td>
                    <td class="text-end">${formatValue(totalGross)}</td>
                    <td class="text-end text-info">${formatValue(totalOvertime)}</td>
                    <td class="text-end text-danger">${formatValue(totalDeductions)}</td>
                    <td class="text-end text-primary">${formatValue(totalNet)}</td>
                    <td colspan="3"></td>
                </tr>`;
        }
    }

    const { Modal } = window.bootstrap as any;
    Modal.getOrCreateInstance(modal).show();
}

export async function triggerExportReconciliation(): Promise<void> {
    if (!payrollRunState.currentRunId) {
        toast("No payroll run selected", true);
        return;
    }
    if (payrollRunState.currentRows.length === 0) {
        toast("Belum ada baris payroll. Lakukan Calculate Draft terlebih dahulu.", true);
        return;
    }
    if (isPostCutoffReviewOnlyMode()) {
        setPayrollReconciliationHint("Mode post-cutoff saat ini bersifat review-only. Calculate Draft tetap boleh untuk cek data, tetapi export dan penandaan pembayaran manual menunggu tenggat payday sesuai policy run aktif.");
        toast("Periode saat ini post-cutoff review-only. Export reconciliation untuk payment menunggu payday.", true);
        return;
    }
    if (String(payrollRunState.currentRunStatus || "").toLowerCase() !== "draft") {
        toast("Export reconciliation hanya untuk payroll run berstatus draft.", true);
        return;
    }

    try {
        const filterPayload = {
            periods: payrollRunState.currentRows.filter((row) => row.lineCount > 0).map((row) => ({ userId: row.userId })),
        };
        const response = await apiRequest("POST", "/v1/reconciliation/exports", {
            featureKey: "payroll_run",
            actionKey: "disburse",
            scopeRef: String(payrollRunState.currentRunId),
            filterPayload,
            fileFormat: "xlsx",
        }) as any;

        if (response && response.data && response.data.id) {
            toast("Export reconciliation berhasil dibuat", false);
            try {
                await downloadReconciliationEvidenceFile(Number(response.data.id), response.data.filePath);
                markReconciliationDownloadedForCurrentRun();
                setPayrollReconciliationHint("");
            } catch (downloadError) {
                console.warn("Reconciliation file download failed:", downloadError);
                clearReconciliationDownloaded();
                toast("Evidence tersimpan, tetapi unduh file gagal. Penandaan pembayaran manual tetap terkunci sampai unduhan berhasil.", true);
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
            const message = RECON_ERROR_MESSAGES[errorCode];
            if (message) {
                setPayrollReconciliationHint(message);
                return;
            }
        }
        toast(`Error: ${error?.message || "Unknown error"}`, true);
    }
}

export function openDisburseModal(userIds?: number[]): void {
    const selectedIds = Array.isArray(userIds) && userIds.length ? userIds : getSelectedUserIds();
    if (!payrollRunState.currentRunId || selectedIds.length === 0) {
        toast("Pilih minimal satu karyawan untuk ditandai selesai dibayar.", true);
        return;
    }
    if (isPostCutoffReviewOnlyMode()) {
        setPayrollReconciliationHint("Mode post-cutoff saat ini bersifat review-only. Calculate Draft tetap boleh untuk cek data, tetapi export dan penandaan pembayaran manual menunggu tenggat payday sesuai policy run aktif.");
        toast("Periode saat ini post-cutoff review-only. Penandaan pembayaran manual menunggu payday sesuai policy run aktif.", true);
        return;
    }
    if (hasMissingTaxProfileAnomaly()) {
        const totalMissing = missingTaxProfileCount();
        setPayrollTaxAnomalyHint(`Terdeteksi ${totalMissing} profil PPh21 karyawan belum lengkap pada run ini. Penandaan pembayaran manual dikunci sampai data dilengkapi dan draft dihitung ulang.`);
        toast("Pembayaran manual dikunci karena masih ada profil PPh21 karyawan yang belum lengkap.", true);
        return;
    }
    if (!hasDownloadedReconciliationForCurrentRun()) {
        setPayrollReconciliationHint("Urutan wajib: Calculate Draft → Export Reconciliation → unduh file XLSX → tandai dibayar manual.");
        toast("Selesaikan Export Reconciliation dan unduh file XLSX terlebih dahulu.", true);
        return;
    }
    const modal = document.getElementById("payroll_gateway_modal");
    if (!modal || !(window as any).bootstrap?.Modal) {
        return;
    }
    populateGatewayModal(selectedIds);
    (window as any).bootstrap.Modal.getOrCreateInstance(modal).show();
}

export async function disburseSelected(): Promise<void> {
    const modal = document.getElementById("payroll_gateway_modal");
    if (!modal || !payrollRunState.currentRunId) return;

    if (isPostCutoffReviewOnlyMode()) {
        setPayrollReconciliationHint("Mode post-cutoff saat ini bersifat review-only. Calculate Draft tetap boleh untuk cek data, tetapi export dan penandaan pembayaran manual menunggu tenggat payday sesuai policy run aktif.");
        toast("Periode saat ini post-cutoff review-only. Penandaan pembayaran manual menunggu payday sesuai policy run aktif.", true);
        return;
    }

    if (hasMissingTaxProfileAnomaly()) {
        setPayrollTaxAnomalyHint(`Terdeteksi ${missingTaxProfileCount()} profil PPh21 karyawan belum lengkap pada run ini. Penandaan pembayaran manual dikunci sampai data dilengkapi dan draft dihitung ulang.`);
        toast("Pembayaran manual diblokir karena ada profil PPh21 yang belum lengkap.", true);
        return;
    }

    const payButton = modal.querySelector<HTMLButtonElement>("[data-payroll-gateway-pay]");
    const ids = String(payButton?.dataset.userIds || "").split(",").map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0);
    if (ids.length === 0) {
        toast("Tidak ada karyawan yang dipilih untuk penandaan pembayaran manual.", true);
        return;
    }

    if (payButton) {
        payButton.disabled = true;
        payButton.textContent = "Menyimpan...";
    }

    try {
        const response = await apiRequest("post", `/v1/hcm/payroll-runs/${payrollRunState.currentRunId}/disburse`, { userIds: ids }) as ApiResponse<any>;
        if (!response.success) {
            toast(formatApiError(response, 400), true);
            return;
        }

        const data = response.data;
        let message = `Pembayaran manual tercatat (${data?.gatewayReference || "OK"}).`;
        if (data?.skippedAlreadyPaidUserIds && data.skippedAlreadyPaidUserIds.length > 0) {
            message += ` (${data.skippedAlreadyPaidUserIds.length} karyawan diskip karena sudah dibayar).`;
        }
        
        const modalApi = (window as any).bootstrap?.Modal;
        if (modalApi?.getInstance && modal) {
            modalApi.getInstance(modal)?.hide?.();
        }
        setPayrollReconciliationHint("");
        clearReconciliationDownloaded();
        toast(message, false);
        await loadPeriod(false);
    } catch (error: any) {
        const code = getApiErrorCode(error.response?.data || {});
        if (code && code.startsWith("EXPORT_RECON_")) {
            setPayrollReconciliationHint(formatApiError(error.response?.data || {}, 500));
        }
        toast(formatApiError(error.response?.data || {}, 500), true);
    } finally {
        if (payButton) {
            const canPay = !!payrollRunState.currentRunId && ids.length > 0 && hasDownloadedReconciliationForCurrentRun();
            payButton.disabled = !canPay;
            payButton.textContent = "Simpan pembayaran manual";
        }
    }
}

export async function resetPayments(): Promise<void> {
    const root = getPayrollRunRoot();
    if (!root || !payrollRunState.currentRunId) return;

    const confirmed = (window as any).ArcavUi?.confirm
        ? await (window as any).ArcavUi.confirm("Reset seluruh metadata pembayaran payroll run ini? Aksi ini khusus helper development.", "Reset Payments")
        : false;
    if (!confirmed) {
        return;
    }

    const resetButton = root.querySelector<HTMLButtonElement>("[data-payroll-run-reset-payments]");
    if (resetButton) {
        resetButton.disabled = true;
        resetButton.textContent = "Resetting...";
    }

    try {
        const response = await apiRequest("post", `/v1/hcm/payroll-runs/${payrollRunState.currentRunId}/reset-payments`) as ApiResponse<any>;
        if (!response.success) {
            toast(formatApiError(response, 400), true);
            return;
        }

        payrollRunState.currentRunId = null;
        payrollRunState.currentRunStatus = null;
        payrollRunState.currentRows = [];

        clearReconciliationDownloaded();
        updateRunUI(null, []);
        syncExportReconciliationButton();
        toast(`Reset pembayaran selesai (${String(response.data?.resetLineCount || 0)} line direset). Jalankan Calculate Draft untuk membuat run baru.`, false);
    } catch (error: any) {
        toast(formatApiError(error.response?.data || {}, 500), true);
    } finally {
        if (resetButton) {
            resetButton.textContent = "Reset Pembayaran (DEV)";
            refreshSelectionSummary();
        }
    }
}