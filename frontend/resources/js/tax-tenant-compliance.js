/**
 * tax-tenant-compliance.js
 * Phase 5 – Tenant Compliance Summary Screen
 * Renders compliance checklist, policy snapshot,
 * and change history from the tenant-self-audit API.
 */
(function (window, document) {
    'use strict';

    /* ------------------------------------------------------------------ */
    /* API helpers                                                         */
    /* ------------------------------------------------------------------ */
    function apiRequest(method, path, data) {
        if (!window.AuthApi || typeof window.AuthApi.request !== 'function') {
            return Promise.reject(new Error('AuthApi not available'));
        }
        return window.AuthApi.request(method, path, data);
    }
    function apiGet(path, params) { return apiRequest('GET', path, params); }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */
    function q(sel) { return document.querySelector(sel); }
    function setText(sel, text) {
        var el = q(sel);
        if (el) { el.textContent = text; }
    }
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDate(val) {
        if (!val) { return '-'; }
        try { return new Date(val).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }); }
        catch (e) { return String(val); }
    }

    /* ------------------------------------------------------------------ */
    /* Checklist rendering                                                 */
    /* ------------------------------------------------------------------ */
    var CHECKS = [
        { iconAttr: '[data-compliance-check-icon-policy]',  labelAttr: '[data-compliance-check-label-policy]',  key: 'has_published_policy',        pass: 'Kebijakan PPh 21 sudah diterbitkan',               fail: 'Belum ada kebijakan yang diterbitkan'              },
        { iconAttr: '[data-compliance-check-icon-recent]',  labelAttr: '[data-compliance-check-label-recent]',  key: 'has_recent_publication',       pass: 'Diterbitkan dalam 90 hari terakhir — tidak kedaluwarsa', fail: 'Publikasi terakhir lebih dari 90 hari yang lalu'   },
        { iconAttr: '[data-compliance-check-icon-payroll]', labelAttr: '[data-compliance-check-label-payroll]', key: 'all_payroll_runs_covered',      pass: 'Semua proses payroll terhubung ke kebijakan aktif', fail: 'Ada proses payroll yang belum menggunakan kebijakan' },
        { iconAttr: '[data-compliance-check-icon-anomaly]', labelAttr: '[data-compliance-check-label-anomaly]', key: 'no_unresolved_anomalies',       pass: 'Tidak ada anomali yang belum diselesaikan',        fail: 'Ada anomali terbuka yang perlu ditindaklanjuti'    },
    ];

    function renderChecklist(checklist) {
        CHECKS.forEach(function (check) {
            var passed = checklist && checklist[check.key];
            var iconEl  = q(check.iconAttr);
            var labelEl = q(check.labelAttr);
            if (iconEl) {
                iconEl.textContent = passed ? '\u2705' : '\u274C';
                iconEl.closest('[role="status"]') && iconEl.closest('[role="status"]').setAttribute(
                    'aria-label', 'Status: ' + (passed ? 'LULUS' : 'GAGAL') + ' - ' + (passed ? check.pass : check.fail)
                );
            }
            if (labelEl) {
                labelEl.textContent = passed ? check.pass : check.fail;
                labelEl.className = 'small ' + (passed ? 'text-success' : 'text-danger');
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Policy snapshot                                                     */
    /* ------------------------------------------------------------------ */
    function renderPolicySnapshot(snapshot) {
        if (!snapshot) { return; }

        var policySummary = snapshot.policy_summary || {};
        var code = snapshot.policy_code || snapshot.policyCode || policySummary.policy_code;
        var name = snapshot.name || policySummary.name;
        var version = snapshot.version || snapshot.revision || snapshot.current_published_version;
        var effectiveDate = snapshot.effective_start_date || snapshot.effectiveStartDate || snapshot.effective_date;
        var coveredPayrollRuns = snapshot.covered_payroll_runs !== undefined
            ? snapshot.covered_payroll_runs
            : snapshot.payroll_runs_covered;

        setText('[data-compliance-policy-code]', code || '-');
        setText('[data-compliance-policy-name]', name || '-');
        setText('[data-compliance-policy-version]', version || '-');
        setText('[data-compliance-policy-effective]', formatDate(effectiveDate));
        setText('[data-compliance-payroll-runs]', coveredPayrollRuns !== undefined ? String(coveredPayrollRuns) : '-');
    }

    /* ------------------------------------------------------------------ */
    /* Change history                                                      */
    /* ------------------------------------------------------------------ */
    function renderHistory(history, periodLabel) {
        var tbody = q('[data-compliance-history-tbody]');
        var periodEl = q('[data-compliance-change-period]');
        if (periodEl) { periodEl.textContent = periodLabel || ''; }
        if (!tbody) { return; }
        if (!history || history.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada perubahan kebijakan pada periode ini.</td></tr>';
            return;
        }
        var MAX_ROWS = 50;
        var displayed = history.slice(0, MAX_ROWS);
        var html = displayed.length < history.length
            ? '<tr><td colspan="4" class="text-center text-muted small py-2">Menampilkan ' + MAX_ROWS + ' event terbaru. Gunakan filter periode lebih sempit untuk detail lebih lanjut.</td></tr>'
            : '';
        displayed.forEach(function (row) {
            html += '<tr>'
                + '<td>' + escHtml(formatDate(row.occurred_at || row.created_at || row.timestamp || '')) + '</td>'
                + '<td><span class="badge bg-secondary-subtle text-secondary">' + escHtml(row.event_type || row.action || '-') + '</span></td>'
                + '<td>' + escHtml(row.actor_name || row.performed_by || row.created_by || '-') + '</td>'
                + '<td class="text-muted small">' + escHtml(row.summary || row.notes || row.note || row.change_summary || '-') + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
    }

    /* ------------------------------------------------------------------ */
    /* Period helpers                                                      */
    /* ------------------------------------------------------------------ */
    function getDefaultPeriod() {
        var now   = new Date();
        var start = new Date(now.getFullYear(), now.getMonth() - 2, 1);
        return {
            start: start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0'),
            end:   now.getFullYear()   + '-' + String(now.getMonth() + 1).padStart(2, '0'),
        };
    }

    function monthToApiDate(monthStr, isEnd) {
        if (!monthStr) { return ''; }
        var parts = monthStr.split('-');
        var year  = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        if (isEnd) {
            var lastDay = new Date(year, month, 0).getDate();
            return year + '-' + String(month).padStart(2, '0') + '-' + lastDay;
        }
        return year + '-' + String(month).padStart(2, '0') + '-01';
    }

    /* ------------------------------------------------------------------ */
    /* Load compliance data                                                */
    /* ------------------------------------------------------------------ */
    function loadCompliance() {
        var startEl = q('[data-compliance-period-start]');
        var endEl   = q('[data-compliance-period-end]');
        var errBox  = q('[data-compliance-error]');
        var exportLink = q('[data-compliance-export-pdf]');

        if (errBox) { errBox.classList.add('d-none'); errBox.textContent = ''; }

        var startMonth = startEl ? startEl.value : '';
        var endMonth   = endEl   ? endEl.value   : '';

        var params = {};
        if (startMonth) { params.period_start = monthToApiDate(startMonth, false); }
        if (endMonth)   { params.period_end   = monthToApiDate(endMonth, true); }

        // Reset checklist icons to loading state
        CHECKS.forEach(function (c) {
            var icon  = q(c.iconAttr);
            var label = q(c.labelAttr);
            if (icon)  { icon.textContent  = '\u23F3'; }
            if (label) { label.textContent = 'Memeriksa...'; label.className = 'small text-muted'; }
        });

        apiGet('/hcm/tax-governance/reports/tenant-self-audit', params)
            .then(function (res) {
                var data = res && res.data ? res.data : res;
                // Unwrap nested Laravel API envelope: { success, data: { ... } }
                if (data && data.data && typeof data.data === 'object') { data = data.data; }
                renderChecklist(data.compliance_checklist || data.checklist || {});
                renderPolicySnapshot(data.policy_snapshot || data.policySnapshot || null);
                renderHistory(
                    data.change_history || data.changeHistory || [],
                    startMonth && endMonth ? startMonth + ' – ' + endMonth : ''
                );

                // Update export link with period params
                if (exportLink && startMonth && endMonth) {
                    var qs = '?period_start=' + encodeURIComponent(monthToApiDate(startMonth, false))
                           + '&period_end='   + encodeURIComponent(monthToApiDate(endMonth, true));
                    exportLink.href = '/hcm/tax-governance/reports/tenant-self-audit-export' + qs;
                }
            })
            .catch(function (err) {
                var msg = (err && err.message) ? err.message : 'Gagal memuat data kepatuhan.';
                if (errBox) {
                    errBox.textContent = msg;
                    errBox.classList.remove('d-none');
                }
                CHECKS.forEach(function (c) {
                    var icon  = q(c.iconAttr);
                    var label = q(c.labelAttr);
                    if (icon)  { icon.textContent  = '\u2753'; }
                    if (label) { label.textContent = 'Error memuat data'; label.className = 'small text-danger'; }
                });
            });
    }

    /* ------------------------------------------------------------------ */
    /* Init                                                                */
    /* ------------------------------------------------------------------ */
    function init() {
        // Only run on tenant-compliance screen
        if (!q('[data-compliance-checklist-area]')) { return; }

        // Set default period values
        var defaults  = getDefaultPeriod();
        var startEl   = q('[data-compliance-period-start]');
        var endEl     = q('[data-compliance-period-end]');
        if (startEl && !startEl.value) { startEl.value = defaults.start; }
        if (endEl   && !endEl.value)   { endEl.value   = defaults.end;   }

        // Auto-load on mount
        loadCompliance();

        // Refresh button
        var refreshBtn = q('[data-compliance-refresh]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', loadCompliance);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
