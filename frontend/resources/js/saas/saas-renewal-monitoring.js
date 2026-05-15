(function (window, document) {
    "use strict";

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function setText(element, value) {
        if (!element) return;
        element.textContent = String(value == null ? "" : value);
    }

    function show(element) {
        if (!element) return;
        element.classList.remove("d-none");
    }

    function hide(element) {
        if (!element) return;
        element.classList.add("d-none");
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function badge(text, kind) {
        var cls = "badge bg-secondary-subtle text-secondary";
        if (kind === "success") cls = "badge bg-success-subtle text-success";
        if (kind === "warning") cls = "badge bg-warning-subtle text-warning";
        if (kind === "danger") cls = "badge bg-danger-subtle text-danger";
        if (kind === "info") cls = "badge bg-info-subtle text-info";
        return '<span class="' + cls + '">' + escapeHtml(text) + '</span>';
    }

    function normalizeLabel(value) {
        return String(value || "")
            .replace(/_/g, " ")
            .replace(/\b\w/g, function (segment) {
                return segment.toUpperCase();
            });
    }

    function formatDate(value) {
        if (!value) return "-";
        var date = new Date(value);
        if (Number.isNaN(date.getTime())) return String(value);
        return new Intl.DateTimeFormat("id-ID", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            timeZone: "Asia/Jakarta",
        }).format(date);
    }

    function formatDateTime(value) {
        if (!value) return "-";
        var date = new Date(value);
        if (Number.isNaN(date.getTime())) return String(value);
        return new Intl.DateTimeFormat("id-ID", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false,
            timeZone: "Asia/Jakarta",
        }).format(date) + " WIB";
    }

    function formatMoney(value) {
        if (value == null || value === "") return "-";
        var amount = Number(value);
        if (Number.isNaN(amount)) return String(value);
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(amount);
    }

    function statusBadge(status, isPaid) {
        var normalized = String(status || "").toLowerCase();
        if (isPaid || normalized === "paid") return badge("Paid", "success");
        if (normalized === "pending") return badge("Pending", "warning");
        if (normalized === "failed") return badge("Failed", "danger");
        if (normalized === "grace_period") return badge("Grace Period", "warning");
        if (normalized === "inactive") return badge("Inactive", "danger");
        if (normalized === "suspended") return badge("Suspended", "danger");
        return badge(normalizeLabel(normalized || "unknown"), "secondary");
    }

    function formatApiError(err) {
        var response = err && err.response ? err.response : null;
        var status = Number(response && response.status ? response.status : 0);
        var data = response && response.data ? response.data : null;

        if (status === 422) {
            var details = [];

            if (data && data.error && Array.isArray(data.error.details)) {
                details = data.error.details.map(function (item) {
                    return String(item);
                }).filter(Boolean);
            } else if (data && data.errors && typeof data.errors === "object") {
                Object.keys(data.errors).forEach(function (field) {
                    var messages = data.errors[field];
                    if (!Array.isArray(messages) || !messages.length) return;
                    details.push(String(field) + ": " + String(messages[0]));
                });
            }

            if (details.length) {
                return "Validation failed: " + details.join(" | ");
            }

            if (data && data.error && data.error.message) {
                return String(data.error.message);
            }

            return "Validation failed. Please check filter values.";
        }

        try {
            if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
                return window.ApiErrorHelper.format(err);
            }
        } catch (_err) {}

        data = err && err.response && err.response.data ? err.response.data : data;
        if (data && data.error && data.error.message) {
            return String(data.error.message);
        }

        return "Request failed.";
    }

    function request(method, path, payload) {
        if (!window.AuthApi || typeof window.AuthApi.request !== "function") {
            return Promise.reject(new Error("AuthApi missing"));
        }

        return window.AuthApi.request(method, path, payload).then(function (response) {
            return response && response.data ? response.data : {};
        });
    }

    function loadWithAuthCheck(callback) {
        if (!window.AuthApi || typeof window.AuthApi.request !== "function") {
            window.setTimeout(function () {
                loadWithAuthCheck(callback);
            }, 100);
            return;
        }

        callback();
    }

    function initPage() {
        var root = qs("[data-saas-renewal-monitoring-page]");
        if (!root) return false;

        var inputDays = qs("[data-renewal-days]", root);
        var inputStatus = qs("[data-renewal-status]", root);
        var inputReason = qs("[data-renewal-reason]", root);
        var inputCompanyId = qs("[data-renewal-company-id]", root);
        var buttonRefresh = qs("[data-renewal-refresh]", root);
        var buttonReset = qs("[data-renewal-reset]", root);
        var buttonPrev = qs("[data-renewal-prev]", root);
        var buttonNext = qs("[data-renewal-next]", root);
        var errorBox = qs("[data-renewal-error]", root);
        var recordsBody = qs("[data-renewal-records-body]", root);
        var anomaliesList = qs("[data-renewal-anomalies-list]", root);
        var detailPanel = qs("[data-renewal-detail-panel]", root);
        var detailKey = qs("[data-renewal-detail-key]", root);
        var recordsPageInfo = qs("[data-renewal-records-page-info]", root);
        var recordsPagination = qs("[data-renewal-records-pagination]", root);

        var state = {
            page: 1,
            lastPage: 1,
        };

        function queryParams() {
            var params = {
                days: Number(inputDays ? inputDays.value : 30),
                page: state.page,
                per_page: 20,
            };

            if (inputStatus && inputStatus.value) params.status = inputStatus.value;
            if (inputReason && String(inputReason.value || "").trim()) params.reason_code = String(inputReason.value).trim();
            if (inputCompanyId && String(inputCompanyId.value || "").trim()) params.company_id = Number(inputCompanyId.value);

            return params;
        }

        function renderSummary(summary) {
            setText(qs("[data-renewal-summary-total]", root), summary.totalRecords || 0);
            setText(qs("[data-renewal-summary-paid]", root), summary.paid || 0);
            setText(qs("[data-renewal-summary-retrying]", root), summary.retrying || 0);
            setText(qs("[data-renewal-summary-grace]", root), summary.gracePeriod || 0);
            setText(qs("[data-renewal-summary-inactive]", root), summary.inactive || 0);
            setText(qs("[data-renewal-summary-anomalies]", root), summary.anomalies || 0);
        }

        function renderRecords(rows, pagination) {
            if (!recordsBody) return;

            if (!rows || !rows.length) {
                recordsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada record renewal untuk filter ini.</td></tr>';
            } else {
                recordsBody.innerHTML = rows.map(function (row) {
                    return '<tr>' +
                        '<td><div class="fw-semibold">' + escapeHtml(row.renewalPeriodKey || '-') + '</div><div class="text-muted small">Invoice ' + escapeHtml((row.invoice && row.invoice.number) || '-') + '</div></td>' +
                        '<td><div class="fw-semibold">' + escapeHtml((row.company && row.company.name) || '-') + '</div><div class="text-muted small">' + escapeHtml((row.company && row.company.code) || '-') + '</div></td>' +
                        '<td>' + statusBadge(row.subscription && row.subscription.status, row.invoice && row.invoice.isPaid) + '<div class="text-muted small mt-1">' + escapeHtml(normalizeLabel((row.subscription && row.subscription.billingCycle) || '-')) + '</div></td>' +
                        '<td><div class="fw-semibold">' + escapeHtml((row.reason && row.reason.code) || '-') + '</div><div class="text-muted small">' + escapeHtml((row.reason && row.reason.message) || '-') + '</div></td>' +
                        '<td class="text-end"><button type="button" class="btn btn-outline-secondary btn-sm" data-renewal-detail-trigger="' + escapeHtml(row.renewalPeriodKey || '') + '"><i class="ti ti-eye"></i> Detail</button></td>' +
                        '</tr>';
                }).join('');
            }

            state.lastPage = Number((pagination && pagination.last_page) || 1);
            state.page = Number((pagination && pagination.current_page) || state.page);
            if (buttonPrev) buttonPrev.disabled = state.page <= 1;
            if (buttonNext) buttonNext.disabled = state.page >= state.lastPage;

            setText(recordsPageInfo, 'Halaman ' + state.page + ' / ' + state.lastPage);
            setText(recordsPagination, 'Total ' + Number((pagination && pagination.total) || 0) + ' record');
        }

        function renderAnomalies(rows) {
            if (!anomaliesList) return;

            if (!rows || !rows.length) {
                anomaliesList.innerHTML = '<div class="list-group-item text-muted text-center py-4">Tidak ada anomali aktif.</div>';
                return;
            }

            anomaliesList.innerHTML = rows.map(function (row) {
                return '<button type="button" class="list-group-item list-group-item-action" data-renewal-detail-trigger="' + escapeHtml(row.renewalPeriodKey || '') + '">' +
                    '<div class="d-flex justify-content-between align-items-start gap-2"><div><div class="fw-semibold">' + escapeHtml((row.company && row.company.name) || '-') + '</div><div class="text-muted small">' + escapeHtml(row.reasonCode || '-') + '</div></div>' + badge(row.isPaid ? 'Paid' : 'Open', row.isPaid ? 'success' : 'danger') + '</div>' +
                    '<div class="text-muted small mt-2">' + escapeHtml(row.reasonMessage || '-') + '</div>' +
                    '<div class="text-muted small mt-2">Invoice: ' + escapeHtml(formatDate(row.issueDate)) + ' • Due: ' + escapeHtml(formatDate(row.dueDate)) + '</div>' +
                    '</button>';
            }).join('');
        }

        function renderDetail(data) {
            if (!detailPanel) return;

            setText(detailKey, data.renewalPeriodKey || 'Tidak diketahui');
            var timeline = Array.isArray(data.timeline) ? data.timeline : [];

            detailPanel.innerHTML =
                '<div class="mb-3">' +
                '<div class="fw-semibold">' + escapeHtml((data.company && data.company.name) || '-') + '</div>' +
                '<div class="text-muted small">' + escapeHtml((data.company && data.company.code) || '-') + ' • Subscription ' + escapeHtml((data.subscription && data.subscription.status) || '-') + '</div>' +
                '<div class="text-muted small mt-1">Invoice ' + escapeHtml((data.invoice && data.invoice.number) || '-') + ' • ' + escapeHtml(formatMoney(data.invoice && data.invoice.amountDue)) + '</div>' +
                '<div class="mt-2">' + badge((data.reason && data.reason.code) || 'NO_REASON', data.invoice && data.invoice.isPaid ? 'success' : 'warning') + '</div>' +
                '<div class="text-muted small mt-2">' + escapeHtml((data.reason && data.reason.message) || '-') + '</div>' +
                '</div>' +
                '<div class="border rounded-2">' +
                (timeline.length ? timeline.map(function (item) {
                    return '<div class="p-3 border-bottom">' +
                        '<div class="d-flex justify-content-between align-items-start gap-2"><div class="fw-semibold">' + escapeHtml(normalizeLabel(item.event_type || '-')) + '</div><div class="text-muted small">' + escapeHtml(formatDateTime(item.occurred_at)) + '</div></div>' +
                        '<div class="text-muted small mt-1">' + escapeHtml(item.reason_code || '-') + '</div>' +
                        '<div class="small mt-1">' + escapeHtml(item.reason_message || '-') + '</div>' +
                        '</div>';
                }).join('') : '<div class="p-3 text-center text-muted">Belum ada timeline untuk renewal ini.</div>') +
                '</div>';
        }

        function fetchDetail(renewalPeriodKey) {
            if (!renewalPeriodKey) return Promise.resolve();
            return request('get', '/saas/renewal-monitoring/records/' + encodeURIComponent(String(renewalPeriodKey)), {})
                .then(function (data) {
                    if (!data.success) throw { response: { data: data, status: 200 } };
                    renderDetail(data.data || {});
                });
        }

        function loadAll() {
            hide(errorBox);
            if (recordsBody) recordsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>';
            if (anomaliesList) anomaliesList.innerHTML = '<div class="list-group-item text-muted text-center py-4">Memuat data...</div>';

            var params = queryParams();
            return Promise.all([
                request('get', '/saas/renewal-monitoring/summary', { days: params.days }),
                request('get', '/saas/renewal-monitoring/records', params),
                request('get', '/saas/renewal-monitoring/anomalies', { days: params.days, page: 1, per_page: 10 }),
            ]).then(function (responses) {
                var summaryData = responses[0];
                var recordsData = responses[1];
                var anomaliesData = responses[2];

                if (!summaryData.success || !recordsData.success || !anomaliesData.success) {
                    throw { response: { data: { error: { message: 'Renewal monitoring response invalid.' } }, status: 200 } };
                }

                renderSummary((summaryData.data && summaryData.data.summary) || {});
                renderRecords(recordsData.data || [], recordsData.pagination || {});
                renderAnomalies(anomaliesData.data || []);
            }).catch(function (err) {
                show(errorBox);
                setText(errorBox, formatApiError(err));
                if (recordsBody) recordsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Gagal memuat data.</td></tr>';
                if (anomaliesList) anomaliesList.innerHTML = '<div class="list-group-item text-muted text-center py-4">Gagal memuat data.</div>';
            });
        }

        function resetFilters() {
            if (inputDays) inputDays.value = '30';
            if (inputStatus) inputStatus.value = '';
            if (inputReason) inputReason.value = '';
            if (inputCompanyId) inputCompanyId.value = '';
            state.page = 1;
            loadAll();
        }

        if (buttonRefresh) {
            buttonRefresh.addEventListener('click', function () {
                state.page = 1;
                loadAll();
            });
        }

        if (buttonReset) {
            buttonReset.addEventListener('click', function () {
                resetFilters();
            });
        }

        [inputDays, inputStatus].forEach(function (element) {
            if (!element) return;
            element.addEventListener('change', function () {
                state.page = 1;
                loadAll();
            });
        });

        [inputReason, inputCompanyId].forEach(function (element) {
            if (!element) return;
            var timer = null;
            element.addEventListener('input', function () {
                if (timer) window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    state.page = 1;
                    loadAll();
                }, 350);
            });
        });

        if (buttonPrev) {
            buttonPrev.addEventListener('click', function () {
                if (state.page <= 1) return;
                state.page -= 1;
                loadAll();
            });
        }

        if (buttonNext) {
            buttonNext.addEventListener('click', function () {
                if (state.page >= state.lastPage) return;
                state.page += 1;
                loadAll();
            });
        }

        root.addEventListener('click', function (event) {
            var target = event && event.target ? event.target : null;
            var trigger = target && target.closest ? target.closest('[data-renewal-detail-trigger]') : null;
            if (!trigger) return;
            var renewalPeriodKey = trigger.getAttribute('data-renewal-detail-trigger');
            fetchDetail(renewalPeriodKey).catch(function (err) {
                show(errorBox);
                setText(errorBox, formatApiError(err));
            });
        });

        loadWithAuthCheck(function () {
            loadAll();
        });

        return true;
    }

    var api = {
        init: initPage,
    };

    window.SaaSRenewalMonitoring = api;

    if (!window.__ARCAV_DISABLE_AUTOINIT__) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                api.init();
            }, { once: true });
        } else {
            api.init();
        }
    }
})(window, document);