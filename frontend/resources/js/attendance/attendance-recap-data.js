(function () {
    'use strict';

    var PERIOD_URLS = {
        weekly: '/v1/hcm/attendance/recap?period=weekly',
        monthly: '/v1/hcm/attendance/recap?period=monthly',
        yearly: '/v1/hcm/attendance/recap?period=yearly',
    };

    var MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    var DAY_NAMES = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    // ── API helper ────────────────────────────────────────────────────

    function apiGet(url) {
        var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        var headers = { Accept: 'application/json' };
        if (token) headers.Authorization = 'Bearer ' + token;
        return fetch(url, { method: 'GET', credentials: 'same-origin', headers: headers }).then(function (res) {
            return res.json();
        });
    }

    // ── Format helpers ────────────────────────────────────────────────

    function esc(v) {
        return String(v || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatTanggal(dateStr) {
        if (!dateStr) return '—';
        var parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        var dayName = DAY_NAMES[d.getDay()] || '';
        var dateNum = parseInt(parts[2], 10);
        var monthName = MONTH_NAMES[parseInt(parts[1], 10) - 1] || parts[1];
        return dayName + ', ' + dateNum + ' ' + monthName;
    }

    function getPeriodLabel(period) {
        var map = { weekly: 'Minggu Ini', monthly: 'Bulan Ini', yearly: 'Tahun Ini' };
        return map[period] || period;
    }

    // ── DOM helpers ───────────────────────────────────────────────────

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    // ── Render ────────────────────────────────────────────────────────

    function render(data, period) {
        var card = $('[data-attendance-recap-card]');
        if (!card) return;

        var loadingEl = $('[data-attendance-recap-loading]', card);
        var errorEl = $('[data-attendance-recap-error]', card);
        var emptyEl = $('[data-attendance-recap-empty]', card);
        var summaryEl = $('[data-attendance-recap-summary]', card);
        var tableWrap = $('[data-attendance-recap-table]', card);
        var tbody = $('[data-attendance-recap-tbody]', card);

        // Hide all
        [loadingEl, errorEl, emptyEl, summaryEl, tableWrap].forEach(function (el) {
            if (el) el.classList.add('d-none');
        });

        // Handle error
        if (!data || data.success !== true) {
            if (errorEl) {
                errorEl.classList.remove('d-none');
                var msgEl = $('[data-attendance-recap-error-msg]', errorEl);
                if (msgEl) msgEl.textContent = (data && data.error && data.error.message)
                    || 'Gagal memuat rekap absensi untuk ' + getPeriodLabel(period) + '.';
            }
            return;
        }

        var payload = data.data || {};
        var items = Array.isArray(payload.items) ? payload.items : [];
        var meta = payload.meta || {};

        // Empty state
        if (!items.length) {
            if (emptyEl) emptyEl.classList.remove('d-none');
            return;
        }

        // ── Summary cards ──
        if (summaryEl) {
            summaryEl.classList.remove('d-none');
            var totalEl = $('[data-recap-total-employees]');
            var presentEl = $('[data-recap-total-present]');
            var absentEl = $('[data-recap-total-absent]');
            var rateEl = $('[data-recap-attendance-rate]');
            if (totalEl) totalEl.textContent = String(meta.totalEmployees ?? items.length);
            if (presentEl) presentEl.textContent = String(meta.totalPresent ?? 0);
            if (absentEl) absentEl.textContent = String(meta.totalAbsent ?? 0);
            if (rateEl) rateEl.textContent = (meta.attendanceRate ?? 0) + '%';
        }

        // ── Table ──
        if (tableWrap && tbody) {
            tableWrap.classList.remove('d-none');
            tbody.innerHTML = items.map(function (item) {
                var name = item.fullName || item.name || '—';
                var totalBolos = item.totalAbsent ?? item.absentCount ?? 0;
                var totalHadir = item.totalPresent ?? item.presentCount ?? 0;
                var totalHari = item.totalDays ?? (totalBolos + totalHadir);
                var kehadiran = totalHari > 0 ? Math.round((totalHadir / totalHari) * 100) : 0;

                // Absent dates badges
                var rincian = '—';
                if (Array.isArray(item.absentDates) && item.absentDates.length) {
                    rincian = item.absentDates.map(function (d) {
                        return '<span class="badge bg-soft-danger text-danger me-1 mb-1 d-inline-block" title="' + esc(formatTanggal(d)) + '">'
                            + esc(d) + '</span>';
                    }).join('');
                }

                // Progress bar color
                var barColor = kehadiran >= 80 ? 'success' : kehadiran >= 60 ? 'warning' : 'danger';
                var textColor = kehadiran >= 80 ? 'text-success' : kehadiran >= 60 ? 'text-warning' : 'text-danger';

                return '<tr class="align-middle">'
                    + '<td><div class="d-flex align-items-center"><div class="avatar avatar-sm bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center me-2 fw-semibold fs-12">' + esc((name.charAt(0) || '?').toUpperCase()) + '</div><span class="fw-medium">' + esc(name) + '</span></div></td>'
                    + '<td class="text-center"><span class="fw-semibold ' + (totalBolos > 0 ? 'text-danger' : 'text-muted') + '">' + totalBolos + '</span></td>'
                    + '<td class="text-center"><span class="fw-semibold text-success">' + totalHadir + '</span></td>'
                    + '<td class="text-center"><span class="text-muted">' + totalHari + '</span></td>'
                    + '<td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px;max-width:120px"><div class="progress-bar bg-' + barColor + '" role="progressbar" style="width:' + kehadiran + '%" aria-valuenow="' + kehadiran + '" aria-valuemin="0" aria-valuemax="100"></div></div><small class="' + textColor + ' fw-medium">' + kehadiran + '%</small></div></td>'
                    + '<td class="text-wrap" style="max-width:260px">' + rincian + '</td>'
                    + '</tr>';
            }).join('');
        }
    }

    // ── Load data ─────────────────────────────────────────────────────

    function loadRecap(period) {
        var card = $('[data-attendance-recap-card]');
        if (!card) return;

        var loadingEl = $('[data-attendance-recap-loading]', card);
        var errorEl = $('[data-attendance-recap-error]', card);
        var emptyEl = $('[data-attendance-recap-empty]', card);
        var summaryEl = $('[data-attendance-recap-summary]', card);
        var tableWrap = $('[data-attendance-recap-table]', card);

        // Hide all except loading
        [errorEl, emptyEl, summaryEl, tableWrap].forEach(function (el) {
            if (el) el.classList.add('d-none');
        });
        if (loadingEl) loadingEl.classList.remove('d-none');

        var url = PERIOD_URLS[period] || PERIOD_URLS.weekly;

        apiGet(url).then(function (data) {
            render(data, period);
        }).catch(function () {
            if (loadingEl) loadingEl.classList.add('d-none');
            if (errorEl) {
                errorEl.classList.remove('d-none');
                var msgEl = $('[data-attendance-recap-error-msg]', errorEl);
                if (msgEl) msgEl.textContent = 'Gagal memuat rekap absensi. Silakan coba lagi.';
            }
        });
    }

    // ── Init ──────────────────────────────────────────────────────────

    function init() {
        var card = $('[data-attendance-recap-card]');
        if (!card) return;

        var periodBtns = $$('[data-attendance-recap-period] button[data-period]', card);
        var refreshBtn = $('[data-attendance-recap-load]', card);
        var retryBtn = $('[data-attendance-recap-retry]', card);

        function getPeriod() {
            var active = $('[data-attendance-recap-period] button.btn-primary', card);
            return active ? active.getAttribute('data-period') : 'weekly';
        }

        function setActivePeriod(period) {
            periodBtns.forEach(function (btn) {
                var isActive = btn.getAttribute('data-period') === period;
                btn.className = 'btn btn-sm ' + (isActive ? 'btn-primary' : 'btn-outline-primary');
            });
        }

        // Period buttons
        periodBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var period = this.getAttribute('data-period');
                setActivePeriod(period);
                loadRecap(period);
            });
        });

        // Refresh button
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () { loadRecap(getPeriod()); });
        }

        // Retry button (in error alert)
        if (retryBtn) {
            retryBtn.addEventListener('click', function () { loadRecap(getPeriod()); });
        }

        // Initial load
        loadRecap('weekly');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
