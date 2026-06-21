// Employee report handlers extracted from employees-data.legacy.js
import { formatEmployeeCode, formatApiError } from './helpers.js';
import { requestJson, requestAuthMe, requestAllEmployeesAggregated } from './api.js';

export function makeReportHandlers(deps) {
    var escapeHtml = deps.escapeHtml || function (v) { return String(v); };
    var _formatEmployeeCode = deps.formatEmployeeCode || formatEmployeeCode;
    var _formatApiError = deps.formatApiError || formatApiError;
    var _requestJson = deps.requestJson || requestJson;
    var _requestAuthMe = deps.requestAuthMe || requestAuthMe;
    var _requestAllEmployeesAggregated = deps.requestAllEmployeesAggregated || requestAllEmployeesAggregated;

    // ── source mode ──────────────────────────────────────────────────

    function getEmployeeReportSourceMode() {
        var sourceEl = document.querySelector('[data-employee-report-source]');
        var source = sourceEl ? String(sourceEl.value || 'live').toLowerCase() : 'live';
        return source === 'archive' ? 'archive' : 'live';
    }

    function getEmployeeReportSnapshotId() {
        var input = document.querySelector('[data-employee-report-snapshot-id]');
        if (!input) return 0;
        var parsed = parseInt(String(input.value || '0'), 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    }

    function setEmployeeReportSourceBadge() {
        var badge = document.querySelector('[data-employee-report-source-badge]');
        if (!badge) return;
        var mode = getEmployeeReportSourceMode();
        if (mode === 'archive') {
            var snapshotId = getEmployeeReportSnapshotId();
            badge.textContent = 'Source: Archive' + (snapshotId > 0 ? ' #' + String(snapshotId) : '');
            return;
        }
        badge.textContent = 'Source: Live';
    }

    function syncEmployeeReportSourceControls() {
        var wrap = document.querySelector('[data-employee-report-snapshot-wrap]');
        var mode = getEmployeeReportSourceMode();
        if (wrap) {
            if (mode === 'archive') {
                wrap.classList.remove('d-none');
            } else {
                wrap.classList.add('d-none');
            }
        }
        setEmployeeReportSourceBadge();
    }

    // ── archive snapshot ─────────────────────────────────────────────

    function normalizeArchiveEmployeeRows(snapshot) {
        var moduleData = snapshot && snapshot.dataByModule ? snapshot.dataByModule.employee : null;
        if (!moduleData) return [];
        var byStatus = moduleData.by_status || {};
        return Object.keys(byStatus).map(function (status) {
            var item = byStatus[status] || {};
            return {
                uuid: 'archive-snapshot-' + String(snapshot.id || '-'),
                fullName: 'Status: ' + String(item.status || status),
                email: 'Employees: ' + String(item.count || 0),
                team: 'Share: ' + String(item.percentage != null ? item.percentage : 0) + '%',
                departmentName: 'Archive Snapshot',
                joinDate: snapshot.periodEnd || snapshot.generatedAt || '-',
                employmentStatus: String(item.status || status || 'active'),
            };
        });
    }

    // ── render ───────────────────────────────────────────────────────

    function renderReportMessage(message) {
        var tbody = document.querySelector('[data-employee-report-body]');
        if (!tbody) return;
        tbody.innerHTML =
            '<tr><td class="text-center text-muted py-4">' + escapeHtml(message) + '</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        tbody.removeAttribute('data-hydrated');
    }

    function renderReportTable(rows) {
        var tbody = document.querySelector('[data-employee-report-body]');
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">No employees.</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.setAttribute('data-hydrated', '1');
            return;
        }
        tbody.innerHTML = rows.map(function (row) {
            var st = row.employmentStatus || 'active';
            var badge = st === 'active' ? 'success' : st === 'inactive' ? 'danger' : 'warning';
            return (
                '<tr>' +
                '<td>' + escapeHtml(_formatEmployeeCode(row.id)) + '</td>' +
                '<td>' + escapeHtml(row.fullName || '') + '</td>' +
                '<td>' + escapeHtml(row.email || '') + '</td>' +
                '<td>' + escapeHtml(row.departmentName || '\u2014') + '</td>' +
                '<td>' + escapeHtml(row.phone || '\u2014') + '</td>' +
                '<td>' + escapeHtml(row.joinDate || '\u2014') + '</td>' +
                '<td><span class="badge badge-' + badge + ' d-inline-flex align-items-center badge-xs">' +
                escapeHtml(st) + '</span></td></tr>'
            );
        }).join('');
        tbody.setAttribute('data-hydrated', '1');
    }

    function renderEmployeeReportChart(rows) {
        var chart = window.__employeeReportChart;
        if (!chart) return;
        var year = window.__employeeReportChartYear || new Date().getFullYear();
        var activeByMonth = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        var inactiveByMonth = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        (rows || []).forEach(function (row) {
            var d = row.joinDate ? new Date(row.joinDate) : null;
            if (!d || isNaN(d.getTime()) || d.getFullYear() !== year) return;
            var m = d.getMonth();
            var st = String(row.employmentStatus || 'active').toLowerCase();
            if (st === 'active' || st === 'probation') {
                activeByMonth[m]++;
            } else {
                inactiveByMonth[m]++;
            }
        });
        chart.updateSeries([
            { name: 'Active Employees', data: activeByMonth },
            { name: 'Inactive Employees', data: inactiveByMonth },
        ]);
    }

    function updateReportSummary(meta) {
        var summary = (meta && meta.summary) || {};
        var totalEmployees = summary.totalEmployees != null ? summary.totalEmployees : summary.total;
        var activeEmployees = summary.activeEmployees != null ? summary.activeEmployees : summary.total_active;
        var inactiveEmployees = summary.inactiveEmployees != null ? summary.inactiveEmployees : summary.total_inactive;
        var newJoiners = summary.newJoiners != null ? summary.newJoiners : summary.total_pending;
        var total = document.querySelector('[data-employee-report-total]');
        var active = document.querySelector('[data-employee-report-active]');
        var inactive = document.querySelector('[data-employee-report-inactive]');
        var newEl = document.querySelector('[data-employee-report-new]');
        if (total) total.textContent = String(totalEmployees || 0);
        if (active) active.textContent = String(activeEmployees || 0);
        if (inactive) inactive.textContent = String(inactiveEmployees || 0);
        if (newEl) newEl.textContent = String(newJoiners || 0);
    }

    // ── data loading ─────────────────────────────────────────────────

    function loadArchiveEmployeeReport(snapshotId) {
        if (!snapshotId) {
            renderReportMessage('Snapshot ID wajib diisi untuk mode Archive.');
            updateReportSummary({ summary: {} });
            return Promise.resolve();
        }

        return _requestJson('get', '/v1/hcm/reports/snapshots/' + encodeURIComponent(String(snapshotId)), null)
            .then(function (payload) {
                if (!payload || payload.success !== true || !payload.data) {
                    renderReportMessage('Snapshot tidak ditemukan atau tidak bisa diakses.');
                    updateReportSummary({ summary: {} });
                    return;
                }
                var snapshot = payload.data;
                if (snapshot.reportType !== 'employee') {
                    renderReportMessage('Snapshot ini bukan untuk employee report.');
                    updateReportSummary({ summary: {} });
                    return;
                }
                if (String(snapshot.status || '').toLowerCase() !== 'completed') {
                    renderReportMessage('Snapshot employee belum siap digunakan.');
                    updateReportSummary({ summary: {} });
                    return;
                }
                var rows = normalizeArchiveEmployeeRows(snapshot);
                if (!rows.length) {
                    renderReportMessage('Snapshot employee tidak memiliki data baris.');
                } else {
                    renderReportTable(rows);
                }
                var moduleData = snapshot.dataByModule && snapshot.dataByModule.employee
                    ? snapshot.dataByModule.employee : {};
                updateReportSummary({ summary: moduleData.summary || {} });
            })
            .catch(function (error) {
                if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                    return;
                }
                renderReportMessage(_formatApiError(error && error.data, error && error.status)
                    || 'Gagal memuat snapshot employee.');
                updateReportSummary({ summary: {} });
            });
    }

    function loadEmployeeReportData() {
        var path = String(window.location.pathname || '').replace(/\/+$/, '') || '/';
        if (path !== '/employee-report') {
            return Promise.resolve();
        }
        var tbody = document.querySelector('[data-employee-report-body]');
        if (tbody) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">Loading employees\u2026</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.removeAttribute('data-hydrated');
        }
        return _requestAuthMe()
            .then(function (me) {
                if (!me || !me.success || !me.data || !me.data.permissions || !me.data.permissions['employee.view']) {
                    window.location.replace('/employee-dashboard');
                    return null;
                }
                var mode = getEmployeeReportSourceMode();
                if (mode === 'archive') {
                    return loadArchiveEmployeeReport(getEmployeeReportSnapshotId()).then(function () { return null; });
                }
                return _requestAllEmployeesAggregated(100);
            })
            .then(function (payload) {
                if (payload === null) return;
                if (!payload || payload.success !== true) {
                    renderReportMessage(_formatApiError(payload, 0) || 'Unable to load employee report.');
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
                renderReportMessage(_formatApiError(error && error.data, error && error.status)
                    || 'Failed loading report. Please try again.');
            });
    }

    return {
        getEmployeeReportSourceMode: getEmployeeReportSourceMode,
        getEmployeeReportSnapshotId: getEmployeeReportSnapshotId,
        setEmployeeReportSourceBadge: setEmployeeReportSourceBadge,
        syncEmployeeReportSourceControls: syncEmployeeReportSourceControls,
        normalizeArchiveEmployeeRows: normalizeArchiveEmployeeRows,
        renderReportMessage: renderReportMessage,
        renderReportTable: renderReportTable,
        renderEmployeeReportChart: renderEmployeeReportChart,
        updateReportSummary: updateReportSummary,
        loadArchiveEmployeeReport: loadArchiveEmployeeReport,
        loadEmployeeReportData: loadEmployeeReportData,
    };
}

export default { makeReportHandlers };
