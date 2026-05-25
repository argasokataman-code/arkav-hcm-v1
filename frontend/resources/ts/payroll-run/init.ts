import { getPayrollRunRoot, getPayrollSettingsRoot } from "./shared";
import { loadPayrollSettings, renderPayrollSettingsPreview, savePayrollSettings, updateSettingsPreviewStateFromInputs } from "./settings";
import { calculateDraft, disburseSelected, loadPeriod, openDisburseModal, openEmployeeDetailModal, openReconciliationPreviewModal, resetPayments, triggerExportReconciliation } from "./run-ui";
import { refreshSelectionSummary } from "./workflow";
import { bindWorkConfigurator } from "./work-config";

type BootstrapModalInstance = {
    show: () => void;
    hide: () => void;
};

type BootstrapApi = {
    Modal?: {
        getOrCreateInstance: (el: HTMLElement) => BootstrapModalInstance;
        getInstance?: (el: HTMLElement) => BootstrapModalInstance | null;
    };
};

function blurFocusedDescendant(container: HTMLElement): void {
    const activeElement = document.activeElement;
    if (activeElement instanceof HTMLElement && container.contains(activeElement)) {
        activeElement.blur();
    }
}

function hideModalSafely(modal: HTMLElement, bootstrapApi?: BootstrapApi): void {
    blurFocusedDescendant(modal);
    bootstrapApi?.Modal?.getOrCreateInstance(modal)?.hide();
}

function bindModalFocusManagement(modal: HTMLElement | null, returnFocusTarget: HTMLElement | null): void {
    if (!modal || modal.dataset.focusBound === "1") {
        return;
    }

    modal.dataset.focusBound = "1";
    modal.addEventListener("hide.bs.modal", () => {
        blurFocusedDescendant(modal);
    });
    modal.addEventListener("hidden.bs.modal", () => {
        returnFocusTarget?.focus();
    });
}

function bindEvents(): void {
    const root = getPayrollRunRoot();
    if (!root || root.dataset.bound === "1") return;
    root.dataset.bound = "1";
    const bootstrapApi = (window as unknown as { bootstrap?: BootstrapApi }).bootstrap;

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
        const target = event.target as HTMLElement;
        if (target.closest("[data-payroll-run-calculate]")) {
            event.preventDefault();
            void calculateDraft(false);
            return;
        }
        if (target.closest("[data-payroll-run-export-evidence]")) {
            event.preventDefault();
            openReconciliationPreviewModal();
            return;
        }
        if (target.closest("[data-payroll-run-disburse]")) {
            event.preventDefault();
            openDisburseModal();
            return;
        }
        if (target.closest("[data-payroll-run-reset-payments]")) {
            event.preventDefault();
            void resetPayments();
            return;
        }

        const payOneButton = target.closest("[data-payroll-run-pay-one]") as HTMLElement | null;
        if (payOneButton) {
            event.preventDefault();
            const userId = Number(payOneButton.getAttribute("data-payroll-run-pay-one") || 0);
            if (userId > 0) {
                root.querySelectorAll<HTMLInputElement>("[data-payroll-run-row-check]").forEach((checkbox) => {
                    checkbox.checked = Number(checkbox.value) === userId;
                });
                refreshSelectionSummary();
                openDisburseModal([userId]);
            }
            return;
        }

        const detailButton = target.closest("[data-payroll-run-view-one]") as HTMLElement | null;
        if (detailButton) {
            event.preventDefault();
            const userId = Number(detailButton.getAttribute("data-payroll-run-view-one") || 0);
            if (userId > 0) {
                openEmployeeDetailModal(userId);
            }
        }
    });

    root.addEventListener("change", (event) => {
        const target = event.target as HTMLElement;
        const selectAll = target.closest("[data-payroll-run-select-all]") as HTMLInputElement | null;
        if (selectAll) {
            root.querySelectorAll<HTMLInputElement>("[data-payroll-run-row-check]").forEach((checkbox) => {
                if (!checkbox.disabled) {
                    checkbox.checked = selectAll.checked;
                }
            });
            refreshSelectionSummary();
            return;
        }

        if (target.closest("[data-payroll-run-row-check]")) {
            const checks = Array.from(root.querySelectorAll<HTMLInputElement>("[data-payroll-run-row-check]"));
            const selectAllInput = root.querySelector<HTMLInputElement>("[data-payroll-run-select-all]");
            if (selectAllInput) {
                selectAllInput.checked = checks.length > 0 && checks.every((checkbox) => checkbox.checked || checkbox.disabled);
            }
            refreshSelectionSummary();
        }
    });

    const gatewayModal = document.getElementById("payroll_gateway_modal");
    const disburseTrigger = root.querySelector<HTMLElement>("[data-payroll-run-disburse]");
    bindModalFocusManagement(gatewayModal, disburseTrigger);
    gatewayModal?.querySelector<HTMLButtonElement>("[data-payroll-gateway-pay]")?.addEventListener("click", () => void disburseSelected());

    const settingsRoot = getPayrollSettingsRoot();
    settingsRoot?.querySelector<HTMLFormElement>("[data-payroll-settings-form]")?.addEventListener("submit", (event) => {
        event.preventDefault();
    });
    settingsRoot?.querySelector<HTMLButtonElement>("[data-payroll-settings-confirm]")?.addEventListener("click", () => {
        const modal = document.getElementById("payroll_settings_confirm_modal");
        if (modal && (window as any).bootstrap?.Modal) {
            new (window as any).bootstrap.Modal(modal).show();
        }
    });
    bindModalFocusManagement(
        document.getElementById("payroll_settings_confirm_modal"),
        settingsRoot?.querySelector<HTMLElement>("[data-payroll-settings-confirm]") ?? null,
    );
    document.getElementById("payroll_settings_confirm_modal")?.querySelector<HTMLButtonElement>("[data-payroll-settings-save]")?.addEventListener("click", () => {
        const modal = document.getElementById("payroll_settings_confirm_modal");
        if (modal && (window as any).bootstrap?.Modal) {
            hideModalSafely(modal, (window as any).bootstrap);
        }
        void savePayrollSettings();
    });
    settingsRoot?.addEventListener("input", () => {
        updateSettingsPreviewStateFromInputs();
    });
    settingsRoot?.addEventListener("change", () => {
        renderPayrollSettingsPreview();
    });

    bindWorkConfigurator();

    const previewModal = document.getElementById("payroll_reconciliation_preview_modal");
    const exportEvidenceTrigger = root.querySelector<HTMLElement>("[data-payroll-run-export-evidence]");
    bindModalFocusManagement(previewModal, exportEvidenceTrigger);
    previewModal?.querySelector("[data-recon-preview-download]")?.addEventListener("click", () => {
        void triggerExportReconciliation().then(() => {
            hideModalSafely(previewModal, bootstrapApi);
        });
    });

    void loadPayrollSettings();
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