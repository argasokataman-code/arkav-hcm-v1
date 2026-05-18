import { roundMoney2 } from "../thr-calculation";
import {
    apiRequest,
    eligibleBadgeHtml,
    escapeHtml,
    fetchThrLatestEvidence,
    fetchThrSlipPdfBlob,
    formatApiError,
    formatIdr,
    formatMultiplierPercent,
    formatPaidAtShort,
    getCalendarYear,
    getThrBatchErrorCode,
    maybeNavigateThrSettingsFromBatchError,
    onAuthFailure,
    pathLooksLikePayrollThr,
    paymentStatusBadgeHtml,
    rowStatusBadgeHtml,
    setThrReconciliationHint,
    thrDisburseGatewayLabel,
    toast,
    triggerThrExportReconciliation,
    truncateMiddle,
} from "./helpers";
import { BatchLine, BatchMeta, ThrSettingsAppliedDetail } from "./types";

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

    const bootstrapApi = (window as unknown as { bootstrap?: { Modal?: { getOrCreateInstance: (el: HTMLElement) => { show: () => void; hide: () => void } } } }).bootstrap;

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
        const fileName = slipPreviewPublicNo && slipPreviewPublicNo.trim() !== ""
            ? `thr-slip-${slipPreviewPublicNo.replace(/^#/, "").trim()}.pdf`
            : `thr-slip-THR-${cy}-${slipPreviewLineId}.pdf`;
        const anchor = document.createElement("a");
        anchor.href = slipPreviewBlobUrl;
        anchor.download = fileName;
        anchor.rel = "noopener";
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
    }

    function openThrSlipPreview(lineId: number): void {
        if (!slipPreviewModalEl) {
            toast("Modal preview tidak ditemukan.", true);
            return;
        }
        const batchError = document.querySelector<HTMLElement>("[data-thr-batch-error]");
        if (batchError) {
            batchError.textContent = "";
            batchError.classList.add("d-none");
        }
        cleanupThrSlipPreview();
        slipPreviewLineId = lineId;
        const lineRecord = lines.find((item) => item.id === lineId);
        slipPreviewPublicNo = (lineRecord?.thrSlipPublicNo && String(lineRecord.thrSlipPublicNo).trim() !== "" ? String(lineRecord.thrSlipPublicNo).trim() : null)
            ?? (lineRecord?.slipNumber && String(lineRecord.slipNumber).trim() !== "" ? String(lineRecord.slipNumber).replace(/^#/, "").trim() : null)
            ?? (batch != null ? `THR-${batch.calendarYear}-${lineId}` : null);
        if (slipPreviewSlipNoEl) {
            slipPreviewSlipNoEl.textContent = slipPreviewPublicNo != null && slipPreviewPublicNo !== "" ? `#${slipPreviewPublicNo}` : "—";
        }
        const modalApi = bootstrapApi?.Modal?.getOrCreateInstance(slipPreviewModalEl);
        modalApi?.show();
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
            .catch((error: unknown) => {
                modalApi?.hide();
                let status = 0;
                let data: unknown;
                if (error && typeof error === "object" && "response" in error) {
                    const axiosError = error as { response?: { status?: number; data?: unknown } };
                    status = axiosError.response?.status ?? 0;
                    data = axiosError.response?.data;
                } else if (error && typeof error === "object" && "status" in error) {
                    const fetchError = error as { status: number; data: unknown };
                    status = fetchError.status;
                    data = fetchError.data;
                }
                if (onAuthFailure(status, data ?? null)) {
                    return;
                }
                if (data instanceof Blob) {
                    data.text().then((text) => {
                        try {
                            const json = JSON.parse(text) as { error?: { message?: string } };
                            toast(json.error?.message || "Slip tidak tersedia.", true);
                        } catch {
                            toast("Slip tidak tersedia.", true);
                        }
                    });
                    return;
                }
                toast(formatApiError(data, status), true);
            });
    }

    slipPreviewModalEl?.addEventListener("hidden.bs.modal", cleanupThrSlipPreview);
    slipModalDownload?.addEventListener("click", downloadThrSlipFromModal);

    root.addEventListener("click", ((event: Event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const previewButton = target.closest<HTMLElement>("[data-thr-slip-preview]");
        if (!previewButton || !root.contains(previewButton)) {
            return;
        }
        event.preventDefault();
        const id = parseInt(previewButton.getAttribute("data-line-id") || "0", 10);
        if (id) {
            openThrSlipPreview(id);
        }
    }) as EventListener);

    function showErr(message: string): void {
        if (errEl) {
            errEl.textContent = message;
            errEl.classList.toggle("d-none", !message);
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
        const checkedCount = boxes.filter((box) => box.checked).length;
        if (checkedCount === 0) {
            selectAllEl.checked = false;
            selectAllEl.indeterminate = false;
        } else if (checkedCount === boxes.length) {
            selectAllEl.checked = true;
            selectAllEl.indeterminate = false;
        } else {
            selectAllEl.checked = false;
            selectAllEl.indeterminate = true;
        }
    }

    function updateActionButtons(): void {
        const isDraft = batch?.status === "draft";
        const checked = bodyEl?.querySelectorAll<HTMLInputElement>("input[data-thr-line-check]:checked:not(:disabled)") ?? [];
        let anyCheckedPayable = false;
        checked.forEach((checkbox) => {
            if (checkbox.getAttribute("data-thr-purpose") !== "pay") {
                return;
            }
            const userId = parseInt(checkbox.getAttribute("data-user-id") || "0", 10);
            const line = lines.find((item) => item.userId === userId);
            if (line && line.eligible && line.thrGross > 0) {
                anyCheckedPayable = true;
            }
        });
        if (disburseBtn) {
            disburseBtn.disabled = !isDraft || !anyCheckedPayable;
        }

        let anyCheckedSlip = false;
        checked.forEach((checkbox) => {
            const lineId = parseInt(checkbox.getAttribute("data-line-id") || "0", 10);
            const line = lines.find((item) => item.id === lineId);
            if (line?.hasSlip) {
                anyCheckedSlip = true;
            }
        });
        if (sendSlipBtn) {
            sendSlipBtn.disabled = !batch || !anyCheckedSlip;
        }
    }

    function updateSummary(): void {
        const checks = bodyEl?.querySelectorAll<HTMLInputElement>("input[data-thr-line-check]:checked") ?? [];
        let sumCents = 0;
        checks.forEach((checkbox) => {
            if (checkbox.disabled || checkbox.getAttribute("data-thr-purpose") !== "pay") {
                return;
            }
            const cents = parseInt(checkbox.getAttribute("data-thr-cents") || "0", 10);
            sumCents += cents;
        });
        const sum = roundMoney2(sumCents / 100);
        if (countEl) {
            let payableCount = 0;
            checks.forEach((checkbox) => {
                if (checkbox.getAttribute("data-thr-purpose") === "pay") {
                    payableCount += 1;
                }
            });
            countEl.textContent = String(payableCount);
        }
        if (sumEl) {
            sumEl.textContent = formatIdr(sum);
        }
        updateActionButtons();
        syncSelectAllCheckbox();
    }

    function bankCellHtml(line: BatchLine): string {
        const name = (line.bankName && String(line.bankName).trim()) || "";
        const accountNo = (line.bankAccountNo && String(line.bankAccountNo).trim()) || "";
        if (!name && !accountNo) {
            return '<span class="text-muted">—</span>';
        }
        return `<div class="text-gray-9">${escapeHtml(name || "—")}</div><div class="text-muted">${escapeHtml(accountNo || "—")}</div>`;
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

        bodyEl.innerHTML = lines.map((line) => {
            const cents = Math.round(line.thrGross * 100);
            const paySelect = Boolean(isDraft && line.eligible && line.thrGross > 0);
            const slipSelect = Boolean(!isDraft && line.hasSlip);
            const showCheckbox = paySelect || slipSelect;
            const defaultChecked = paySelect && (line.paymentStatus === "unpaid" || line.paymentStatus === "failed" || line.paymentStatus === "pending");
            const purposeAttr = paySelect ? "pay" : "slip";
            const payBadge = line.paymentStatus === "failed" && line.paymentFailureReason ? `<div class="small text-danger mt-1">${escapeHtml(line.paymentFailureReason)}</div>` : "";
            const slipCell = line.hasSlip
                ? `<button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" data-thr-slip-preview data-line-id="${line.id}" title="Preview slip THR (PDF)"><i class="ti ti-eye fs-14" aria-hidden="true"></i><span>Preview</span></button>${line.slipNotifySentAt ? ' <span class="badge badge-soft-secondary fw-normal ms-1">Terkirim</span>' : ""}`
                : '<span class="text-muted">—</span>';
            const refDisplay = escapeHtml(truncateMiddle(line.paymentGatewayRef, 22));
            const checkCell = showCheckbox
                ? `<div class="form-check form-check-md d-flex justify-content-center mb-0"><input type="checkbox" class="form-check-input" data-thr-line-check data-thr-purpose="${purposeAttr}" data-thr-cents="${cents}" data-user-id="${line.userId}" data-line-id="${line.id}" ${defaultChecked ? "checked" : ""}></div>`
                : '<span class="text-muted">—</span>';
            return `<tr data-thr-line="${line.userId}">
                <td class="align-middle text-center">${checkCell}</td>
                <td class="align-middle"><div class="fw-medium">${escapeHtml(line.fullName)}</div><span class="fs-12 text-muted">${escapeHtml(line.employeeNo || "—")}</span></td>
                <td class="align-middle fs-12 text-nowrap">${bankCellHtml(line)}</td>
                <td class="align-middle text-center">${eligibleBadgeHtml(line)}</td>
                <td class="align-middle"><span class="text-gray-5">${escapeHtml(line.joinDateUsed)}</span></td>
                <td class="align-middle text-end fs-12 text-gray-5">${formatIdr(line.baseSalary)}</td>
                <td class="align-middle text-end fw-medium">${formatIdr(line.referenceWage)}</td>
                <td class="align-middle text-center"><span class="text-gray-5">${line.monthsOfService}</span></td>
                <td class="align-middle text-end fs-12 text-gray-5">${formatMultiplierPercent(line.multiplier)}</td>
                <td class="align-middle">${rowStatusBadgeHtml(line.rowStatus)}</td>
                <td class="align-middle text-end fw-semibold text-gray-9">${formatIdr(line.thrGross)}</td>
                <td class="align-middle">${paymentStatusBadgeHtml(line.paymentStatus)}${payBadge}</td>
                <td class="align-middle fs-12 text-nowrap text-gray-5">${escapeHtml(formatPaidAtShort(line.paidAt))}</td>
                <td class="align-middle fs-12 text-gray-5" title="${escapeHtml(line.paymentGatewayRef || "")}"><code class="small text-muted mb-0">${refDisplay}</code></td>
                <td class="align-middle">${slipCell}</td>
            </tr>`;
        }).join("");

        bodyEl.querySelectorAll("input[data-thr-line-check]").forEach((element) => {
            element.addEventListener("change", updateSummary);
        });
        updateSummary();

        if (batchHint && batch) {
            if (batch.status === "assigned") {
                batchHint.innerHTML = `THR tahun <strong>${batch.calendarYear}</strong> sudah diposting ke payroll. Status pembayaran per karyawan dan slip tercatat di bawah.`;
            } else {
                batchHint.innerHTML = `Cut-off perhitungan: <strong>${escapeHtml(batch.cutoffDate)}</strong>. Centang karyawan → <strong>Tandai THR Dibayar</strong>. Data otomatis diposting ke payroll jika semua pembayaran lunas.`;
            }
        }
    }

    async function loadBatch(): Promise<void> {
        const year = getCalendarYear();
        if (year === null) {
            return;
        }
        try {
            const response = await apiRequest("get", "/v1/hcm/payroll/thr-batch?calendarYear=" + encodeURIComponent(String(year))) as { success?: boolean; data?: { batch: BatchMeta | null; lines: BatchLine[] } };
            if (!response || response.success !== true || !response.data) {
                return;
            }
            batch = response.data.batch;
            lines = response.data.lines || [];
            render();
            if (batch?.id) {
                void fetchThrLatestEvidence(batch.id);
            }
        } catch {
            return;
        }
    }

    async function maybeAutoGenerateFromSetup(detail: ThrSettingsAppliedDetail): Promise<void> {
        const year = getCalendarYear();
        if (year === null) {
            return;
        }
        if (detail.calendarYear !== null && detail.calendarYear !== year) {
            return;
        }
        const cutoff = detail.settings?.calculationCutoffDate;
        if (!cutoff || String(cutoff).trim() === "" || batch?.status === "assigned" || batch?.status === "draft" || !genBtn) {
            return;
        }

        genBtn.disabled = true;
        try {
            const response = await apiRequest("post", "/v1/hcm/payroll/thr-batch/generate", { calendarYear: year }) as { success?: boolean; data?: { batch: BatchMeta; lines: BatchLine[] }; error?: { code?: string } };
            if (!response || response.success !== true || !response.data) {
                const code = response?.error?.code;
                if (code === "THR_YEAR_ALREADY_ASSIGNED" || code === "THR_SETUP_CUTOFF_REQUIRED") {
                    return;
                }
                return;
            }
            batch = response.data.batch;
            lines = response.data.lines || [];
            toast("Daftar THR dibuat otomatis dari pengaturan periode.", false);
            render();
        } catch (error: unknown) {
            const err = error as { data?: { error?: { code?: string } } };
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
        const year = getCalendarYear();
        if (year === null) {
            toast("Pilih tahun kalender di pengaturan.", true);
            return;
        }
        showErr("");
        genBtn.disabled = true;
        try {
            const response = await apiRequest("post", "/v1/hcm/payroll/thr-batch/generate", { calendarYear: year }) as { success?: boolean; data?: { batch: BatchMeta; lines: BatchLine[] }; error?: { message?: string; code?: string } };
            if (!response || response.success !== true || !response.data) {
                const message = formatApiError(response ?? null, 422);
                maybeNavigateThrSettingsFromBatchError(response ?? null);
                showErr(message);
                toast(message, true);
                return;
            }
            batch = response.data.batch;
            lines = response.data.lines || [];
            toast("Daftar THR dihasilkan.", false);
            render();
        } catch (error: unknown) {
            const err = error as { status?: number; data?: { error?: { message?: string } } };
            const message = formatApiError(err.data, err.status ?? 0);
            maybeNavigateThrSettingsFromBatchError(err.data ?? null);
            showErr(message);
            toast(message, true);
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

        const formatValue = (value: number) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(value);
        const eligibleLines = lines.filter((line) => line.eligible);
        const yearElement = modal.querySelector("[data-thr-recon-preview-year]");
        const countElement = modal.querySelector("[data-thr-recon-preview-count]");
        const totalElement = modal.querySelector("[data-thr-recon-preview-total]");
        const statusElement = modal.querySelector("[data-thr-recon-preview-status]");
        const tbody = modal.querySelector("[data-thr-recon-preview-body]");

        if (yearElement) yearElement.textContent = String(batch.calendarYear);
        if (countElement) countElement.textContent = String(eligibleLines.length);
        if (totalElement) totalElement.textContent = formatValue(batch.grandTotalEligible);
        if (statusElement) statusElement.textContent = batch.status ?? "—";

        if (tbody) {
            if (eligibleLines.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada karyawan eligible.</td></tr>';
            } else {
                tbody.innerHTML = eligibleLines.map((line) => `
                    <tr>
                        <td><div class="fw-semibold">${line.fullName || "—"}</div><div class="text-muted small">${line.employeeNo || String(line.userId)}</div></td>
                        <td class="text-end">${formatValue(line.referenceWage)}</td>
                        <td class="text-center">${line.monthsOfService} bln</td>
                        <td class="text-center">${(line.multiplier * 100).toFixed(0)}%</td>
                        <td class="text-end fw-semibold text-primary">${formatValue(line.thrGross)}</td>
                        <td class="text-center"><span class="badge bg-light text-dark border">${line.paymentStatus || "pending"}</span></td>
                    </tr>`).join("");
                tbody.innerHTML += `
                    <tr class="table-light fw-semibold">
                        <td>Total (${eligibleLines.length} karyawan eligible)</td>
                        <td colspan="3"></td>
                        <td class="text-end text-primary">${formatValue(batch.grandTotalEligible)}</td>
                        <td></td>
                    </tr>`;
            }
        }

        bootstrapApi?.Modal?.getOrCreateInstance(modal)?.show();
    }

    exportBtn?.addEventListener("click", () => {
        openThrReconciliationPreviewModal();
    });

    const previewDownloadButton = document.querySelector<HTMLButtonElement>("[data-thr-recon-preview-download]");
    previewDownloadButton?.addEventListener("click", async () => {
        if (!batch?.id) return;
        const modal = document.getElementById("thr_reconciliation_preview_modal");
        if (modal) {
            bootstrapApi?.Modal?.getOrCreateInstance(modal)?.hide();
        }
        try {
            void await triggerThrExportReconciliation(batch.id, lines);
        } catch (error) {
            toast(`Error export: ${String(error)}`, true);
        }
    });

    function getCheckedUserIds(): number[] {
        const ids: number[] = [];
        bodyEl?.querySelectorAll<HTMLInputElement>("input[data-thr-line-check]:checked:not(:disabled)").forEach((checkbox) => {
            if (checkbox.getAttribute("data-thr-purpose") !== "pay") {
                return;
            }
            const userId = parseInt(checkbox.getAttribute("data-user-id") || "0", 10);
            if (userId) {
                ids.push(userId);
            }
        });
        return ids;
    }

    function countDisburseTargets(ids: number[]): number {
        let count = 0;
        for (const userId of ids) {
            const line = lines.find((item) => item.userId === userId);
            if (line && line.paymentStatus !== "paid") {
                count += 1;
            }
        }
        return count;
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
        const targetCount = countDisburseTargets(ids);
        if (targetCount === 0) {
            toast("Semua terpilih sudah lunas — tidak ada yang perlu ditandai manual.", true);
            return;
        }
        if (disburseModalEl && batch) {
            const checked = ids.length;
            const skipPaid = checked - targetCount;
            let totalGross = 0;
            for (const userId of ids) {
                const line = lines.find((item) => item.userId === userId);
                if (line && line.paymentStatus !== "paid") {
                    totalGross += line.thrGross;
                }
            }
            const driverRaw = disburseModalEl.getAttribute("data-thr-disburse-gateway-driver")?.trim() || "stub";
            const setText = (selector: string, text: string): void => {
                const element = disburseModalEl.querySelector(selector);
                if (element) {
                    element.textContent = text;
                }
            };
            setText("[data-thr-disburse-modal-year]", String(batch.calendarYear));
            setText("[data-thr-disburse-modal-driver]", thrDisburseGatewayLabel(driverRaw));
            setText("[data-thr-disburse-modal-checked]", String(checked));
            setText("[data-thr-disburse-modal-count]", String(targetCount));
            setText("[data-thr-disburse-modal-skip-paid]", String(skipPaid));
            setText("[data-thr-disburse-modal-total]", formatIdr(totalGross));
            const stubNote = disburseModalEl.querySelector<HTMLElement>("[data-thr-disburse-modal-stub-note]");
            if (stubNote) {
                stubNote.hidden = driverRaw.toLowerCase() !== "stub";
            }
        } else if (disburseCountEl) {
            disburseCountEl.textContent = String(targetCount);
        }
        bootstrapApi?.Modal?.getOrCreateInstance(disburseModalEl as HTMLElement)?.show();
    });

    disburseConfirmBtn?.addEventListener("click", async () => {
        if (!batch || batch.status !== "draft" || !batch.id) {
            return;
        }
        const ids = getCheckedUserIds();
        disburseConfirmBtn.disabled = true;
        try {
            const response = await apiRequest("post", "/v1/hcm/payroll/thr-batch/disburse", { batchId: batch.id, userIds: ids }) as { success?: boolean; data?: { lines: BatchLine[]; batch: BatchMeta; skippedAlreadyPaidUserIds?: number[] } };
            if (!response || response.success !== true || !response.data) {
                maybeNavigateThrSettingsFromBatchError(response ?? null);
                const code = getThrBatchErrorCode(response ?? null);
                if (code && code.startsWith("EXPORT_RECON_")) {
                    setThrReconciliationHint(formatApiError(response ?? null, 422));
                }
                toast(formatApiError(response ?? null, 422), true);
                return;
            }
            setThrReconciliationHint("");
            lines = response.data.lines || lines;
            batch = response.data.batch || batch;
            const skipped = response.data.skippedAlreadyPaidUserIds?.length ?? 0;
            toast(skipped ? `Selesai (${skipped} sudah lunas dilewati).` : "Pembayaran manual THR tercatat.", false);
            bootstrapApi?.Modal?.getOrCreateInstance(disburseModalEl as HTMLElement)?.hide();
            render();
        } catch (error: unknown) {
            const err = error as { status?: number; data?: unknown };
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
        checked.forEach((checkbox) => {
            const lineId = parseInt(checkbox.getAttribute("data-line-id") || "0", 10);
            const line = lines.find((item) => item.id === lineId);
            if (!lineId || !line?.hasSlip) {
                return;
            }
            lineIds.push(lineId);
        });
        if (!lineIds.length) {
            toast("Centang karyawan yang sudah punya slip PDF.", true);
            return;
        }
        sendSlipBtn.disabled = true;
        try {
            const response = await apiRequest("post", "/v1/hcm/payroll/thr-batch/send-slip", { batchId: batch.id, lineIds }) as { success?: boolean };
            if (!response || response.success !== true) {
                toast(formatApiError(response ?? null, 422), true);
                return;
            }
            toast("Status kirim slip diperbarui (integrasi email/WA dapat ditambahkan).", false);
            await loadBatch();
        } catch (error: unknown) {
            const err = error as { status?: number; data?: unknown };
            toast(formatApiError(err.data, err.status ?? 0), true);
        } finally {
            sendSlipBtn.disabled = false;
        }
    });

    selectAllEl?.addEventListener("change", () => {
        if (!selectAllEl || !bodyEl) {
            return;
        }
        const checked = selectAllEl.checked;
        bodyEl.querySelectorAll<HTMLInputElement>("input[data-thr-line-check]:not(:disabled)").forEach((checkbox) => {
            checkbox.checked = checked;
        });
        updateSummary();
    });

    window.addEventListener("arcavThrSettingsApplied", ((event: Event) => {
        const customEvent = event as CustomEvent<ThrSettingsAppliedDetail>;
        const detail = customEvent.detail;
        if (!detail) {
            return;
        }
        void onThrSettingsApplied(detail);
    }) as EventListener);

    syncSelectAllCheckbox();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}