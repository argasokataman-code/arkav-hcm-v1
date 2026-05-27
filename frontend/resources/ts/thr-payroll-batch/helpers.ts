import { BatchLine } from "./types";

export function onAuthFailure(status: number, data: unknown): boolean {
    const authApi = (window as unknown as { AuthApi?: { handleUnauthorizedFromApi?: (s: number, d: unknown) => boolean } }).AuthApi;
    if (authApi && typeof authApi.handleUnauthorizedFromApi === "function") {
        return authApi.handleUnauthorizedFromApi(status, data);
    }
    return false;
}

export function apiRequest(method: string, url: string, body?: object): Promise<unknown> {
    const authApi = (window as unknown as { AuthApi?: { getToken?: () => string | null } }).AuthApi;
    const token = (authApi && typeof authApi.getToken === "function" && authApi.getToken()) || localStorage.getItem("arcav_access_token");
    const headers: Record<string, string> = { Accept: "application/json" };
    if (token) { headers["Authorization"] = "Bearer " + token; }
    if (body && typeof body === "object") {
        headers["Content-Type"] = "application/json";
    }
    const axios = (window as unknown as { axios?: (config: object) => Promise<{ data: unknown }> }).axios;
    if (axios) {
        return axios({ method, url, headers, data: body, withCredentials: true })
            .then((response) => response.data)
            .catch((error: { response?: { status?: number; data?: unknown } }) => {
                const resolvedStatus = error.response?.status ?? 0;
                const resolvedData = error.response?.data ?? null;
                if (onAuthFailure(resolvedStatus, resolvedData)) {
                    return null;
                }
                return Promise.reject({ status: resolvedStatus, data: resolvedData });
            });
    }

    const options: RequestInit = { method, headers, credentials: "same-origin" };
    if (body && method !== "GET") {
        options.body = JSON.stringify(body);
    }
    return fetch(url, options).then((response) => response.json().then((data) => {
        if (!response.ok) {
            if (onAuthFailure(response.status, data)) {
                return null;
            }
            return Promise.reject({ status: response.status, data });
        }
        return data;
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
    return `reconciliation-thr-${evidenceId}.xlsx`;
}

export async function downloadReconciliationEvidenceFile(evidenceId: number, filePath?: string | null): Promise<void> {
    const authApi = (window as unknown as { AuthApi?: { downloadV1Binary?: (path: string, filename?: string) => Promise<void> } }).AuthApi;
    if (!authApi || typeof authApi.downloadV1Binary !== "function") {
        throw new Error("AuthApi.downloadV1Binary tidak tersedia");
    }
    const name = reconciliationExportFileName(filePath ?? undefined, evidenceId);
    await authApi.downloadV1Binary(`/reconciliation/exports/${evidenceId}/download`, name);
}

export function formatApiError(data: unknown, status: number): string {
    const reconciliationMessages: Record<string, string> = {
        EXPORT_RECON_REQUIRED: "Sebelum lanjut proses THR, lakukan export reconciliation batch terbaru.",
        EXPORT_RECON_EXPIRED: "Evidence reconciliation THR sudah kedaluwarsa. Silakan export ulang.",
        EXPORT_RECON_SCOPE_MISMATCH: "Evidence reconciliation tidak sesuai dengan batch THR yang diproses.",
        EXPORT_RECON_STALE_DATA: "Data THR berubah sejak export terakhir. Silakan export ulang.",
    };
    if (data && typeof data === "object") {
        const code = (data as { error?: { code?: string } }).error?.code;
        if (code && reconciliationMessages[code]) {
            return reconciliationMessages[code];
        }
    }
    if (data && typeof data === "object") {
        const message = (data as { error?: { message?: string } }).error?.message;
        if (message !== undefined && String(message).trim() !== "") {
            return String(message);
        }
    }
    const helper = (window as unknown as { ApiErrorHelper?: { format?: (d: unknown, s: number) => string } }).ApiErrorHelper;
    if (helper && typeof helper.format === "function") {
        const out = helper.format(data, status);
        if (out && out !== "Permintaan gagal.") {
            return out;
        }
    }
    if (status === 422) return "Data belum lengkap atau tidak valid. Periksa isian lalu coba lagi.";
    if (status === 403) return "Anda tidak punya akses untuk aksi ini.";
    if (status === 401) return "Sesi habis. Silakan login ulang.";
    return "Terjadi kesalahan. Coba lagi nanti.";
}

export function getThrBatchErrorCode(data: unknown): string | null {
    if (!data || typeof data !== "object") {
        return null;
    }
    const code = (data as { error?: { code?: string } }).error?.code;
    return typeof code === "string" ? code : null;
}

function goToThrSettingsField(field: "cutoff" | "payment"): void {
    const card = document.getElementById("thr-periode-settings-card");
    if (card) {
        card.scrollIntoView({ behavior: "smooth", block: "center" });
        card.classList.add("border-warning", "border-2", "shadow-sm");
        window.setTimeout(() => {
            card.classList.remove("border-warning", "border-2", "shadow-sm");
        }, 2600);
    }
    const selector = field === "cutoff" ? "[data-thr-settings-cutoff]" : "[data-thr-settings-payment]";
    window.setTimeout(() => {
        document.querySelector<HTMLInputElement>(selector)?.focus({ preventScroll: true });
    }, 400);
}

export function maybeNavigateThrSettingsFromBatchError(data: unknown): void {
    const code = getThrBatchErrorCode(data);
    if (code === "THR_SETUP_CUTOFF_REQUIRED") {
        goToThrSettingsField("cutoff");
    } else if (code === "THR_PAYMENT_DATE_REQUIRED") {
        goToThrSettingsField("payment");
    }
}

export function setThrReconciliationHint(message: string): void {
    const hintElement = document.querySelector<HTMLElement>("[data-thr-reconciliation-hint]");
    if (!hintElement) return;
    if (!message) {
        hintElement.classList.add("d-none");
        hintElement.textContent = "";
        return;
    }
    hintElement.textContent = message;
    hintElement.classList.remove("d-none");
}

function showThrEvidenceIndicator(evidence: any): void {
    const indicatorElement = document.querySelector<HTMLElement>("[data-thr-evidence-indicator]");
    if (!indicatorElement) return;

    const statusBadge = indicatorElement.querySelector<HTMLElement>("[data-evidence-status]");
    const timestampElement = indicatorElement.querySelector<HTMLElement>("[data-evidence-timestamp]");

    if (!evidence) {
        indicatorElement.classList.add("d-none");
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
    if (timestampElement && evidence.exported_at) {
        const date = new Date(evidence.exported_at).toLocaleString("id-ID");
        const user = evidence.exported_by_name || evidence.exported_by_user_id || "—";
        timestampElement.textContent = `Exported: ${date} oleh ${user}`;
    }
    indicatorElement.classList.remove("d-none");
}

export async function fetchThrLatestEvidence(batchId: number): Promise<void> {
    if (!batchId) return;
    try {
        const response = await apiRequest("GET", "/v1/reconciliation/exports", {
            featureKey: "thr_batch",
            actionKey: "disburse",
            scopeRef: String(batchId),
        }) as { data?: unknown[] };
        if (response && Array.isArray(response.data) && response.data.length > 0) {
            showThrEvidenceIndicator(response.data[0]);
        } else {
            showThrEvidenceIndicator(null);
        }
    } catch (error) {
        console.warn("Failed to fetch THR evidence status:", error);
        showThrEvidenceIndicator(null);
    }
}

export async function triggerThrExportReconciliation(batchId: number, lines: BatchLine[]): Promise<void> {
    if (!batchId) {
        toast("No THR batch selected", true);
        return;
    }
    try {
        const filterPayload = {
            lineIds: lines.filter((line) => line.eligible).map((line) => line.id),
        };
        const response = await apiRequest("POST", "/v1/reconciliation/exports", {
            featureKey: "thr_batch",
            actionKey: "disburse",
            scopeRef: String(batchId),
            filterPayload,
            format: "xlsx",
        }) as any;
        if (response && response.data && response.data.id) {
            toast("Export reconciliation THR berhasil dibuat", false);
            try {
                await downloadReconciliationEvidenceFile(Number(response.data.id), response.data.filePath);
            } catch (downloadError) {
                console.warn("THR reconciliation file download failed:", downloadError);
                toast("Evidence tersimpan, tetapi unduh file gagal. Silakan coba lagi dari daftar evidence.", true);
            }
            await fetchThrLatestEvidence(batchId);
        } else {
            toast("Gagal membuat export reconciliation THR", true);
        }
    } catch (error: any) {
        const errorCode = getThrBatchErrorCode(error?.data || {});
        if (errorCode && errorCode.startsWith("EXPORT_RECON_")) {
            const message = formatApiError(error?.data || {}, 400);
            if (message) {
                setThrReconciliationHint(message);
                return;
            }
        }
        toast(`Error: ${error?.data?.error?.message || "Unknown error"}`, true);
    }
}

export function toast(message: string, danger: boolean): void {
    const ui = (window as unknown as { ArcavUi?: { showToast?: (m: string, t: string) => void } }).ArcavUi;
    if (ui && ui.showToast) {
        ui.showToast(message, danger ? "danger" : "success");
    }
    if (danger) {
        const batchError = document.querySelector<HTMLElement>("[data-thr-batch-error]");
        if (batchError) {
            batchError.textContent = message;
            batchError.classList.remove("d-none");
        }
    }
}

export function fetchThrSlipPdfBlob(lineId: number): Promise<Blob> {
    const url = `/v1/hcm/payroll/thr-batch/lines/${lineId}/slip`;
    const authApi = (window as unknown as { AuthApi?: { getToken?: () => string | null } }).AuthApi;
    const slipToken = (authApi && typeof authApi.getToken === "function" && authApi.getToken()) || localStorage.getItem("arcav_access_token");
    const slipHeaders: Record<string, string> = { Accept: "application/pdf" };
    if (slipToken) { slipHeaders["Authorization"] = "Bearer " + slipToken; }
    const axios = (window as unknown as { axios?: (config: object) => Promise<{ data: Blob }> }).axios;
    if (axios) {
        return axios({
            method: "get",
            url,
            responseType: "blob",
            withCredentials: true,
            headers: slipHeaders,
        }).then((response) => response.data);
    }
    return fetch(url, {
        credentials: "same-origin",
        headers: slipHeaders,
    }).then(async (response) => {
        if (!response.ok) {
            const contentType = response.headers.get("content-type") || "";
            let data: unknown;
            if (contentType.includes("application/json")) {
                data = await response.json().catch(() => ({}));
            } else {
                data = await response.blob();
            }
            return Promise.reject({ status: response.status, data });
        }
        return response.blob();
    });
}

export function pathLooksLikePayrollThr(): boolean {
    const pathname = (window.location.pathname || "").replace(/\/+$/, "");
    return pathname === "/payroll-thr" || /\/payroll-thr$/.test(pathname);
}

export function formatIdr(value: number): string {
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(value);
}

export function thrDisburseGatewayLabel(code: string): string {
    const normalized = (code || "").trim().toLowerCase();
    if (normalized === "manual_external") return "Manual external settlement";
    if (normalized === "stub") return "Stub (simulasi — tanpa transfer bank nyata)";
    if (normalized === "xendit") return "Xendit";
    if (normalized === "midtrans") return "Midtrans";
    return code.trim() || "—";
}

export function escapeHtml(value: string | null | undefined): string {
    if (value == null || value === "") {
        return "";
    }
    return String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;");
}

export function getCalendarYear(): number | null {
    const element = document.querySelector<HTMLInputElement>("[data-thr-settings-year]");
    if (!element || element.value === "") {
        return null;
    }
    const year = parseInt(element.value, 10);
    return Number.isFinite(year) ? year : null;
}

export function rowStatusLabel(code: string): string {
    const labels: Record<string, string> = {
        full: "Penuh",
        pro_rata: "Pro rata",
        nihil: "Nihil",
        invalid: "Tanggal tidak valid",
    };
    return labels[code] || code;
}

export function paymentStatusLabel(status: string | null | undefined): string {
    if (status == null || status === "") {
        return "Belum dibayar";
    }
    const labels: Record<string, string> = {
        unpaid: "Belum dibayar",
        pending: "Menunggu",
        paid: "Lunas",
        failed: "Gagal",
    };
    return labels[status] || status;
}

export function formatMultiplierPercent(multiplier: number): string {
    const percentage = multiplier * 100;
    const rounded = Math.round(percentage * 1000) / 1000;
    return new Intl.NumberFormat("id-ID", { maximumFractionDigits: 3, minimumFractionDigits: 0 }).format(rounded) + "%";
}

export function formatPaidAtShort(iso: string | null | undefined): string {
    if (iso == null || String(iso).trim() === "") {
        return "—";
    }
    try {
        const value = new Date(iso);
        if (Number.isNaN(value.getTime())) {
            return "—";
        }
        return new Intl.DateTimeFormat("id-ID", { dateStyle: "short", timeStyle: "short" }).format(value);
    } catch {
        return "—";
    }
}

export function truncateMiddle(value: string | null | undefined, maxLen: number): string {
    if (value == null || value === "") {
        return "—";
    }
    const text = String(value).trim();
    if (text.length <= maxLen) {
        return text;
    }
    const keep = maxLen - 1;
    const head = Math.ceil(keep / 2);
    const tail = Math.floor(keep / 2);
    return text.slice(0, head) + "…" + text.slice(-tail);
}

export function eligibleBadgeHtml(line: BatchLine): string {
    if (line.eligible) {
        const cls = line.thrGross > 0 ? "badge badge-soft-success" : "badge badge-soft-warning";
        const label = line.thrGross > 0 ? "Ya" : "Ya (Rp0)";
        return `<span class="${cls} fw-normal">${label}</span>`;
    }
    return '<span class="badge badge-soft-secondary fw-normal">Tidak</span>';
}

export function rowStatusBadgeHtml(code: string): string {
    const label = rowStatusLabel(code);
    const cls = code === "full"
        ? "badge badge-soft-success"
        : code === "pro_rata"
            ? "badge badge-soft-info"
            : code === "nihil"
                ? "badge badge-soft-secondary"
                : "badge badge-soft-warning";
    return `<span class="${cls} fw-normal">${escapeHtml(label)}</span>`;
}

export function paymentStatusBadgeHtml(status: string | null | undefined): string {
    const label = paymentStatusLabel(status);
    const code = status ?? "";
    const cls = code === "paid"
        ? "badge badge-soft-success"
        : code === "failed"
            ? "badge badge-soft-danger"
            : code === "pending"
                ? "badge badge-soft-info"
                : "badge badge-soft-dark";
    return `<span class="${cls} fw-normal">${escapeHtml(label)}</span>`;
}