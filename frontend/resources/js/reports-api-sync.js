(function (window, document) {
    "use strict";

    var realtimeTimer = null;
    var dailyReportState = {
        page: 1,
        perPage: 100,
        totalPages: 1,
        total: 0,
        loading: false,
    };

    function onAuthFailure(status, data) {
        if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
            return window.AuthApi.handleUnauthorizedFromApi(status, data);
        }
        return false;
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
        if (tenant.companyCode) {
            headers["X-Company-Code"] = tenant.companyCode;
        }
        if (tenant.companyId) {
            headers["X-Company-Id"] = String(tenant.companyId);
        }
        if (tenant.companyUuid) {
            headers["X-Company-UUID"] = String(tenant.companyUuid);
        }

        return headers;
    }

    function withCompanyIdQuery(url) {
        var tenant = getTenantContext();
        if (!tenant.companyId) {
            return url;
        }

        if (String(url).indexOf("/v1/saas/reports/revenue") !== 0) {
            return url;
        }

        var sep = String(url).indexOf("?") === -1 ? "?" : "&";
        return url + sep + "company_id=" + encodeURIComponent(String(tenant.companyId));
    }

    function apiGet(url) {
        var requestUrl = withCompanyIdQuery(url);
        var headers = buildHeaders();

        if (window.axios) {
            return window.axios({
                method: "get",
                url: requestUrl,
                headers: headers,
                withCredentials: true,
            }).then(function (res) {
                return res.data;
            }).catch(function (err) {
                var status = err && err.response ? err.response.status : 0;
                var data = err && err.response ? err.response.data : null;
                if (onAuthFailure(status, data)) {
                    return null;
                }
                throw err;
            });
        }

        return fetch(requestUrl, {
            headers: headers,
            credentials: "same-origin",
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    if (onAuthFailure(res.status, data)) {
                        return null;
                    }
                    throw new Error("Request failed: " + url);
                }
                return data;
            });
        });
    }

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function toNum(v) {
        var n = Number(v || 0);
        return isFinite(n) ? n : 0;
    }

    function fmtMoney(v) {
        var n = toNum(v);
        return "Rp" + n.toLocaleString("id-ID", { maximumFractionDigits: 0 });
    }

    function fmtDate(v) {
        if (!v) return "-";
        var d = new Date(v);
        if (isNaN(d.getTime())) return "-";
        return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
    }

    function setMetricByLabel(labelText, valueText) {
        var labels = document.querySelectorAll("span, p, h6");
        for (var i = 0; i < labels.length; i++) {
            var txt = String(labels[i].textContent || "").replace(/\s+/g, " ").trim().toLowerCase();
            if (txt !== labelText.toLowerCase()) {
                continue;
            }
            var card = labels[i].closest(".card-body, .col-md-6, .col-lg-6");
            if (!card) {
                continue;
            }
            var valueEl = card.querySelector("h4, h5");
            if (valueEl) {
                valueEl.textContent = valueText;
                return true;
            }
        }
        return false;
    }

    function findReportTable() {
        return document.querySelector("table.datatable[data-api-report-table='1'] tbody");
    }

    function findReportTableElement() {
        return document.querySelector("table.datatable[data-api-report-table='1']");
    }

    function setDailyPaginationLoading(loading) {
        var root = document.querySelector("[data-daily-report-pagination='1']");
        if (!root) {
            return;
        }
        var prevBtn = root.querySelector("[data-daily-report-prev='1']");
        var nextBtn = root.querySelector("[data-daily-report-next='1']");
        if (prevBtn) {
            prevBtn.disabled = loading || dailyReportState.page <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = loading || dailyReportState.page >= dailyReportState.totalPages;
        }
    }

    function ensureDailyPaginationControls() {
        var table = findReportTableElement();
        if (!table) {
            return;
        }
        var wrapper = table.closest(".custom-datatable-filter") || table.parentElement;
        if (!wrapper || !wrapper.parentElement) {
            return;
        }

        var root = wrapper.parentElement.querySelector("[data-daily-report-pagination='1']");
        if (!root) {
            root = document.createElement("div");
            root.setAttribute("data-daily-report-pagination", "1");
            root.className = "d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-3 border-top";
            root.innerHTML =
                '<div class="text-muted small" data-daily-report-count="1">-</div>' +
                '<div class="d-flex align-items-center gap-2">' +
                '  <button type="button" class="btn btn-sm btn-outline-secondary" data-daily-report-prev="1">Prev</button>' +
                '  <span class="small text-muted" data-daily-report-page-info="1">Page 1 of 1</span>' +
                '  <button type="button" class="btn btn-sm btn-outline-secondary" data-daily-report-next="1">Next</button>' +
                '</div>';
            wrapper.parentElement.appendChild(root);
        }

        var prevBtn = root.querySelector("[data-daily-report-prev='1']");
        var nextBtn = root.querySelector("[data-daily-report-next='1']");

        if (prevBtn && !prevBtn.dataset.bound) {
            prevBtn.dataset.bound = "1";
            prevBtn.addEventListener("click", function () {
                if (dailyReportState.loading || dailyReportState.page <= 1) {
                    return;
                }
                syncDailyReport({ page: dailyReportState.page - 1 }).catch(function () {});
            });
        }

        if (nextBtn && !nextBtn.dataset.bound) {
            nextBtn.dataset.bound = "1";
            nextBtn.addEventListener("click", function () {
                if (dailyReportState.loading || dailyReportState.page >= dailyReportState.totalPages) {
                    return;
                }
                syncDailyReport({ page: dailyReportState.page + 1 }).catch(function () {});
            });
        }

        setDailyPaginationLoading(dailyReportState.loading);
    }

    function updateDailyPagination(meta) {
        meta = meta || {};
        dailyReportState.page = Math.max(1, toNum(meta.page) || dailyReportState.page || 1);
        dailyReportState.perPage = Math.max(1, toNum(meta.perPage) || dailyReportState.perPage || 100);
        dailyReportState.totalPages = Math.max(1, toNum(meta.totalPages) || 1);
        dailyReportState.total = Math.max(0, toNum(meta.total));

        var root = document.querySelector("[data-daily-report-pagination='1']");
        if (!root) {
            return;
        }

        var info = root.querySelector("[data-daily-report-page-info='1']");
        var count = root.querySelector("[data-daily-report-count='1']");
        if (info) {
            info.textContent = "Page " + dailyReportState.page + " of " + dailyReportState.totalPages;
        }

        if (count) {
            if (dailyReportState.total <= 0) {
                count.textContent = "No employee data.";
            } else {
                var from = ((dailyReportState.page - 1) * dailyReportState.perPage) + 1;
                var to = Math.min(dailyReportState.total, dailyReportState.page * dailyReportState.perPage);
                count.textContent = "Showing " + from + "-" + to + " of " + dailyReportState.total + " employees";
            }
        }

        setDailyPaginationLoading(dailyReportState.loading);
    }

    function setRows(rowsHtml, colCount, emptyMsg) {
        var tbody = findReportTable();
        if (!tbody) {
            return;
        }
        if (!rowsHtml || !rowsHtml.length) {
            var cells = "";
            for (var i = 0; i < Math.max(colCount, 1); i++) {
                cells += i === 0
                    ? '<td class="text-center text-muted py-4">' + esc(emptyMsg) + "</td>"
                    : "<td></td>";
            }
            tbody.innerHTML = "<tr>" + cells + "</tr>";
            return;
        }
        tbody.innerHTML = rowsHtml.join("");
    }

    function sumBy(arr, fn) {
        var out = 0;
        for (var i = 0; i < arr.length; i++) {
            out += toNum(fn(arr[i]));
        }
        return out;
    }

    function byPath(path) {
        var p = window.location.pathname || "";
        return p === path || p.slice(-path.length) === path;
    }

    function companyNameOf(row) {
        if (!row || typeof row !== "object") {
            return "-";
        }
        return row.company_name || row.companyName || (row.company && row.company.name) || "-";
    }

    function syncInvoiceReport() {
        return Promise.all([
            apiGet("/v1/saas/invoices"),
            apiGet("/v1/saas/reports/revenue?period=monthly")
        ]).then(function (result) {
            var payload = result[0] || {};
            var revenuePayload = result[1] || {};
            var rows = Array.isArray(payload.data) ? payload.data : [];
            var today = new Date();

            var paid = rows.filter(function (r) { return !!r.is_paid || !!r.isPaid; });
            var unpaid = rows.filter(function (r) { return !(!!r.is_paid || !!r.isPaid); });
            var overdue = unpaid.filter(function (r) {
                var due = new Date(r.due_date || r.dueDate || "");
                return !isNaN(due.getTime()) && due < today;
            });
            var totalRevenue = revenuePayload && revenuePayload.data ? toNum(revenuePayload.data.totalRevenue) : sumBy(paid, function (r) { return r.amount_due || r.amountDue; });

            setMetricByLabel("Total Invoice", String(rows.length));
            setMetricByLabel("Partially Paid", String(rows.filter(function (r) {
                var s = String(r.status || "").toLowerCase();
                return s.indexOf("partial") !== -1;
            }).length));
            setMetricByLabel("Paid Invoices", String(paid.length));
            setMetricByLabel("Overdue Invoices", String(overdue.length));
            setMetricByLabel("Unpaid Invoices", String(unpaid.length));
            setMetricByLabel("Revenue", fmtMoney(totalRevenue));

            var html = rows.map(function (r) {
                var invoiceNo = r.invoice_number || r.invoiceNumber || "-";
                var company = companyNameOf(r);
                var issue = r.issue_date || r.issueDate;
                var due = r.due_date || r.dueDate;
                var amount = r.amount_due || r.amountDue || 0;
                var isPaid = !!(r.is_paid || r.isPaid);
                var status = isPaid ? "Paid" : (r.status || "Unpaid");
                var badgeClass = isPaid ? "success" : "warning";
                return '<tr>' +
                    '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                    '<td>' + esc(invoiceNo) + '</td>' +
                    '<td><h6 class="fw-medium">' + esc(company) + '</h6></td>' +
                    '<td>' + esc(company) + '</td>' +
                    '<td>' + esc(fmtDate(issue)) + '</td>' +
                    '<td>' + esc(fmtDate(due)) + '</td>' +
                    '<td>' + esc(fmtMoney(amount)) + '</td>' +
                    '<td><span class="badge badge-soft-' + badgeClass + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + esc(status) + '</span></td>' +
                    '</tr>';
            });
            setRows(html, 8, "No invoice data.");
        });
    }

    function syncPaymentReport() {
        return apiGet("/v1/saas/payments").then(function (payload) {
            payload = payload || {};
            var rows = Array.isArray(payload.data) ? payload.data : [];
            var totalAmount = sumBy(rows, function (r) { return r.amount; });
            var pending = rows.filter(function (r) { return String(r.status || "").toLowerCase() === "pending"; });
            var failed = rows.filter(function (r) {
                var s = String(r.status || "").toLowerCase();
                return s === "failed" || s === "cancelled";
            });
            var completed = rows.filter(function (r) { return String(r.status || "").toLowerCase() === "completed"; });
            var successRate = rows.length ? Math.round((completed.length / rows.length) * 100) : 0;

            setMetricByLabel("Total Payments", fmtMoney(totalAmount));
            setMetricByLabel("Pending Payments", fmtMoney(sumBy(pending, function (r) { return r.amount; })));
            setMetricByLabel("Failed Payments", fmtMoney(sumBy(failed, function (r) { return r.amount; })));
            setMetricByLabel("Payment Success Rate", String(successRate) + "%");

            var methodTotals = {
                paypal: 0,
                debitCard: 0,
                bankTransfer: 0,
                creditCard: 0
            };
            rows.forEach(function (r) {
                var m = String(r.payment_method || r.paymentMethod || "").toLowerCase();
                var amount = toNum(r.amount);
                if (m === "bank_transfer") methodTotals.bankTransfer += amount;
                else if (m === "credit_card") {
                    methodTotals.creditCard += amount;
                    methodTotals.debitCard += amount;
                }
                else if (m === "e_wallet") methodTotals.paypal += amount;
                else if (m === "cash" || m === "check") methodTotals.debitCard += amount;
            });
            setMetricByLabel("Paypal", fmtMoney(methodTotals.paypal));
            setMetricByLabel("Debit Card", fmtMoney(methodTotals.debitCard));
            setMetricByLabel("Bank Transfer", fmtMoney(methodTotals.bankTransfer));
            setMetricByLabel("Credit Card", fmtMoney(methodTotals.creditCard));

            var html = rows.map(function (r) {
                var invoiceNo = r.invoice_number || r.invoiceNumber || (r.invoice && r.invoice.invoice_number) || "-";
                var company = companyNameOf(r);
                var payType = r.payment_method || r.paymentMethod || "-";
                var paidAt = r.paid_at || r.paidAt || r.created_at || r.createdAt;
                return '<tr>' +
                    '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                    '<td>' + esc(invoiceNo) + '</td>' +
                    '<td><h6 class="fw-medium">' + esc(company) + '</h6></td>' +
                    '<td>' + esc(company) + '</td>' +
                    '<td>' + esc(String(payType).replace(/_/g, " ")) + '</td>' +
                    '<td>' + esc(fmtDate(paidAt)) + '</td>' +
                    '<td>' + esc(fmtMoney(r.amount)) + '</td>' +
                    '</tr>';
            });
            setRows(html, 7, "No payment data.");
        });
    }

    function syncExpensesReport() {
        return apiGet("/v1/saas/transactions").then(function (payload) {
            payload = payload || {};
            var rows = Array.isArray(payload.data) ? payload.data : [];
            var total = sumBy(rows, function (r) { return r.amount; });
            var approved = rows.filter(function (r) { return String(r.status || "").toLowerCase() === "completed"; });
            var rejected = rows.filter(function (r) {
                var s = String(r.status || "").toLowerCase();
                return s === "failed" || s === "refunded";
            });

            setMetricByLabel("Total Expense", fmtMoney(total));
            setMetricByLabel("Approved Expense", fmtMoney(sumBy(approved, function (r) { return r.amount; })));
            setMetricByLabel("Net Pay", fmtMoney(sumBy(approved, function (r) { return r.amount; })));
            setMetricByLabel("Allowances", fmtMoney(sumBy(rejected, function (r) { return r.amount; })));

            var html = rows.map(function (r) {
                var name = r.invoiceNumber || r.transactionId || "Transaction";
                return '<tr>' +
                    '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                    '<td><h6 class="fs-14 fw-medium">' + esc(name) + '</h6></td>' +
                    '<td>' + esc(fmtDate(r.createdAt)) + '</td>' +
                    '<td>' + esc(String(r.paymentMethod || "-").replace(/_/g, " ")) + '</td>' +
                    '<td>' + esc(fmtMoney(r.amount)) + '</td>' +
                    '</tr>';
            });
            setRows(html, 5, "No expense data.");
        });
    }

    function syncUserReport() {
        return apiGet("/v1/hcm/user-management/users?status=all&perPage=100").then(function (payload) {
            payload = payload || {};
            var rows = Array.isArray(payload.data) ? payload.data : [];
            var now = new Date();
            var active = rows.filter(function (r) { return String(r.status || "").toLowerCase() === "active"; });
            var inactive = rows.filter(function (r) { return String(r.status || "").toLowerCase() !== "active"; });
            var newly = rows.filter(function (r) {
                var d = new Date(r.createdAt || "");
                return !isNaN(d.getTime()) && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
            });

            setMetricByLabel("Total Users", String(rows.length));
            setMetricByLabel("Active Users", String(active.length));
            setMetricByLabel("New Users", String(newly.length));
            setMetricByLabel("Inactive Users", String(inactive.length));

            var html = rows.map(function (r) {
                var roles = Array.isArray(r.activeRoleCodes) ? r.activeRoleCodes.join(", ") : (r.companyRole || "-");
                var status = String(r.status || "-");
                var badgeClass = status.toLowerCase() === "active" ? "success" : "danger";
                return '<tr>' +
                    '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                    '<td><div class="d-flex align-items-center"><div><h6 class="fw-medium">' + esc(r.name || "-") + '</h6></div></div></td>' +
                    '<td>' + esc(r.email || "-") + '</td>' +
                    '<td>' + esc(fmtDate(r.createdAt)) + '</td>' +
                    '<td>' + esc(roles || "-") + '</td>' +
                    '<td><span class="badge badge-soft-' + badgeClass + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + esc(status) + '</span></td>' +
                    '</tr>';
            });
            setRows(html, 6, "No user data.");
        });
    }

    function syncDailyReport(options) {
        options = options || {};
        if (options.page !== undefined && options.page !== null) {
            dailyReportState.page = Math.max(1, toNum(options.page) || 1);
        }
        if (dailyReportState.loading) {
            return Promise.resolve();
        }

        ensureDailyPaginationControls();
        dailyReportState.loading = true;
        setDailyPaginationLoading(true);

        var today = new Date().toISOString().slice(0, 10);
        return Promise.all([
            apiGet("/v1/hcm/attendance/admin?date=" + encodeURIComponent(today) + "&perPage=" + encodeURIComponent(String(dailyReportState.perPage)) + "&page=" + encodeURIComponent(String(dailyReportState.page)) + "&sort=name_asc"),
            apiGet("/v1/hcm/timesheets?dateFrom=" + encodeURIComponent(today) + "&dateTo=" + encodeURIComponent(today) + "&perPage=200")
        ]).then(function (res) {
            var attendancePayload = res[0] || {};
            var tsPayload = res[1] || {};
            var rows = Array.isArray(attendancePayload.data) ? attendancePayload.data : [];
            var tasks = Array.isArray(tsPayload.data) ? tsPayload.data : [];
            var pagination = (attendancePayload.meta && attendancePayload.meta.pagination) ? attendancePayload.meta.pagination : null;

            // Use server-side summary (covers ALL employees, not just current page)
            var summary = (attendancePayload.meta && attendancePayload.meta.summary) ? attendancePayload.meta.summary : {};
            var present = summary.present !== undefined ? summary.present : rows.filter(function (r) { return String(r.statusKey || "").toLowerCase() === "present"; }).length;
            var absent = summary.absent !== undefined ? summary.absent : rows.filter(function (r) { return String(r.statusKey || "").toLowerCase() === "absent"; }).length;
            var completedTasks = tasks.filter(function (r) { return toNum(r.workedHours) >= toNum(r.assignedHours); }).length;
            var pendingTasks = Math.max(tasks.length - completedTasks, 0);

            setMetricByLabel("Total Present", String(present));
            setMetricByLabel("Completed Tasks", String(completedTasks));
            setMetricByLabel("Total Absent", String(absent));
            setMetricByLabel("Pending Tasks", String(pendingTasks));

            var html = rows.map(function (r) {
                var badgeClass = String(r.statusKey || "").toLowerCase() === "present" ? "success" : "danger";
                var status = r.statusLabel || r.statusKey || "-";
                return '<tr>' +
                    '<td><div class="d-flex align-items-center"><div><p class="text-dark mb-0">' + esc(r.employeeName || "-") + '</p><span class="fs-12">' + esc(r.team || "-") + '</span></div></div></td>' +
                    '<td>' + esc(r.dateLabel || fmtDate(today)) + '</td>' +
                    '<td>' + esc(r.team || "-") + '</td>' +
                    '<td><span class="badge badge-soft-' + badgeClass + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + esc(status) + '</span></td>' +
                    '</tr>';
            });
            setRows(html, 4, "No attendance data for today.");
            updateDailyPagination(pagination);
        }).finally(function () {
            dailyReportState.loading = false;
            setDailyPaginationLoading(false);
        });
    }

    function deriveProjectTaskData(timesheetRows) {
        var projectMap = {};
        for (var i = 0; i < timesheetRows.length; i++) {
            var row = timesheetRows[i] || {};
            var key = String(row.project || "General");
            if (!projectMap[key]) {
                projectMap[key] = { name: key, team: key.replace(/\s+Ops$/i, "") || "General", hours: 0, members: 0, rows: 0 };
            }
            projectMap[key].hours += toNum(row.workedHours);
            projectMap[key].rows += 1;
            projectMap[key].members += 1;
        }
        var projects = Object.keys(projectMap).map(function (k) { return projectMap[k]; });
        projects.sort(function (a, b) { return b.hours - a.hours; });
        return projects;
    }

    function syncProjectReport() {
        var to = new Date();
        var from = new Date(to.getTime() - (29 * 86400000));
        var q = "?dateFrom=" + encodeURIComponent(from.toISOString().slice(0, 10)) + "&dateTo=" + encodeURIComponent(to.toISOString().slice(0, 10)) + "&perPage=200";
        return apiGet("/v1/hcm/timesheets" + q).then(function (payload) {
            payload = payload || {};
            var tsRows = Array.isArray(payload.data) ? payload.data : [];
            var projects = deriveProjectTaskData(tsRows);
            var completed = projects.filter(function (p) { return p.hours >= 120; });
            var pending = projects.filter(function (p) { return p.hours < 120; });

            setMetricByLabel("Total Projects", String(projects.length));
            setMetricByLabel("Completed Projects", String(completed.length));
            setMetricByLabel("Pending Projects", String(pending.length));
            setMetricByLabel("New Projects", String(projects.filter(function (p) { return p.rows <= 3; }).length));

            var html = projects.slice(0, 15).map(function (p, idx) {
                var priority = p.hours >= 160 ? "High" : (p.hours >= 80 ? "Medium" : "Low");
                var status = p.hours >= 120 ? "Completed" : "In Progress";
                var statusClass = p.hours >= 120 ? "success" : "warning";
                var deadline = new Date(to.getTime() + ((idx + 1) * 86400000));
                return '<tr>' +
                    '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                    '<td>PRO-' + esc(String(1000 + idx)) + '</td>' +
                    '<td><h6 class="fw-medium">' + esc(p.name) + '</h6></td>' +
                    '<td>' + esc(p.team + " Lead") + '</td>' +
                    '<td>' + esc(String(p.members)) + ' Members</td>' +
                    '<td>' + esc(fmtDate(deadline.toISOString())) + '</td>' +
                    '<td>' + esc(priority) + '</td>' +
                    '<td><span class="badge badge-soft-' + statusClass + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + esc(status) + '</span></td>' +
                    '</tr>';
            });
            setRows(html, 8, "No project data from timesheets.");
        });
    }

    function syncTaskReport() {
        var to = new Date();
        var from = new Date(to.getTime() - (29 * 86400000));
        var q = "?dateFrom=" + encodeURIComponent(from.toISOString().slice(0, 10)) + "&dateTo=" + encodeURIComponent(to.toISOString().slice(0, 10)) + "&perPage=200";
        return apiGet("/v1/hcm/timesheets" + q).then(function (payload) {
            payload = payload || {};
            var rows = Array.isArray(payload.data) ? payload.data : [];
            var completed = rows.filter(function (r) { return toNum(r.workedHours) >= toNum(r.assignedHours); });
            var inprogress = rows.filter(function (r) { return toNum(r.workedHours) > 0 && toNum(r.workedHours) < toNum(r.assignedHours); });
            var pending = rows.filter(function (r) { return toNum(r.workedHours) <= 0; });

            var cards = document.querySelectorAll(".page-wrapper .content > .row .col-lg-6.col-md-6.d-flex .row.flex-fill .card .card-body h5");
            if (cards.length >= 4) {
                cards[0].textContent = String(rows.length);
                cards[1].textContent = String(completed.length);
                cards[2].textContent = String(inprogress.length);
                cards[3].textContent = String(pending.length);
            }

            var html = rows.slice(0, 20).map(function (r, idx) {
                var worked = toNum(r.workedHours);
                var assigned = Math.max(toNum(r.assignedHours), 1);
                var status = worked >= assigned ? "Completed" : (worked > 0 ? "In Progress" : "Pending");
                var statusClass = worked >= assigned ? "success" : (worked > 0 ? "warning" : "secondary");
                var priority = worked >= assigned ? "Low" : (worked > 0 ? "Medium" : "High");
                var createdDate = r.date || from.toISOString().slice(0, 10);
                var due = new Date(new Date(createdDate).getTime() + (3 + (idx % 5)) * 86400000).toISOString();
                return '<tr>' +
                    '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                    '<td><h6 class="fw-medium">' + esc((r.employeeName || "Task") + " Worklog") + '</h6></td>' +
                    '<td>' + esc(r.project || "General Ops") + '</td>' +
                    '<td>' + esc(fmtDate(createdDate)) + '</td>' +
                    '<td>' + esc(fmtDate(due)) + '</td>' +
                    '<td>' + esc(priority) + '</td>' +
                    '<td><span class="badge badge-soft-' + statusClass + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + esc(status) + '</span></td>' +
                    '</tr>';
            });
            setRows(html, 7, "No task data from timesheets.");
        });
    }

    function boot() {
        var run;
        if (byPath("/invoice-report")) run = syncInvoiceReport;
        else if (byPath("/payment-report")) run = syncPaymentReport;
        else if (byPath("/expenses-report")) run = syncExpensesReport;
        else if (byPath("/user-report")) run = syncUserReport;
        else if (byPath("/daily-report")) run = syncDailyReport;

        if (!run) {
            return;
        }

        if (byPath("/daily-report")) {
            ensureDailyPaginationControls();
        }

        run().catch(function () {
            // Keep existing template layout visible if API request fails.
        });

        if (byPath("/daily-report")) {
            if (realtimeTimer) {
                window.clearInterval(realtimeTimer);
            }

            // Keep admin daily report fresh without manual page reload.
            realtimeTimer = window.setInterval(function () {
                syncDailyReport().catch(function () {});
            }, 30000);

            document.addEventListener("visibilitychange", function () {
                if (document.visibilityState === "visible") {
                    syncDailyReport().catch(function () {});
                }
            });

            window.addEventListener("focus", function () {
                syncDailyReport().catch(function () {});
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})(window, document);
