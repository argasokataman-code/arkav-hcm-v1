import { formatApiError, formatIdr, parseIntOrNull, showErr, toast } from "./helpers";
import { apiRequest } from "./helpers";
import {
    currentRunStatus,
    getPayrollRunRoot,
    getPayrollSettingsRoot,
    payrollRunState,
    payrollSettingsState,
} from "./shared";
import { ApiResponse, PayrollPolicySnapshot, PayrollSettings } from "./types";

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

export function currentLocalDateIso(timeZone: string): string {
    return new Intl.DateTimeFormat("en-CA", {
        timeZone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    }).format(new Date());
}

export function isPostCutoffReviewOnlyMode(): boolean {
    const snapshot = payrollRunState.currentPolicySnapshot;
    if (!snapshot?.resolvedCutoffDate || !snapshot?.resolvedPaydayDate || !snapshot?.payrollTimezone) {
        return false;
    }
    if (snapshot.disburseBeforePaydayAllowed) {
        return false;
    }
    const today = currentLocalDateIso(snapshot.payrollTimezone);
    return today > snapshot.resolvedCutoffDate && today < snapshot.resolvedPaydayDate;
}

export function showPayrollSettingsFeedback(message: string, danger = true): void {
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

export function renderPayrollSettingsPreview(): void {
    const root = getPayrollSettingsRoot();
    const settings = payrollSettingsState.settings;
    if (!root || !settings) {
        return;
    }

    const runRoot = getPayrollRunRoot();
    const year = parseIntOrNull(runRoot?.querySelector<HTMLInputElement>("[data-payroll-run-year]")?.value || null);
    const month = parseIntOrNull(runRoot?.querySelector<HTMLSelectElement>("[data-payroll-run-month]")?.value || null);
    const preview = resolvePayrollPolicyPreview(settings, year, month);
    const stage = payrollPolicyStage(preview);

    const periodElement = root.querySelector<HTMLElement>("[data-payroll-settings-preview-period]");
    const paydayElement = root.querySelector<HTMLElement>("[data-payroll-settings-preview-payday]");
    const cutoffElement = root.querySelector<HTMLElement>("[data-payroll-settings-preview-cutoff]");
    const noteElement = root.querySelector<HTMLElement>("[data-payroll-settings-preview-note]");
    const stageElement = root.querySelector<HTMLElement>("[data-payroll-settings-stage]");

    if (periodElement) {
        periodElement.textContent = year && month ? `${String(month).padStart(2, "0")}/${year}` : "Menunggu periode aktif...";
    }
    if (paydayElement) {
        paydayElement.textContent = preview ? formatIsoDateLabel(preview.resolvedPaydayDate, settings.payrollTimezone) : "-";
    }
    if (cutoffElement) {
        cutoffElement.textContent = preview ? formatIsoDateLabel(preview.resolvedCutoffDate, settings.payrollTimezone) : "-";
    }
    if (noteElement) {
        noteElement.textContent = stage.note;
    }
    if (stageElement) {
        stageElement.className = `badge ${stage.badgeClass}`;
        stageElement.textContent = stage.label;
    }
}

export async function loadPayrollSettings(): Promise<void> {
    if (!getPayrollSettingsRoot()) {
        return;
    }

    try {
        const response = await apiRequest("get", "/v1/hcm/payroll/settings") as ApiResponse<PayrollSettings>;
        if (!response.success) {
            showPayrollSettingsFeedback(formatApiError(response, 400), true);
            return;
        }

        payrollSettingsState.settings = {
            paydayDay: Number(response.data.paydayDay || 0) || 28,
            cutoffOffsetDays: Number(response.data.cutoffOffsetDays || 0),
            payrollTimezone: String(response.data.payrollTimezone || "Asia/Jakarta"),
            disburseBeforePaydayAllowed: !!response.data.disburseBeforePaydayAllowed,
            paydayHolidayStrategy: (response.data.paydayHolidayStrategy || "previous_working_day") as PayrollSettings["paydayHolidayStrategy"],
        };
        fillPayrollSettingsForm(payrollSettingsState.settings);
        showPayrollSettingsFeedback("", false);
        renderPayrollSettingsPreview();
    } catch (error: any) {
        showPayrollSettingsFeedback(formatApiError(error.response?.data || {}, 500), true);
    }
}

export async function savePayrollSettings(): Promise<void> {
    const root = getPayrollSettingsRoot();
    if (!root) {
        return;
    }

    const saveButton = root.querySelector<HTMLButtonElement>("[data-payroll-settings-save]");
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

    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = "Menyimpan...";
    }

    try {
        const response = await apiRequest("put", "/v1/hcm/payroll/settings", payload) as ApiResponse<PayrollSettings>;
        if (!response.success) {
            showPayrollSettingsFeedback(formatApiError(response, 400), true);
            return;
        }

        payrollSettingsState.settings = {
            paydayDay: Number(response.data.paydayDay || 0) || 28,
            cutoffOffsetDays: Number(response.data.cutoffOffsetDays || 0),
            payrollTimezone: String(response.data.payrollTimezone || "Asia/Jakarta"),
            disburseBeforePaydayAllowed: !!response.data.disburseBeforePaydayAllowed,
            paydayHolidayStrategy: (response.data.paydayHolidayStrategy || "previous_working_day") as PayrollSettings["paydayHolidayStrategy"],
        };
        fillPayrollSettingsForm(payrollSettingsState.settings);
        renderPayrollSettingsPreview();
        showPayrollSettingsFeedback("Policy payroll berhasil disimpan. Run finalized yang belum paid tetap memakai snapshot lama; void lalu Calculate Draft ulang jika policy baru harus diterapkan.", false);
    } catch (error: any) {
        showPayrollSettingsFeedback(formatApiError(error.response?.data || {}, 500), true);
    } finally {
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.textContent = "Simpan policy payroll";
        }
    }
}

export function updateSettingsPreviewStateFromInputs(): void {
    const settingsRoot = getPayrollSettingsRoot();
    if (!settingsRoot) {
        return;
    }
    const paydayInput = settingsRoot.querySelector<HTMLInputElement>("[data-payroll-settings-payday-day]");
    const cutoffInput = settingsRoot.querySelector<HTMLInputElement>("[data-payroll-settings-cutoff-offset]");
    const timezoneSelect = settingsRoot.querySelector<HTMLSelectElement>("[data-payroll-settings-timezone]");
    const disburseEarlyInput = settingsRoot.querySelector<HTMLInputElement>("[data-payroll-settings-disburse-early]");
    const holidayStrategySelect = settingsRoot.querySelector<HTMLSelectElement>("[data-payroll-settings-holiday-strategy]");
    if (!paydayInput || !cutoffInput || !timezoneSelect || !disburseEarlyInput || !holidayStrategySelect) {
        return;
    }
    payrollSettingsState.settings = {
        paydayDay: parseIntOrNull(paydayInput.value) || 28,
        cutoffOffsetDays: parseIntOrNull(cutoffInput.value) || 0,
        payrollTimezone: timezoneSelect.value || "Asia/Jakarta",
        disburseBeforePaydayAllowed: disburseEarlyInput.checked,
        paydayHolidayStrategy: (holidayStrategySelect.value || "previous_working_day") as PayrollSettings["paydayHolidayStrategy"],
    };
    renderPayrollSettingsPreview();
}