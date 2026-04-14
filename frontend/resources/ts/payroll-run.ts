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
    status: string;
    paymentStatus?: string;
    period?: { periodYear: number; periodMonth: number; status: string };
};
type EmployeeRow = {
    userId: number;
    name: string;
    gross: number;
    deductions: number;
    net: number;
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
        window.alert(msg);
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

function formatApiError(res: unknown, fallbackStatus: number): string {
    const r = res as { error?: { message?: string; code?: string }; message?: string };
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
    currentRows: EmployeeRow[];
    loading: boolean;
} = {
    currentPeriodId: null,
    currentRunId: null,
    currentRows: [],
    loading: false,
};

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
        disburseBtn.disabled = !_state.currentRunId || _state.currentRows.length === 0 || selectedIds.length === 0;
    }
    if (resetBtn) {
        resetBtn.disabled = !_state.currentRunId;
    }
}

function updateRunUI(runData: PayrollRun | null, lines: PayrollLine[] | null = null): void {
    const root = _getRoot();
    if (!root) return;

    const calculateBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-calculate]");
    const disburseBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-disburse]");
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

    if (calculateBtn) {
        calculateBtn.disabled = !_state.currentPeriodId || !!_state.currentRunId;
    }
    if (disburseBtn) {
        disburseBtn.disabled = !_state.currentRunId || _state.currentRows.length === 0;
    }

    if (emptyEl && (!runData || _state.currentRows.length === 0)) {
        emptyEl.textContent = runData
            ? "Belum ada karyawan payroll untuk periode ini. Gunakan Calculate Draft untuk refresh data aktif."
            : "Payroll dimuat otomatis. Jika draft belum ada, sistem akan menghitungnya untuk periode yang dipilih.";
        emptyEl.classList.remove("d-none");
        if (gridEl) gridEl.classList.add("d-none");
        return;
    }

    if (emptyEl) emptyEl.classList.add("d-none");
    if (!gridEl || !tbody) return;

    gridEl.classList.remove("d-none");
    tbody.innerHTML = _state.currentRows.map((row) => {
        const isPaid = row.paymentStatus === "paid";
        const paymentBadgeClass = row.paymentStatus === "paid" ? "bg-success" : "bg-light text-dark";
        const paymentLabel = row.paymentStatus === "paid" ? "Paid" : "Pending";
        const rowAction = isPaid
            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Telah Dibayar</span>'
            : `<button type="button" class="btn btn-sm btn-outline-success" data-payroll-run-pay-one="${row.userId}">Pay</button>`;
        return `
            <tr>
                <td>
                    <div class="form-check form-check-md">
                        <input class="form-check-input" type="checkbox" value="${row.userId}" data-payroll-run-row-check ${isPaid ? "disabled" : "checked"}>
                    </div>
                </td>
                <td>
                    <div class="fw-semibold text-dark">${row.name}</div>
                    <div class="text-muted small">UID: ${row.userId}</div>
                </td>
                <td class="text-end">${formatIdr(row.gross)}</td>
                <td class="text-end">${formatIdr(row.deductions)}</td>
                <td class="text-end fw-bold">${formatIdr(row.net)}</td>
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
        const unpaidRows = _state.currentRows.filter((row) => row.paymentStatus !== "paid");
        selectAll.checked = unpaidRows.length > 0;
        selectAll.disabled = unpaidRows.length === 0;
    }
    refreshSelectionSummary();
}

async function loadRunDetails(runId: number): Promise<void> {
    try {
        const resp = await apiRequest("get", `/v1/hcm/payroll-runs/${runId}`) as ApiResponse<{ run: PayrollRun; lines: PayrollLine[] }>;
        if (!resp.success) {
            showErr(formatApiError(resp, 400));
            return;
        }
        updateRunUI(resp.data.run, Array.isArray(resp.data.lines) ? resp.data.lines : []);
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

    try {
        const activeResp = await apiRequest("get", "/v1/hcm/payroll-periods/active") as ApiResponse<any>;
        if (!activeResp.success) {
            showErr(formatApiError(activeResp, 400));
            return;
        }

        const period = activeResp.data;
        if (period && period.periodYear) {
            yearInput.value = String(period.periodYear);
        }
        if (period && period.periodMonth) {
            monthSelect.value = String(period.periodMonth);
        }
        monthSelect.disabled = true;
        yearInput.readOnly = true;

        _state.currentPeriodId = Number(period.id || 0) || null;
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
            _state.currentRunId = Number(detailedPeriod.latestRun.id);
            await loadRunDetails(_state.currentRunId);
            return;
        }

        _state.currentRunId = null;
        updateRunUI(null, []);
        if (autoCalculateMissing) {
            await calculateDraft(true);
        }
    } catch (e: any) {
        showErr(formatApiError(e.response?.data || {}, 500));
    } finally {
        _state.loading = false;
    }
}

async function calculateDraft(silent = false): Promise<void> {
    const root = _getRoot();
    if (!root || !_state.currentPeriodId) return;

    const calculateBtn = root.querySelector<HTMLButtonElement>("[data-payroll-run-calculate]");
    if (calculateBtn) calculateBtn.disabled = true;

    try {
        const resp = await apiRequest("post", `/v1/hcm/payroll-periods/${_state.currentPeriodId}/calculate-draft`) as ApiResponse<any>;
        if (!resp.success) {
            toast(formatApiError(resp, 400), true);
            return;
        }
        _state.currentRunId = Number(resp.data?.run?.id || 0) || null;
        if (!silent) {
            toast("Draft payroll berhasil direfresh.", false);
        }
        if (_state.currentRunId) {
            await loadRunDetails(_state.currentRunId);
        }
    } catch (e: any) {
        toast(formatApiError(e.response?.data || {}, 500), true);
    } finally {
        if (calculateBtn) {
            calculateBtn.disabled = !_state.currentPeriodId || !!_state.currentRunId;
        }
    }
}

function populateGatewayModal(userIds: number[]): EmployeeRow[] {
    const modal = document.getElementById("payroll_gateway_modal");
    if (!modal) return [];

    const rows = _state.currentRows.filter((row) => userIds.includes(row.userId));
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
        payBtn.disabled = rows.length === 0 || !_state.currentRunId;
    }

    return rows;
}

function openDisburseModal(userIds?: number[]): void {
    const selectedIds = Array.isArray(userIds) && userIds.length ? userIds : getSelectedUserIds();
    if (!_state.currentRunId || selectedIds.length === 0) {
        toast("Pilih minimal satu karyawan untuk dibayar melalui gateway.", true);
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
            toast(formatApiError(resp, 400), true);
            return;
        }

        const selectedUserIds = Array.isArray(resp.data?.selectedUserIds)
            ? resp.data.selectedUserIds.map((value: unknown) => Number(value)).filter((value: number) => Number.isFinite(value) && value > 0)
            : ids;
        const paidSet = new Set<number>(selectedUserIds);
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
        toast(`Pembayaran gateway selesai (${resp.data?.gatewayReference || "OK"}).`, false);
        if ((window as any).bootstrap?.Modal) {
            (window as any).bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
    } catch (e: any) {
        toast(formatApiError(e.response?.data || {}, 500), true);
    } finally {
        if (payBtn) {
            payBtn.disabled = false;
            payBtn.textContent = "Pay now";
        }
    }
}

async function resetPayments(): Promise<void> {
    const root = _getRoot();
    if (!root || !_state.currentRunId) return;

    const confirmed = window.confirm("Reset seluruh metadata pembayaran payroll run ini? Aksi ini khusus helper development.");
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

        updateRunUI((resp.data?.run || null) as PayrollRun | null, null);
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

    void loadPeriod(true);
}

(window as any).payrollRunLoadPeriod = () => loadPeriod(true);
(window as any).payrollRunCalculateDraft = () => calculateDraft(false);
(window as any).payrollRunDisburse = () => openDisburseModal();

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindEvents);
} else {
    bindEvents();
}

