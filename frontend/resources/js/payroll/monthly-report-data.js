(function (window, document) {
    "use strict";

    var rows = [];

    function formatIdr(value) {
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(Number(value || 0));
    }

    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;");
    }

    function apiRequest(method, url) {
        if (window.axios) {
            return window.axios({ method: method, url: url, withCredentials: true, headers: { Accept: "application/json" } })
                .then(function (response) { return response.data; })
                .catch(function (error) {
                    var data = error && error.response ? error.response.data : null;
                    return Promise.reject({ status: error && error.response ? error.response.status : 0, data: data });
                });
        }

        return fetch(url, {
            method: method,
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) {
                    return Promise.reject({ status: response.status, data: data });
                }
                return data;
            });
        });
    }

    function formatApiError(error) {
        if (error && error.data && error.data.error && error.data.error.message) {
            return error.data.error.message;
        }
        return "Gagal memuat monthly report.";
    }

    function paymentBadge(status) {
        var value = String(status || "unpaid").toLowerCase();
        if (value === "paid") {
            return '<span class="badge bg-success-subtle text-success border border-success-subtle">Paid</span>';
        }
        if (value === "partial") {
            return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Partial</span>';
        }
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Unpaid</span>';
    }

    function currentParams(extra) {
        var yearEl = document.querySelector("[data-monthly-report-year]");
        var monthEl = document.querySelector("[data-monthly-report-month]");
        var params = new URLSearchParams();
        if (yearEl && yearEl.value) params.set("periodYear", String(yearEl.value));
        if (monthEl && monthEl.value) params.set("periodMonth", String(monthEl.value));
        Object.keys(extra || {}).forEach(function (key) {
            params.set(key, String(extra[key]));
        });
        return params;
    }

    function setInfo(text) {
        var el = document.querySelector("[data-monthly-report-info]");
        if (el) el.textContent = text;
    }

    function setError(message) {
        var el = document.querySelector("[data-monthly-report-error]");
        if (!el) return;
        if (message) {
            el.textContent = message;
            el.classList.remove("d-none");
        } else {
            el.textContent = "";
            el.classList.add("d-none");
        }
    }

    function renderSummary(summary) {
        var wrap = document.querySelector("[data-monthly-report-summary]");
        if (wrap) wrap.style.removeProperty("display");
        var rowsEl = document.querySelector("[data-monthly-report-total-rows]");
        var employeesEl = document.querySelector("[data-monthly-report-total-employees]");
        var periodsEl = document.querySelector("[data-monthly-report-total-periods]");
        var netEl = document.querySelector("[data-monthly-report-total-net]");
        var overtimeEl = document.querySelector("[data-monthly-report-total-overtime]");
        var monthlyEl = document.querySelector("[data-monthly-report-total-monthly]");
        var thrEl = document.querySelector("[data-monthly-report-total-thr]");
        var pkwtEl = document.querySelector("[data-monthly-report-total-pkwt]");

        if (rowsEl) rowsEl.textContent = String(summary.totalRows || 0);
        if (employeesEl) employeesEl.textContent = String(summary.totalEmployees || 0);
        if (periodsEl) periodsEl.textContent = String(summary.totalPeriods || 0);
        if (netEl) netEl.textContent = formatIdr(summary.totalNetPay || 0);
        if (overtimeEl) overtimeEl.textContent = formatIdr(summary.totalOvertimePay || 0);
        if (monthlyEl) monthlyEl.textContent = formatIdr(summary.totalsByPurpose ? summary.totalsByPurpose.monthly || 0 : 0);
        if (thrEl) thrEl.textContent = formatIdr(summary.totalsByPurpose ? summary.totalsByPurpose.thr || 0 : 0);
        if (pkwtEl) pkwtEl.textContent = formatIdr(summary.totalsByPurpose ? summary.totalsByPurpose.pkwt_compensation || 0 : 0);
    }

    function renderRows(nextRows) {
        var body = document.querySelector("[data-monthly-report-body]");
        if (!body) return;

        if (!nextRows.length) {
            body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Tidak ada data payroll untuk filter ini.</td></tr>';
            return;
        }

        body.innerHTML = nextRows.map(function (row) {
            var breakdown = row.breakdown || {};
            var monthly = breakdown.monthly || {};
            var thr = breakdown.thr || {};
            var pkwt = breakdown.pkwt_compensation || {};
            var bankLine = row.bankName ? esc(row.bankName) + ' • ' + esc(row.accountNumber || '-') : '<span class="text-danger">Bank belum lengkap</span>';

            return '<tr>' +
                '<td><span class="fw-semibold">' + esc(String(row.periodMonth).padStart(2, '0') + '/' + String(row.periodYear)) + '</span></td>' +
                '<td><div class="fw-semibold">' + esc(row.employeeName) + '</div><div class="text-muted small">' + esc(row.designation || '-') + ' • ' + esc(row.team || '-') + '</div></td>' +
                '<td><div>' + bankLine + '</div><div class="text-muted small">' + esc(row.bankBranch || '-') + '</div></td>' +
                '<td>' + paymentBadge(row.paymentStatus) + '</td>' +
                '<td class="text-end">' + formatIdr(monthly.netPay || 0) + '</td>' +
                '<td class="text-end text-info fw-semibold">' + formatIdr(monthly.overtime && monthly.overtime.amountTotal || 0) + '</td>' +
                '<td class="text-end">' + formatIdr(thr.netPay || 0) + '</td>' +
                '<td class="text-end">' + formatIdr(pkwt.netPay || 0) + '</td>' +
                '<td class="text-end fw-semibold text-primary">' + formatIdr(row.totals ? row.totals.netPay || 0 : 0) + '</td>' +
                '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-monthly-report-detail="' + esc(row.rowKey) + '"><i class="ti ti-eye me-1"></i>Detail</button></td>' +
                '</tr>';
        }).join('');
    }

    function isOvertimeLine(line) {
        return String(line && (line.componentCode || line.component_code) || '') === 'upah_lembur';
    }

    function renderLineTable(lines) {
        if (!lines || !lines.length) {
            return '<div class="text-muted small">Tidak ada line.</div>';
        }

        return '<div class="table-responsive"><table class="table table-sm align-middle mb-0">' +
            '<thead><tr><th>Komponen</th><th>Jenis</th><th class="text-end">Nominal</th></tr></thead><tbody>' +
            lines.map(function (line) {
                var overtimeBadge = isOvertimeLine(line)
                    ? '<span class="badge bg-info-subtle text-info border border-info-subtle ms-2">OT</span>'
                    : '';
                return '<tr>' +
                    '<td>' + esc(line.componentName || line.componentCode || line.component_code || '-') + overtimeBadge + '</td>' +
                    '<td>' + esc(line.kind || '-') + '</td>' +
                    '<td class="text-end">' + formatIdr(line.amount || 0) + '</td>' +
                    '</tr>';
            }).join('') +
            '</tbody></table></div>';
    }

    function renderPurposeDetail(title, detail) {
        if (!detail || !detail.runId) {
            return '';
        }

        return '<div class="card border-0 shadow-sm mb-3">' +
            '<div class="card-body">' +
            '<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">' +
            '<div><h6 class="mb-1">' + esc(title) + '</h6><div class="text-muted small">Run #' + esc(detail.runId) + ' • ' + esc(detail.paymentStatus || '-') + '</div></div>' +
            '<div class="text-end small">' +
            '<div>Gross: <strong>' + formatIdr(detail.earningsTotal || 0) + '</strong></div>' +
            '<div>Overtime: <strong class="text-info">' + formatIdr(detail.overtime && detail.overtime.amountTotal || 0) + '</strong></div>' +
            '<div>Deductions: <strong>' + formatIdr(detail.deductionsTotal || 0) + '</strong></div>' +
            '<div>Net: <strong class="text-primary">' + formatIdr(detail.netPay || 0) + '</strong></div>' +
            '</div></div>' +
            '<div class="row g-3">' +
            '<div class="col-lg-6"><div class="border rounded p-3 bg-white"><div class="fw-semibold mb-2">Earnings</div>' + renderLineTable(detail.earnings) + '</div></div>' +
            '<div class="col-lg-6"><div class="border rounded p-3 bg-white"><div class="fw-semibold mb-2">Deductions</div>' + renderLineTable(detail.deductions) + '</div></div>' +
            '</div></div></div>';
    }

    function openDetail(rowKey) {
        var row = rows.find(function (item) { return String(item.rowKey) === String(rowKey); });
        if (!row) return;

        var title = document.querySelector("[data-monthly-report-detail-title]");
        var body = document.querySelector("[data-monthly-report-detail-body]");
        if (title) {
            title.textContent = row.employeeName + ' • ' + String(row.periodMonth).padStart(2, '0') + '/' + String(row.periodYear);
        }
        if (body) {
            body.innerHTML =
                '<div class="row g-3 mb-3">' +
                '<div class="col-md-4"><div class="border rounded p-3 bg-white"><div class="text-muted small">Bank</div><div class="fw-semibold">' + esc(row.bankName || '-') + '</div><div class="small text-muted">' + esc(row.accountNumber || '-') + ' • ' + esc(row.bankBranch || '-') + '</div></div></div>' +
                '<div class="col-md-4"><div class="border rounded p-3 bg-white"><div class="text-muted small">Payment Status</div><div class="fw-semibold">' + esc(row.paymentStatus || '-') + '</div></div></div>' +
                '<div class="col-md-4"><div class="border rounded p-3 bg-white"><div class="text-muted small">Total Net</div><div class="fw-semibold text-primary">' + formatIdr(row.totals ? row.totals.netPay || 0 : 0) + '</div></div></div>' +
                '</div>' +
                renderPurposeDetail('Monthly Payroll', row.breakdown ? row.breakdown.monthly : null) +
                renderPurposeDetail('THR Run', row.breakdown ? row.breakdown.thr : null) +
                renderPurposeDetail('PKWT Compensation', row.breakdown ? row.breakdown.pkwt_compensation : null);
        }

        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('monthly_report_detail_modal')).show();
        }
    }

    function loadReport() {
        var body = document.querySelector("[data-monthly-report-body]");
        if (body) {
            body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Memuat laporan…</td></tr>';
        }
        setError('');
        setInfo('Mengambil agregasi payroll monthly, THR, dan PKWT untuk periode terpilih…');

        apiRequest('GET', '/v1/hcm/payroll/monthly-report?' + currentParams().toString())
            .then(function (response) {
                if (!response || response.success !== true || !response.data) {
                    throw { data: response };
                }
                rows = Array.isArray(response.data.rows) ? response.data.rows : [];
                renderRows(rows);
                renderSummary(response.data.summary || {});
                setInfo('Menampilkan ' + rows.length + ' baris payroll detail untuk periode terpilih.');
            })
            .catch(function (error) {
                rows = [];
                renderRows(rows);
                setError(formatApiError(error));
                setInfo('Laporan gagal dimuat.');
            });
    }

    function exportReport(format) {
        var params = currentParams({ format: format || 'xlsx' });
        var path = '/hcm/payroll/monthly-report/export?' + params.toString();
        if (window.AuthApi && typeof window.AuthApi.downloadV1Binary === 'function') {
            window.AuthApi.downloadV1Binary(path, 'monthly-report.' + (format || 'xlsx')).catch(function () {
                window.open('/v1' + path, '_blank', 'noopener');
            });
            return;
        }
        window.open('/v1' + path, '_blank', 'noopener');
    }

    document.addEventListener('click', function (event) {
        var loadTrigger = event.target && event.target.closest ? event.target.closest('[data-monthly-report-load]') : null;
        if (loadTrigger) {
            loadReport();
            return;
        }

        var exportTrigger = event.target && event.target.closest ? event.target.closest('[data-monthly-report-export]') : null;
        if (exportTrigger) {
            exportReport(exportTrigger.getAttribute('data-monthly-report-export') || 'xlsx');
            return;
        }

        var detailTrigger = event.target && event.target.closest ? event.target.closest('[data-monthly-report-detail]') : null;
        if (detailTrigger) {
            openDetail(detailTrigger.getAttribute('data-monthly-report-detail'));
        }
    });

    document.addEventListener('DOMContentLoaded', loadReport);
}(window, document));