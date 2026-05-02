<?php $page = 'saas-reports'; ?>
@extends('layout.mainlayout')

@section('content')
<div class="page-wrapper">
    <div class="content" data-saas-reports-page>
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">SaaS Reports</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('dashboard')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">SaaS</li>
                        <li class="breadcrumb-item active" aria-current="page">Reports</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <button class="btn btn-primary" id="btn_refresh_reports">
                        <i class="ti ti-refresh me-2"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="report-type" class="form-label">Report Type</label>
                            <select id="report-type" class="form-select">
                                <option value="revenue">Revenue Report</option>
                                <option value="aging">Aging Report</option>
                                <option value="churn">Churn Report</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="period-filter" class="form-label">Period</label>
                            <select id="period-filter" class="form-select">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div class="alert alert-danger d-none mb-0" id="reports_error_box"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Rows</p>
                    <h4 class="mb-0" id="reports_total_rows">0</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Primary Metric</p>
                    <h4 class="mb-0" id="reports_primary_metric">-</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Primary Value</p>
                    <h4 class="mb-0" id="reports_primary_value">-</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr id="reports_table_head">
                            <th>Metric</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody id="reports_table_body">
                        <tr>
                            <td colspan="2" class="text-center text-muted py-4">Loading report data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

@push('scripts')
<script>
(function (window, document) {
    "use strict";

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function show(el) {
        if (el) {
            el.classList.remove("d-none");
        }
    }

    function hide(el) {
        if (el) {
            el.classList.add("d-none");
        }
    }

    function getTenantContext() {
        if (window.AuthApi && typeof window.AuthApi.getTenantContext === "function") {
            return window.AuthApi.getTenantContext() || {};
        }
        return {};
    }

    function buildHeaders() {
        var headers = { Accept: "application/json" };
        var token = window.AuthApi && typeof window.AuthApi.getToken === "function"
            ? window.AuthApi.getToken()
            : null;
        if (token) {
            headers.Authorization = "Bearer " + token;
        }
        var tenant = getTenantContext();
        if (tenant.companyCode) headers["X-Company-Code"] = tenant.companyCode;
        if (tenant.companyId) headers["X-Company-Id"] = String(tenant.companyId);
        if (tenant.companyUuid) headers["X-Company-UUID"] = String(tenant.companyUuid);
        return headers;
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatValue(value) {
        if (typeof value === "number" && isFinite(value)) {
            return value.toLocaleString("id-ID");
        }
        if (value === null || value === undefined || value === "") {
            return "-";
        }
        return String(value);
    }

    function endpointFor(reportType) {
        if (reportType === "aging") return "/v1/saas/reports/aging";
        if (reportType === "churn") return "/v1/saas/reports/churn";
        return "/v1/saas/reports/revenue";
    }

    function withQuery(url, query) {
        var pairs = [];
        Object.keys(query || {}).forEach(function (k) {
            if (query[k] === null || query[k] === undefined || query[k] === "") {
                return;
            }
            pairs.push(encodeURIComponent(k) + "=" + encodeURIComponent(String(query[k])));
        });
        if (!pairs.length) {
            return url;
        }
        return url + (url.indexOf("?") === -1 ? "?" : "&") + pairs.join("&");
    }

    function request(path, query) {
        return fetch(withQuery(path, query), {
            method: "GET",
            headers: buildHeaders(),
            credentials: "same-origin",
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    throw new Error((data && data.error && data.error.message) || "Failed loading report data.");
                }
                return data;
            });
        });
    }

    function toRows(payload) {
        if (!payload || typeof payload !== "object") {
            return [];
        }
        var data = payload.data || payload;
        if (Array.isArray(data)) {
            return data;
        }
        if (Array.isArray(data.items)) {
            return data.items;
        }
        var firstArrayKey = Object.keys(data).find(function (key) {
            return Array.isArray(data[key]);
        });
        if (firstArrayKey) {
            return data[firstArrayKey];
        }
        return Object.keys(data).map(function (key) {
            return { metric: key, value: data[key] };
        });
    }

    function toColumns(rows) {
        if (!rows.length) {
            return ["metric", "value"];
        }
        var first = rows[0];
        if (first && typeof first === "object" && !Array.isArray(first)) {
            return Object.keys(first);
        }
        return ["value"];
    }

    function render(rows, columns) {
        var head = qs("#reports_table_head");
        var body = qs("#reports_table_body");
        var totalRows = qs("#reports_total_rows");
        var primaryMetric = qs("#reports_primary_metric");
        var primaryValue = qs("#reports_primary_value");

        if (!head || !body || !totalRows || !primaryMetric || !primaryValue) {
            return;
        }

        head.innerHTML = columns.map(function (col) {
            return "<th>" + escapeHtml(col) + "</th>";
        }).join("");

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="' + columns.length + '" class="text-center text-muted py-4">No report data found.</td></tr>';
            totalRows.textContent = "0";
            primaryMetric.textContent = "-";
            primaryValue.textContent = "-";
            return;
        }

        body.innerHTML = rows.map(function (row) {
            if (row && typeof row === "object" && !Array.isArray(row)) {
                return "<tr>" + columns.map(function (col) {
                    return "<td>" + escapeHtml(formatValue(row[col])) + "</td>";
                }).join("") + "</tr>";
            }
            return "<tr><td>" + escapeHtml(formatValue(row)) + "</td></tr>";
        }).join("");

        totalRows.textContent = String(rows.length);
        primaryMetric.textContent = columns[0] || "metric";
        var firstRow = rows[0];
        var firstValue = firstRow && typeof firstRow === "object" && !Array.isArray(firstRow)
            ? firstRow[columns[0]]
            : firstRow;
        primaryValue.textContent = formatValue(firstValue);
    }

    function init() {
        var root = qs("[data-saas-reports-page]");
        if (!root) return;

        var reportType = qs("#report-type", root);
        var periodFilter = qs("#period-filter", root);
        var refreshBtn = qs("#btn_refresh_reports", root);
        var errorBox = qs("#reports_error_box", root);

        function load() {
            hide(errorBox);
            var selectedType = reportType ? reportType.value : "revenue";
            var selectedPeriod = periodFilter ? periodFilter.value : "monthly";
            var endpoint = endpointFor(selectedType);
            var query = selectedType === "revenue" ? { period: selectedPeriod } : { period: selectedPeriod };

            request(endpoint, query)
                .then(function (payload) {
                    var rows = toRows(payload);
                    var columns = toColumns(rows);
                    render(rows, columns);
                })
                .catch(function (err) {
                    if (errorBox) {
                        errorBox.textContent = err && err.message ? err.message : "Failed loading report data.";
                        show(errorBox);
                    }
                    render([], ["metric", "value"]);
                });
        }

        if (reportType) reportType.addEventListener("change", load);
        if (periodFilter) periodFilter.addEventListener("change", load);
        if (refreshBtn) refreshBtn.addEventListener("click", load);

        load();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
</script>
@endpush
@endsection
