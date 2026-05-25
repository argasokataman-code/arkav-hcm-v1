// Binders: centralize DOM event binding functions for employees module
import { normalizeEmployeeScope as defaultNormalizeScope } from './helpers.js';

export function makeBinders(deps) {
    var loaders = window.ArcavEmployeesModuleLoaders || {};
    var resolveBindEmployeeCompensationFormsModule = loaders.resolveBindEmployeeCompensationFormsModule || function () { return null; };
    var loadBindEmployeeCompensationFormsModule = loaders.loadBindEmployeeCompensationFormsModule || function () { return Promise.resolve(null); };
    var resolveBindQuickPreviewModule = loaders.resolveBindQuickPreviewModule || function () { return null; };
    var loadBindQuickPreviewModule = loaders.loadBindQuickPreviewModule || function () { return Promise.resolve(null); };
    var resolveBindEmployeePhotoModalPreviewModule = loaders.resolveBindEmployeePhotoModalPreviewModule || function () { return null; };
    var loadBindEmployeePhotoModalPreviewModule = loaders.loadBindEmployeePhotoModalPreviewModule || function () { return Promise.resolve(null); };
    var resolveBindSalaryBulkUploadModule = loaders.resolveBindSalaryBulkUploadModule || function () { return null; };
    var loadBindSalaryBulkUploadModule = loaders.loadBindSalaryBulkUploadModule || function () { return Promise.resolve(null); };

    var normalizeEmployeeScope = deps.normalizeEmployeeScope || defaultNormalizeScope;

    function bindQuickPreview() {
        var moduleFn = resolveBindQuickPreviewModule();
        var moduleArgs = {
            buildEmployeeDetailUrl: deps.buildEmployeeDetailUrl,
            requestEmployeeDetail: deps.requestEmployeeDetail,
            formatApiError: deps.formatApiError,
            saveReturnState: deps.saveReturnState,
            updateActiveRowHighlight: deps.updateActiveRowHighlight,
            getSelectedPreviewEmployeeId: deps.getSelectedPreviewEmployeeId,
            setSelectedPreviewEmployeeId: deps.setSelectedPreviewEmployeeId,
            escapeHtml: deps.escapeHtml,
            formatRupiah: deps.formatRupiah,
        };

        if (moduleFn) {
            return moduleFn(moduleArgs);
        }
        loadBindQuickPreviewModule().then(function (loadedFn) {
            if (typeof loadedFn === 'function') loadedFn(moduleArgs);
        });
        return null;
    }

    function bindEmployeeCompensationForms() {
        var moduleFn = resolveBindEmployeeCompensationFormsModule();
        var moduleArgs = {
            requestJson: deps.requestJson,
            requestEmployeeDetail: deps.requestEmployeeDetail,
            fillDesignationSelectForDepartment: deps.fillDesignationSelectForDepartment,
            loadTeamsDropdown: deps.loadTeamsDropdown,
            formatApiError: deps.formatApiError,
            loadEmployeesData: deps.loadEmployeesData,
        };
        if (moduleFn) return moduleFn(moduleArgs);
        loadBindEmployeeCompensationFormsModule().then(function (loadedFn) {
            if (typeof loadedFn === 'function') loadedFn(moduleArgs);
        });
        return null;
    }

    function bindEmployeePhotoModalPreview() {
        var moduleFn = resolveBindEmployeePhotoModalPreviewModule();
        if (moduleFn) return moduleFn();
        loadBindEmployeePhotoModalPreviewModule().then(function (loadedFn) {
            if (typeof loadedFn === 'function') loadedFn();
        });
        return null;
    }

    function bindSalaryBulkUpload() {
        var moduleFn = resolveBindSalaryBulkUploadModule();
        var moduleArgs = {
            requestFormData: deps.requestFormData,
            formatApiError: deps.formatApiError,
            escapeHtml: deps.escapeHtml,
            loadEmployeesData: deps.loadEmployeesData,
            getOrganizationReferenceSnapshot: deps.getOrganizationReferenceSnapshot,
        };
        if (moduleFn) return moduleFn(moduleArgs);
        loadBindSalaryBulkUploadModule().then(function (loadedFn) {
            if (typeof loadedFn === 'function') loadedFn(moduleArgs);
        });
        return null;
    }

    function bindEmployeesListControls() {
        if (document.body.getAttribute('data-employees-controls-bound') === '1') return;
        document.body.setAttribute('data-employees-controls-bound', '1');

        var searchInput = document.querySelector('[data-employees-search]');
        var statusSel = document.querySelector('[data-employees-filter-status]');
        var depSel = document.querySelector('[data-employees-filter-department]');
        var desSel = document.querySelector('[data-employees-filter-designation]');
        var teamSel = document.querySelector('[data-employees-filter-team]');
        var perPageSel = document.querySelector('[data-employees-per-page]');
        var debounceTimer = null;

        var params = new URL(window.location.href).searchParams;
        deps.employeesTableState.page = Math.max(1, parseInt(params.get('page') || String(deps.employeesTableState.page), 10) || 1);
        deps.employeesTableState.perPage = Math.max(1, parseInt(params.get('perPage') || String(deps.employeesTableState.perPage), 10) || 20);
        deps.employeesTableState.search = String(params.get('search') || deps.employeesTableState.search || '').trim();
        deps.employeesTableState.status = String(params.get('status') || deps.employeesTableState.status || '').trim();
        deps.employeesTableState.departmentId = String(params.get('departmentId') || deps.employeesTableState.departmentId || '').trim();
        deps.employeesTableState.designationId = String(params.get('designationId') || deps.employeesTableState.designationId || '').trim();
        deps.employeesTableState.teamId = String(params.get('teamId') || deps.employeesTableState.teamId || '').trim();
        deps.employeesTableState.scope = normalizeEmployeeScope(params.get('scope') || deps.employeesTableState.scope || '');

        if (searchInput) searchInput.value = deps.employeesTableState.search;
        if (statusSel) statusSel.value = deps.employeesTableState.status;
        if (teamSel) teamSel.value = deps.employeesTableState.teamId;
        if (perPageSel) perPageSel.value = String(deps.employeesTableState.perPage);
        deps.bulk.fillBulkTargetTeamOptions();
        deps.updateBulkSelectionUi();

        function triggerReload(resetPage) {
            if (resetPage) deps.employeesTableState.page = 1;
            if (typeof deps.loadEmployeesData === 'function') deps.loadEmployeesData();
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(function () {
                    deps.employeesTableState.search = String(searchInput.value || '').trim();
                    triggerReload(true);
                }, 350);
            });
        }
        if (statusSel) {
            statusSel.addEventListener('change', function () { deps.employeesTableState.status = String(statusSel.value || ''); triggerReload(true); });
        }
        if (depSel) {
            depSel.addEventListener('change', function () { deps.employeesTableState.departmentId = String(depSel.value || ''); triggerReload(true); });
        }
        if (desSel) {
            desSel.addEventListener('change', function () { deps.employeesTableState.designationId = String(desSel.value || ''); triggerReload(true); });
        }
        if (teamSel) {
            teamSel.addEventListener('change', function () { deps.employeesTableState.teamId = String(teamSel.value || ''); triggerReload(true); });
        }
        if (perPageSel) {
            perPageSel.addEventListener('change', function () { deps.employeesTableState.perPage = Math.max(1, parseInt(perPageSel.value || '20', 10) || 20); triggerReload(true); });
        }

        document.addEventListener('change', function (event) {
            var selectAll = event.target.closest ? event.target.closest('[data-employees-select-all]') : null;
            if (selectAll) {
                var checked = Boolean(selectAll.checked);
                document.querySelectorAll('[data-employees-select]').forEach(function (cb) {
                    cb.checked = checked;
                    var profileId = String(cb.getAttribute('data-employee-profile-id') || '').trim();
                    if (!profileId) return;
                    if (checked) deps.selectedEmployeeProfilesMap[profileId] = true; else delete deps.selectedEmployeeProfilesMap[profileId];
                });
                deps.syncSelectAllCheckboxState();
                deps.updateBulkSelectionUi();
                return;
            }

            var rowSelect = event.target.closest ? event.target.closest('[data-employees-select]') : null;
            if (rowSelect) {
                var profileId = String(rowSelect.getAttribute('data-employee-profile-id') || '').trim();
                if (profileId) {
                    if (rowSelect.checked) deps.selectedEmployeeProfilesMap[profileId] = true; else delete deps.selectedEmployeeProfilesMap[profileId];
                }
                deps.syncSelectAllCheckboxState();
                deps.updateBulkSelectionUi();
            }
        });

        document.addEventListener('click', function (event) {
            var pageLink = event.target.closest ? event.target.closest('[data-employees-page]') : null;
            if (pageLink) {
                event.preventDefault();
                var target = parseInt(pageLink.getAttribute('data-employees-page') || '1', 10);
                var maxPage = Math.max(1, Math.ceil(Number(deps.employeesTableMeta.total || 0) / Math.max(1, Number(deps.employeesTableMeta.perPage || 20))))
                if (target < 1 || target > maxPage || target === deps.employeesTableState.page) return;
                deps.employeesTableState.page = target; deps.loadEmployeesData(); return;
            }

            var exportBtn = event.target.closest ? event.target.closest('[data-employees-export]') : null;
            if (exportBtn) { event.preventDefault(); deps.exportEmployees(exportBtn.getAttribute('data-employees-export') || 'xlsx'); return; }

            var openBulkReassign = event.target.closest ? event.target.closest('[data-employees-bulk-reassign-open]') : null;
            if (openBulkReassign) {
                event.preventDefault();
                if (deps.getSelectedEmployeeProfileIds().length < 1) { window.ArcavUi.showToast('Pilih minimal 1 employee dulu.', 'warning'); return; }
                deps.bulk.fillBulkTargetTeamOptions(); deps.bulk.renderBulkReassignResult('', ''); var modal = deps.bulk.getBulkReassignModalInstance(); if (modal) modal.show(); return;
            }

            var scopeBtn = event.target.closest ? event.target.closest('[data-employees-scope-tab]') : null;
            if (scopeBtn && deps.employeesViewerContext && deps.employeesViewerContext.isSpecialSuperAdminCode1) {
                event.preventDefault(); var nextScope = normalizeEmployeeScope(scopeBtn.getAttribute('data-employees-scope-tab')); if (!nextScope || nextScope === deps.employeesTableState.scope) return; deps.employeesTableState.scope = nextScope; if (typeof deps.syncEmployeesScopeTabState === 'function') deps.syncEmployeesScopeTabState(nextScope); triggerReload(true);
            }
        });

        document.addEventListener('submit', function (event) {
            var form = event.target.closest ? event.target.closest('[data-employees-bulk-reassign-form]') : null;
            if (!form) return; event.preventDefault(); deps.bulk.submitBulkTeamReassign();
        });
    }

    return {
        bindQuickPreview: bindQuickPreview,
        bindEmployeeCompensationForms: bindEmployeeCompensationForms,
        bindEmployeePhotoModalPreview: bindEmployeePhotoModalPreview,
        bindSalaryBulkUpload: bindSalaryBulkUpload,
        bindEmployeesListControls: bindEmployeesListControls,
    };
}

export default { makeBinders };
