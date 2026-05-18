import {
    apiRequest,
    RECON_ERROR_MESSAGES,
    formatIdr,
    setBadgeState,
    setPayrollReconciliationHint,
    setPayrollTaxAnomalyHint,
    setPayrollTaxPolicyHint,
    setPayrollTenantHint,
    toneButton,
} from "./helpers";
import {
    POST_CUTOFF_REVIEW_ONLY_HINT,
    currentRunStatus,
    getPayrollRunRoot,
    getSelectedUserIds,
    hasDownloadedReconciliationForCurrentRun,
    hasMissingTaxProfileAnomaly,
    missingTaxProfileCount,
    payrollRunState,
} from "./shared";
import { isPostCutoffReviewOnlyMode } from "./settings";
import { WorkflowBadge } from "./types";

function applyWorkflowStepState(stepKey: string, state: WorkflowBadge, highlight: boolean): void {
    const root = getPayrollRunRoot();
    const stepElement = root?.querySelector<HTMLElement>(`[data-payroll-step="${stepKey}"]`);
    const badgeElement = stepElement?.querySelector<HTMLElement>("[data-payroll-step-status]") || null;
    if (!stepElement) {
        return;
    }

    stepElement.classList.toggle("border-primary", highlight);
    stepElement.classList.toggle("bg-primary-subtle", highlight);
    stepElement.classList.toggle("bg-white", !highlight);
    setBadgeState(badgeElement, state);
}

export function renderPayrollWorkflow(): void {
    const root = getPayrollRunRoot();
    if (!root) {
        return;
    }

    const hasPeriod = !!payrollRunState.currentPeriodId;
    const hasRun = !!payrollRunState.currentRunId;
    const hasRows = payrollRunState.currentRows.length > 0;
    const selectedCount = getSelectedUserIds().length;
    const runStatus = currentRunStatus();
    const hasPaidRows = payrollRunState.currentRows.some((row) => row.paymentStatus === "paid");
    const tenantReady = !!payrollRunState.activeTenantContext.companyId;
    const policyReady = !payrollRunState.currentRunId || !!payrollRunState.currentTaxGovernancePolicy || !!payrollRunState.currentPolicySnapshot;
    const reviewOnly = isPostCutoffReviewOnlyMode();
    const hasMissingTaxProfile = hasMissingTaxProfileAnomaly();
    const missingTaxProfileUsers = missingTaxProfileCount();
    const overtimeTotal = payrollRunState.currentRows.reduce((sum, row) => sum + Number(row.overtime || 0), 0);
    const overtimeEmployeeCount = payrollRunState.currentRows.filter((row) => Number(row.overtime || 0) > 0).length;
    const evidenceDownloaded = hasDownloadedReconciliationForCurrentRun();
    const canExport = hasRun && hasRows && runStatus === "draft" && !reviewOnly && !hasMissingTaxProfile;
    const canPayWindow = hasRun && hasRows && (runStatus === "draft" || runStatus === "finalized") && !reviewOnly && !hasMissingTaxProfile;

    let stageTitle = "Langkah berikutnya: Calculate Draft";
    let stageDescription = "Mulai atau refresh draft payroll untuk periode aktif sebelum operator meninjau rincian payroll.";
    let stageBadge: WorkflowBadge = { label: "BUTUH TINDAKAN", badgeClass: "bg-primary" };
    let primaryActionTitle = "Hitung draft payroll periode aktif";
    let primaryActionNote = "Gunakan Calculate Draft untuk membuat atau me-refresh draft sebelum review payroll.";
    let primaryActionState: WorkflowBadge = { label: "PRIMARY ACTION", badgeClass: "bg-primary" };
    let guidance = "Gunakan Calculate Draft untuk memulai run payroll aktif.";
    let readinessBadge: WorkflowBadge = { label: "PERLU TINDAKAN", badgeClass: "bg-warning text-dark" };

    if (!hasRun) {
        stageDescription = "Belum ada run aktif. Operator perlu menghitung draft terlebih dahulu sebelum review atau payment.";
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
    } else if (hasMissingTaxProfile) {
        stageTitle = "Lengkapi profil PPh21 terlebih dahulu";
        stageDescription = `${missingTaxProfileUsers} karyawan masih fallback tax status karena profil PPh21 belum lengkap. Export evidence dan payment dikunci sampai anomali selesai.`;
        stageBadge = { label: "ANOMALI PPH21", badgeClass: "bg-danger" };
        primaryActionTitle = "Perbaiki profil PPh21 di modul Tax Employee";
        primaryActionNote = "Payroll sekarang membaca status pajak dari modul PPh21. Lengkapi tax status karyawan lalu Calculate Draft ulang.";
        primaryActionState = { label: "BLOCKED", badgeClass: "bg-danger" };
        guidance = "Buka menu Tax Employee Profiles, lengkapi status pajak karyawan yang belum terisi, lalu kembali ke payroll untuk Calculate Draft ulang.";
        readinessBadge = { label: "PPH21 INCOMPLETE", badgeClass: "bg-danger" };
    } else if (reviewOnly) {
        stageTitle = "Mode post-cutoff: review-only";
        stageDescription = "Operator masih bisa meninjau draft payroll, tetapi export evidence dan payment menunggu payday sesuai snapshot policy run aktif.";
        stageBadge = { label: "REVIEW-ONLY", badgeClass: "bg-warning text-dark" };
        primaryActionTitle = "Review payroll hingga payday tiba";
        primaryActionNote = POST_CUTOFF_REVIEW_ONLY_HINT;
        primaryActionState = { label: "DITAHAN POLICY", badgeClass: "bg-warning text-dark" };
        guidance = "Gunakan periode ini untuk audit hasil draft, tenant aktif, serta snapshot policy sebelum window penandaan pembayaran manual terbuka.";
        readinessBadge = { label: "WAITING PAYDAY", badgeClass: "bg-warning text-dark" };
    } else if (!evidenceDownloaded) {
        stageTitle = "Langkah berikutnya: Export Reconciliation";
        stageDescription = "Draft payroll sudah siap ditinjau. Operator wajib membuat dan mengunduh evidence reconciliation sebelum pembayaran manual dicatat di aplikasi.";
        stageBadge = { label: "EXPORT EVIDENCE", badgeClass: "bg-secondary" };
        primaryActionTitle = "Buat dan unduh evidence reconciliation";
        primaryActionNote = "Evidence harus diunduh dengan sukses. Tanpa unduhan sukses, penandaan pembayaran manual tetap terkunci.";
        primaryActionState = { label: "WAJIB EXPORT", badgeClass: "bg-secondary" };
        guidance = "Urutan operasional: review payroll → export reconciliation → unduh XLSX → tandai selesai dibayar manual.";
        readinessBadge = { label: "SIAP EXPORT", badgeClass: "bg-info text-dark" };
    } else if (!hasPaidRows) {
        stageTitle = "Langkah berikutnya: Tandai Dibayar Manual";
        stageDescription = selectedCount > 0
            ? "Evidence sudah terunduh dan employee sudah dipilih. Operator bisa lanjut mencatat payroll ini sebagai selesai dibayar di luar aplikasi."
            : "Evidence sudah terunduh. Pilih employee yang akan ditandai sudah dibayar di luar aplikasi.";
        stageBadge = { label: "SIAP BAYAR", badgeClass: "bg-success" };
        primaryActionTitle = "Catat pembayaran manual";
        primaryActionNote = "Status paid hanya bisa dicatat setelah evidence payroll run ini terunduh dengan sukses.";
        primaryActionState = { label: "READY TO PAY", badgeClass: "bg-success" };
        guidance = selectedCount > 0
            ? "Review selection sudah siap. Buka modal konfirmasi untuk menandai batch ini sebagai dibayar di luar aplikasi."
            : "Pilih minimal satu employee eligible pada tabel payroll untuk mulai mencatat pembayaran manual.";
        readinessBadge = { label: "PAYMENT READY", badgeClass: "bg-success" };
    } else {
        stageTitle = "Pembayaran payroll sudah tercatat";
        stageDescription = "Sebagian atau seluruh employee pada run ini sudah ditandai paid. Operator dapat audit hasil atau lanjutkan penandaan manual untuk baris yang masih unpaid.";
        stageBadge = { label: "PAYMENT ONGOING", badgeClass: "bg-success" };
        primaryActionTitle = "Audit hasil pembayaran manual atau lanjutkan batch tersisa";
        primaryActionNote = "Jika masih ada baris unpaid, pilih employee yang tersisa lalu lanjutkan penandaan manual dengan evidence terbaru.";
        primaryActionState = { label: "AUDIT / CONTINUE", badgeClass: "bg-success" };
        guidance = "Setelah penandaan manual atau reset DEV, evidence perlu dibuat ulang sebelum batch berikutnya.";
        readinessBadge = { label: "PAYMENT RECORDED", badgeClass: "bg-success" };
    }

    const titleElement = root.querySelector<HTMLElement>("[data-payroll-run-stage-title]");
    const descriptionElement = root.querySelector<HTMLElement>("[data-payroll-run-stage-description]");
    const stageBadgeElement = root.querySelector<HTMLElement>("[data-payroll-run-stage-badge]");
    const actionTitleElement = root.querySelector<HTMLElement>("[data-payroll-run-primary-action-title]");
    const actionNoteElement = root.querySelector<HTMLElement>("[data-payroll-run-primary-action-note]");
    const actionStateElement = root.querySelector<HTMLElement>("[data-payroll-run-primary-action-state]");
    const actionGuidanceElement = root.querySelector<HTMLElement>("[data-payroll-run-action-guidance]");
    const readinessBadgeElement = root.querySelector<HTMLElement>("[data-payroll-run-readiness-badge]");

    if (titleElement) titleElement.textContent = stageTitle;
    if (descriptionElement) descriptionElement.textContent = stageDescription;
    if (actionTitleElement) actionTitleElement.textContent = primaryActionTitle;
    if (actionNoteElement) actionNoteElement.textContent = primaryActionNote;
    if (actionGuidanceElement) actionGuidanceElement.textContent = guidance;
    setBadgeState(stageBadgeElement, stageBadge);
    setBadgeState(actionStateElement, primaryActionState);
    setBadgeState(readinessBadgeElement, readinessBadge);

    applyWorkflowStepState("period", hasPeriod ? { label: "READY", badgeClass: "bg-success" } : { label: "WAITING", badgeClass: "bg-secondary" }, !hasRun);
    applyWorkflowStepState("calculate", hasRun ? { label: "DONE", badgeClass: "bg-success" } : { label: "ACTIVE", badgeClass: "bg-primary" }, !hasRun && hasPeriod);
    applyWorkflowStepState("review", !hasRun ? { label: "WAITING", badgeClass: "bg-secondary" } : hasRows ? { label: evidenceDownloaded || hasPaidRows ? "DONE" : "ACTIVE", badgeClass: evidenceDownloaded || hasPaidRows ? "bg-success" : "bg-primary" } : { label: "CHECK", badgeClass: "bg-warning text-dark" }, hasRun && hasRows && !evidenceDownloaded && !hasPaidRows);
    applyWorkflowStepState("export", !hasRun || !hasRows ? { label: "WAITING", badgeClass: "bg-secondary" } : reviewOnly ? { label: "BLOCKED", badgeClass: "bg-warning text-dark" } : evidenceDownloaded ? { label: "DONE", badgeClass: "bg-success" } : canExport ? { label: "ACTIVE", badgeClass: "bg-primary" } : { label: "LOCKED", badgeClass: "bg-secondary" }, canExport && !evidenceDownloaded);
    applyWorkflowStepState("pay", !hasRun || !hasRows ? { label: "WAITING", badgeClass: "bg-secondary" } : reviewOnly ? { label: "BLOCKED", badgeClass: "bg-warning text-dark" } : hasPaidRows ? { label: "IN PROGRESS", badgeClass: "bg-success" } : evidenceDownloaded ? { label: selectedCount > 0 ? "ACTIVE" : "READY", badgeClass: selectedCount > 0 ? "bg-primary" : "bg-success" } : { label: "WAITING", badgeClass: "bg-secondary" }, evidenceDownloaded && !reviewOnly);

    const tenantNote = root.querySelector<HTMLElement>("[data-payroll-checklist-tenant-note]");
    const policyNote = root.querySelector<HTMLElement>("[data-payroll-checklist-policy-note]");
    const taxProfileNote = root.querySelector<HTMLElement>("[data-payroll-checklist-tax-profile-note]");
    const overtimeNote = root.querySelector<HTMLElement>("[data-payroll-checklist-overtime-note]");
    const evidenceNote = root.querySelector<HTMLElement>("[data-payroll-checklist-evidence-note]");
    const disburseNote = root.querySelector<HTMLElement>("[data-payroll-checklist-disburse-note]");
    const tenantBadge = root.querySelector<HTMLElement>("[data-payroll-checklist-tenant]");
    const policyBadge = root.querySelector<HTMLElement>("[data-payroll-checklist-policy]");
    const taxProfileBadge = root.querySelector<HTMLElement>("[data-payroll-checklist-tax-profile]");
    const overtimeBadge = root.querySelector<HTMLElement>("[data-payroll-checklist-overtime]");
    const evidenceBadge = root.querySelector<HTMLElement>("[data-payroll-checklist-evidence]");
    const disburseBadge = root.querySelector<HTMLElement>("[data-payroll-checklist-disburse]");

    if (tenantNote) {
        tenantNote.textContent = tenantReady
            ? `${payrollRunState.activeTenantContext.companyName || "Tenant aktif"} (ID ${payrollRunState.activeTenantContext.companyId}) terdeteksi dari sesi login.`
            : "Tenant aktif belum terdeteksi dari sesi login. Global super admin perlu memastikan tenant sudah dipilih dengan benar.";
    }
    if (policyNote) {
        policyNote.textContent = policyReady
            ? (payrollRunState.currentTaxGovernancePolicy?.policyCode
                ? `Snapshot policy ${payrollRunState.currentTaxGovernancePolicy.policyCode}${payrollRunState.currentTaxGovernancePolicy.version ? ` v${payrollRunState.currentTaxGovernancePolicy.version}` : ""} terpasang pada run aktif.`
                : "Snapshot payroll policy untuk run aktif tersedia dan dapat dipakai sebagai referensi operasional.")
            : "Run aktif belum menyimpan snapshot policy yang jelas. Review policy tenant sebelum calculate ulang atau payment.";
    }
    if (taxProfileNote) {
        taxProfileNote.textContent = hasMissingTaxProfile
            ? `${missingTaxProfileUsers} karyawan masih fallback tax status karena profil PPh21 belum lengkap. Payroll dikunci hingga data dilengkapi.`
            : "Semua karyawan pada run aktif sudah punya profil PPh21 valid; payroll memakai data status pajak dari modul PPh21.";
    }
    if (overtimeNote) {
        overtimeNote.textContent = !hasRun || !hasRows
            ? "Subtotal overtime akan muncul setelah draft payroll aktif tersedia."
            : overtimeTotal > 0
                ? `${overtimeEmployeeCount} karyawan memiliki overtime dengan subtotal ${formatIdr(overtimeTotal)} pada run aktif. Export XLSX akan memisahkan kolom overtime_total.`
                : "Tidak ada overtime pada run aktif. Export XLSX tetap menyertakan kolom overtime_total dengan nilai 0 agar format tetap seragam.";
    }
    if (evidenceNote) {
        evidenceNote.textContent = evidenceDownloaded
            ? "Evidence reconciliation payroll run ini sudah terunduh. Penandaan pembayaran manual dapat dibuka sesuai selection employee."
            : hasMissingTaxProfile
                ? "Evidence belum bisa dibuat karena masih ada profil PPh21 yang missing pada run ini."
                : canExport
                    ? "Evidence belum terunduh. Operator wajib export lalu menyelesaikan unduhan XLSX sebelum menandai pembayaran manual; file akan membawa subtotal overtime secara terpisah."
                    : "Evidence belum siap karena draft belum lengkap atau window pembayaran manual belum terbuka.";
    }
    if (disburseNote) {
        disburseNote.textContent = hasMissingTaxProfile
            ? "Window penandaan pembayaran manual dikunci sampai semua profil PPh21 karyawan di run ini lengkap, lalu draft dihitung ulang."
            : reviewOnly
                ? POST_CUTOFF_REVIEW_ONLY_HINT
                : canPayWindow
                    ? (selectedCount > 0 ? `${selectedCount} employee dipilih untuk penandaan pembayaran berikutnya.` : "Window penandaan pembayaran manual terbuka. Pilih employee eligible dari tabel payroll untuk lanjut.")
                    : "Window penandaan pembayaran manual masih menunggu draft/evidence atau run belum berada pada status yang bisa dibayar.";
    }

    setBadgeState(tenantBadge, tenantReady ? { label: "SIAP", badgeClass: "bg-success" } : { label: "PERIKSA", badgeClass: "bg-warning text-dark" });
    setBadgeState(policyBadge, policyReady ? { label: "SIAP", badgeClass: "bg-success" } : { label: "PERIKSA", badgeClass: "bg-warning text-dark" });
    setBadgeState(taxProfileBadge, hasMissingTaxProfile ? { label: "DIBLOKIR", badgeClass: "bg-danger" } : { label: "SIAP", badgeClass: "bg-success" });
    setBadgeState(overtimeBadge, !hasRun || !hasRows
        ? { label: "PENDING", badgeClass: "bg-secondary" }
        : overtimeTotal > 0
            ? { label: "ADA", badgeClass: "bg-info text-dark" }
            : { label: "TIDAK ADA", badgeClass: "bg-light text-dark" });
    setBadgeState(evidenceBadge, hasMissingTaxProfile ? { label: "DIBLOKIR", badgeClass: "bg-danger" } : evidenceDownloaded ? { label: "SIAP", badgeClass: "bg-success" } : canExport ? { label: "PENDING", badgeClass: "bg-primary" } : { label: "PENDING", badgeClass: "bg-secondary" });
    setBadgeState(disburseBadge, hasMissingTaxProfile ? { label: "DIBLOKIR", badgeClass: "bg-danger" } : reviewOnly ? { label: "DIBLOKIR", badgeClass: "bg-warning text-dark" } : canPayWindow ? { label: "SIAP", badgeClass: "bg-success" } : { label: "PENDING", badgeClass: "bg-secondary" });

    toneButton(root.querySelector<HTMLButtonElement>("[data-payroll-run-calculate]"), "btn-primary", "btn-outline-primary", !hasRun || (!hasRows && !reviewOnly));
    toneButton(root.querySelector<HTMLButtonElement>("[data-payroll-run-export-evidence]"), "btn-secondary", "btn-outline-secondary", canExport && !evidenceDownloaded);
    toneButton(root.querySelector<HTMLButtonElement>("[data-payroll-run-disburse]"), "btn-success", "btn-outline-success", evidenceDownloaded && !reviewOnly && !hasMissingTaxProfile);
}

export function refreshSelectionSummary(): void {
    const root = getPayrollRunRoot();
    if (!root) return;
    const selectedIds = getSelectedUserIds();
    const selectedCountElement = root.querySelector<HTMLElement>("[data-payroll-run-selected-count]");
    const disburseButton = root.querySelector<HTMLButtonElement>("[data-payroll-run-disburse]");
    const resetButton = root.querySelector<HTMLButtonElement>("[data-payroll-run-reset-payments]");
    if (selectedCountElement) {
        selectedCountElement.textContent = String(selectedIds.length);
    }
    if (disburseButton) {
        const status = currentRunStatus();
        const reviewOnly = isPostCutoffReviewOnlyMode();
        const hasMissingTaxProfile = hasMissingTaxProfileAnomaly();
        const shouldDisable = !payrollRunState.currentRunId ||
            payrollRunState.currentRows.length === 0 ||
            selectedIds.length === 0 ||
            !hasDownloadedReconciliationForCurrentRun() ||
            !(status === "draft" || status === "finalized") ||
            reviewOnly ||
            hasMissingTaxProfile;
        disburseButton.disabled = shouldDisable;
        if (reviewOnly) {
            setPayrollReconciliationHint(POST_CUTOFF_REVIEW_ONLY_HINT);
        } else if (hasMissingTaxProfile) {
            setPayrollReconciliationHint("Pembayaran manual dikunci: masih ada profil PPh21 karyawan yang belum lengkap pada run ini.");
        }
    }
    if (resetButton) {
        resetButton.disabled = !payrollRunState.currentRunId;
    }
    renderPayrollWorkflow();
}

export function syncExportReconciliationButton(): void {
    const root = getPayrollRunRoot();
    if (!root) return;
    const exportButton = root.querySelector<HTMLButtonElement>("[data-payroll-run-export-evidence]");
    if (exportButton) {
        const status = String(payrollRunState.currentRunStatus || "").toLowerCase();
        const hasMissingTaxProfile = hasMissingTaxProfileAnomaly();
        const exportAllowed = !!payrollRunState.currentRunId && payrollRunState.currentRows.length > 0 && status === "draft" && !isPostCutoffReviewOnlyMode() && !hasMissingTaxProfile;
        exportButton.disabled = !exportAllowed;
    }
    renderPayrollWorkflow();
}

export function syncCalculateDraftButton(): void {
    const root = getPayrollRunRoot();
    if (!root) return;
    const calculateButton = root.querySelector<HTMLButtonElement>("[data-payroll-run-calculate]");
    if (!calculateButton) return;
    const status = String(payrollRunState.currentRunStatus || "").toLowerCase();
    const hasPaidRows = payrollRunState.currentRows.some((row) => row.paymentStatus === "paid");
    const canCalculate = !!payrollRunState.currentPeriodId && (
        !payrollRunState.currentRunId
        || status === "draft"
        || status === "void"
        || (status === "finalized" && !hasPaidRows)
    );
    calculateButton.disabled = !canCalculate;
    renderPayrollWorkflow();
}

export function renderRunContextSummary(): void {
    const root = getPayrollRunRoot();
    if (!root) {
        return;
    }

    const tenantElement = root.querySelector<HTMLElement>("[data-payroll-run-tenant-context]");
    const policyElement = root.querySelector<HTMLElement>("[data-payroll-run-tax-policy]");
    const tenantId = payrollRunState.activeTenantContext.companyId;
    const tenantName = payrollRunState.activeTenantContext.companyName;

    if (tenantElement) {
        tenantElement.textContent = tenantId ? `${tenantName ? `${tenantName} ` : ""}(ID ${tenantId})` : "Tenant tidak terdeteksi";
    }

    if (!tenantId) {
        setPayrollTenantHint("Tenant aktif tidak terdeteksi dari sesi login. Pastikan global super admin memilih tenant yang benar sebelum Calculate Draft.");
    } else {
        setPayrollTenantHint("");
    }

    const policy = payrollRunState.currentTaxGovernancePolicy;
    if (policyElement) {
        if (policy?.policyCode || policy?.version) {
            const code = policy.policyCode || "POLICY";
            const version = policy.version ? `v${policy.version}` : "v?";
            policyElement.textContent = `${code} (${version})`;
        } else {
            policyElement.textContent = "Tidak ada snapshot policy";
        }
    }

    if (payrollRunState.currentRunId && !policy) {
        setPayrollTaxPolicyHint("Run ini belum menyimpan snapshot policy tax governance. Pastikan tenant punya policy published yang efektif sebelum Calculate Draft.");
    } else {
        setPayrollTaxPolicyHint("");
    }

    if (payrollRunState.currentRunId && hasMissingTaxProfileAnomaly()) {
        setPayrollTaxAnomalyHint(`Terdeteksi ${missingTaxProfileCount()} karyawan dengan profil PPh21 belum lengkap (fallback status pajak). Lengkapi di modul Tax Employee lalu Calculate Draft ulang.`);
    } else {
        setPayrollTaxAnomalyHint("");
    }

    renderPayrollWorkflow();
}

function showEvidenceIndicator(evidence: any): void {
    const root = getPayrollRunRoot();
    if (!root) return;
    const indicatorElement = root.querySelector<HTMLElement>("[data-payroll-run-evidence-indicator]");
    if (!indicatorElement) return;

    const statusBadge = indicatorElement.querySelector<HTMLElement>("[data-evidence-status]");
    const timestampElement = indicatorElement.querySelector<HTMLElement>("[data-evidence-timestamp]");

    if (!evidence) {
        indicatorElement.classList.add("d-none");
        renderPayrollWorkflow();
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
    renderPayrollWorkflow();
}

export async function fetchLatestEvidence(): Promise<void> {
    if (!payrollRunState.currentRunId) return;
    try {
        const payload = await apiRequest("GET", "/v1/reconciliation/exports", {
            featureKey: "payroll_run",
            actionKey: "disburse",
            scopeRef: String(payrollRunState.currentRunId),
        }) as { data?: unknown[] };
        const evidence = payload && Array.isArray(payload.data) && payload.data.length > 0 ? payload.data[0] : null;
        showEvidenceIndicator(evidence);
    } catch (error) {
        console.warn("Failed to fetch evidence status:", error);
        showEvidenceIndicator(null);
    }
}