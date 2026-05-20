// State store for the employees module
export const RETURN_STATE_KEY = "arcav_employees_return_state_v1";

export let selectedPreviewEmployeeId = null;

export const employeesTableState = {
    page: 1,
    perPage: 20,
    search: "",
    status: "",
    departmentId: "",
    designationId: "",
    teamId: "",
    scope: "",
};

export const employeesTableMeta = {
    page: 1,
    perPage: 20,
    total: 0,
};

export const employeesViewerContext = {
    isSpecialSuperAdminCode1: false,
};

export const selectedEmployeeProfilesMap = {};

export function persistEmployeesTableState() {
    try {
        localStorage.setItem(RETURN_STATE_KEY, JSON.stringify(employeesTableState));
    } catch (e) {
        // ignore
    }
}

export function restoreEmployeesTableState() {
    try {
        var raw = localStorage.getItem(RETURN_STATE_KEY);
        if (raw) {
            var parsed = JSON.parse(raw);
            Object.assign(employeesTableState, parsed);
        }
    } catch (e) {
        // ignore
    }
    return employeesTableState;
}

export function updateEmployeesTableState(partial) {
    if (!partial || typeof partial !== "object") return employeesTableState;
    Object.assign(employeesTableState, partial);
    persistEmployeesTableState();
    return employeesTableState;
}
