import { apiRequest, arrangementModeLabel, dayTypeLabel, formatApiError, parseIntOrNull, showWorkConfigError, toast } from "./helpers";
import { getPayrollRunRoot, getWorkConfigRoot, payrollRunState, workConfigState } from "./shared";
import { ApiResponse, PayrollWorkArrangement, PayrollWorkProfile } from "./types";

function renderWorkProfiles(profiles: PayrollWorkProfile[]): void {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    const profileBody = root.querySelector<HTMLElement>("[data-payroll-work-profiles-body]");
    const profileSelect = root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-profile]");

    if (profileBody) {
        if (!profiles.length) {
            profileBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Belum ada profile.</td></tr>';
        } else {
            profileBody.innerHTML = profiles.map((profile) => {
                const summary = `${arrangementModeLabel(profile.arrangementMode)} · ${dayTypeLabel(profile.defaultDayType)} · ${profile.weeklyWorkDays} hari`;
                return `
                    <tr>
                        <td>
                            <div class="fw-semibold">${profile.name}</div>
                            <div class="text-muted small">${profile.code}</div>
                        </td>
                        <td class="small">${summary}</td>
                        <td class="text-center">${profile.isDefault ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Default</span>' : '<span class="text-muted">-</span>'}</td>
                    </tr>
                `;
            }).join("");
        }
    }

    if (profileSelect) {
        const current = profileSelect.value;
        profileSelect.innerHTML = '<option value="">Custom tanpa profile</option>' + profiles.map((profile) => `<option value="${profile.id}">${profile.name} (${profile.code})</option>`).join("");
        if (current && profileSelect.querySelector(`option[value="${current}"]`)) {
            profileSelect.value = current;
        }
    }
}

function renderWorkArrangements(arrangements: PayrollWorkArrangement[]): void {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    const body = root.querySelector<HTMLElement>("[data-payroll-work-arrangements-body]");
    if (!body) {
        return;
    }

    if (!arrangements.length) {
        body.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Belum ada assignment.</td></tr>';
        return;
    }

    body.innerHTML = arrangements.map((row) => {
        const profileText = row.profileName
            ? `${row.profileName} (${row.profileCode || "-"})`
            : `${arrangementModeLabel(row.arrangementMode)} · ${dayTypeLabel(row.defaultDayType)} · ${row.weeklyWorkDays || "auto"} hari`;
        const effectiveText = `${row.effectiveFrom || "-"} s/d ${row.effectiveTo || "open"}`;
        return `
            <tr>
                <td>
                    <div class="fw-semibold">${row.userName || `User #${row.userId}`}</div>
                    <div class="text-muted small">UID: ${row.userId}</div>
                </td>
                <td class="small">${profileText}</td>
                <td class="small">${effectiveText}</td>
            </tr>
        `;
    }).join("");
}

function renderEmployeeOptions(users: Array<{ id: number; name: string; email?: string | null }>): void {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    const select = root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-user]");
    if (!select) {
        return;
    }

    const current = select.value;
    select.innerHTML = '<option value="">Pilih karyawan</option>' + users.map((user) => {
        const emailSuffix = user.email ? ` · ${user.email}` : "";
        return `<option value="${user.id}">${user.name}${emailSuffix}</option>`;
    }).join("");

    if (current && select.querySelector(`option[value="${current}"]`)) {
        select.value = current;
    }
}

function isoDateFromPayrollPeriodOrToday(): string {
    const root = getPayrollRunRoot();
    const year = Number(root?.querySelector<HTMLInputElement>("[data-payroll-run-year]")?.value || 0);
    const month = Number(root?.querySelector<HTMLSelectElement>("[data-payroll-run-month]")?.value || 0);
    if (Number.isFinite(year) && year >= 2000 && Number.isFinite(month) && month >= 1 && month <= 12) {
        return `${String(year).padStart(4, "0")}-${String(month).padStart(2, "0")}-01`;
    }

    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-01`;
}

function isArrangementActiveOnDate(row: PayrollWorkArrangement, isoDate: string): boolean {
    const from = String(row.effectiveFrom || "").trim();
    const to = String(row.effectiveTo || "").trim();
    if (!from) {
        return false;
    }
    if (from > isoDate) {
        return false;
    }
    if (!to) {
        return true;
    }
    return to >= isoDate;
}

function usersFromCurrentPayrollRows(): Array<{ id: number; name: string; email?: string | null }> {
    const unique = new Map<number, { id: number; name: string; email?: string | null }>();
    for (const row of payrollRunState.currentRows) {
        const userId = Number(row.userId);
        if (!Number.isFinite(userId) || userId <= 0) {
            continue;
        }
        const name = String(row.name || `User #${userId}`).trim();
        if (!unique.has(userId)) {
            unique.set(userId, { id: userId, name: name || `User #${userId}`, email: null });
        }
    }
    return Array.from(unique.values());
}

async function usersFromCurrentPayrollRunDetail(): Promise<Array<{ id: number; name: string; email?: string | null }>> {
    if (!payrollRunState.currentRunId) {
        return [];
    }

    const response = await apiRequest("get", `/v1/hcm/payroll-runs/${payrollRunState.currentRunId}`) as ApiResponse<{ lines?: Array<{ userId: number; userName?: string | null; meta?: { userName?: string } }> }>;
    if (!response?.success) {
        return [];
    }

    const lines = Array.isArray(response.data?.lines) ? response.data.lines : [];
    const unique = new Map<number, { id: number; name: string; email?: string | null }>();
    for (const line of lines) {
        const userId = Number(line.userId);
        if (!Number.isFinite(userId) || userId <= 0) {
            continue;
        }
        const name = String(line.userName || line.meta?.userName || `User #${userId}`).trim();
        if (!unique.has(userId)) {
            unique.set(userId, { id: userId, name: name || `User #${userId}`, email: null });
        }
    }

    return Array.from(unique.values());
}

export async function autoGenerateWorkArrangementsFromRun(options?: { showToast?: boolean; useWorkConfigError?: boolean }): Promise<{ created: number; skipped: number }> {
    const root = getWorkConfigRoot();
    if (options?.useWorkConfigError !== false && root) {
        showWorkConfigError("");
    }

    const button = root?.querySelector<HTMLButtonElement>("[data-payroll-work-auto-generate]") || null;
    if (button) {
        button.disabled = true;
        button.textContent = "Generating...";
    }

    try {
        const effectiveFrom = isoDateFromPayrollPeriodOrToday();
        const profileResponse = await apiRequest("get", "/v1/hcm/payroll/work-profiles") as ApiResponse<PayrollWorkProfile[]>;
        const arrangementResponse = await apiRequest("get", "/v1/hcm/payroll/work-arrangements?perPage=25") as ApiResponse<PayrollWorkArrangement[]>;
        if (!profileResponse?.success) {
            throw new Error(formatApiError(profileResponse, 400));
        }
        if (!arrangementResponse?.success) {
            throw new Error(formatApiError(arrangementResponse, 400));
        }

        const profiles = Array.isArray(profileResponse.data) ? profileResponse.data : [];
        const arrangements = Array.isArray(arrangementResponse.data) ? arrangementResponse.data : [];

        let users = usersFromCurrentPayrollRows();
        if (!users.length) {
            users = await usersFromCurrentPayrollRunDetail();
        }
        if (!users.length) {
            if (options?.useWorkConfigError !== false && root) {
                showWorkConfigError("Belum ada user dari run aktif untuk di-auto-generate.");
            }
            return { created: 0, skipped: 0 };
        }

        const activeUserSet = new Set<number>();
        for (const arrangement of arrangements) {
            if (isArrangementActiveOnDate(arrangement, effectiveFrom)) {
                activeUserSet.add(Number(arrangement.userId));
            }
        }

        const defaultProfile = profiles.find((profile) => !!profile.isDefault) || null;
        let created = 0;
        let skipped = 0;
        for (const user of users) {
            if (activeUserSet.has(user.id)) {
                skipped += 1;
                continue;
            }

            const payload = {
                userId: user.id,
                profileId: defaultProfile ? defaultProfile.id : null,
                arrangementMode: defaultProfile ? defaultProfile.arrangementMode : "office_hour",
                defaultDayType: defaultProfile ? null : "workday",
                weeklyWorkDays: defaultProfile ? null : 5,
                effectiveFrom,
                effectiveTo: null,
            };

            const response = await apiRequest("post", "/v1/hcm/payroll/work-arrangements", payload) as ApiResponse<PayrollWorkArrangement>;
            if (!response?.success) {
                throw new Error(formatApiError(response, 400));
            }
            created += 1;
        }

        if (root) {
            await refreshWorkConfigurator();
        }
        if (options?.showToast !== false) {
            toast(`Auto-generate selesai. Created: ${created}, skipped: ${skipped}.`, false);
        }
        return { created, skipped };
    } catch (error: any) {
        if (options?.useWorkConfigError !== false && root) {
            showWorkConfigError(error?.message || "Auto-generate assignment gagal.");
        }
        return { created: 0, skipped: 0 };
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = "Auto Generate dari Run Aktif";
        }
    }
}

export async function refreshWorkConfigurator(): Promise<void> {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    showWorkConfigError("");
    const profilesBody = root.querySelector<HTMLElement>("[data-payroll-work-profiles-body]");
    const arrangementsBody = root.querySelector<HTMLElement>("[data-payroll-work-arrangements-body]");

    if (profilesBody) {
        profilesBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Memuat profile...</td></tr>';
    }
    if (arrangementsBody) {
        arrangementsBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Memuat assignment...</td></tr>';
    }

    try {
        const profileResponse = await apiRequest("get", "/v1/hcm/payroll/work-profiles") as ApiResponse<PayrollWorkProfile[]>;
        const arrangementResponse = await apiRequest("get", "/v1/hcm/payroll/work-arrangements?perPage=25") as ApiResponse<PayrollWorkArrangement[]>;
        let employeeResponse: ApiResponse<Array<{ id?: number; userId?: number; name?: string; fullName?: string; email?: string | null }>> | null = null;
        try {
            employeeResponse = await apiRequest("get", "/v1/hcm/employees?page=1&perPage=100") as ApiResponse<Array<{ id?: number; userId?: number; name?: string; fullName?: string; email?: string | null }>>;
        } catch {
            employeeResponse = null;
        }

        if (!profileResponse?.success) {
            throw new Error(formatApiError(profileResponse, 400));
        }
        if (!arrangementResponse?.success) {
            throw new Error(formatApiError(arrangementResponse, 400));
        }

        const profiles = Array.isArray(profileResponse.data) ? profileResponse.data : [];
        const arrangements = Array.isArray(arrangementResponse.data) ? arrangementResponse.data : [];
        const employeeRows = employeeResponse?.success && Array.isArray(employeeResponse.data) ? employeeResponse.data : [];
        const usersFromDirectory = employeeRows
            .map((item) => ({
                id: Number(item.userId ?? item.id ?? 0),
                name: String(item.fullName || item.name || ""),
                email: item.email || null,
            }))
            .filter((item) => Number.isFinite(item.id) && item.id > 0 && item.name);
        const fallbackFromState = usersFromCurrentPayrollRows();
        let fallbackFromRun: Array<{ id: number; name: string; email?: string | null }> = [];
        if (!usersFromDirectory.length && !fallbackFromState.length) {
            fallbackFromRun = await usersFromCurrentPayrollRunDetail();
        }

        const usersById = new Map<number, { id: number; name: string; email?: string | null }>();
        for (const user of [...usersFromDirectory, ...fallbackFromState, ...fallbackFromRun]) {
            if (!Number.isFinite(user.id) || user.id <= 0) {
                continue;
            }
            if (!usersById.has(user.id)) {
                usersById.set(user.id, user);
            }
        }

        workConfigState.profiles = profiles;
        workConfigState.arrangements = arrangements;
        workConfigState.users = Array.from(usersById.values());

        renderWorkProfiles(profiles);
        renderWorkArrangements(arrangements);
        renderEmployeeOptions(workConfigState.users);

        if (!workConfigState.users.length) {
            showWorkConfigError("Belum ada kandidat karyawan. Coba refresh setelah data payroll run bulanan dimuat.");
        }
    } catch (error: any) {
        showWorkConfigError(error?.message || "Gagal memuat konfigurasi payroll work arrangement.");
    }
}

async function submitWorkProfileForm(form: HTMLFormElement): Promise<void> {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    const code = root.querySelector<HTMLInputElement>("[data-payroll-work-profile-code]")?.value?.trim() || "";
    const name = root.querySelector<HTMLInputElement>("[data-payroll-work-profile-name]")?.value?.trim() || "";
    const arrangementMode = root.querySelector<HTMLSelectElement>("[data-payroll-work-profile-mode]")?.value || "office_hour";
    const defaultDayType = root.querySelector<HTMLSelectElement>("[data-payroll-work-profile-day-type]")?.value || "workday";
    const weeklyWorkDays = parseIntOrNull(root.querySelector<HTMLSelectElement>("[data-payroll-work-profile-weekly-days]")?.value) || 5;
    const isDefault = !!root.querySelector<HTMLInputElement>("[data-payroll-work-profile-default]")?.checked;
    const submitButton = root.querySelector<HTMLButtonElement>("[data-payroll-work-profile-submit]");

    if (!code || !name) {
        showWorkConfigError("Kode dan nama profile wajib diisi.");
        return;
    }

    if (submitButton) {
        submitButton.disabled = true;
    }

    try {
        const response = await apiRequest("post", "/v1/hcm/payroll/work-profiles", {
            code,
            name,
            arrangementMode,
            defaultDayType,
            weeklyWorkDays,
            isDefault,
        }) as ApiResponse<PayrollWorkProfile>;

        if (!response?.success) {
            showWorkConfigError(formatApiError(response, 400));
            return;
        }

        form.reset();
        const weeklySelect = root.querySelector<HTMLSelectElement>("[data-payroll-work-profile-weekly-days]");
        if (weeklySelect) {
            weeklySelect.value = "5";
        }
        toast("Profile payroll work berhasil disimpan.", false);
        await refreshWorkConfigurator();
    } catch (error: any) {
        showWorkConfigError(formatApiError(error?.response?.data || {}, 500));
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
        }
    }
}

async function submitWorkArrangementForm(form: HTMLFormElement): Promise<void> {
    const root = getWorkConfigRoot();
    if (!root) {
        return;
    }

    const userId = parseIntOrNull(root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-user]")?.value);
    const profileId = parseIntOrNull(root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-profile]")?.value);
    const arrangementMode = root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-mode]")?.value || "office_hour";
    const defaultDayTypeRaw = root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-day-type]")?.value || "";
    const weeklyWorkDays = parseIntOrNull(root.querySelector<HTMLSelectElement>("[data-payroll-work-arrangement-weekly-days]")?.value);
    const effectiveFrom = root.querySelector<HTMLInputElement>("[data-payroll-work-arrangement-effective-from]")?.value || "";
    const effectiveToRaw = root.querySelector<HTMLInputElement>("[data-payroll-work-arrangement-effective-to]")?.value || "";
    const submitButton = root.querySelector<HTMLButtonElement>("[data-payroll-work-arrangement-submit]");

    if (!userId || !effectiveFrom) {
        showWorkConfigError("Karyawan dan effective from wajib diisi.");
        return;
    }

    if (submitButton) {
        submitButton.disabled = true;
    }

    try {
        const response = await apiRequest("post", "/v1/hcm/payroll/work-arrangements", {
            userId,
            profileId,
            arrangementMode,
            defaultDayType: defaultDayTypeRaw || null,
            weeklyWorkDays,
            effectiveFrom,
            effectiveTo: effectiveToRaw || null,
        }) as ApiResponse<PayrollWorkArrangement>;

        if (!response?.success) {
            showWorkConfigError(formatApiError(response, 400));
            return;
        }

        form.reset();
        toast("Assignment payroll work berhasil disimpan.", false);
        await refreshWorkConfigurator();
    } catch (error: any) {
        showWorkConfigError(formatApiError(error?.response?.data || {}, 500));
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
        }
    }
}

export function bindWorkConfigurator(): void {
    const root = getWorkConfigRoot();
    if (!root || root.dataset.bound === "1") {
        return;
    }
    root.dataset.bound = "1";

    const refreshButton = root.querySelector<HTMLButtonElement>("[data-payroll-work-refresh]");
    if (refreshButton) {
        refreshButton.addEventListener("click", () => {
            void refreshWorkConfigurator();
        });
    }

    const autoGenerateButton = root.querySelector<HTMLButtonElement>("[data-payroll-work-auto-generate]");
    if (autoGenerateButton) {
        autoGenerateButton.addEventListener("click", () => {
            void autoGenerateWorkArrangementsFromRun();
        });
    }

    const profileForm = root.querySelector<HTMLFormElement>("[data-payroll-work-profile-form]");
    if (profileForm) {
        profileForm.addEventListener("submit", (event) => {
            event.preventDefault();
            void submitWorkProfileForm(profileForm);
        });
    }

    const arrangementForm = root.querySelector<HTMLFormElement>("[data-payroll-work-arrangement-form]");
    if (arrangementForm) {
        arrangementForm.addEventListener("submit", (event) => {
            event.preventDefault();
            void submitWorkArrangementForm(arrangementForm);
        });
    }

    void refreshWorkConfigurator();
}