/**
 * Package Compliance Monitor — Super Admin
 * Route: /super-admin/package-compliance
 * API:   GET /v1/hcm/super-admin/package-compliance
 */
(function () {
    'use strict';

    var apiUrl       = '/v1/hcm/super-admin/package-compliance';
    var allTenants   = [];
    var activeFilter = 'all';
    var donutChart   = null;
    var barChart     = null;

    /* ─────────────────────────────────────────
     * Fetch
     * ─────────────────────────────────────────*/
    function loadData() {
        showTableLoading();
        fetch(apiUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json.success) {
                    showTableError('Gagal memuat data: ' + ((json.error && json.error.message) || 'Unknown error'));
                    return;
                }
                allTenants = json.data.tenants || [];
                renderKpi(json.data.summary);
                renderDonut(json.data.summary);
                renderBar(allTenants);
                applyFilterAndRender();
                setText('pc-total-tenants', allTenants.length);
            })
            .catch(function (e) { showTableError('Gagal menghubungi server. ' + e.message); });
    }

    /* ─────────────────────────────────────────
     * KPI
     * ─────────────────────────────────────────*/
    function renderKpi(s) {
        setText('pc-stat-violation', s.violation);
        setText('pc-stat-warning', s.warning);
        setText('pc-stat-compliant', s.compliant);
        setText('pc-stat-unlimited', s.unlimited);
    }

    /* ─────────────────────────────────────────
     * Donut Chart — status distribution
     * ─────────────────────────────────────────*/
    function renderDonut(s) {
        var el = document.getElementById('pc-donut-chart');
        if (!el || typeof window.ApexCharts !== 'function') return;

        var labels  = ['Violation', 'Warning', 'Compliant', 'Unlimited'];
        var series  = [s.violation, s.warning, s.compliant, s.unlimited];
        var colors  = ['#dc3545', '#ffc107', '#198754', '#adb5bd'];

        if (donutChart) { donutChart.destroy(); donutChart = null; }

        // Hide no-data chart if all zeros
        if (series.every(function (v) { return v === 0; })) {
            el.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted" style="min-height:200px;">Belum ada data</div>';
            return;
        }

        donutChart = new window.ApexCharts(el, {
            chart: { type: 'donut', height: 240, toolbar: { show: false } },
            series: series,
            labels: labels,
            colors: colors,
            legend: { position: 'bottom', fontSize: '11px' },
            dataLabels: { enabled: true, formatter: function (val, opts) { return opts.w.config.series[opts.seriesIndex]; } },
            tooltip: { y: { formatter: function (v) { return v + ' tenant'; } } },
            plotOptions: { pie: { donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Total', formatter: function (w) { return w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0); } } } } } },
        });
        donutChart.render();
    }

    /* ─────────────────────────────────────────
     * Bar Chart — usage % per tenant (top 15)
     * ─────────────────────────────────────────*/
    function renderBar(tenants) {
        var el = document.getElementById('pc-bar-chart');
        if (!el || typeof window.ApexCharts !== 'function') return;

        // Only show tenants with a limit (not unlimited) capped at top 20
        var limited = tenants.filter(function (t) { return t.compliance_status !== 'unlimited'; });
        limited.sort(function (a, b) { return b.usage_pct - a.usage_pct; });
        var top = limited.slice(0, 20);

        if (barChart) { barChart.destroy(); barChart = null; }

        if (!top.length) {
            el.innerHTML = '<div class="d-flex align-items-center justify-content-center text-muted" style="min-height:200px;">Semua tenant unlimited / belum ada limit terkonfigurasi</div>';
            return;
        }

        var names   = top.map(function (t) { return t.company_code || t.company_name; });
        var usages  = top.map(function (t) { return t.usage_pct; });
        var colors  = top.map(function (t) {
            if (t.compliance_status === 'violation') return '#dc3545';
            if (t.compliance_status === 'warning')   return '#ffc107';
            return '#198754';
        });

        barChart = new window.ApexCharts(el, {
            chart: { type: 'bar', height: 240, toolbar: { show: false } },
            series: [{ name: 'Usage %', data: usages }],
            xaxis: { categories: names, labels: { style: { fontSize: '10px' }, rotate: -30 } },
            yaxis: { max: Math.max(120, Math.max.apply(null, usages) + 10), labels: { formatter: function (v) { return Math.round(v) + '%'; } } },
            colors: colors,
            dataLabels: { enabled: true, formatter: function (v) { return v + '%'; }, style: { fontSize: '10px' } },
            plotOptions: { bar: { borderRadius: 3, columnWidth: '60%', distributed: true } },
            legend: { show: false },
            annotations: { yaxis: [{ y: 80, borderColor: '#ffc107', borderWidth: 2, strokeDashArray: 4, label: { text: '80% threshold', style: { color: '#856404' } } }, { y: 100, borderColor: '#dc3545', borderWidth: 2, strokeDashArray: 4, label: { text: 'Limit', style: { color: '#842029' } } }] },
            tooltip: { y: { formatter: function (v, opts) { var t = top[opts.dataPointIndex]; return v + '% (' + (t ? t.actual + ' / ' + t.limit : '') + ' emp)'; } } },
        });
        barChart.render();
    }

    /* ─────────────────────────────────────────
     * Table
     * ─────────────────────────────────────────*/
    function applyFilterAndRender() {
        var q = (document.getElementById('pc-search-input') || {}).value || '';
        q = q.toLowerCase().trim();

        var filtered = allTenants.filter(function (t) {
            var matchFilter = activeFilter === 'all' || t.compliance_status === activeFilter;
            var matchSearch = !q || t.company_name.toLowerCase().includes(q) || (t.company_code || '').toLowerCase().includes(q) || (t.package_name || '').toLowerCase().includes(q);
            return matchFilter && matchSearch;
        });

        renderTable(filtered);
    }

    function renderTable(tenants) {
        var tbody = document.getElementById('pc-tbody');
        var tfoot = document.getElementById('pc-tfoot');
        if (!tbody) return;

        if (!tenants.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Tidak ada data sesuai filter.</td></tr>';
            if (tfoot) tfoot.innerHTML = '';
            return;
        }

        var rows = tenants.map(function (t) {
            // Compliance badge
            var badge, rowClass = '';
            if (t.compliance_status === 'violation') {
                badge = '<span class="badge bg-danger">🔴 Violation</span>';
                rowClass = 'table-danger';
            } else if (t.compliance_status === 'warning') {
                badge = '<span class="badge bg-warning text-dark">🟡 Warning</span>';
                rowClass = 'table-warning';
            } else if (t.compliance_status === 'compliant') {
                badge = '<span class="badge bg-success">🟢 OK</span>';
            } else {
                badge = '<span class="badge bg-secondary">∞ Unlimited</span>';
            }

            // Subscription status badge
            var subBadge = t.sub_status === 'active' ? '<span class="badge badge-soft-success">Active</span>'
                : t.sub_status === 'trial' ? '<span class="badge badge-soft-info">Trial</span>'
                : '<span class="badge badge-soft-danger">' + escHtml(t.sub_status) + '</span>';

            // Usage progress bar
            var pct = t.compliance_status === 'unlimited' ? null : t.usage_pct;
            var barColor = t.compliance_status === 'violation' ? 'bg-danger' : t.compliance_status === 'warning' ? 'bg-warning' : 'bg-success';
            var progressHtml = pct === null
                ? '<span class="text-muted small">∞ Unlimited</span>'
                : '<div class="d-flex align-items-center gap-1">'
                    + '<div class="progress flex-grow-1" style="height:8px;min-width:80px;">'
                    + '<div class="progress-bar ' + barColor + '" style="width:' + Math.min(pct, 100) + '%;"></div>'
                    + '</div>'
                    + '<span class="small fw-semibold" style="min-width:38px;text-align:right;">' + pct + '%</span>'
                    + '</div>';

            var excessHtml = t.excess > 0
                ? '<span class="fw-bold text-danger">+' + t.excess + '</span>'
                : '<span class="text-muted">—</span>';

            var limitHtml  = t.limit !== null ? t.limit : '<span class="text-muted">∞</span>';

            return '<tr class="' + rowClass + '">'
                + '<td class="ps-4">'
                    + '<div class="fw-semibold">' + escHtml(t.company_name) + '</div>'
                    + '<code class="text-muted" style="font-size:.75rem;">' + escHtml(t.company_code || '') + '</code>'
                + '</td>'
                + '<td>'
                    + '<div class="fw-medium">' + escHtml(t.package_name || '—') + '</div>'
                    + '<span class="text-muted" style="font-size:.75rem;">' + escHtml(t.plan_code || '') + '</span>'
                + '</td>'
                + '<td class="text-center">' + subBadge + '</td>'
                + '<td class="text-center fw-semibold">' + limitHtml + '</td>'
                + '<td class="text-center fw-semibold">' + t.actual + '</td>'
                + '<td class="text-center">' + excessHtml + '</td>'
                + '<td>' + progressHtml + '</td>'
                + '<td class="text-center">' + badge + '</td>'
                + '<td class="text-center">'
                    + '<a href="' + escHtml('/companies') + '" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:.75rem;" title="Lihat company">View</a>'
                + '</td>'
                + '</tr>';
        });

        tbody.innerHTML = rows.join('');

        // Footer totals
        var totViolation = tenants.filter(function (t) { return t.compliance_status === 'violation'; }).length;
        var totWarning   = tenants.filter(function (t) { return t.compliance_status === 'warning'; }).length;
        var totExcess    = tenants.reduce(function (a, t) { return a + (t.excess || 0); }, 0);

        if (tfoot) {
            tfoot.innerHTML = '<tr class="table-secondary fw-semibold">'
                + '<td class="ps-4" colspan="2">Menampilkan ' + tenants.length + ' tenant</td>'
                + '<td class="text-center">—</td>'
                + '<td class="text-center">—</td>'
                + '<td class="text-center">—</td>'
                + '<td class="text-center text-danger">' + (totExcess > 0 ? '+' + totExcess + ' total' : '—') + '</td>'
                + '<td>—</td>'
                + '<td class="text-center"><span class="text-danger me-1">' + totViolation + ' violation</span><span class="text-warning">' + totWarning + ' warn</span></td>'
                + '<td></td>'
                + '</tr>';
        }
    }

    /* ─────────────────────────────────────────
     * Filter buttons
     * ─────────────────────────────────────────*/
    function initFilters() {
        var group = document.getElementById('pc-filter-group');
        if (!group) return;
        group.addEventListener('click', function (e) {
            var btn = e.target.closest('.pc-filter-btn');
            if (!btn) return;
            group.querySelectorAll('.pc-filter-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeFilter = btn.dataset.filter || 'all';
            applyFilterAndRender();
        });
    }

    /* ─────────────────────────────────────────
     * Search
     * ─────────────────────────────────────────*/
    function initSearch() {
        var input = document.getElementById('pc-search-input');
        if (!input) return;
        input.addEventListener('input', function () { applyFilterAndRender(); });
    }

    /* ─────────────────────────────────────────
     * Export CSV
     * ─────────────────────────────────────────*/
    function initExport() {
        var btn = document.getElementById('btn-export-compliance');
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (!allTenants.length) return;
            var header = 'Company,Code,Package,Plan,Sub Status,Limit,Actual,Excess,Usage %,Compliance\n';
            var rows = allTenants.map(function (t) {
                return [t.company_name, t.company_code, t.package_name, t.plan_code, t.sub_status,
                    t.limit !== null ? t.limit : 'Unlimited', t.actual, t.excess, t.usage_pct + '%', t.compliance_status].join(',');
            }).join('\n');
            var blob = new Blob([header + rows], { type: 'text/csv' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'package-compliance-' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
            URL.revokeObjectURL(a.href);
        });
    }

    /* ─────────────────────────────────────────
     * Refresh button
     * ─────────────────────────────────────────*/
    function initRefresh() {
        var btn = document.getElementById('btn-refresh-compliance');
        if (!btn) return;
        btn.addEventListener('click', function () {
            allTenants = [];
            if (donutChart) { donutChart.destroy(); donutChart = null; }
            if (barChart)   { barChart.destroy();   barChart   = null; }
            loadData();
        });
    }

    /* ─────────────────────────────────────────
     * Helpers
     * ─────────────────────────────────────────*/
    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showTableLoading() {
        var tbody = document.getElementById('pc-tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5">'
                + '<div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>'
                + 'Memuat data compliance...</td></tr>';
        }
    }

    function showTableError(msg) {
        var tbody = document.getElementById('pc-tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">'
                + '<i class="ti ti-alert-circle me-2"></i>' + escHtml(msg) + '</td></tr>';
        }
    }

    /* ─────────────────────────────────────────
     * Init
     * ─────────────────────────────────────────*/
    document.addEventListener('DOMContentLoaded', function () {
        loadData();
        initFilters();
        initSearch();
        initExport();
        initRefresh();
    });
}());
