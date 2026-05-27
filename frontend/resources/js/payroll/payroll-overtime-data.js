(function (window, document) {
    "use strict";

    var currentPage = 1;
    var lastTotalPages = 1;

    function todayIsoLocal() {
        var today = new Date();
        var y = today.getFullYear();
        var m = String(today.getMonth() + 1).padStart(2, "0");
        var da = String(today.getDate()).padStart(2, "0");
        return y + "-" + m + "-" + da;
    }

    function getSelectedWorkDate() {
        var input = document.querySelector("[data-payroll-overtime-date]");
        if (input && input.value && /^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
            return input.value;
        }
        var params = new URLSearchParams(window.location.search || "");
        var qd = params.get("date");
        if (qd && /^\d{4}-\d{2}-\d{2}$/.test(qd)) {
            return qd;
        }
        return todayIsoLocal();
    }

    function getStatusFilter() {
        var sel = document.querySelector("[data-payroll-overtime-status]");
        if (!sel) {
            return "";
        }
        return String(sel.value || "").trim();
    }

    function syncDateInputFromUrl() {
        var input = document.querySelector("[data-payroll-overtime-date]");
        if (!input) {
            return;
        }
        var params = new URLSearchParams(window.location.search || "");
        var qd = params.get("date");
        if (qd && /^\d{4}-\d{2}-\d{2}$/.test(qd)) {
            input.value = qd;
        } else if (!input.value) {
            input.value = todayIsoLocal();
        }
    }

    function replaceUrlDate(dateStr) {
        try {
            var u = new URL(window.location.href);
            u.searchParams.set("date", dateStr);
            window.history.replaceState({}, "", u.pathname + u.search + u.hash);
        } catch (e) {
            /* ignore */
        }
    }

    function updateAttendanceLink(dateStr) {
        var a = document.querySelector("[data-payroll-overtime-attendance-link]");
        if (!a) {
            return;
        }
        try {
            var base = a.getAttribute("href") || "/attendance-admin";
            var u = new URL(base, window.location.origin);
            u.searchParams.set("date", dateStr);
            a.setAttribute("href", u.pathname + u.search);
        } catch (e) {
            a.setAttribute("href", (a.getAttribute("href") || "").split("?")[0] + "?date=" + encodeURIComponent(dateStr));
        }
    }

    function onAuthFailure(status, data) {
        if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
            return window.AuthApi.handleUnauthorizedFromApi(status, data);
        }
        return false;
    }

    function apiRequest(method, url) {
        var headers = { Accept: "application/json" };
        var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        if (token) { headers['Authorization'] = 'Bearer ' + token; }
        if (window.axios) {
            return window.axios({ method: method, url: url, headers: headers, withCredentials: true }).then(function (res) {
                return res.data;
            }).catch(function (err) {
                var st = err && err.response ? err.response.status : 0;
                var d = err && err.response ? err.response.data : null;
                if (onAuthFailure(st, d)) {
                    return null;
                }
                return Promise.reject({ status: st, data: d });
            });
        }
        return fetch(url, { method: method, headers: headers, credentials: "same-origin" }).then(function (res) {
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!res.ok) {
                    if (onAuthFailure(res.status, data)) {
                        return null;
                    }
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    function escapeHtml(s) {
        if (s == null) {
            return "";
        }
        return String(s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function formatApiError(data, status) {
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            return window.ApiErrorHelper.format(data, status);
        }
        return (data && data.error && data.error.message) || "Gagal memuat data.";
    }

    function statusBadge(status) {
        if (status === "approved") {
            return '<span class="badge bg-success">Approved</span>';
        }
        if (status === "declined") {
            return '<span class="badge bg-danger">Declined</span>';
        }
        return '<span class="badge bg-warning text-dark">Pending</span>';
    }

    function renderSummary(meta) {
        var row = document.querySelector("[data-payroll-overtime-summary-row]");
        var s = meta && meta.summary;
        if (!row || !s) {
            if (row) {
                row.style.display = "none";
            }
            return;
        }
        row.style.display = "";
        var set = function (sel, v) {
            var el = document.querySelector(sel);
            if (el) {
                el.textContent = String(v != null ? v : "0");
            }
        };
        set("[data-payroll-overtime-summary-users]", s.distinctUsers);
        set("[data-payroll-overtime-summary-pending]", s.pending);
        set("[data-payroll-overtime-summary-declined]", s.declined);
        set("[data-payroll-overtime-summary-minutes]", s.approvedMinutes);
    }

    function renderPagination(p) {
        var foot = document.querySelector("[data-payroll-overtime-pagination]");
        var info = document.querySelector("[data-payroll-overtime-page-info]");
        if (!foot) {
            return;
        }
        if (!p || p.totalPages == null || p.totalPages <= 1) {
            foot.style.display = "none";
            return;
        }
        foot.style.display = "";
        var total = parseInt(p.total, 10) || 0;
        var page = parseInt(p.page, 10) || 1;
        var perPage = parseInt(p.perPage, 10) || 20;
        if (info) {
            var from = total === 0 ? 0 : (page - 1) * perPage + 1;
            var to = Math.min(page * perPage, total);
            info.textContent = "Menampilkan " + from + "–" + to + " dari " + total;
        }
        var prev = foot.querySelector("[data-payroll-overtime-prev]");
        var next = foot.querySelector("[data-payroll-overtime-next]");
        if (prev) {
            prev.disabled = page <= 1;
        }
        if (next) {
            next.disabled = page >= p.totalPages;
        }
    }

    function renderRequestRows(rows) {
        var tbody = document.querySelector("[data-payroll-overtime-requests-body]");
        if (!tbody) {
            return;
        }
        if (!rows || !rows.length) {
            tbody.innerHTML =
                '<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada pengajuan lembur untuk filter ini.</td></tr>';
            return;
        }
        tbody.innerHTML = rows
            .map(function (r) {
                var comp =
                    r.salaryComponentCode || r.salaryComponentName
                                                ? '<span class="text-muted small">' +
                                                    escapeHtml(r.salaryComponentName || "Komponen lembur payroll") +
                                                    "</span>"
                        : '<span class="text-muted">—</span>';
                return (
                    "<tr>" +
                    '<td><h6 class="fs-14 fw-medium text-gray-9 mb-0">' +
                    escapeHtml(r.employeeName || "—") +
                    '</h6><span class="text-muted small">' +
                    escapeHtml(r.email || "") +
                    "</span></td>" +
                    "<td>" +
                    escapeHtml(r.workDate || "—") +
                    "</td>" +
                    "<td>" +
                    escapeHtml(String(r.minutes != null ? r.minutes : "—")) +
                    "</td>" +
                    "<td>" +
                    escapeHtml(r.overtimeTypeName || "—") +
                    "</td>" +
                    "<td>" +
                    statusBadge(r.status) +
                    "</td>" +
                    "<td>" +
                    comp +
                    "</td>" +
                    "</tr>"
                );
            })
            .join("");
    }

    function renderTypeRows(rows) {
        var tbody = document.querySelector("[data-payroll-overtime-types-body]");
        if (!tbody) {
            return;
        }
        if (!rows || !rows.length) {
            tbody.innerHTML =
                '<tr><td colspan="3" class="text-center text-muted py-4">Tidak ada tipe lembur.</td></tr>';
            return;
        }
        tbody.innerHTML = rows
            .map(function (t) {
                var active = t.isActive ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>';
                return (
                    "<tr>" +
                    '<td><h6 class="fs-14 fw-medium text-gray-9 mb-0">' +
                    escapeHtml(t.name || "—") +
                    '</h6><span class="text-muted small">' +
                    escapeHtml(t.code || "—") +
                    "</span></td>" +
                    "<td>" +
                    escapeHtml(t.paymentMultiplier != null ? String(t.paymentMultiplier) : "—") +
                    "</td>" +
                    "<td>" +
                    active +
                    "</td>" +
                    "</tr>"
                );
            })
            .join("");
    }

    function buildListUrl(page) {
        var wd = getSelectedWorkDate();
        var st = getStatusFilter();
        var q = new URLSearchParams();
        q.set("workDate", wd);
        q.set("perPage", "50");
        q.set("page", String(page || 1));
        if (st) {
            q.set("status", st);
        }
        return "/v1/hcm/overtime-requests?" + q.toString();
    }

    function loadOvertimeRequests() {
        var tbody = document.querySelector("[data-payroll-overtime-requests-body]");
        if (tbody) {
            tbody.innerHTML =
                '<tr><td colspan="6" class="text-center text-muted py-4">Memuat…</td></tr>';
        }
        apiRequest("get", buildListUrl(currentPage))
            .then(function (resp) {
                if (!resp || resp.success !== true) {
                    throw { status: 0, data: resp };
                }
                renderRequestRows(resp.data || []);
                renderSummary(resp.meta || {});
                var pag = (resp.meta || {}).pagination;
                if (pag && pag.totalPages != null) {
                    lastTotalPages = parseInt(pag.totalPages, 10) || 1;
                    if (currentPage > lastTotalPages) {
                        currentPage = lastTotalPages;
                    }
                }
                renderPagination(pag);
            })
            .catch(function (err) {
                if (err == null) {
                    return;
                }
                var msg = formatApiError(err.data, err.status);
                if (tbody) {
                    tbody.innerHTML =
                        '<tr><td colspan="6" class="text-center text-danger py-4">' + escapeHtml(msg) + "</td></tr>";
                }
                var row = document.querySelector("[data-payroll-overtime-summary-row]");
                if (row) {
                    row.style.display = "none";
                }
                var foot = document.querySelector("[data-payroll-overtime-pagination]");
                if (foot) {
                    foot.style.display = "none";
                }
                if (window.ArcavUi && window.ArcavUi.showToast) {
                    window.ArcavUi.showToast(msg, "danger");
                }
            });
    }

    function loadOvertimeTypes() {
        var tbody = document.querySelector("[data-payroll-overtime-types-body]");
        apiRequest("get", "/v1/hcm/overtime-types")
            .then(function (resp) {
                if (!resp || resp.success !== true) {
                    throw { status: 0, data: resp };
                }
                renderTypeRows(resp.data || []);
            })
            .catch(function (err) {
                if (err == null || !tbody) {
                    return;
                }
                var msg = formatApiError(err.data, err.status);
                tbody.innerHTML =
                    '<tr><td colspan="3" class="text-center text-danger py-4">' + escapeHtml(msg) + "</td></tr>";
            });
    }

    function setupDateAndFilters() {
        syncDateInputFromUrl();
        var dateStr = getSelectedWorkDate();
        replaceUrlDate(dateStr);
        updateAttendanceLink(dateStr);

        var input = document.querySelector("[data-payroll-overtime-date]");
        if (input && !input.getAttribute("data-bound")) {
            input.setAttribute("data-bound", "1");
            input.addEventListener("change", function () {
                var v = input.value;
                if (!v || !/^\d{4}-\d{2}-\d{2}$/.test(v)) {
                    return;
                }
                currentPage = 1;
                replaceUrlDate(v);
                updateAttendanceLink(v);
                loadOvertimeRequests();
            });
        }

        var sel = document.querySelector("[data-payroll-overtime-status]");
        if (sel && !sel.getAttribute("data-bound")) {
            sel.setAttribute("data-bound", "1");
            sel.addEventListener("change", function () {
                currentPage = 1;
                loadOvertimeRequests();
            });
        }

        var foot = document.querySelector("[data-payroll-overtime-pagination]");
        if (foot && !foot.getAttribute("data-bound")) {
            foot.setAttribute("data-bound", "1");
            var prev = foot.querySelector("[data-payroll-overtime-prev]");
            var next = foot.querySelector("[data-payroll-overtime-next]");
            if (prev) {
                prev.addEventListener("click", function () {
                    if (currentPage > 1) {
                        currentPage -= 1;
                        loadOvertimeRequests();
                    }
                });
            }
            if (next) {
                next.addEventListener("click", function () {
                    if (currentPage < lastTotalPages) {
                        currentPage += 1;
                        loadOvertimeRequests();
                    }
                });
            }
        }
    }

    function loadPayrollOvertimePage() {
        var path = (window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path !== "/payroll-overtime") {
            return;
        }
        setupDateAndFilters();
        loadOvertimeRequests();
        loadOvertimeTypes();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", loadPayrollOvertimePage);
    } else {
        loadPayrollOvertimePage();
    }
})(window, document);
