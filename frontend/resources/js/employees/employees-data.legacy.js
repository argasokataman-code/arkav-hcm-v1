import {
    employeesListUrl,
    requestAuthMe,
    requestEmployees,
    requestEmployeesByState,
    requestAllEmployeesAggregated,
    requestJson,
    requestFormData,
    requestEmployeeDetail,
} from './api.js';

import {
    escapeHtml,
    formatEmployeeCode,
    formatApiError,
    formatRupiah,
    getCurrentListUrl,
    buildEmployeeDetailUrl,
    downloadBlob,
    toCsv,
    normalizeEmployeeScope,
} from './helpers.js';

import * as State from './state.js';
import * as Org from './org.js';
import * as Renderers from './renderers.js';
import * as Binders from './binders.js';
import * as Bulk from './bulk.js';
import * as List from './list.js';

var employeesModuleLoaders = window.ArcavEmployeesModuleLoaders || {};
var resolveBindEmployeeCompensationFormsModule = employeesModuleLoaders.resolveBindEmployeeCompensationFormsModule || function () { return null; };
var loadBindEmployeeCompensationFormsModule = employeesModuleLoaders.loadBindEmployeeCompensationFormsModule || function () { return Promise.resolve(null); };
var resolveBindQuickPreviewModule = employeesModuleLoaders.resolveBindQuickPreviewModule || function () { return null; };
var loadBindQuickPreviewModule = employeesModuleLoaders.loadBindQuickPreviewModule || function () { return Promise.resolve(null); };
var resolveBindEmployeePhotoModalPreviewModule = employeesModuleLoaders.resolveBindEmployeePhotoModalPreviewModule || function () { return null; };
var loadBindEmployeePhotoModalPreviewModule = employeesModuleLoaders.loadBindEmployeePhotoModalPreviewModule || function () { return Promise.resolve(null); };
var resolveBindSalaryBulkUploadModule = employeesModuleLoaders.resolveBindSalaryBulkUploadModule || function () { return null; };
var loadBindSalaryBulkUploadModule = employeesModuleLoaders.loadBindSalaryBulkUploadModule || function () { return Promise.resolve(null); };

(function (window, document) {
    "use strict";
    var RETURN_STATE_KEY = "arcav_employees_return_state_v1";
    var selectedPreviewEmployeeId = null;
    var employeesTableState = State.employeesTableState;
    var employeesTableMeta = State.employeesTableMeta;
    var employeesViewerContext = State.employeesViewerContext;
    var selectedEmployeeProfilesMap = State.selectedEmployeeProfilesMap;

    var list = List.makeListHandlers({
        employeesTableState: employeesTableState,
        employeesViewerContext: employeesViewerContext,
        selectedEmployeeProfilesMap: selectedEmployeeProfilesMap,
        selectedPreviewEmployeeId: selectedPreviewEmployeeId,
        getCurrentListUrl: getCurrentListUrl,
    });

    function isSpecialSuperAdminCode1(meData) {
        return list.isSpecialSuperAdminCode1(meData);
    }

    function saveReturnState(employeeId) {
        list.saveReturnState(employeeId);
    }

    function updateActiveRowHighlight() {
        list.updateActiveRowHighlight(selectedPreviewEmployeeId);
    }

    function restoreReturnStateIfAny() {
        list.restoreReturnStateIfAny();
        selectedPreviewEmployeeId = list.getSelectedPreviewEmployeeId();
    }

    function syncEmployeesScopeTabState(activeScope) {
        list.syncEmployeesScopeTabState(activeScope);
    }

    function applyEmployeesScopeTabs(meData) {
        var wrap = document.querySelector("[data-employees-scope-tabs-wrap]");
        if (!wrap) {
            return;
        }

        var eligible = isSpecialSuperAdminCode1(meData);
        employeesViewerContext.isSpecialSuperAdminCode1 = eligible;

        if (!eligible) {
            wrap.classList.add("d-none");
            employeesTableState.scope = "";
            syncEmployeesScopeTabState("");
            return;
        }

        wrap.classList.remove("d-none");
        var requestedScope = normalizeEmployeeScope(employeesTableState.scope);
        if (!requestedScope) {
            requestedScope = "active_company";
        }
        employeesTableState.scope = requestedScope;
        syncEmployeesScopeTabState(requestedScope);
    }

    var orgDepartmentsFlat = [];
    var orgDesignationsFlat = [];
    var orgTeamsFlat = [];
    var orgMastersPromise = null;

    

    function renderListMessage(message) {
        renderers.renderListMessage(message);
    }

    var renderers = Renderers.makeRenderers({
        selectedEmployeeProfilesMap: selectedEmployeeProfilesMap,
        getSelectedEmployeeProfileIds: function () { return list.getSelectedEmployeeProfileIds(); },
        syncSelectAllCheckboxState: function () { list.syncSelectAllCheckboxState(); },
        updateBulkSelectionUi: function () { list.updateBulkSelectionUi(); },
    });

    var bulk = Bulk.makeBulkHandlers({
        getSelectedEmployeeProfileIds: function () { return list.getSelectedEmployeeProfileIds(); },
        clearSelectedEmployeesSelection: function () { list.clearSelectedEmployeesSelection(); },
        loadEmployeesData: loadEmployeesData,
    });
    // bulk handlers moved to employees/bulk.js (instantiated above)

    var binders = Binders.makeBinders({
        buildEmployeeDetailUrl: buildEmployeeDetailUrl,
        requestEmployeeDetail: requestEmployeeDetail,
        formatApiError: formatApiError,
        saveReturnState: function (id) { list.saveReturnState(id); },
        updateActiveRowHighlight: function () { list.updateActiveRowHighlight(selectedPreviewEmployeeId); },
        getSelectedPreviewEmployeeId: function () { return selectedPreviewEmployeeId; },
        setSelectedPreviewEmployeeId: function (v) { selectedPreviewEmployeeId = v; },
        escapeHtml: escapeHtml,
        formatRupiah: formatRupiah,
        requestJson: requestJson,
        fillDesignationSelectForDepartment: Org.fillDesignationSelectForDepartment,
        loadTeamsDropdown: Org.loadTeamsDropdown,
        requestFormData: requestFormData,
        getOrganizationReferenceSnapshot: Org.getOrgSnapshot,
        loadEmployeesData: loadEmployeesData,
        getSelectedEmployeeProfileIds: function () { return list.getSelectedEmployeeProfileIds(); },
        clearSelectedEmployeesSelection: function () { list.clearSelectedEmployeesSelection(); },
        bulk: bulk,
        employeesTableState: employeesTableState,
        employeesTableMeta: employeesTableMeta,
        employeesViewerContext: employeesViewerContext,
        updateBulkSelectionUi: function () { list.updateBulkSelectionUi(); },
        syncSelectAllCheckboxState: function () { list.syncSelectAllCheckboxState(); },
        syncEmployeesScopeTabState: function (scope) { list.syncEmployeesScopeTabState(scope); },
        exportEmployees: function (fmt) { list.exportEmployees(fmt); },
        selectedEmployeeProfilesMap: selectedEmployeeProfilesMap,
        normalizeEmployeeScope: normalizeEmployeeScope,
    });

    

    function bindQuickPreview() {
        var moduleFn = resolveBindQuickPreviewModule();
        var moduleArgs = {
            buildEmployeeDetailUrl: buildEmployeeDetailUrl,
            requestEmployeeDetail: requestEmployeeDetail,
            formatApiError: formatApiError,
            saveReturnState: function (id) { list.saveReturnState(id); },
            updateActiveRowHighlight: function () { list.updateActiveRowHighlight(selectedPreviewEmployeeId); },
            getSelectedPreviewEmployeeId: function () {
                return selectedPreviewEmployeeId;
            },
            setSelectedPreviewEmployeeId: function (value) {
                selectedPreviewEmployeeId = value;
            },
            escapeHtml: escapeHtml,
            formatRupiah: formatRupiah,
        };

        if (moduleFn) {
            return moduleFn(moduleArgs);
        }

        loadBindQuickPreviewModule().then(function (loadedFn) {
            if (typeof loadedFn === "function") {
                loadedFn(moduleArgs);
            }
        });
        return null;
    }

    function bindEmployeeCompensationForms() {
        var moduleFn = resolveBindEmployeeCompensationFormsModule();
        var moduleArgs = {
            requestJson: requestJson,
            requestEmployeeDetail: requestEmployeeDetail,
            fillDesignationSelectForDepartment: Org.fillDesignationSelectForDepartment,
            loadTeamsDropdown: Org.loadTeamsDropdown,
            formatApiError: formatApiError,
            loadEmployeesData: loadEmployeesData,
        };

        if (moduleFn) {
            return moduleFn(moduleArgs);
        }

        loadBindEmployeeCompensationFormsModule().then(function (loadedFn) {
            if (typeof loadedFn === "function") {
                loadedFn(moduleArgs);
            }
        });
        return null;
    }

    function bindEmployeePhotoModalPreview() {
        var moduleFn = resolveBindEmployeePhotoModalPreviewModule();
        if (moduleFn) {
            return moduleFn();
        }
        loadBindEmployeePhotoModalPreviewModule().then(function (loadedFn) {
            if (typeof loadedFn === "function") {
                loadedFn();
            }
        });
        return null;
    }

    function bindSalaryBulkUpload() {
        var moduleFn = resolveBindSalaryBulkUploadModule();
        var moduleArgs = {
            requestFormData: requestFormData,
            formatApiError: formatApiError,
            escapeHtml: escapeHtml,
            loadEmployeesData: loadEmployeesData,
            getOrganizationReferenceSnapshot: function () {
                return {
                    departments: orgDepartmentsFlat.slice(),
                    designations: orgDesignationsFlat.slice(),
                };
            },
        };

        if (moduleFn) {
            return moduleFn(moduleArgs);
        }
        loadBindSalaryBulkUploadModule().then(function (loadedFn) {
            if (typeof loadedFn === "function") {
                loadedFn(moduleArgs);
            }
        });
        return null;
    }

    function renderGridMessage(message) {
        list.renderGridMessage(message);
    }

    function updateSummary(meta) {
        list.updateSummary(meta);
    }

    function syncEmployeesFilterOptions() {
        list.syncEmployeesFilterOptions(orgDepartmentsFlat, orgDesignationsFlat, orgTeamsFlat);
    }

    function renderEmployeesShowing(meta, rowCount) {
        list.renderEmployeesShowing(meta, rowCount);
    }

    function renderEmployeesPagination(meta) {
        list.renderEmployeesPagination(meta);
    }

    function exportEmployees(format) {
        list.exportEmployees(format);
    }

    function bindEmployeesListControls() {
        if (document.body.getAttribute("data-employees-controls-bound") === "1") {
            return;
        }
        document.body.setAttribute("data-employees-controls-bound", "1");

        var searchInput = document.querySelector("[data-employees-search]");
        var statusSel = document.querySelector("[data-employees-filter-status]");
        var depSel = document.querySelector("[data-employees-filter-department]");
        var desSel = document.querySelector("[data-employees-filter-designation]");
        var teamSel = document.querySelector("[data-employees-filter-team]");
        var perPageSel = document.querySelector("[data-employees-per-page]");
        var debounceTimer = null;

        var params = new URL(window.location.href).searchParams;
        employeesTableState.page = Math.max(1, parseInt(params.get("page") || String(employeesTableState.page), 10) || 1);
        employeesTableState.perPage = Math.max(1, parseInt(params.get("perPage") || String(employeesTableState.perPage), 10) || 20);
        employeesTableState.search = String(params.get("search") || employeesTableState.search || "").trim();
        employeesTableState.status = String(params.get("status") || employeesTableState.status || "").trim();
        employeesTableState.departmentId = String(params.get("departmentId") || employeesTableState.departmentId || "").trim();
        employeesTableState.designationId = String(params.get("designationId") || employeesTableState.designationId || "").trim();
        employeesTableState.teamId = String(params.get("teamId") || employeesTableState.teamId || "").trim();
        employeesTableState.scope = normalizeEmployeeScope(params.get("scope") || employeesTableState.scope || "");

        if (searchInput) {
            searchInput.value = employeesTableState.search;
        }
        if (statusSel) {
            statusSel.value = employeesTableState.status;
        }
        if (teamSel) {
            teamSel.value = employeesTableState.teamId;
        }
        if (perPageSel) {
            perPageSel.value = String(employeesTableState.perPage);
        }
        bulk.fillBulkTargetTeamOptions();
        updateBulkSelectionUi();

        function triggerReload(resetPage) {
            if (resetPage) {
                employeesTableState.page = 1;
            }
            loadEmployeesData();
        }

        if (searchInput) {
            searchInput.addEventListener("input", function () {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(function () {
                    employeesTableState.search = String(searchInput.value || "").trim();
                    triggerReload(true);
                }, 350);
            });
        }
        if (statusSel) {
            statusSel.addEventListener("change", function () {
                employeesTableState.status = String(statusSel.value || "");
                triggerReload(true);
            });
        }
        if (depSel) {
            depSel.addEventListener("change", function () {
                employeesTableState.departmentId = String(depSel.value || "");
                triggerReload(true);
            });
        }
        if (desSel) {
            desSel.addEventListener("change", function () {
                employeesTableState.designationId = String(desSel.value || "");
                triggerReload(true);
            });
        }
        if (teamSel) {
            teamSel.addEventListener("change", function () {
                employeesTableState.teamId = String(teamSel.value || "");
                triggerReload(true);
            });
        }
        if (perPageSel) {
            perPageSel.addEventListener("change", function () {
                employeesTableState.perPage = Math.max(1, parseInt(perPageSel.value || "20", 10) || 20);
                triggerReload(true);
            });
        }

        document.addEventListener("change", function (event) {
            var selectAll = event.target.closest("[data-employees-select-all]");
            if (selectAll) {
                var checked = Boolean(selectAll.checked);
                document.querySelectorAll("[data-employees-select]").forEach(function (cb) {
                    cb.checked = checked;
                    var profileId = String(cb.getAttribute("data-employee-profile-id") || "").trim();
                    if (!profileId) {
                        return;
                    }
                    if (checked) {
                        selectedEmployeeProfilesMap[profileId] = true;
                    } else {
                        delete selectedEmployeeProfilesMap[profileId];
                    }
                });
                syncSelectAllCheckboxState();
                updateBulkSelectionUi();
                return;
            }

            var rowSelect = event.target.closest("[data-employees-select]");
            if (rowSelect) {
                var profileId = String(rowSelect.getAttribute("data-employee-profile-id") || "").trim();
                if (profileId) {
                    if (rowSelect.checked) {
                        selectedEmployeeProfilesMap[profileId] = true;
                    } else {
                        delete selectedEmployeeProfilesMap[profileId];
                    }
                }
                syncSelectAllCheckboxState();
                updateBulkSelectionUi();
            }
        });

        document.addEventListener("click", function (event) {
            var pageLink = event.target.closest("[data-employees-page]");
            if (pageLink) {
                event.preventDefault();
                var target = parseInt(pageLink.getAttribute("data-employees-page") || "1", 10);
                var maxPage = Math.max(1, Math.ceil(Number(employeesTableMeta.total || 0) / Math.max(1, Number(employeesTableMeta.perPage || 20))));
                if (target < 1 || target > maxPage || target === employeesTableState.page) {
                    return;
                }
                employeesTableState.page = target;
                loadEmployeesData();
                return;
            }

            var exportBtn = event.target.closest("[data-employees-export]");
            if (exportBtn) {
                event.preventDefault();
                exportEmployees(exportBtn.getAttribute("data-employees-export") || "xlsx");
                return;
            }

            var openBulkReassign = event.target.closest("[data-employees-bulk-reassign-open]");
            if (openBulkReassign) {
                event.preventDefault();
                if (getSelectedEmployeeProfileIds().length < 1) {
                    window.ArcavUi.showToast("Pilih minimal 1 employee dulu.", "warning");
                    return;
                }
                bulk.fillBulkTargetTeamOptions();
                bulk.renderBulkReassignResult("", "");
                var modal = bulk.getBulkReassignModalInstance();
                if (modal) {
                    modal.show();
                }
                return;
            }

            var scopeBtn = event.target.closest("[data-employees-scope-tab]");
            if (scopeBtn && employeesViewerContext.isSpecialSuperAdminCode1) {
                event.preventDefault();
                var nextScope = normalizeEmployeeScope(scopeBtn.getAttribute("data-employees-scope-tab"));
                if (!nextScope || nextScope === employeesTableState.scope) {
                    return;
                }
                employeesTableState.scope = nextScope;
                syncEmployeesScopeTabState(nextScope);
                triggerReload(true);
            }
        });

        document.addEventListener("submit", function (event) {
            var form = event.target.closest("[data-employees-bulk-reassign-form]");
            if (!form) {
                return;
            }
            event.preventDefault();
            bulk.submitBulkTeamReassign();
        });
    }

    function renderReportMessage(message) {
        var tbody = document.querySelector("[data-employee-report-body]");
        if (!tbody) {
            return;
        }
        tbody.innerHTML =
            '<tr><td class="text-center text-muted py-4">' + escapeHtml(message) + "</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
        tbody.removeAttribute("data-hydrated");
    }

    function renderReportTable(rows) {
        var tbody = document.querySelector("[data-employee-report-body]");
        if (!tbody) {
            return;
        }
        if (!rows.length) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">No employees.</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.setAttribute("data-hydrated", "1");
            return;
        }
        tbody.innerHTML = rows
            .map(function (row) {
                var st = row.employmentStatus || "active";
                var badge = st === "active" ? "success" : st === "inactive" ? "danger" : "warning";
                return (
                    "<tr>" +
                    "<td>" +
                    escapeHtml(formatEmployeeCode(row.id)) +
                    "</td><td>" +
                    escapeHtml(row.fullName || "") +
                    "</td><td>" +
                    escapeHtml(row.email || "") +
                    "</td><td>" +
                    escapeHtml(row.departmentName || "—") +
                    "</td><td>" +
                    escapeHtml(row.phone || "—") +
                    "</td><td>" +
                    escapeHtml(row.joinDate || "—") +
                    '</td><td><span class="badge badge-' +
                    badge +
                    ' d-inline-flex align-items-center badge-xs">' +
                    escapeHtml(st) +
                    "</span></td></tr>"
                );
            })
            .join("");
        tbody.setAttribute("data-hydrated", "1");
    }

    function renderEmployeeReportChart(rows) {
        var chart = window.__employeeReportChart;
        if (!chart) {
            return;
        }
        var year = window.__employeeReportChartYear || new Date().getFullYear();
        var activeByMonth = [0,0,0,0,0,0,0,0,0,0,0,0];
        var inactiveByMonth = [0,0,0,0,0,0,0,0,0,0,0,0];
        (rows || []).forEach(function (row) {
            var d = row.joinDate ? new Date(row.joinDate) : null;
            if (!d || isNaN(d.getTime()) || d.getFullYear() !== year) {
                return;
            }
            var m = d.getMonth();
            var st = String(row.employmentStatus || "active").toLowerCase();
            if (st === "active" || st === "probation") {
                activeByMonth[m]++;
            } else {
                inactiveByMonth[m]++;
            }
        });
        chart.updateSeries([
            { name: "Active Employees", data: activeByMonth },
            { name: "Inactive Employees", data: inactiveByMonth }
        ]);
    }

    function updateReportSummary(meta) {
        var summary = (meta && meta.summary) || {};
        var totalEmployees = summary.totalEmployees != null ? summary.totalEmployees : summary.total;
        var activeEmployees = summary.activeEmployees != null ? summary.activeEmployees : summary.total_active;
        var inactiveEmployees = summary.inactiveEmployees != null ? summary.inactiveEmployees : summary.total_inactive;
        var newJoiners = summary.newJoiners != null ? summary.newJoiners : summary.total_pending;
        var total = document.querySelector("[data-employee-report-total]");
        var active = document.querySelector("[data-employee-report-active]");
        var inactive = document.querySelector("[data-employee-report-inactive]");
        var newEl = document.querySelector("[data-employee-report-new]");
        if (total) {
            total.textContent = String(totalEmployees || 0);
        }
        if (active) {
            active.textContent = String(activeEmployees || 0);
        }
        if (inactive) {
            inactive.textContent = String(inactiveEmployees || 0);
        }
        if (newEl) {
            newEl.textContent = String(newJoiners || 0);
        }
    }

    function getEmployeeReportSourceMode() {
        var sourceEl = document.querySelector("[data-employee-report-source]");
        var source = sourceEl ? String(sourceEl.value || "live").toLowerCase() : "live";
        return source === "archive" ? "archive" : "live";
    }

    function getEmployeeReportSnapshotId() {
        var input = document.querySelector("[data-employee-report-snapshot-id]");
        if (!input) {
            return 0;
        }
        var parsed = parseInt(String(input.value || "0"), 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    }

    function setEmployeeReportSourceBadge() {
        var badge = document.querySelector("[data-employee-report-source-badge]");
        if (!badge) {
            return;
        }
        var mode = getEmployeeReportSourceMode();
        if (mode === "archive") {
            var snapshotId = getEmployeeReportSnapshotId();
            badge.textContent = "Source: Archive" + (snapshotId > 0 ? " #" + String(snapshotId) : "");
            return;
        }
        badge.textContent = "Source: Live";
    }

    function syncEmployeeReportSourceControls() {
        var wrap = document.querySelector("[data-employee-report-snapshot-wrap]");
        var mode = getEmployeeReportSourceMode();
        if (wrap) {
            if (mode === "archive") {
                wrap.classList.remove("d-none");
            } else {
                wrap.classList.add("d-none");
            }
        }
        setEmployeeReportSourceBadge();
    }

    function normalizeArchiveEmployeeRows(snapshot) {
        var moduleData = snapshot && snapshot.dataByModule ? snapshot.dataByModule.employee : null;
        if (!moduleData) {
            return [];
        }
        var byStatus = moduleData.by_status || {};
        return Object.keys(byStatus).map(function (status) {
            var item = byStatus[status] || {};
            return {
                uuid: "archive-snapshot-" + String(snapshot.id || "-"),
                fullName: "Status: " + String(item.status || status),
                email: "Employees: " + String(item.count || 0),
                team: "Share: " + String(item.percentage != null ? item.percentage : 0) + "%",
                departmentName: "Archive Snapshot",
                joinDate: snapshot.periodEnd || snapshot.generatedAt || "-",
                employmentStatus: String(item.status || status || "active"),
            };
        });
    }

    function loadArchiveEmployeeReport(snapshotId) {
        if (!snapshotId) {
            renderReportMessage("Snapshot ID wajib diisi untuk mode Archive.");
            updateReportSummary({ summary: {} });
            return;
        }

        requestJson("get", "/v1/hcm/reports/snapshots/" + encodeURIComponent(String(snapshotId)), null)
            .then(function (payload) {
                if (!payload || payload.success !== true || !payload.data) {
                    renderReportMessage("Snapshot tidak ditemukan atau tidak bisa diakses.");
                    updateReportSummary({ summary: {} });
                    return;
                }
                var snapshot = payload.data;
                if (snapshot.reportType !== "employee") {
                    renderReportMessage("Snapshot ini bukan untuk employee report.");
                    updateReportSummary({ summary: {} });
                    return;
                }
                if (String(snapshot.status || "").toLowerCase() !== "completed") {
                    renderReportMessage("Snapshot employee belum siap digunakan.");
                    updateReportSummary({ summary: {} });
                    return;
                }
                var rows = normalizeArchiveEmployeeRows(snapshot);
                if (!rows.length) {
                    renderReportMessage("Snapshot employee tidak memiliki data baris.");
                } else {
                    renderReportTable(rows);
                }
                var moduleData = snapshot.dataByModule && snapshot.dataByModule.employee ? snapshot.dataByModule.employee : {};
                updateReportSummary({ summary: moduleData.summary || {} });
            })
            .catch(function (error) {
                if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                    return;
                }
                renderReportMessage(formatApiError(error && error.data, error && error.status) || "Gagal memuat snapshot employee.");
                updateReportSummary({ summary: {} });
            });
    }

    function loadEmployeesData() {
        var hasEmployeesPage = window.location.pathname.indexOf("/employees") === 0 || window.location.pathname.indexOf("/employees-grid") === 0;
        if (!hasEmployeesPage) {
            return;
        }

        // Prevent template dummy rows from flashing before API data arrives.
        var listBody = document.querySelector("[data-employees-list-body]");
        if (listBody) {
            listBody.innerHTML = '<tr><td class="text-center text-muted py-4">Loading employees...</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }
        var gridBody = document.querySelector("[data-employees-grid-body]");
        if (gridBody) {
            gridBody.innerHTML = '<div class="col-12"><div class="alert alert-light text-center mb-0">Loading employees...</div></div>';
        }

        requestAuthMe()
            .then(function (me) {
                if (!me || !me.success || !me.data || !me.data.permissions || !me.data.permissions['employee.view']) {
                    window.location.replace("/employee-dashboard");
                    return null;
                }

                applyEmployeesScopeTabs(me.data || {});
                Org.bindEmployeeOrgDepartmentChange();
                return Org.ensureEmployeeOrgMastersLoaded().then(function () {
                    syncEmployeesFilterOptions();
                    return requestEmployeesByState(employeesTableState);
                });
            })
            .then(function (payload) {
                if (payload === null) {
                    return;
                }
                if (!payload || payload.success !== true) {
                    var msg = formatApiError(payload, 0);
                    renderListMessage(msg || "Unable to load employees data.");
                    renderGridMessage(msg || "Unable to load employees data.");
                    return;
                }

                var rows = Array.isArray(payload.data) ? payload.data : [];
                employeesTableMeta.page = Number(payload.meta && payload.meta.page ? payload.meta.page : employeesTableState.page);
                employeesTableMeta.perPage = Number(payload.meta && payload.meta.perPage ? payload.meta.perPage : employeesTableState.perPage);
                employeesTableMeta.total = Number(payload.meta && payload.meta.total ? payload.meta.total : rows.length);
                employeesTableState.page = employeesTableMeta.page;
                renderers.renderList(rows);
                renderers.renderGrid(rows);
                updateActiveRowHighlight();
                updateSummary(payload.meta || {});
                renderEmployeesShowing(employeesTableMeta, rows.length);
                renderEmployeesPagination(employeesTableMeta);
            })
            .catch(function (error) {
                console.error("Failed to load employees data", error);
                if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                    return;
                }
                var msg = formatApiError(error && error.data, error && error.status);
                renderListMessage(msg || "Failed loading employees. Please try again.");
                renderGridMessage(msg || "Failed loading employees. Please try again.");
                renderEmployeesShowing({ total: 0, page: 1, perPage: employeesTableState.perPage }, 0);
                renderEmployeesPagination({ total: 0, page: 1, perPage: employeesTableState.perPage });
            });
    }

    function loadEmployeeReportData() {
        var path = String(window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path !== "/employee-report") {
            return;
        }
        var tbody = document.querySelector("[data-employee-report-body]");
        if (tbody) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">Loading employees…</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.removeAttribute("data-hydrated");
        }
        requestAuthMe()
            .then(function (me) {
                if (!me || !me.success || !me.data || !me.data.permissions || !me.data.permissions['employee.view']) {
                    window.location.replace("/employee-dashboard");
                    return null;
                }
                var mode = getEmployeeReportSourceMode();
                if (mode === "archive") {
                    loadArchiveEmployeeReport(getEmployeeReportSnapshotId());
                    return null;
                }
                return requestAllEmployeesAggregated(100);
            })
            .then(function (payload) {
                if (payload === null) {
                    return;
                }
                if (!payload || payload.success !== true) {
                    renderReportMessage(formatApiError(payload, 0) || "Unable to load employee report.");
                    return;
                }
                var rows = Array.isArray(payload.data) ? payload.data : [];
                renderReportTable(rows);
                renderEmployeeReportChart(rows);
                updateReportSummary(payload.meta || {});
            })
            .catch(function (error) {
                if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                    return;
                }
                renderReportMessage(formatApiError(error && error.data, error && error.status) || "Failed loading report. Please try again.");
            });
    }

    function init() {
        restoreReturnStateIfAny();
        binders.bindEmployeesListControls();
        binders.bindEmployeePhotoModalPreview();
        loadEmployeesData();
        syncEmployeeReportSourceControls();
        document.addEventListener("change", function (event) {
            var sourceEl = event.target && event.target.closest ? event.target.closest("[data-employee-report-source]") : null;
            if (sourceEl) {
                syncEmployeeReportSourceControls();
                return;
            }
            var snapshotInput = event.target && event.target.closest ? event.target.closest("[data-employee-report-snapshot-id]") : null;
            if (snapshotInput) {
                setEmployeeReportSourceBadge();
            }
        });
        document.addEventListener("click", function (event) {
            var trigger = event.target && event.target.closest ? event.target.closest("[data-employee-report-load]") : null;
            if (!trigger) {
                return;
            }
            event.preventDefault();
            loadEmployeeReportData();
        });
        loadEmployeeReportData();
        binders.bindQuickPreview();
        binders.bindEmployeeCompensationForms();
        binders.bindSalaryBulkUpload();
        document.addEventListener("employees:view-swapped", function () {
            loadEmployeesData();
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            try {
                init();
            }
            catch (err) {
                console.error && console.error('employees legacy init error', err);
            }
        });
    }
    else {
        try {
            init();
        }
        catch (err) {
            console.error && console.error('employees legacy init error', err);
        }
    }
})(window, document);
