/**
 * Global Employee Monitor — Super Admin
 * Route: /super-admin/employees-monitor
 * API: GET /v1/hcm/super-admin/employees-monitor
 */
(function () {
    'use strict';

    var apiUrl = '/v1/hcm/super-admin/employees-monitor';
    var allCompanies = [];
    var statusChartInstance = null;
    var trendChartInstance = null;

    /* ────────────────────────────────────────────
     * Fetch data
     * ────────────────────────────────────────────*/
    function loadData() {
        var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        var reqHeaders = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
        if (token) { reqHeaders['Authorization'] = 'Bearer ' + token; }
        fetch(apiUrl, {
            headers: reqHeaders,
            credentials: 'same-origin',
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.success) {
                    showError('Gagal memuat data: ' + (json.error?.message || 'Unknown error'));
                    return;
                }
                var d = json.data;
                allCompanies = d.companies || [];
                renderKpi(d.summary);
                renderStatusChart(d.status_breakdown);
                renderTrendChart(d.hire_trend);
                renderTable(allCompanies);
            })
            .catch(function (err) {
                showError('Gagal menghubungi server. ' + err.message);
            });
    }

    /* ────────────────────────────────────────────
     * KPI Cards
     * ────────────────────────────────────────────*/
    function renderKpi(s) {
        setText('em-stat-active-companies', s.total_active_companies + ' / ' + s.total_companies);
        setText('em-stat-total', s.total_employees.toLocaleString());
        setText('em-stat-active', s.total_active.toLocaleString());
        setText('em-stat-probation', s.total_probation.toLocaleString());
        setText('em-stat-new-hires', s.new_hires_this_month.toLocaleString());
        setText('em-stat-expiring', s.expiring_contracts_30d.toLocaleString());
        setText('em-stat-month-label', s.month_label || '');
    }

    /* ────────────────────────────────────────────
     * Status Donut Chart
     * ────────────────────────────────────────────*/
    function renderStatusChart(breakdown) {
        var el = document.getElementById('em-status-chart');
        if (!el || typeof window.ApexCharts !== 'function') return;

        var statusLabels = {
            active: 'Active', probation: 'Probation', resigned: 'Resigned',
            terminated: 'Terminated', inactive: 'Inactive', unknown: 'Unknown',
        };
        var colorMap = {
            active: '#198754', probation: '#ffc107', resigned: '#6c757d',
            terminated: '#dc3545', inactive: '#adb5bd', unknown: '#e9ecef',
        };

        var labels = Object.keys(breakdown).map(function (k) { return statusLabels[k] || k; });
        var series = Object.values(breakdown).map(Number);
        var colors = Object.keys(breakdown).map(function (k) { return colorMap[k] || '#6c757d'; });

        if (statusChartInstance) { statusChartInstance.destroy(); }

        statusChartInstance = new window.ApexCharts(el, {
            chart: { type: 'donut', height: 220, toolbar: { show: false } },
            series: series,
            labels: labels,
            colors: colors,
            legend: { position: 'bottom', fontSize: '11px' },
            dataLabels: { enabled: false },
            tooltip: { y: { formatter: function (v) { return v.toLocaleString(); } } },
            plotOptions: { pie: { donut: { size: '65%' } } },
        });
        statusChartInstance.render();
    }

    /* ────────────────────────────────────────────
     * Hire Trend Bar Chart
     * ────────────────────────────────────────────*/
    function renderTrendChart(trend) {
        var el = document.getElementById('em-hire-trend-chart');
        if (!el || typeof window.ApexCharts !== 'function') return;

        var months = trend.map(function (t) { return t.month; });
        var counts = trend.map(function (t) { return t.count; });

        if (trendChartInstance) { trendChartInstance.destroy(); }

        trendChartInstance = new window.ApexCharts(el, {
            chart: { type: 'bar', height: 220, toolbar: { show: false } },
            series: [{ name: 'New Hires', data: counts }],
            xaxis: { categories: months, labels: { style: { fontSize: '11px' } } },
            yaxis: { labels: { formatter: function (v) { return Math.round(v); } } },
            colors: ['#0d6efd'],
            dataLabels: { enabled: true },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
            tooltip: { y: { formatter: function (v) { return v + ' employees'; } } },
        });
        trendChartInstance.render();
    }

    /* ────────────────────────────────────────────
     * Company Table
     * ────────────────────────────────────────────*/
    function renderTable(companies) {
        var tbody = document.getElementById('em-company-tbody');
        var tfoot = document.getElementById('em-company-tfoot');
        if (!tbody) return;

        if (!companies || companies.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Belum ada data employee di semua tenant.</td></tr>';
            return;
        }

        var rows = companies.map(function (c) {
            var statusBadge = c.status === 'active'
                ? '<span class="badge badge-soft-success">Active</span>'
                : '<span class="badge badge-soft-secondary">' + (c.status || '-') + '</span>';

            return '<tr>'
                + '<td class="ps-3 fw-medium">' + escHtml(c.name) + '</td>'
                + '<td><code class="text-muted">' + escHtml(c.code) + '</code></td>'
                + '<td>' + statusBadge + '</td>'
                + '<td class="text-center fw-bold">' + c.total_employees + '</td>'
                + '<td class="text-center text-success">' + c.active_employees + '</td>'
                + '<td class="text-center text-warning">' + c.probation_employees + '</td>'
                + '<td class="text-center text-danger">' + c.inactive_employees + '</td>'
                + '<td class="text-center text-info">' + c.new_hires_this_month + '</td>'
                + '<td class="text-center ' + (c.expiring_contracts_30d > 0 ? 'text-danger fw-bold' : 'text-muted') + '">'
                    + (c.expiring_contracts_30d > 0 ? '⚠ ' : '') + c.expiring_contracts_30d
                + '</td>'
                + '</tr>';
        }).join('');

        tbody.innerHTML = rows;

        // Footer totals
        var totals = companies.reduce(function (acc, c) {
            acc.total += c.total_employees;
            acc.active += c.active_employees;
            acc.probation += c.probation_employees;
            acc.inactive += c.inactive_employees;
            acc.new_hires += c.new_hires_this_month;
            acc.expiring += c.expiring_contracts_30d;
            return acc;
        }, { total: 0, active: 0, probation: 0, inactive: 0, new_hires: 0, expiring: 0 });

        tfoot.innerHTML = '<tr class="table-secondary">'
            + '<td class="ps-3 fw-bold" colspan="3">Total (' + companies.length + ' tenants)</td>'
            + '<td class="text-center fw-bold">' + totals.total + '</td>'
            + '<td class="text-center fw-bold text-success">' + totals.active + '</td>'
            + '<td class="text-center fw-bold text-warning">' + totals.probation + '</td>'
            + '<td class="text-center fw-bold text-danger">' + totals.inactive + '</td>'
            + '<td class="text-center fw-bold text-info">' + totals.new_hires + '</td>'
            + '<td class="text-center fw-bold ' + (totals.expiring > 0 ? 'text-danger' : '') + '">' + totals.expiring + '</td>'
            + '</tr>';
    }

    /* ────────────────────────────────────────────
     * Search Filter
     * ────────────────────────────────────────────*/
    function initSearch() {
        var input = document.getElementById('em-search-input');
        if (!input) return;
        input.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            var filtered = allCompanies.filter(function (c) {
                return c.name.toLowerCase().includes(q) || c.code.toLowerCase().includes(q);
            });
            renderTable(filtered);
        });
    }

    /* ────────────────────────────────────────────
     * CSV Export
     * ────────────────────────────────────────────*/
    function initExport() {
        var btn = document.getElementById('btn-export-monitor');
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (!allCompanies.length) return;
            var header = 'Company,Code,Status,Total,Active,Probation,Inactive,New Hires,Expiring Contracts\n';
            var rows = allCompanies.map(function (c) {
                return [c.name, c.code, c.status, c.total_employees, c.active_employees,
                    c.probation_employees, c.inactive_employees, c.new_hires_this_month, c.expiring_contracts_30d].join(',');
            }).join('\n');
            var blob = new Blob([header + rows], { type: 'text/csv' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'global-employee-monitor.csv';
            a.click();
            URL.revokeObjectURL(a.href);
        });
    }

    /* ────────────────────────────────────────────
     * Helpers
     * ────────────────────────────────────────────*/
    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showError(msg) {
        var tbody = document.getElementById('em-company-tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">'
                + '<i class="ti ti-alert-circle me-2"></i>' + escHtml(msg) + '</td></tr>';
        }
    }

    /* ────────────────────────────────────────────
     * Init
     * ────────────────────────────────────────────*/
    document.addEventListener('DOMContentLoaded', function () {
        loadData();
        initSearch();
        initExport();

        var btnRefresh = document.getElementById('btn-refresh-monitor');
        if (btnRefresh) {
            btnRefresh.addEventListener('click', function () {
                allCompanies = [];
                var tbody = document.getElementById('em-company-tbody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4">'
                        + '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>'
                        + '<span class="ms-2">Loading data...</span></td></tr>';
                }
                loadData();
            });
        }
    });
})();
