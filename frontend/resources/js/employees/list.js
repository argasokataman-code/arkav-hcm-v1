// List handlers extracted from employees-data.legacy.js
import { escapeHtml, getCurrentListUrl } from './helpers.js';

export function makeListHandlers(deps) {
    var employeesTableState = deps.employeesTableState;
    var employeesViewerContext = deps.employeesViewerContext;
    var selectedEmployeeProfilesMap = deps.selectedEmployeeProfilesMap;
    var _selectedPreviewEmployeeId = deps.selectedPreviewEmployeeId != null ? deps.selectedPreviewEmployeeId : null;
    var _getCurrentListUrl = deps.getCurrentListUrl || getCurrentListUrl;

    // ── helpers ────────────────────────────────────────────────────────

    function isSpecialSuperAdminCode1(meData) {
        if (!meData || !meData.hcmGlobalAdmin) {
            return false;
        }
        var activeCompanyId = meData.activeCompany && meData.activeCompany.id != null
            ? Number(meData.activeCompany.id)
            : 0;
        var userId = meData.id != null ? Number(meData.id) : 0;
        return activeCompanyId === 1 || userId === 1;
    }

    function getSelectedEmployeeProfileIds() {
        return Object.keys(selectedEmployeeProfilesMap)
            .filter(function (id) { return Boolean(selectedEmployeeProfilesMap[id]); })
            .map(function (id) { return Number(id); })
            .filter(function (id) { return Number.isFinite(id) && id > 0; });
    }

    function syncSelectAllCheckboxState() {
        var selectAll = document.querySelector('[data-employees-select-all]');
        if (!selectAll) return;
        var rowCheckboxes = Array.prototype.slice.call(document.querySelectorAll('[data-employees-select]'));
        if (!rowCheckboxes.length) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }
        var selectedCount = rowCheckboxes.filter(function (cb) { return cb.checked; }).length;
        selectAll.checked = selectedCount > 0 && selectedCount === rowCheckboxes.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < rowCheckboxes.length;
    }

    function updateBulkSelectionUi() {
        var selectedIds = getSelectedEmployeeProfileIds();
        var count = selectedIds.length;
        document.querySelectorAll('[data-employees-selected-count], [data-employees-bulk-selected-count]').forEach(function (el) {
            el.textContent = String(count);
        });
        var openBtn = document.querySelector('[data-employees-bulk-reassign-open]');
        if (openBtn) openBtn.disabled = count < 1;
    }

    function clearSelectedEmployeesSelection() {
        Object.keys(selectedEmployeeProfilesMap).forEach(function (k) { delete selectedEmployeeProfilesMap[k]; });
        document.querySelectorAll('[data-employees-select]').forEach(function (cb) { cb.checked = false; });
        syncSelectAllCheckboxState();
        updateBulkSelectionUi();
    }

    function renderGridMessage(message) {
        var gridBody = document.querySelector('[data-employees-grid-body]');
        if (!gridBody) return;
        gridBody.innerHTML = '<div class="col-12"><div class="alert alert-light text-center mb-0">' + escapeHtml(message) + '</div></div>';
        gridBody.setAttribute('data-hydrated', '1');
    }

    function updateSummary(meta) {
        var summary = (meta && meta.summary) || {};
        var total = document.querySelector('[data-employees-total]');
        var active = document.querySelector('[data-employees-active]');
        var inactive = document.querySelector('[data-employees-inactive]');
        var newJoiners = document.querySelector('[data-employees-new-joiners]');
        if (total) total.textContent = String(summary.totalEmployees || 0);
        if (active) active.textContent = String(summary.activeEmployees || 0);
        if (inactive) inactive.textContent = String(summary.inactiveEmployees || 0);
        if (newJoiners) newJoiners.textContent = String(summary.newJoiners || 0);
    }

    function renderEmployeesShowing(meta, rowCount) {
        var el = document.querySelector('[data-employees-showing]');
        if (!el) return;
        var total = Number(meta && meta.total ? meta.total : 0);
        var page = Number(meta && meta.page ? meta.page : 1);
        var perPage = Number(meta && meta.perPage ? meta.perPage : 20);
        if (!total || !rowCount) {
            el.textContent = 'Showing 0 - 0 of 0 entries';
            return;
        }
        var start = ((page - 1) * perPage) + 1;
        var end = Math.min(start + rowCount - 1, total);
        el.textContent = 'Showing ' + start + ' - ' + end + ' of ' + total + ' entries';
    }

    function renderEmployeesPagination(meta) {
        var list = document.querySelector('[data-employees-pagination]');
        if (!list) return;
        var total = Number(meta && meta.total ? meta.total : 0);
        var page = Number(meta && meta.page ? meta.page : 1);
        var perPage = Number(meta && meta.perPage ? meta.perPage : 20);
        var totalPages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));
        if (totalPages <= 1) { list.innerHTML = ''; return; }

        var startPage = Math.max(1, page - 2);
        var endPage = Math.min(totalPages, page + 2);
        var html = '';
        html += '<li class="page-item ' + (page <= 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-employees-page="' + (page - 1) + '">Prev</a></li>';
        for (var p = startPage; p <= endPage; p += 1) {
            html += '<li class="page-item ' + (p === page ? 'active' : '') + '"><a class="page-link" href="#" data-employees-page="' + p + '">' + p + '</a></li>';
        }
        html += '<li class="page-item ' + (page >= totalPages ? 'disabled' : '') + '"><a class="page-link" href="#" data-employees-page="' + (page + 1) + '">Next</a></li>';
        list.innerHTML = html;
    }

    function exportEmployees(format) {
        var params = new URLSearchParams();
        if (employeesTableState.search) params.set('search', employeesTableState.search);
        if (employeesTableState.status) params.set('status', employeesTableState.status);
        if (employeesTableState.departmentId) params.set('departmentId', employeesTableState.departmentId);
        if (employeesTableState.designationId) params.set('designationId', employeesTableState.designationId);
        if (employeesTableState.teamId) params.set('teamId', employeesTableState.teamId);
        if (employeesTableState.scope) params.set('scope', employeesTableState.scope);
        params.set('format', format === 'pdf' ? 'pdf' : 'xlsx');
        window.location.assign('/v1/hcm/employees/export?' + params.toString());
    }

    function syncEmployeesScopeTabState(activeScope) {
        var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-employees-scope-tab]'));
        tabs.forEach(function (tab) {
            var tabScope = String(tab.getAttribute('data-employees-scope-tab') || '').toLowerCase();
            var isActive = tabScope && tabScope === activeScope;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function syncEmployeesFilterOptions(departments, designations, teams) {
        var depSel = document.querySelector('[data-employees-filter-department]');
        var desSel = document.querySelector('[data-employees-filter-designation]');
        var teamSel = document.querySelector('[data-employees-filter-team]');
        if (depSel) {
            var depPrev = employeesTableState.departmentId || depSel.value || '';
            depSel.innerHTML = '<option value="">All Departments</option>';
            (departments || []).forEach(function (d) {
                var opt = document.createElement('option');
                opt.value = String(d.id);
                opt.textContent = d.name || d.code || String(d.id);
                depSel.appendChild(opt);
            });
            depSel.value = depPrev;
        }
        if (desSel) {
            var desPrev = employeesTableState.designationId || desSel.value || '';
            desSel.innerHTML = '<option value="">All Designations</option>';
            (designations || []).forEach(function (d) {
                var opt = document.createElement('option');
                opt.value = String(d.id);
                opt.textContent = d.name || d.code || String(d.id);
                desSel.appendChild(opt);
            });
            desSel.value = desPrev;
        }
        if (teamSel) {
            var teamPrev = employeesTableState.teamId || teamSel.value || '';
            teamSel.innerHTML = '<option value="">All Teams</option>';
            (teams || []).forEach(function (t) {
                var opt = document.createElement('option');
                opt.value = String(t.id);
                opt.textContent = t.name || String(t.id);
                teamSel.appendChild(opt);
            });
            teamSel.value = teamPrev;
        }
    }

    function updateActiveRowHighlight(selectedId) {
        var id = selectedId != null ? selectedId : _selectedPreviewEmployeeId;
        var rows = document.querySelectorAll('[data-employees-row-preview]');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var active = id && row.getAttribute('data-employees-row-preview') === String(id);
            row.classList.toggle('table-primary', !!active);
        }
    }

    function applyEmployeesScopeTabs(meData) {
        var wrap = document.querySelector('[data-employees-scope-tabs-wrap]');
        if (!wrap) return;
        var eligible = isSpecialSuperAdminCode1(meData);
        employeesViewerContext.isSpecialSuperAdminCode1 = eligible;
        if (!eligible) {
            wrap.classList.add('d-none');
            employeesTableState.scope = '';
            syncEmployeesScopeTabState('');
            return;
        }
        wrap.classList.remove('d-none');
        var requestedScope = String(employeesTableState.scope || 'active_company').toLowerCase();
        if (requestedScope !== 'global' && requestedScope !== 'active_company') {
            requestedScope = 'active_company';
        }
        employeesTableState.scope = requestedScope;
        syncEmployeesScopeTabState(requestedScope);
    }

    function saveReturnState(employeeId) {
        try {
            window.sessionStorage.setItem('arcav_employees_return_state_v1', JSON.stringify({
                url: _getCurrentListUrl(),
                scrollY: window.scrollY || 0,
                selectedId: employeeId ? String(employeeId) : '',
                ts: Date.now()
            }));
        } catch (_e) {}
    }

    function restoreReturnStateIfAny() {
        try {
            var raw = window.sessionStorage.getItem('arcav_employees_return_state_v1');
            if (!raw) return;
            var s = JSON.parse(raw);
            if (!s || s.url !== _getCurrentListUrl()) return;
            window.setTimeout(function () { window.scrollTo(0, Number(s.scrollY || 0)); }, 0);
            if (s.selectedId) {
                _selectedPreviewEmployeeId = String(s.selectedId);
            }
        } catch (_e) {}
    }

    function getSelectedPreviewEmployeeId() {
        return _selectedPreviewEmployeeId;
    }

    return {
        isSpecialSuperAdminCode1: isSpecialSuperAdminCode1,
        getSelectedEmployeeProfileIds: getSelectedEmployeeProfileIds,
        syncSelectAllCheckboxState: syncSelectAllCheckboxState,
        updateBulkSelectionUi: updateBulkSelectionUi,
        clearSelectedEmployeesSelection: clearSelectedEmployeesSelection,
        renderGridMessage: renderGridMessage,
        updateSummary: updateSummary,
        renderEmployeesShowing: renderEmployeesShowing,
        renderEmployeesPagination: renderEmployeesPagination,
        exportEmployees: exportEmployees,
        syncEmployeesScopeTabState: syncEmployeesScopeTabState,
        syncEmployeesFilterOptions: syncEmployeesFilterOptions,
        updateActiveRowHighlight: updateActiveRowHighlight,
        applyEmployeesScopeTabs: applyEmployeesScopeTabs,
        saveReturnState: saveReturnState,
        restoreReturnStateIfAny: restoreReturnStateIfAny,
        getSelectedPreviewEmployeeId: getSelectedPreviewEmployeeId,
    };
}

export default { makeListHandlers };
