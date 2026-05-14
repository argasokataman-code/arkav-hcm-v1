(function (window, document) {
    "use strict";

    function setText(selector, value) {
        var els = document.querySelectorAll(selector);
        if (!els || els.length === 0) return;
        var text = String(value == null ? "0" : value);
        for (var i = 0; i < els.length; i += 1) {
            els[i].textContent = text;
        }
    }

    function setWidth(selector, pct) {
        var el = document.querySelector(selector);
        if (!el) return;
        el.style.width = Math.max(0, Math.min(100, pct)).toFixed(1) + "%";
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatCurrency(value) {
        var amount = Number(value || 0);
        if (!Number.isFinite(amount)) amount = 0;
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(amount);
    }

    function apiGet(url) {
        if (window.axios) {
            return window.axios({ method: "get", url: url, headers: { Accept: "application/json" }, withCredentials: true })
                .then(function (r) { return r.data; });
        }
        return fetch(url, { method: "GET", headers: { Accept: "application/json" }, credentials: "same-origin" })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (!res.ok) {
                        throw { status: res.status, data: data };
                    }
                    return data;
                });
            });
    }

    function toHours(minutes) {
        var val = Number(minutes || 0);
        if (!Number.isFinite(val)) return "0";
        return (val / 60).toFixed(1);
    }

    function pct(part, total) {
        if (!total || total <= 0) return 0;
        return (part / total) * 100;
    }

    function updateAttendanceChart(overview) {
        window.__arcavPendingAttendanceOverview = overview || {};
        if (typeof window.updateAttendanceOverviewChart === "function") {
            window.updateAttendanceOverviewChart(window.__arcavPendingAttendanceOverview);
        }
    }

    function renderDepartmentBreakdown(items) {
        var wrap = document.querySelector("[data-legacy-department-breakdown]");
        var summary = document.querySelector("[data-legacy-department-summary]");
        if (!wrap || !summary) return;

        if (!items || items.length === 0) {
            wrap.innerHTML = '<p class="fs-12 text-muted mb-2">Belum ada data departemen.</p>';
            summary.textContent = "Distribusi departemen belum tersedia.";
            return;
        }

        var first = items[0];
        summary.textContent = first.name + " merupakan departemen terbesar dengan " + first.count + " karyawan aktif.";

        wrap.innerHTML = items.slice(0, 4).map(function (item) {
            return '<div class="d-flex align-items-center justify-content-between mb-1">'
                + '<span class="fs-12 text-gray-9">' + escapeHtml(item.name) + '</span>'
                + '<span class="fs-12 fw-medium">' + escapeHtml(item.count) + '</span>'
                + '</div>';
        }).join("");
    }

    function updateDepartmentChart(items) {
        window.__arcavPendingDepartmentBreakdown = Array.isArray(items) ? items : [];
        if (typeof window.updateEmployeeDepartmentChart === "function") {
            window.updateEmployeeDepartmentChart(window.__arcavPendingDepartmentBreakdown);
        }
    }

    function renderClockList(items) {
        var wrap = document.querySelector("[data-legacy-clock-list]");
        if (!wrap) return;

        if (!items || items.length === 0) {
            wrap.innerHTML = '<div class="mb-3 p-2 border br-5"><p class="fs-13 mb-0">Belum ada clock-in hari ini.</p></div>';
            return;
        }

        wrap.innerHTML = items.map(function (item) {
            return '<div class="mb-3 p-2 border br-5">'
                + '<div class="d-flex align-items-center justify-content-between">'
                + '<div>'
                + '<h6 class="fs-14 fw-medium text-truncate mb-1">' + escapeHtml(item.name) + '</h6>'
                + '<p class="fs-13 mb-0">' + escapeHtml(item.role) + '</p>'
                + '</div>'
                + '<span class="fs-10 fw-medium d-inline-flex align-items-center badge badge-success"><i class="ti ti-circle-filled fs-5 me-1"></i>' + escapeHtml(item.checkIn) + '</span>'
                + '</div>'
                + '<div class="d-flex align-items-center justify-content-between flex-wrap mt-2 border br-5 p-2 pb-0">'
                + '<div><p class="mb-1 d-inline-flex align-items-center"><i class="ti ti-circle-filled text-success fs-5 me-1"></i>Clock In</p><h6 class="fs-13 fw-normal mb-2">' + escapeHtml(item.checkIn) + '</h6></div>'
                + '<div><p class="mb-1 d-inline-flex align-items-center"><i class="ti ti-circle-filled text-danger fs-5 me-1"></i>Clock Out</p><h6 class="fs-13 fw-normal mb-2">' + escapeHtml(item.checkOut) + '</h6></div>'
                + '<div><p class="mb-1 d-inline-flex align-items-center"><i class="ti ti-circle-filled text-warning fs-5 me-1"></i>Production</p><h6 class="fs-13 fw-normal mb-2">' + escapeHtml(item.productiveHours) + ' Hrs</h6></div>'
                + '</div>'
                + '</div>';
        }).join("");
    }

    function renderLateList(items) {
        var wrap = document.querySelector("[data-legacy-late-list]");
        if (!wrap) return;

        if (!items || items.length === 0) {
            wrap.innerHTML = '<div class="mb-3 p-2 border border-dashed br-5"><p class="fs-13 mb-0">Tidak ada karyawan yang terlambat hari ini.</p></div>';
            return;
        }

        wrap.innerHTML = items.map(function (item) {
            return '<div class="d-flex align-items-center justify-content-between mb-3 p-2 border border-dashed br-5">'
                + '<div class="ms-1">'
                + '<h6 class="fs-14 fw-medium text-truncate mb-1">' + escapeHtml(item.name) + ' <span class="fs-10 fw-medium d-inline-flex align-items-center badge badge-success"><i class="ti ti-clock-hour-11 me-1"></i>' + escapeHtml(item.lateMinutes) + ' Min</span></h6>'
                + '<p class="fs-13 mb-0">' + escapeHtml(item.role) + '</p>'
                + '</div>'
                + '<span class="fs-10 fw-medium d-inline-flex align-items-center badge badge-danger"><i class="ti ti-circle-filled fs-5 me-1"></i>' + escapeHtml(item.checkIn) + '</span>'
                + '</div>';
        }).join("");
    }

    function renderEmployees(items) {
        var body = document.querySelector("[data-legacy-employees-body]");
        if (!body) return;

        if (!items || items.length === 0) {
            body.innerHTML = '<tr><td colspan="2" class="text-center fs-13 text-muted py-3">Belum ada data karyawan.</td></tr>';
            return;
        }

        body.innerHTML = items.map(function (item) {
            return '<tr>'
                + '<td><div class="d-flex align-items-center"><div class="ms-2"><h6 class="fw-medium mb-0">' + escapeHtml(item.name) + '</h6><span class="fs-12">' + escapeHtml(item.designation) + '</span></div></div></td>'
                + '<td><span class="badge badge-secondary-transparent badge-xs">' + escapeHtml(item.department) + '</span></td>'
                + '</tr>';
        }).join("");
    }

    function renderInvoices(items) {
        var body = document.querySelector("[data-legacy-invoices-body]");
        if (!body) return;

        if (!items || items.length === 0) {
            body.innerHTML = '<tr><td colspan="3" class="text-center fs-13 text-muted py-3">Belum ada invoice.</td></tr>';
            return;
        }

        body.innerHTML = items.map(function (item) {
            var badgeClass = item.status === "paid"
                ? "badge-success-transparent"
                : "badge-danger-transparent";
            var label = item.status === "paid" ? "Paid" : "Unpaid";
            return '<tr>'
                + '<td class="px-0"><div class="ms-1"><h6 class="fw-medium mb-1">' + escapeHtml(item.invoiceNumber) + '</h6></div></td>'
                + '<td><p class="fs-13 mb-1">Payment</p><h6 class="fw-medium">' + escapeHtml(formatCurrency(item.amountDue)) + '</h6></td>'
                + '<td class="px-0 text-end"><span class="badge ' + badgeClass + ' badge-xs d-inline-flex align-items-center"><i class="ti ti-circle-filled fs-5 me-1"></i>' + label + '</span></td>'
                + '</tr>';
        }).join("");
    }

    function renderActivities(items) {
        var body = document.querySelector("[data-legacy-activities-body]");
        if (!body) return;

        if (!items || items.length === 0) {
            body.innerHTML = '<p class="fs-13 text-muted mb-0">Belum ada aktivitas terbaru.</p>';
            return;
        }

        body.innerHTML = items.map(function (item) {
            return '<div class="recent-item">'
                + '<div class="d-flex justify-content-between">'
                + '<div class="d-flex align-items-center w-100">'
                + '<div class="ms-2 flex-fill">'
                + '<div class="d-flex align-items-center justify-content-between">'
                + '<h6 class="fs-medium text-truncate">' + escapeHtml(item.actor) + '</h6>'
                + '<p class="fs-13">' + escapeHtml(item.time) + '</p>'
                + '</div>'
                + '<p class="fs-13">' + escapeHtml(item.title) + '</p>'
                + '</div>'
                + '</div>'
                + '</div>'
                + '</div>';
        }).join("");
    }

    function renderBirthdays(sectionSelector, items, emptyText) {
        var el = document.querySelector(sectionSelector);
        if (!el) return;

        if (!items || items.length === 0) {
            el.innerHTML = '<div class="d-flex align-items-center justify-content-between"><p class="fs-13 mb-0 text-muted">' + escapeHtml(emptyText) + '</p></div>';
            return;
        }

        el.innerHTML = items.map(function (item) {
            return '<div class="d-flex align-items-center justify-content-between mb-2">'
                + '<div class="d-flex align-items-center">'
                + '<div class="ms-2 overflow-hidden">'
                + '<h6 class="fs-medium mb-0">' + escapeHtml(item.name) + '</h6>'
                + '<p class="fs-13 mb-0">' + escapeHtml(item.role) + '</p>'
                + '</div>'
                + '</div>'
                + '<a href="javascript:void(0);" class="btn btn-secondary btn-xs"><i class="ti ti-cake me-1"></i>Send</a>'
                + '</div>';
        }).join("");
    }

    function loadDashboardSummary() {
        apiGet("/v1/hcm/dashboard-summary").then(function (payload) {
            if (!payload || payload.success !== true) {
                return;
            }
            var data = payload.data || {};
            var executive = data.executive || {};
            var attendanceToday = executive.attendanceToday || {};
            var payrollActiveMonth = executive.payrollActiveMonth || {};
            var payroll = data.payrollCommandCenter || {};
            var approval = data.approvalInbox || {};
            var signals = data.workforceAndAlerts || {};
            var anomaly = signals.attendanceAnomaly || {};
            var legacy = data.legacyWidgets || {};
            var legacyAttendance = legacy.attendanceOverview || {};

            var total = Number(executive.totalEmployees || 0);
            var active = Number(executive.activeEmployees || 0);
            var probation = Number(executive.probationEmployees || 0);
            var inactive = Number(executive.inactiveEmployees || 0);
            var pkwtDue = Number(executive.pkwtDueIn30Days || 0);

            // Existing data-attrs (used by admin-home-dashboard partial too)
            setText("[data-exec-active]", active);
            setText("[data-exec-probation]", probation);
            setText("[data-exec-pkwt-due]", pkwtDue);
            setText("[data-exec-att-present]", attendanceToday.present || 0);
            setText("[data-exec-att-late]", attendanceToday.late || 0);
            setText("[data-exec-att-missing]", attendanceToday.noCheckIn || 0);
            setText("[data-exec-leave-pending]", executive.pendingLeaveRequests || 0);
            setText("[data-exec-payroll-draft]", payrollActiveMonth.draft || 0);
            setText("[data-exec-payroll-paid]", payrollActiveMonth.paid || 0);
            setText("[data-exec-payroll-unpaid]", payrollActiveMonth.unpaid || 0);

            setText("[data-payroll-period-status]", payroll.periodStatus || "-");
            setText("[data-payroll-run-status]", payroll.latestRunStatus || "-");
            setText("[data-payroll-run-payment-status]", payroll.latestRunPaymentStatus || "-");
            setText("[data-payroll-line-count]", payroll.employeeLineCount || 0);

            setText("[data-approval-leave]", approval.pendingLeaveRequest || 0);
            setText("[data-approval-overtime]", approval.pendingOvertimeRequest || 0);
            setText("[data-approval-resign-termination]", approval.pendingResignationOrTermination || 0);
            setText("[data-approval-promotion]", approval.pendingPromotionReview || 0);

            setText("[data-signal-joiner]", signals.joinerThisMonth || 0);
            setText("[data-signal-resignation]", signals.resignationThisMonth || 0);
            setText("[data-signal-promotion]", signals.promotionThisMonth || 0);
            setText("[data-signal-overtime-hours]", toHours(signals.overtimeTotalMinutesThisMonth));
            setText("[data-signal-anomaly-missing]", anomaly.clockInMissing || 0);
            setText("[data-signal-anomaly-double]", anomaly.doubleShift || 0);

            // Attendance overview card
            setText("[data-legacy-attendance-total]", legacyAttendance.totalAttendance || 0);
            setText("[data-legacy-attendance-present-pct]", (legacyAttendance.presentPct || 0) + "%");
            setText("[data-legacy-attendance-late-pct]", (legacyAttendance.latePct || 0) + "%");
            setText("[data-legacy-attendance-permission-pct]", (legacyAttendance.permissionPct || 0) + "%");
            setText("[data-legacy-attendance-absent-pct]", (legacyAttendance.absentPct || 0) + "%");
            setText("[data-legacy-attendance-absent-total]", legacyAttendance.absentTotal || 0);
            updateAttendanceChart(legacyAttendance);

            // Top performer card
            setText("[data-legacy-top-performer-name]", (legacy.topPerformer || {}).name || "Employee");
            setText("[data-legacy-top-performer-role]", (legacy.topPerformer || {}).role || "Team Member");
            setText("[data-legacy-top-performer-score]", ((legacy.topPerformer || {}).score || 0) + "%");

            renderDepartmentBreakdown(legacy.departmentBreakdown || []);
            updateDepartmentChart(legacy.departmentBreakdown || []);
            renderClockList(legacy.clockInOut || []);
            renderLateList(legacy.lateEmployees || []);
            renderEmployees(legacy.employees || []);
            renderInvoices(legacy.invoices || []);
            renderActivities(legacy.recentActivities || []);
            renderBirthdays("[data-legacy-birthdays-today]", (legacy.birthdays || {}).today || [], "Tidak ada ulang tahun hari ini.");
            renderBirthdays("[data-legacy-birthdays-tomorrow]", (legacy.birthdays || {}).tomorrow || [], "Tidak ada ulang tahun besok.");
            var extraTomorrow = document.querySelector("[data-legacy-birthdays-tomorrow-extra]");
            if (extraTomorrow) {
                extraTomorrow.remove();
            }

            // Legacy index dashboard additional attrs
            setText("[data-exec-total-employees]", total);
            setText("[data-legacy-att-active]", active);
            setText("[data-exec-probation-card]", probation);
            setText("[data-exec-pkwt-due-card]", pkwtDue);
            setText("[data-legacy-inactive]", inactive);

            // Percentage labels for Employee Status section
            if (total > 0) {
                var activePct = pct(active, total);
                var probationPct = pct(probation, total);
                var inactivePct = pct(inactive, total);
                var pkwtPct = pct(pkwtDue, total);
                setText("[data-legacy-active-pct]", "(" + activePct.toFixed(0) + "%)");
                setText("[data-legacy-probation-pct]", "(" + probationPct.toFixed(0) + "%)");
                setText("[data-legacy-inactive-pct]", "(" + inactivePct.toFixed(0) + "%)");
                setText("[data-legacy-pkwt-pct]", "(" + pkwtPct.toFixed(0) + "%)");

                // Update progress bar widths
                setWidth("[data-progress-active]", activePct);
                setWidth("[data-progress-probation]", probationPct);
                setWidth("[data-progress-inactive]", inactivePct);
                setWidth("[data-progress-pkwt]", pkwtPct);
            }
        }).catch(function (err) {
            var status = err && err.status ? err.status : (err && err.response ? err.response.status : 0);
            var data = err && err.data ? err.data : (err && err.response ? err.response.data : null);
            if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                return;
            }
        });
    }

    function downloadDashboardExport(format) {
        var token = (window.AuthApi && typeof window.AuthApi.getToken === "function") ? window.AuthApi.getToken() : null;
        var tenant = (window.AuthApi && typeof window.AuthApi.getTenantContext === "function")
            ? (window.AuthApi.getTenantContext() || {})
            : {};

        var headers = {
            Accept: format === "csv" ? "text/csv" : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        };

        if (token) {
            headers.Authorization = "Bearer " + token;
        }
        if (tenant.companyCode) {
            headers["X-Company-Code"] = tenant.companyCode;
        }
        if (tenant.companyId) {
            headers["X-Company-Id"] = String(tenant.companyId);
        }

        return fetch("/v1/hcm/dashboard-summary/export?format=" + encodeURIComponent(format), {
            method: "GET",
            headers: headers,
            credentials: "same-origin"
        }).then(function (response) {
            if (!response.ok) {
                return response.text().then(function (text) {
                    throw new Error(text || ("Export gagal dengan status " + response.status));
                });
            }

            return response.blob().then(function (blob) {
                var blobUrl = window.URL.createObjectURL(blob);
                var a = document.createElement("a");
                a.href = blobUrl;
                a.download = "dashboard-summary." + (format === "csv" ? "csv" : "xlsx");
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(blobUrl);
            });
        });
    }

    function bindDashboardExportActions() {
        document.addEventListener("click", function (event) {
            var trigger = event.target.closest("[data-index-dashboard-export]");
            if (!trigger) return;

            event.preventDefault();
            var rawFormat = String(trigger.getAttribute("data-index-dashboard-export") || "xlsx").toLowerCase();
            var format = rawFormat === "csv" ? "csv" : "xlsx";
            downloadDashboardExport(format).catch(function (_error) {
                // Fallback to plain navigation if fetch/blob flow is blocked by browser policy.
                window.location.assign("/v1/hcm/dashboard-summary/export?format=" + encodeURIComponent(format));
            });
        });
    }

    function init() {
        var path = String(window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path !== "/index") {
            return;
        }
        bindDashboardExportActions();
        loadDashboardSummary();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
