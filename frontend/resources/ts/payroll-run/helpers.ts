import { PayrollLine, WorkflowBadge } from "./types";
import { getPayrollRunRoot, getWorkConfigRoot } from "./shared";

export const RECON_ERROR_MESSAGES: Record<string, string> = {
    EXPORT_RECON_REQUIRED: "Sebelum lanjut pembayaran, lakukan export reconciliation terbaru untuk payroll run ini.",
    EXPORT_RECON_EXPIRED: "Evidence reconciliation sudah kedaluwarsa. Silakan export ulang data terbaru.",
    EXPORT_RECON_SCOPE_MISMATCH: "Evidence reconciliation tidak sesuai scope run saat ini. Gunakan evidence yang cocok.",
    EXPORT_RECON_STALE_DATA: "Data payroll berubah sejak export terakhir. Silakan export ulang lalu lanjutkan.",
};

export function formatIdr(value: number): string {
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(value);
}

export function isOvertimeLine(line: PayrollLine): boolean {
    return String(line.componentCode || "").toLowerCase() === "upah_lembur";
}

export function formatLineAuditMeta(line: PayrollLine): string {
    const meta = line.meta || {};
    const bits: string[] = [];
    const resolvedRate = Number.isFinite(Number(meta.ratePercent))
        ? Number(meta.ratePercent)
        : (Number.isFinite(Number(meta.taxRateApplied)) ? Number(meta.taxRateApplied) : null);
    const resolvedBasis = Number.isFinite(Number(meta.basisAmount))
        ? Number(meta.basisAmount)
        : (Number.isFinite(Number(meta.monthlyTaxableGross)) ? Number(meta.monthlyTaxableGross) : null);

    if (resolvedRate !== null) {
        bits.push(`Rate ${resolvedRate.toFixed(4)}%`);
    }
    if (resolvedBasis !== null) {
        bits.push(`Basis ${formatIdr(resolvedBasis)}`);
    }
    if (meta.capApplied && Number.isFinite(Number(meta.salaryCap))) {
        bits.push(`Cap ${formatIdr(Number(meta.salaryCap))}`);
    }
    if (Number.isFinite(Number(meta.riskCategory))) {
        bits.push(`Risk Cat ${Number(meta.riskCategory)}`);
    }
    if (typeof meta.pph21TerCategory === "string" && meta.pph21TerCategory.trim() !== "") {
        bits.push(`TER ${meta.pph21TerCategory.trim().toUpperCase()}`);
    }
    if (typeof meta.taxStatusUsed === "string" && meta.taxStatusUsed.trim() !== "") {
        bits.push(`Tax Status ${meta.taxStatusUsed.trim().toUpperCase()}`);
    }
    if (resolvedRate !== null && resolvedBasis !== null && line.kind === "deduction") {
        const estimatedAmount = Math.round((resolvedBasis * (resolvedRate / 100)) * 100) / 100;
        bits.push(`~ ${formatIdr(estimatedAmount)} (${resolvedRate.toFixed(4)}% × ${formatIdr(resolvedBasis)})`);
    }

    const auditLine = bits.length > 0
        ? `<div class="text-muted small mt-1">${bits.join(" • ")}</div>`
        : "";
    const employerInfo = line.category === "employer_cost_display"
        ? '<div class="text-info small mt-1">Info slip perusahaan, tidak mengubah THP karyawan.</div>'
        : "";

    return auditLine + employerInfo;
}

export function toast(message: string, danger: boolean): void {
    const ui = (window as unknown as { ArcavUi?: { showToast?: (m: string, t: string) => void } }).ArcavUi;
    if (ui && ui.showToast) {
        ui.showToast(message, danger ? "danger" : "success");
        return;
    }

    const fallback = document.querySelector<HTMLElement>("[data-payroll-run-error]");
    if (fallback) {
        fallback.classList.remove("d-none", "alert-danger", "alert-success");
        fallback.classList.add(danger ? "alert-danger" : "alert-success");
        fallback.textContent = message;
        window.setTimeout(() => {
            fallback.classList.add("d-none");
        }, 3200);
        return;
    }

    if (danger) {
        console.warn(message);
    }
}

export function apiRequest(method: string, url: string, data?: unknown): Promise<unknown> {
    const authApi = (window as unknown as { AuthApi?: { request?: (m: string, p: string, d?: unknown) => Promise<unknown> } }).AuthApi;
    if (authApi && authApi.request) {
        const path = url.replace(/^\/v1/, "");
        return authApi.request(method, path, data).then((response: any) => {
            if (response && typeof response === "object" && "data" in response) {
                return response.data;
            }
            return response;
        });
    }

    const axios = (window as unknown as { axios?: (config: object) => Promise<{ data: unknown }> }).axios;
    if (axios) {
        return axios({ method, url, data, withCredentials: true }).then((response) => response.data).catch((error: any) => {
            if (error.response && error.response.data) {
                return error.response.data;
            }
            throw error;
        });
    }

    return fetch(url, {
        method: method.toUpperCase(),
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        credentials: "same-origin",
        body: data ? JSON.stringify(data) : undefined,
    }).then((response) => response.json().catch(() => ({})).then((payload) => {
        if (!response.ok) {
            const error = new Error("Request failed") as any;
            error.response = { data: payload, status: response.status };
            throw error;
        }
        return payload;
    }));
}

export function reconciliationExportFileName(filePath: string | undefined | null, evidenceId: number): string {
    if (filePath && typeof filePath === "string") {
        const parts = filePath.split("/").filter(Boolean);
        const last = parts[parts.length - 1];
        if (last) {
            return last;
        }
    }
    return `reconciliation-export-${evidenceId}.xlsx`;
}

export async function downloadReconciliationEvidenceFile(evidenceId: number, filePath?: string | null): Promise<void> {
    const authApi = (window as unknown as { AuthApi?: { downloadV1Binary?: (path: string, filename?: string) => Promise<void> } }).AuthApi;
    if (!authApi || typeof authApi.downloadV1Binary !== "function") {
        throw new Error("AuthApi.downloadV1Binary tidak tersedia");
    }
    const fileName = reconciliationExportFileName(filePath ?? undefined, evidenceId);
    await authApi.downloadV1Binary(`/reconciliation/exports/${evidenceId}/download`, fileName);
}

export function formatApiError(response: unknown, fallbackStatus: number): string {
    const record = response as { error?: { message?: string; code?: string }; message?: string };
    const code = record?.error?.code;
    if (code && RECON_ERROR_MESSAGES[code]) {
        return RECON_ERROR_MESSAGES[code];
    }
    if (record?.error?.message) {
        return record.error.message;
    }
    if (record?.message) {
        return record.message;
    }
    return `Terjadi kesalahan (Kode: ${fallbackStatus})`;
}

export function setBadgeState(element: HTMLElement | null, state: WorkflowBadge): void {
    if (!element) {
        return;
    }
    element.className = `badge ${state.badgeClass}`;
    element.textContent = state.label;
}

export function toneButton(button: HTMLButtonElement | null, activeClass: string, inactiveClass: string, isActive: boolean): void {
    if (!button) {
        return;
    }
    button.classList.remove(...activeClass.split(" "), ...inactiveClass.split(" "));
    button.classList.add(...(isActive ? activeClass : inactiveClass).split(" "));
}

export function parseIntOrNull(value: string | null | undefined): number | null {
    const parsed = Number.parseInt(String(value || "").trim(), 10);
    return Number.isFinite(parsed) ? parsed : null;
}

export function getApiErrorCode(response: unknown): string | null {
    const record = response as { error?: { code?: string } };
    return typeof record?.error?.code === "string" ? record.error.code : null;
}

function setHint(selector: string, message: string): void {
    const root = getPayrollRunRoot();
    if (!root) return;
    const hintElement = root.querySelector<HTMLElement>(selector);
    if (!hintElement) return;
    if (!message) {
        hintElement.classList.add("d-none");
        hintElement.textContent = "";
        return;
    }
    hintElement.textContent = message;
    hintElement.classList.remove("d-none");
}

export function setPayrollReconciliationHint(message: string): void {
    setHint("[data-payroll-run-reconciliation-hint]", message);
}

export function setPayrollTenantHint(message: string): void {
    setHint("[data-payroll-run-tenant-hint]", message);
}

export function setPayrollTaxPolicyHint(message: string): void {
    setHint("[data-payroll-run-tax-policy-hint]", message);
}

export function setPayrollTaxAnomalyHint(message: string): void {
    setHint("[data-payroll-run-tax-anomaly-hint]", message);
}

export function showErr(message: string): void {
    const root = getPayrollRunRoot();
    if (!root) return;
    const errorElement = root.querySelector<HTMLElement>("[data-payroll-run-error]");
    if (!errorElement) return;
    if (!message) {
        errorElement.classList.add("d-none");
        errorElement.textContent = "";
    } else {
        errorElement.classList.remove("d-none");
        errorElement.textContent = message;
    }
}

export function showWorkConfigError(message: string): void {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }
    const errorElement = root.querySelector<HTMLElement>("[data-payroll-work-error]");
    if (!errorElement) {
        return;
    }
    if (!message) {
        errorElement.classList.add("d-none");
        errorElement.textContent = "";
        return;
    }
    errorElement.classList.remove("d-none");
    errorElement.textContent = message;
}

export function arrangementModeLabel(mode: string): string {
    return mode === "shift_worker" ? "Shift Worker" : "Office Hour";
}

export function dayTypeLabel(dayType: string | null | undefined): string {
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