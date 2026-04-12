(function (window, document) {
    "use strict";

    function setText(selector, value) {
        var el = document.querySelector(selector);
        if (!el) return;
        el.textContent = String(value == null ? "0" : value);
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

    function loadDashboardSummary() {
        var root = document.querySelector("[data-admin-home-dashboard]");
        if (!root) return;

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

            setText("[data-exec-active]", executive.activeEmployees || 0);
            setText("[data-exec-probation]", executive.probationEmployees || 0);
            setText("[data-exec-pkwt-due]", executive.pkwtDueIn30Days || 0);
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
        }).catch(function (err) {
            var status = err && err.status ? err.status : (err && err.response ? err.response.status : 0);
            var data = err && err.data ? err.data : (err && err.response ? err.response.data : null);
            if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                return;
            }
        });
    }

    function init() {
        var path = String(window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path !== "/index") {
            return;
        }
        loadDashboardSummary();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
