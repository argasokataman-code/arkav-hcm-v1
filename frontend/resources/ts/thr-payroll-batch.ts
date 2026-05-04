import { roundMoney2 } from "./thr-calculation";

type BatchLine = {
    id: number;
    userId: number;
    fullName: string;
    employeeNo: string;
    /** Dari profil karyawan (`employee_profiles`), untuk cek sebelum Pay THR. */
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
    /** Dari API (`serializeBatchLine`); fallback UI pakai tahun batch + id baris. */
    slipNumber?: string | null;
    /** Kode tersimpan DB tanpa `#`; sama dengan yang di PDF. */
    thrSlipPublicNo?: string | null;
    calendarYear?: number | null;
};

type BatchMeta = {
    id: number;
    calendarYear: number;
    cutoffDate: string;
    grandTotalEligible: number;
    eligibleLineCount: number;
    totalLineCount: number;
    status: string;
    canPostToPayroll?: boolean;
};

/** Snapshot baris pengaturan THR (dari `payroll-thr-data` / GET thr-settings). */
type ThrSettingsSnapshot = {
    calendarYear?: number;
    calculationCutoffDate?: string | null;
    eidDate?: string | null;
};

type ThrSettingsAppliedDetail = {
    calendarYear: number | null;
    settings: ThrSettingsSnapshot | null;
};

function onAuthFailure(status: number, data: unknown): boolean {
    const AuthApi = (window as unknown as { AuthApi?: { handleUnauthorizedFromApi?: (s: number, d: unknown) => boolean } }).AuthApi;
    if (AuthApi && typeof AuthApi.handleUnauthorizedFromApi === "function") {
        return AuthApi.handleUnauthorizedFromApi(status, data);
    }
    return false;
}

function apiRequest(method: string, url: string, body?: object): Promise<unknown> {
    const headers: Record<string, string> = { Accept: "application/json" };
    if (body && typeof body === "object") {
        headers["Content-Type"] = "application/json";
    }
    const axios = (window as unknown as { axios?: (c: object) => Promise<{ data: unknown }> }).axios;
    if (axios) {
        return axios({ method, url, headers, data: body, withCredentials: true })
            .then((res) => res.data)
            .catch((err: { response?: { status?: number; data?: unknown } }) => {
                const st = err.response?.status ?? 0;
                const d = err.response?.data ?? null;
                if (onAuthFailure(st, d)) {
                    return null;
                }
                return Promise.reject({ status: st, data: d });
            });
    }
    const opts: RequestInit = { method, headers, credentials: "same-origin" };
    if (body && method !== "GET") {
        opts.body = JSON.stringify(body);
    }
    return fetch(url, opts).then((res) =>
        res.json().then((data) => {
            if (!res.ok) {
                if (onAuthFailure(res.status, data)) {
                    return null;
                }
                return Promise.reject({ status: res.status, data });
            }
            return data;
        }),
    );
}

function reconciliationExportFileName(filePath: string | undefined | null, evidenceId: number): string {
    if (filePath && typeof filePath === "string") {
        const parts = filePath.split("/").filter(Boolean);
        const last = parts[parts.length - 1];
        if (last) {
            return last;
        }
    }
    return `reconciliation-thr-${evidenceId}.csv`;
}

async function downloadReconciliationEvidenceFile(evidenceId: number, filePath?: string | null): Promise<void> {
    const AuthApi = (window as unknown as { AuthApi?: { downloadV1Binary?: (path: string, filename?: string) => Promise<void> } }).AuthApi;
    if (!AuthApi || typeof AuthApi.downloadV1Binary !== "function") {
        throw new Error("AuthApi.downloadV1Binary tidak tersedia");
    }
    const name = reconciliationExportFileName(filePath ?? undefined, evidenceId);
    await AuthApi.downloadV1Binary(`/reconciliation/exports/${evidenceId}/download`, name);
}

/**
 * Pesan untuk user: utamakan `error.message` dari API (envelope Arcav), baru fallback helper / generik.
 */
function formatApiError(data: unknown, status: number): string {
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
        const msg = (data as { error?: { message?: string } }).error?.message;
        if (msg !== undefined && String(msg).trim() !== "") {
            return String(msg);
        }
    }
    const Helper = (window as unknown as { ApiErrorHelper?: { format?: (d: unknown, s: number) => string } }).ApiErrorHelper;
    if (Helper && typeof Helper.format === "function") {
        const out = Helper.format(data, status);
        if (out && out !== "Permintaan gagal.") {
            return out;
        }
    }
    if (status === 422) {
        return "Data belum lengkap atau tidak valid. Periksa isian lalu coba lagi.";
    }
    if (status === 403) {
        return "Anda tidak punya akses untuk aksi ini.";
    }
    if (status === 401) {
        return "Sesi habis. Silakan login ulang.";
    }
    return "Terjadi kesalahan. Coba lagi nanti.";
}

function getThrBatchErrorCode(data: unknown): string | null {
    if (!data || typeof data !== "object") {
        return null;
    }
    const code = (data as { error?: { code?: string } }).error?.code;
    return typeof code === "string" ? code : null;
}

/** UX: arahkan ke card Pengaturan periode THR + fokus field yang kurang. */
function goToThrSettingsField(field: "cutoff" | "payment"): void {
    const card = document.getElementById("thr-periode-settings-card");
    if (card) {
        card.scrollIntoView({ behavior: "smooth", block: "center" });
        card.classList.add("border-warning", "border-2", "shadow-sm");
        window.setTimeout(() => {
            card.classList.remove("border-warning", "border-2", "shadow-sm");
        }, 2600);
    }
    const sel = field === "cutoff" ? "[data-thr-settings-cutoff]" : "[data-thr-settings-payment]";
    window.setTimeout(() => {
        document.querySelector<HTMLInputElement>(sel)?.focus({ preventScroll: true });
    }, 400);
}

function maybeNavigateThrSettingsFromBatchError(data: unknown): void {
    const code = getThrBatchErrorCode(data);
    if (code === "THR_SETUP_CUTOFF_REQUIRED") {
        goToThrSettingsField("cutoff");
    } else if (code === "THR_PAYMENT_DATE_REQUIRED") {
        goToThrSettingsField("payment");
    }
}

function setThrReconciliationHint(message: string): void {
    const hintEl = document.querySelector<HTMLElement>("[data-thr-reconciliation-hint]");
    if (!hintEl) {
        return;
    }
    if (!message) {
        hintEl.classList.add("d-none");
        hintEl.textContent = "";
        return;
    }
    hintEl.textContent = message;
    hintEl.classList.remove("d-none");
}

function showThrEvidenceIndicator(evidence: any): void {
    const indicatorEl = document.querySelector<HTMLElement>("[data-thr-evidence-indicator]");
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

async function fetchThrLatestEvidence(batchId: number): Promise<void> {
    if (!batchId) return;
    try {
        const res = (await apiRequest("GET", "/v1/reconciliation/exports", {
            featureKey: "thr_batch",
            actionKey: "disburse",
            scopeRef: String(batchId),
        })) as any;

        if (res && res.data && Array.isArray(res.data) && res.data.length > 0) {
            showThrEvidenceIndicator(res.data[0]);
        } else {
            showThrEvidenceIndicator(null);
        }
    } catch (error) {
        console.warn("Failed to fetch THR evidence status:", error);
        showThrEvidenceIndicator(null);
    }
}

async function triggerThrExportReconciliation(batchId: number, lines: BatchLine[]): Promise<void> {
    if (!batchId) {
        toast("No THR batch selected", true);
        return;
    }

    try {
        const filterPayload = {
            lineIds: lines.filter((l) => l.eligible).map((l) => l.id),
        };

        const res = (await apiRequest("POST", "/v1/reconciliation/exports", {
            featureKey: "thr_batch",
            actionKey: "disburse",
            scopeRef: String(batchId),
            filterPayload: filterPayload,
            format: "csv",
        })) as any;

        if (res && res.data && res.data.id) {
            toast("Export reconciliation THR berhasil dibuat", false);
            try {
                await downloadReconciliationEvidenceFile(Number(res.data.id), res.data.filePath);
            } catch (dlErr) {
                console.warn("THR reconciliation file download failed:", dlErr);
                toast("Evidence tersimpan, tetapi unduh file gagal. Silakan coba lagi dari daftar evidence.", true);
            }
            await fetchThrLatestEvidence(batchId);
        } else {
            toast("Gagal membuat export reconciliation THR", true);
        }
    } catch (error: any) {
        const errorCode = getThrBatchErrorCode(error?.data || {});
        if (errorCode && errorCode.startsWith("EXPORT_RECON_")) {
            const msg = formatApiError(error?.data || {}, 400);
            if (msg) {
                setThrReconciliationHint(msg);
                return;
            }
        }
        toast(`Error: ${error?.data?.error?.message || "Unknown error"}`, true);
    }
}

function toast(msg: string, danger: boolean): void {
    const Ui = (window as unknown as { ArcavUi?: { showToast?: (m: string, t: string) => void } }).ArcavUi;
    if (Ui && Ui.showToast) {
        Ui.showToast(msg, danger ? "danger" : "success");
    }
    if (danger) {
        const batchErr = document.querySelector<HTMLElement>("[data-thr-batch-error]");
        if (batchErr) {
            batchErr.textContent = msg;
            batchErr.classList.remove("d-none");
        }
    }
}

/** PDF slip: axios jarang dimuat di layout; fetch + cookie sama dengan pola API lain. */
function fetchThrSlipPdfBlob(lineId: number): Promise<Blob> {
    const url = `/v1/hcm/payroll/thr-batch/lines/${lineId}/slip`;
    const axios = (window as unknown as { axios?: (c: object) => Promise<{ data: Blob }> }).axios;
    if (axios) {
        return axios({
            method: "get",
            url,
            responseType: "blob",
            withCredentials: true,
            headers: { Accept: "application/pdf" },
        }).then((res) => res.data);
    }
    return fetch(url, {
        credentials: "same-origin",
        headers: { Accept: "application/pdf" },
    }).then(async (r) => {
        if (!r.ok) {
            const ct = r.headers.get("content-type") || "";
            let data: unknown;
            if (ct.includes("application/json")) {
                data = await r.json().catch(() => ({}));
            } else {
                data = await r.blob();
            }
            return Promise.reject({ status: r.status, data });
        }
        return r.blob();
    });
}

function pathLooksLikePayrollThr(): boolean {
    const p = (window.location.pathname || "").replace(/\/+$/, "");
    return p === "/payroll-thr" || /\/payroll-thr$/.test(p);
}

function formatIdr(n: number): string {
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(n);
}

function thrDisburseGatewayLabel(code: string): string {
    const c = (code || "").trim().toLowerCase();
    if (c === "stub") {
        return "Stub (simulasi — tanpa transfer bank nyata)";
    }
    if (c === "xendit") {
        return "Xendit";
    }
    if (c === "midtrans") {
        return "Midtrans";
    }
    return code.trim() || "—";
}

function escapeHtml(s: string | null | undefined): string {
    if (s == null || s === "") {
        return "";
    }
    return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function getCalendarYear(): number | null {
    const el = document.querySelector<HTMLInputElement>("[data-thr-settings-year]");
    if (!el || el.value === "") {
        return null;
    }
    const y = parseInt(el.value, 10);
    return Number.isFinite(y) ? y : null;
}

function rowStatusLabel(code: string): string {
    const m: Record<string, string> = {
        full: "Penuh",
        pro_rata: "Pro rata",
        nihil: "Nihil",
        invalid: "Tanggal tidak valid",
    };
    return m[code] || code;
}

function paymentStatusLabel(status: string | null | undefined): string {
    if (status == null || status === "") {
        return "Belum dibayar";
    }
    const m: Record<string, string> = {
        unpaid: "Belum dibayar",
        pending: "Menunggu",
        paid: "Lunas",
        failed: "Gagal",
    };
    return m[status] || status;
}

/** Proporsi pro rata sebagai persen (id-ID), mis. 100% atau 58,333%. */
function formatMultiplierPercent(multiplier: number): string {
    const pct = multiplier * 100;
    const rounded = Math.round(pct * 1000) / 1000;
    return new Intl.NumberFormat("id-ID", { maximumFractionDigits: 3, minimumFractionDigits: 0 }).format(rounded) + "%";
}

function formatPaidAtShort(iso: string | null | undefined): string {
    if (iso == null || String(iso).trim() === "") {
        return "—";
    }
    try {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) {
            return "—";
        }
        return new Intl.DateTimeFormat("id-ID", { dateStyle: "short", timeStyle: "short" }).format(d);
    } catch {
        return "—";
    }
}

function truncateMiddle(s: string | null | undefined, maxLen: number): string {
    if (s == null || s === "") {
        return "—";
    }
    const t = String(s).trim();
    if (t.length <= maxLen) {
        return t;
    }
    const keep = maxLen - 1;
    const head = Math.ceil(keep / 2);
    const tail = Math.floor(keep / 2);
    return t.slice(0, head) + "…" + t.slice(-tail);
}

function eligibleBadgeHtml(l: BatchLine): string {
    if (l.eligible) {
        const cls = l.thrGross > 0 ? "badge badge-soft-success" : "badge badge-soft-warning";
        const label = l.thrGross > 0 ? "Ya" : "Ya (Rp0)";
        return `<span class="${cls} fw-normal">${label}</span>`;
    }
    return `<span class="badge badge-soft-secondary fw-normal">Tidak</span>`;
}

function rowStatusBadgeHtml(code: string): string {
    const label = rowStatusLabel(code);
    const cls =
        code === "full"
            ? "badge badge-soft-success"
            : code === "pro_rata"
              ? "badge badge-soft-info"
              : code === "nihil"
                ? "badge badge-soft-secondary"
                : "badge badge-soft-warning";
    return `<span class="${cls} fw-normal">${escapeHtml(label)}</span>`;
}

function paymentStatusBadgeHtml(status: string | null | undefined): string {
    const label = paymentStatusLabel(status);
    const s = status ?? "";
    const cls =
        s === "paid"
            ? "badge badge-soft-success"
            : s === "failed"
              ? "badge badge-soft-danger"
              : s === "pending"
                ? "badge badge-soft-info"
                : "badge badge-soft-dark";
    return `<span class="${cls} fw-normal">${escapeHtml(label)}</span>`;
}

function boot(): void {
    const root = document.querySelector("[data-thr-batch-panel]");
    if (!root || !pathLooksLikePayrollThr()) {
        return;
    }

    const bodyEl = root.querySelector("[data-thr-batch-body]");
    const emptyEl = root.querySelector("[data-thr-batch-empty]");
    const errEl = root.querySelector("[data-thr-batch-error]");
    const genBtn = root.querySelector<HTMLButtonElement>("[data-thr-batch-generate]");
    const exportBtn = root.querySelector<HTMLButtonElement>("[data-thr-batch-export-evidence]");
    const disburseBtn = root.querySelector<HTMLButtonElement>("[data-thr-batch-disburse]");
    const sendSlipBtn = root.querySelector<HTMLButtonElement>("[data-thr-batch-send-slip]");
    const grandEl = root.querySelector("[data-thr-batch-grand]");
    const countEl = root.querySelector("[data-thr-batch-checked-count]");
    const sumEl = root.querySelector("[data-thr-batch-checked-sum]");
    const batchHint = root.querySelector("[data-thr-batch-status-hint]");
    const selectAllEl = root.querySelector<HTMLInputElement>("[data-thr-select-all]");

    const disburseModalEl = document.getElementById("thrDisburseConfirmModal");
    const disburseCountEl = document.querySelector("[data-thr-disburse-modal-count]");
    const disburseConfirmBtn = document.querySelector<HTMLButtonElement>("[data-thr-disburse-confirm]");

    let lines: BatchLine[] = [];
    let batch: BatchMeta | null = null;

    const slipPreviewModalEl = document.getElementById("thrBatchSlipPreviewModal");
    const slipPreviewLoading = document.querySelector("[data-thr-slip-preview-loading]");
    const slipPreviewIframe = document.querySelector<HTMLIFrameElement>("[data-thr-slip-preview-iframe]");
    const slipModalDownload = document.querySelector<HTMLButtonElement>("[data-thr-slip-modal-download]");
    const slipPreviewSlipNoEl = document.querySelector<HTMLElement>("[data-thr-slip-preview-slip-no]");

    let slipPreviewBlobUrl: string | null = null;
    let slipPreviewLineId: number | null = null;
    let slipPreviewPublicNo: string | null = null;

    function cleanupThrSlipPreview(): void {
        if (slipPreviewBlobUrl) {
            URL.revokeObjectURL(slipPreviewBlobUrl);
            slipPreviewBlobUrl = null;
        }
        slipPreviewLineId = null;
        slipPreviewPublicNo = null;
        if (slipPreviewIframe) {
            slipPreviewIframe.src = "about:blank";
            slipPreviewIframe.classList.add("d-none");
        }
        if (slipPreviewLoading) {
            slipPreviewLoading.textContent = "Memuat PDF…";
            slipPreviewLoading.classList.remove("d-none");
        }
        if (slipModalDownload) {
            slipModalDownload.disabled = true;
        }
        if (slipPreviewSlipNoEl) {
            slipPreviewSlipNoEl.textContent = "—";
        }
    }

    function downloadThrSlipFromModal(): void {
        if (!slipPreviewBlobUrl || slipPreviewLineId == null) {
            return;
        }
        const cy = batch?.calendarYear ?? "thr";
        const fn =
            slipPreviewPublicNo && slipPreviewPublicNo.trim() !== ""
                ? `thr-slip-${slipPreviewPublicNo.replace(/^#/, "").trim()}.pdf`
                : `thr-slip-THR-${cy}-${slipPreviewLineId}.pdf`;
        const a = document.createElement("a");
        a.href = slipPreviewBlobUrl;
        a.download = fn;
        a.rel = "noopener";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function openThrSlipPreview(lineId: number): void {
        if (!slipPreviewModalEl) {
            toast("Modal preview tidak ditemukan.", true);
            return;
        }
        const batchErr = document.querySelector<HTMLElement>("[data-thr-batch-error]");
        if (batchErr) {
            batchErr.textContent = "";
            batchErr.classList.add("d-none");
        }
        cleanupThrSlipPreview();
        slipPreviewLineId = lineId;
        const lineRec = lines.find((x) => x.id === lineId);
        slipPreviewPublicNo =
            (lineRec?.thrSlipPublicNo && String(lineRec.thrSlipPublicNo).trim() !== ""
                ? String(lineRec.thrSlipPublicNo).trim()
                : null) ??
            (lineRec?.slipNumber && String(lineRec.slipNumber).trim() !== "" ? String(lineRec.slipNumber).replace(/^#/, "").trim() : null) ??
            (batch != null ? `THR-${batch.calendarYear}-${lineId}` : null);
        if (slipPreviewSlipNoEl) {
            slipPreviewSlipNoEl.textContent = slipPreviewPublicNo != null && slipPreviewPublicNo !== "" ? `#${slipPreviewPublicNo}` : "—";
        }
        const Bootstrap = (window as unknown as { bootstrap?: { Modal?: { getOrCreateInstance: (el: HTMLElement) => { show: () => void; hide: () => void } } } })
            .bootstrap;
        const bsModal = Bootstrap?.Modal?.getOrCreateInstance(slipPreviewModalEl);
        bsModal?.show();
        fetchThrSlipPdfBlob(lineId)
            .then((blob) => {
                slipPreviewBlobUrl = URL.createObjectURL(blob);
                if (slipPreviewIframe) {
                    slipPreviewIframe.src = slipPreviewBlobUrl;
                    slipPreviewIframe.classList.remove("d-none");
                }
                if (slipPreviewLoading) {
                    slipPreviewLoading.classList.add("d-none");
                }
                if (slipModalDownload) {
                    slipModalDownload.disabled = false;
                }
            })
            .catch((err: unknown) => {
                bsModal?.hide();
                let st = 0;
                let d: unknown;
                if (err && typeof err === "object" && "response" in err) {
                    const ax = err as { response?: { status?: number; data?: unknown } };
                    st = ax.response?.status ?? 0;
                    d = ax.response?.data;
                } else if (err && typeof err === "object" && "status" in err) {
                    const fe = err as { status: number; data: unknown };
                    st = fe.status;
                    d = fe.data;
                }
                if (onAuthFailure(st, d ?? null)) {
                    return;
                }
                if (d instanceof Blob) {
                    d.text().then((t) => {
                        try {
                            const j = JSON.parse(t) as { error?: { message?: string } };
                            toast(j.error?.message || "Slip tidak tersedia.", true);
                        } catch {
                            toast("Slip tidak tersedia.", true);
                        }
                    });
                    return;
                }
                toast(formatApiError(d, st), true);
            });
    }

    slipPreviewModalEl?.addEventListener("hidden.bs.modal", cleanupThrSlipPreview);
    slipModalDownload?.addEventListener("click", downloadThrSlipFromModal);

    /** Delegasi ke panel — lebih tahan banting daripada listener per baris (cache skrip / plugin DOM). */
    root.addEventListener("click", (ev: MouseEvent) => {
        const t = ev.target;
        if (!(t instanceof Element)) {
            return;
        }
        const btn = t.closest<HTMLElement>("[data-thr-slip-preview]");
        if (!btn || !root.contains(btn)) {
            return;
        }
        ev.preventDefault();
        const id = parseInt(btn.getAttribute("data-line-id") || "0", 10);
        if (id) {
            openThrSlipPreview(id);
        }
    });

    function showErr(msg: string): void {
        if (errEl) {
            errEl.textContent = msg;
            errEl.classList.toggle("d-none", !msg);
        }
    }

    function syncSelectAllCheckbox(): void {
        if (!selectAllEl || !bodyEl) {
            return;
        }
        const boxes = [...bodyEl.querySelectorAll<HTMLInputElement>("input[data-thr-line-check]:not(:disabled)")];
        if (boxes.length === 0) {
            selectAllEl.disabled = true;
            selectAllEl.checked = false;
            selectAllEl.indeterminate = false;
            return;
        }
        selectAllEl.disabled = false;
        const nChecked = boxes.filter((b) => b.checked).length;
        if (nChecked === 0) {
            selectAllEl.checked = false;
            selectAllEl.indeterminate = false;
        } else if (nChecked === boxes.length) {
            selectAllEl.checked = true;
            selectAllEl.indeterminate = false;
        } else {
            selectAllEl.checked = false;
            selectAllEl.indeterminate = true;
        }
    }

    function updateSummary(): void {
        const checks = bodyEl?.querySelectorAll<HTMLInputElement>("input[data-thr-line-check]:checked") ?? [];
        let sumCents = 0;
        checks.forEach((ch) => {
            if (ch.disabled) {
                return;
            }
            if (ch.getAttribute("data-thr-purpose") !== "pay") {
                return;
            }
            const cents = parseInt(ch.getAttribute("data-thr-cents") || "0", 10);
            sumCents += cents;
        });
        const sum = roundMoney2(sumCents / 100);
        if (countEl) {
            let payN = 0;
            checks.forEach((ch) => {
                if (ch.getAttribute("data-thr-purpose") === "pay") {
                    payN++;
                }
            });
            countEl.textContent = String(payN);
        }
        if (sumEl) {
            sumEl.textContent = formatIdr(sum);
        }
        updateActionButtons();
        syncSelectAllCheckbox();
    }

    function updateActionButtons(): void {
        const isDraft = batch?.status === "draft";
        const checked = bodyEl?.querySelectorAll<HTMLInputElement>("input[data-thr-line-check]:checked:not(:disabled)") ?? [];
        let anyCheckedPayable = false;
        checked.forEach((ch) => {
            if (ch.getAttribute("data-thr-purpose") !== "pay") {
                return;
            }
            const uid = parseInt(ch.getAttribute("data-user-id") || "0", 10);
            const line = lines.find((l) => l.userId === uid);
            if (line && line.eligible && line.thrGross > 0) {
                anyCheckedPayable = true;
            }
        });

        if (disburseBtn) {
            disburseBtn.disabled = !isDraft || !anyCheckedPayable;
        }

        let anyCheckedSlip = false;
        checked.forEach((ch) => {
            const lid = parseInt(ch.getAttribute("data-line-id") || "0", 10);
            const line = lines.find((l) => l.id === lid);
            if (line?.hasSlip) {
                anyCheckedSlip = true;
            }
        });
        if (sendSlipBtn) {
            sendSlipBtn.disabled = !batch || !anyCheckedSlip;
        }
    }

    function bankCellHtml(l: BatchLine): string {
        const name = (l.bankName && String(l.bankName).trim()) || "";
        const no = (l.bankAccountNo && String(l.bankAccountNo).trim()) || "";
        if (!name && !no) {
            return `<span class="text-muted">—</span>`;
        }
        return (
            `<div class="text-gray-9">${escapeHtml(name || "—")}</div>` +
            `<div class="text-muted">${escapeHtml(no || "—")}</div>`
        );
    }

    function render(): void {
        showErr("");
        if (!bodyEl) {
            return;
        }
        if (!lines.length) {
            bodyEl.innerHTML = "";
            if (emptyEl) {
                emptyEl.classList.remove("d-none");
            }
            if (grandEl && batch) {
                grandEl.textContent = formatIdr(batch.grandTotalEligible);
            }
            if (disburseBtn) {
                disburseBtn.disabled = true;
            }
            if (sendSlipBtn) {
                sendSlipBtn.disabled = true;
            }
            updateSummary();
            return;
        }
        if (emptyEl) {
            emptyEl.classList.add("d-none");
        }
        if (grandEl && batch) {
            grandEl.textContent = formatIdr(batch.grandTotalEligible);
        }

        const isDraft = batch?.status === "draft";

        bodyEl.innerHTML = lines
            .map((l) => {
                const cents = Math.round(l.thrGross * 100);
                const paySelect = Boolean(isDraft && l.eligible && l.thrGross > 0);
                const slipSelect = Boolean(!isDraft && l.hasSlip);
                const showCheckbox = paySelect || slipSelect;
                const defaultChecked =
                    paySelect && (l.paymentStatus === "unpaid" || l.paymentStatus === "failed" || l.paymentStatus === "pending");
                const purposeAttr = paySelect ? "pay" : "slip";
                const payBadge =
                    l.paymentStatus === "failed" && l.paymentFailureReason
                        ? `<div class="small text-danger mt-1">${escapeHtml(l.paymentFailureReason)}</div>`
                        : "";
                const slipCell = l.hasSlip
                    ? `<button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" data-thr-slip-preview data-line-id="${l.id}" title="Preview slip THR (PDF)">` +
                      `<i class="ti ti-eye fs-14" aria-hidden="true"></i><span>Preview</span></button>` +
                      (l.slipNotifySentAt ? ` <span class="badge badge-soft-secondary fw-normal ms-1">Terkirim</span>` : "")
                    : `<span class="text-muted">—</span>`;

                const refDisplay = escapeHtml(truncateMiddle(l.paymentGatewayRef, 22));

                const checkCell = showCheckbox
                    ? `<div class="form-check form-check-md d-flex justify-content-center mb-0">` +
                      `<input type="checkbox" class="form-check-input" data-thr-line-check data-thr-purpose="${purposeAttr}" data-thr-cents="${cents}" ` +
                      `data-user-id="${l.userId}" data-line-id="${l.id}" ${defaultChecked ? "checked" : ""}>` +
                      `</div>`
                    : `<span class="text-muted">—</span>`;

                return (
                    `<tr data-thr-line="${l.userId}">` +
                    `<td class="align-middle text-center">${checkCell}</td>` +
                    `<td class="align-middle">` +
                    `<div class="fw-medium">${escapeHtml(l.fullName)}</div>` +
                    `<span class="fs-12 text-muted">${escapeHtml(l.employeeNo || "—")}</span>` +
                    `</td>` +
                    `<td class="align-middle fs-12 text-nowrap">${bankCellHtml(l)}</td>` +
                    `<td class="align-middle text-center">${eligibleBadgeHtml(l)}</td>` +
                    `<td class="align-middle"><span class="text-gray-5">${escapeHtml(l.joinDateUsed)}</span></td>` +
                    `<td class="align-middle text-end fs-12 text-gray-5">${formatIdr(l.baseSalary)}</td>` +
                    `<td class="align-middle text-end fw-medium">${formatIdr(l.referenceWage)}</td>` +
                    `<td class="align-middle text-center"><span class="text-gray-5">${l.monthsOfService}</span></td>` +
                    `<td class="align-middle text-end fs-12 text-gray-5">${formatMultiplierPercent(l.multiplier)}</td>` +
                    `<td class="align-middle">${rowStatusBadgeHtml(l.rowStatus)}</td>` +
                    `<td class="align-middle text-end fw-semibold text-gray-9">${formatIdr(l.thrGross)}</td>` +
                    `<td class="align-middle">${paymentStatusBadgeHtml(l.paymentStatus)}${payBadge}</td>` +
                    `<td class="align-middle fs-12 text-nowrap text-gray-5">${escapeHtml(formatPaidAtShort(l.paidAt))}</td>` +
                    `<td class="align-middle fs-12 text-gray-5" title="${escapeHtml(l.paymentGatewayRef || "")}"><code class="small text-muted mb-0">${refDisplay}</code></td>` +
                    `<td class="align-middle">${slipCell}</td>` +
                    `</tr>`
                );
            })
            .join("");

        bodyEl.querySelectorAll("input[data-thr-line-check]").forEach((el) => {
            el.addEventListener("change", updateSummary);
        });
        updateSummary();

        if (batchHint && batch) {
            if (batch.status === "assigned") {
                batchHint.innerHTML =
                    `THR tahun <strong>${batch.calendarYear}</strong> sudah diposting ke payroll. Status pembayaran per karyawan dan slip tercatat di bawah.`;
            } else {
                batchHint.innerHTML =
                    `Cut-off perhitungan: <strong>${escapeHtml(batch.cutoffDate)}</strong>. ` +
                    `Centang karyawan → <strong>Pay THR</strong> (gateway). Data otomatis diposting ke payroll jika semua pembayaran lunas.`;
            }
        }
    }

    async function loadBatch(): Promise<void> {
        const y = getCalendarYear();
        if (y === null) {
            return;
        }
        try {
            const resp = (await apiRequest(
                "get",
                "/v1/hcm/payroll/thr-batch?calendarYear=" + encodeURIComponent(String(y)),
            )) as { success?: boolean; data?: { batch: BatchMeta | null; lines: BatchLine[] } };
            if (!resp || resp.success !== true || !resp.data) {
                return;
            }
            batch = resp.data.batch;
            lines = resp.data.lines || [];
            render();
            if (batch?.id) {
                void fetchThrLatestEvidence(batch.id);
            }
        } catch {
            /* ignore */
        }
    }

    /**
     * Jika pengaturan tahun sudah punya cut-off dan belum ada draft berisi baris (atau batch belum ada),
     * panggil generate otomatis — supaya admin tidak lupa setelah setup periode.
     */
    async function maybeAutoGenerateFromSetup(detail: ThrSettingsAppliedDetail): Promise<void> {
        const y = getCalendarYear();
        if (y === null) {
            return;
        }
        if (detail.calendarYear !== null && detail.calendarYear !== y) {
            return;
        }
        const settings = detail.settings;
        const cutoff = settings?.calculationCutoffDate;
        if (!cutoff || String(cutoff).trim() === "") {
            return;
        }
        if (batch?.status === "assigned") {
            return;
        }
        /* Draft yang sudah ada (meski 0 baris) tidak di-regenerate otomatis — pakai tombol Generate. */
        if (batch?.status === "draft") {
            return;
        }

        if (!genBtn) {
            return;
        }
        genBtn.disabled = true;
        try {
            const resp = (await apiRequest("post", "/v1/hcm/payroll/thr-batch/generate", {
                calendarYear: y,
            })) as {
                success?: boolean;
                data?: { batch: BatchMeta; lines: BatchLine[] };
                error?: { code?: string };
            };
            if (!resp) {
                return;
            }
            if (resp.success !== true || !resp.data) {
                const code = resp.error?.code;
                if (code === "THR_YEAR_ALREADY_ASSIGNED" || code === "THR_SETUP_CUTOFF_REQUIRED") {
                    return;
                }
                return;
            }
            batch = resp.data.batch;
            lines = resp.data.lines || [];
            toast("Daftar THR dibuat otomatis dari pengaturan periode.", false);
            render();
        } catch (e: unknown) {
            const err = e as { data?: { error?: { code?: string } } };
            const code = err.data?.error?.code;
            if (code === "THR_YEAR_ALREADY_ASSIGNED" || code === "THR_SETUP_CUTOFF_REQUIRED") {
                return;
            }
        } finally {
            genBtn.disabled = false;
        }
    }

    async function onThrSettingsApplied(detail: ThrSettingsAppliedDetail): Promise<void> {
        await loadBatch();
        await maybeAutoGenerateFromSetup(detail);
    }

    genBtn?.addEventListener("click", async () => {
        const y = getCalendarYear();
        if (y === null) {
            toast("Pilih tahun kalender di pengaturan.", true);
            return;
        }
        showErr("");
        genBtn.disabled = true;
        try {
            const resp = (await apiRequest("post", "/v1/hcm/payroll/thr-batch/generate", {
                calendarYear: y,
            })) as {
                success?: boolean;
                data?: { batch: BatchMeta; lines: BatchLine[] };
                error?: { message?: string; code?: string };
            };
            if (!resp || resp.success !== true || !resp.data) {
                const msg = formatApiError(resp ?? null, 422);
                maybeNavigateThrSettingsFromBatchError(resp ?? null);
                showErr(msg);
                toast(msg, true);
                return;
            }
            batch = resp.data.batch;
            lines = resp.data.lines || [];
            toast("Daftar THR dihasilkan.", false);
            render();
        } catch (e: unknown) {
            const err = e as { status?: number; data?: { error?: { message?: string } } };
            const msg = formatApiError(err.data, err.status ?? 0);
            maybeNavigateThrSettingsFromBatchError(err.data ?? null);
            showErr(msg);
            toast(msg, true);
        } finally {
            genBtn.disabled = false;
        }
    });

    function openThrReconciliationPreviewModal(): void {
        if (!batch?.id) {
            toast("Belum ada batch THR", true);
            return;
        }

        const modal = document.getElementById("thr_reconciliation_preview_modal");
        if (!modal) return;

        const fmt = (v: number) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(v);
        const eligibleLines = lines.filter((l) => l.eligible);

        const yearEl = modal.querySelector("[data-thr-recon-preview-year]");
        const countEl = modal.querySelector("[data-thr-recon-preview-count]");
        const totalEl = modal.querySelector("[data-thr-recon-preview-total]");
        const statusEl = modal.querySelector("[data-thr-recon-preview-status]");
        const tbody = modal.querySelector("[data-thr-recon-preview-body]");

        if (yearEl) yearEl.textContent = String(batch.calendarYear);
        if (countEl) countEl.textContent = String(eligibleLines.length);
        if (totalEl) totalEl.textContent = fmt(batch.grandTotalEligible);
        if (statusEl) statusEl.textContent = batch.status ?? "—";

        if (tbody) {
            if (eligibleLines.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada karyawan eligible.</td></tr>`;
            } else {
                tbody.innerHTML = eligibleLines
                    .map(
                        (l) => `
                    <tr>
                        <td>
                            <div class="fw-semibold">${l.fullName || "—"}</div>
                            <div class="text-muted small">${l.employeeNo || String(l.userId)}</div>
                        </td>
                        <td class="text-end">${fmt(l.referenceWage)}</td>
                        <td class="text-center">${l.monthsOfService} bln</td>
                        <td class="text-center">${(l.multiplier * 100).toFixed(0)}%</td>
                        <td class="text-end fw-semibold text-primary">${fmt(l.thrGross)}</td>
                        <td class="text-center"><span class="badge bg-light text-dark border">${l.paymentStatus || "pending"}</span></td>
                    </tr>`,
                    )
                    .join("");

                tbody.innerHTML += `
                    <tr class="table-light fw-semibold">
                        <td>Total (${eligibleLines.length} karyawan eligible)</td>
                        <td colspan="3"></td>
                        <td class="text-end text-primary">${fmt(batch.grandTotalEligible)}</td>
                        <td></td>
                    </tr>`;
            }
        }

        const { Modal } = window.bootstrap as any;
        Modal.getOrCreateInstance(modal).show();
    }

    exportBtn?.addEventListener("click", () => {
        openThrReconciliationPreviewModal();
    });

    const thrReconPreviewDownloadBtn = document.querySelector<HTMLButtonElement>("[data-thr-recon-preview-download]");
    thrReconPreviewDownloadBtn?.addEventListener("click", async () => {
        if (!batch?.id) return;
        const modal = document.getElementById("thr_reconciliation_preview_modal");
        if (modal) {
            const { Modal } = window.bootstrap as any;
            Modal.getOrCreateInstance(modal).hide();
        }
        try {
            void await triggerThrExportReconciliation(batch.id, lines);
        } catch (e) {
            toast(`Error export: ${String(e)}`, true);
        }
    });

    function getCheckedUserIds(): number[] {
        const ids: number[] = [];
        bodyEl?.querySelectorAll<HTMLInputElement>("input[data-thr-line-check]:checked:not(:disabled)").forEach((ch) => {
            if (ch.getAttribute("data-thr-purpose") !== "pay") {
                return;
            }
            const uid = parseInt(ch.getAttribute("data-user-id") || "0", 10);
            if (uid) {
                ids.push(uid);
            }
        });
        return ids;
    }

    function countDisburseTargets(ids: number[]): number {
        let n = 0;
        for (const uid of ids) {
            const line = lines.find((l) => l.userId === uid);
            if (line && line.paymentStatus !== "paid") {
                n++;
            }
        }
        return n;
    }

    disburseBtn?.addEventListener("click", () => {
        if (!batch || batch.status !== "draft") {
            return;
        }
        const ids = getCheckedUserIds();
        if (!ids.length) {
            toast("Centang minimal satu karyawan eligible.", true);
            return;
        }
        const n = countDisburseTargets(ids);
        if (n === 0) {
            toast("Semua terpilih sudah lunas — tidak ada yang dikirim ke gateway.", true);
            return;
        }
        if (disburseModalEl && batch) {
            const checked = ids.length;
            const skipPaid = checked - n;
            let totalGross = 0;
            for (const uid of ids) {
                const line = lines.find((l) => l.userId === uid);
                if (line && line.paymentStatus !== "paid") {
                    totalGross += line.thrGross;
                }
            }
            const driverRaw = disburseModalEl.getAttribute("data-thr-disburse-gateway-driver")?.trim() || "stub";
            const setTxt = (sel: string, text: string): void => {
                const el = disburseModalEl.querySelector(sel);
                if (el) {
                    el.textContent = text;
                }
            };
            setTxt("[data-thr-disburse-modal-year]", String(batch.calendarYear));
            setTxt("[data-thr-disburse-modal-driver]", thrDisburseGatewayLabel(driverRaw));
            setTxt("[data-thr-disburse-modal-checked]", String(checked));
            setTxt("[data-thr-disburse-modal-count]", String(n));
            setTxt("[data-thr-disburse-modal-skip-paid]", String(skipPaid));
            setTxt("[data-thr-disburse-modal-total]", formatIdr(totalGross));
            const stubNote = disburseModalEl.querySelector<HTMLElement>("[data-thr-disburse-modal-stub-note]");
            if (stubNote) {
                stubNote.hidden = driverRaw.toLowerCase() !== "stub";
            }
        } else if (disburseCountEl) {
            disburseCountEl.textContent = String(n);
        }
        if (disburseModalEl && window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(disburseModalEl).show();
        } else {
            toast("Modal tidak tersedia.", true);
        }
    });

    disburseConfirmBtn?.addEventListener("click", async () => {
        if (!batch || batch.status !== "draft") {
            return;
        }
        const ids = getCheckedUserIds();
        if (!batch.id) {
            return;
        }
        disburseConfirmBtn.disabled = true;
        try {
            const resp = (await apiRequest("post", "/v1/hcm/payroll/thr-batch/disburse", {
                batchId: batch.id,
                userIds: ids,
            })) as {
                success?: boolean;
                data?: { lines: BatchLine[]; batch: BatchMeta; skippedAlreadyPaidUserIds?: number[] };
                error?: { message?: string };
            };
            if (!resp || resp.success !== true || !resp.data) {
                maybeNavigateThrSettingsFromBatchError(resp ?? null);
                const code = getThrBatchErrorCode(resp ?? null);
                if (code && code.startsWith("EXPORT_RECON_")) {
                    setThrReconciliationHint(formatApiError(resp ?? null, 422));
                }
                toast(formatApiError(resp ?? null, 422), true);
                return;
            }
            setThrReconciliationHint("");
            lines = resp.data.lines || lines;
            batch = resp.data.batch || batch;
            const skipped = resp.data.skippedAlreadyPaidUserIds?.length ?? 0;
            toast(skipped ? `Selesai (${skipped} sudah lunas dilewati).` : "Pembayaran gateway selesai.", false);
            if (disburseModalEl && window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(disburseModalEl).hide();
            }
            render();
        } catch (e: unknown) {
            const err = e as { status?: number; data?: unknown };
            maybeNavigateThrSettingsFromBatchError(err.data ?? null);
            const code = getThrBatchErrorCode(err.data ?? null);
            if (code && code.startsWith("EXPORT_RECON_")) {
                setThrReconciliationHint(formatApiError(err.data, err.status ?? 0));
            }
            toast(formatApiError(err.data, err.status ?? 0), true);
        } finally {
            disburseConfirmBtn.disabled = false;
        }
    });

    sendSlipBtn?.addEventListener("click", async () => {
        if (!batch?.id) {
            return;
        }
        const checked = bodyEl?.querySelectorAll<HTMLInputElement>("input[data-thr-line-check]:checked:not(:disabled)") ?? [];
        const lineIds: number[] = [];
        checked.forEach((ch) => {
            const lid = parseInt(ch.getAttribute("data-line-id") || "0", 10);
            const line = lines.find((l) => l.id === lid);
            if (!lid || !line?.hasSlip) {
                return;
            }
            if (ch.getAttribute("data-thr-purpose") === "slip") {
                lineIds.push(lid);
                return;
            }
            if (ch.getAttribute("data-thr-purpose") === "pay") {
                lineIds.push(lid);
            }
        });
        if (!lineIds.length) {
            toast("Centang karyawan yang sudah punya slip PDF.", true);
            return;
        }
        sendSlipBtn.disabled = true;
        try {
            const resp = (await apiRequest("post", "/v1/hcm/payroll/thr-batch/send-slip", {
                batchId: batch.id,
                lineIds,
            })) as { success?: boolean; error?: { message?: string } };
            if (!resp || resp.success !== true) {
                toast(formatApiError(resp ?? null, 422), true);
                return;
            }
            toast("Status kirim slip diperbarui (integrasi email/WA dapat ditambahkan).", false);
            await loadBatch();
        } catch (e: unknown) {
            const err = e as { status?: number; data?: unknown };
            toast(formatApiError(err.data, err.status ?? 0), true);
        } finally {
            sendSlipBtn.disabled = false;
        }
    });

    selectAllEl?.addEventListener("change", () => {
        if (!selectAllEl || !bodyEl) {
            return;
        }
        const on = selectAllEl.checked;
        bodyEl.querySelectorAll<HTMLInputElement>("input[data-thr-line-check]:not(:disabled)").forEach((ch) => {
            ch.checked = on;
        });
        updateSummary();
    });

    window.addEventListener("arcavThrSettingsApplied", ((ev: Event) => {
        const ce = ev as CustomEvent<ThrSettingsAppliedDetail>;
        const d = ce.detail;
        if (!d) {
            return;
        }
        void onThrSettingsApplied(d);
    }) as EventListener);

    syncSelectAllCheckbox();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
