import { bindLeaveReport } from "./hcm-extras/leave/report-page.js";
import { bindLeaves } from "./hcm-extras/leave/request-page.js";
import {
    apiRequest,
    canAccessAdminPage,
    createEmployeeDirectoryStore,
    downloadCsv,
    downloadFileFromUrl,
    esc,
    formatApiError,
    notify,
} from "./hcm-extras/shared/runtime.js";
import {
    formatOvertimeComplianceError,
    isPendingOlderThan24h,
    normalizeOvertimeDayType,
    overtimeDayTypeLabel,
    overtimePolicyTypeLabel,
    overtimeStatusMeta,
} from "./hcm-extras/shared/overtime.js";

var hcmExtrasModuleLoaders = window.ArcavHcmExtrasModuleLoaders || {};
var resolveBindHolidaysModule = hcmExtrasModuleLoaders.resolveBindHolidaysModule || function () { return null; };
var loadBindHolidaysModule = hcmExtrasModuleLoaders.loadBindHolidaysModule || function () { return Promise.resolve(null); };
var resolveBindOvertimeCalculatorModule = hcmExtrasModuleLoaders.resolveBindOvertimeCalculatorModule || function () { return null; };
var loadBindOvertimeCalculatorModule = hcmExtrasModuleLoaders.loadBindOvertimeCalculatorModule || function () { return Promise.resolve(null); };
var resolveBindOvertimeModule = hcmExtrasModuleLoaders.resolveBindOvertimeModule || function () { return null; };
var loadBindOvertimeModule = hcmExtrasModuleLoaders.loadBindOvertimeModule || function () { return Promise.resolve(null); };

var employeeStore = createEmployeeDirectoryStore({
    apiRequest: apiRequest,
    esc: esc,
    notify: notify,
    formatApiError: formatApiError,
});

function bindHolidays() {
    var moduleFn = resolveBindHolidaysModule();
    var moduleArgs = {
        apiRequest: apiRequest,
        esc: esc,
        notify: notify,
        downloadCsv: downloadCsv,
        downloadFileFromUrl: downloadFileFromUrl,
        formatApiError: formatApiError,
        formatOvertimeComplianceError: formatOvertimeComplianceError,
    };

    if (moduleFn) {
        return moduleFn(moduleArgs);
    }

    loadBindHolidaysModule().then(function (loadedFn) {
        if (typeof loadedFn === "function") {
            loadedFn(moduleArgs);
        }
    });
    return null;
}

function bindOvertime(isAdmin) {
    var moduleArgs = {
        notify: notify,
        formatApiError: formatApiError,
        apiRequest: apiRequest,
        loadEmployeeOptions: employeeStore.loadEmployeeOptions,
        overtimeStatusMeta: overtimeStatusMeta,
        isPendingOlderThan24h: isPendingOlderThan24h,
        overtimePolicyTypeLabel: overtimePolicyTypeLabel,
        esc: esc,
    };

    var moduleFn = resolveBindOvertimeModule();
    if (moduleFn) {
        return moduleFn(moduleArgs, isAdmin);
    }

    loadBindOvertimeModule().then(function (loadedFn) {
        if (typeof loadedFn === "function") {
            loadedFn(moduleArgs, isAdmin);
        }
    });
    return null;
}

function bindOvertimeCalculator() {
    var moduleFn = resolveBindOvertimeCalculatorModule();
    var moduleArgs = {
        notify: notify,
        apiRequest: apiRequest,
        normalizeOvertimeDayType: normalizeOvertimeDayType,
        overtimeDayTypeLabel: overtimeDayTypeLabel,
        formatOvertimeComplianceError: formatOvertimeComplianceError,
        loadEmployeeOptions: employeeStore.loadEmployeeOptions,
        getEmployeeCompensationById: employeeStore.getEmployeeCompensationById,
    };

    if (moduleFn) {
        return moduleFn(moduleArgs);
    }

    loadBindOvertimeCalculatorModule().then(function (loadedFn) {
        if (typeof loadedFn === "function") {
            loadedFn(moduleArgs);
        }
    });
    return null;
}

function init() {
    var path = String(window.location.pathname || "").replace(/\/+$/, "") || "/";
    if (path === "/holidays") {
        apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
            if (m && m.success && !canAccessAdminPage(m, "holiday.view")) {
                window.location.replace("/employee-dashboard");
                return;
            }
            bindHolidays();
        });
        return;
    }

    if (path === "/leaves") {
        apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
            if (m && m.success && m.data && m.data.id) {
                window.__arcav_me_id = m.data.id;
            }
            var isAdmin = !!(m && m.success && canAccessAdminPage(m, "leave.view"));
            if (m && m.success && !isAdmin) {
                window.location.replace("/leaves-employee");
                return;
            }
            bindLeaves({
                apiRequest: apiRequest,
                esc: esc,
                notify: notify,
                downloadFileFromUrl: downloadFileFromUrl,
                formatApiError: formatApiError,
                loadEmployeeOptions: employeeStore.loadEmployeeOptions,
            }, "all", true);
        });
        return;
    }

    if (path === "/leaves-employee") {
        bindLeaves({
            apiRequest: apiRequest,
            esc: esc,
            notify: notify,
            downloadFileFromUrl: downloadFileFromUrl,
            formatApiError: formatApiError,
            loadEmployeeOptions: employeeStore.loadEmployeeOptions,
        }, "me", false);
        return;
    }

    if (path === "/leave-report") {
        apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
            if (m && m.success && !canAccessAdminPage(m, "leave.view")) {
                window.location.replace("/employee-dashboard");
                return;
            }
            bindLeaveReport({
                apiRequest: apiRequest,
                esc: esc,
                notify: notify,
                formatApiError: formatApiError,
            });
        });
        return;
    }

    if (path === "/overtime") {
        apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
            if (m && m.success && !canAccessAdminPage(m, "overtime.view")) {
                window.location.replace("/overtime-employee");
                return;
            }
            if (m && m.success && m.data && m.data.id) {
                window.__arcav_me_id = m.data.id;
            }
            bindOvertimeCalculator();
            document.querySelectorAll("[data-hcm-ot-admin-only]").forEach(function (el) {
                el.style.display = "";
            });
            var addTitle = document.querySelector("[data-hcm-ot-add-title]");
            if (addTitle) {
                addTitle.textContent = "Add overtime";
            }
            bindOvertime(true);
        });
        return;
    }

    if (path === "/overtime-employee") {
        bindOvertimeCalculator();
        apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
            if (m && m.success && m.data && m.data.id) {
                window.__arcav_me_id = m.data.id;
            }
            document.querySelectorAll("[data-hcm-ot-admin-only]").forEach(function (el) {
                el.style.display = "none";
            });
            var addTitle = document.querySelector("[data-hcm-ot-add-title]");
            if (addTitle) {
                addTitle.textContent = "Request overtime";
            }
            bindOvertime(false);
        });
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}