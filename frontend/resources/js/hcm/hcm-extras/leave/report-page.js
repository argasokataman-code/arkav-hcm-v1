export function bindLeaveReport(deps) {
    var apiRequest = deps.apiRequest;
    var esc = deps.esc;
    var notify = deps.notify;
    var formatApiError = deps.formatApiError;
    var tbody = document.querySelector("[data-leave-report-body]");
    var leaveChart = null;

    function renderLeaveChart(summary, byStatus) {
        var chartEl = document.getElementById("leave-report-chart");
        if (!chartEl) {
            return;
        }
        var statusMap = byStatus || {};
        var approved = parseInt((statusMap.approved && statusMap.approved.count) || 0, 10) || 0;
        var pending = parseInt((statusMap.pending && statusMap.pending.count) || 0, 10) || 0;
        var declined = parseInt((statusMap.declined && statusMap.declined.count) || 0, 10) || 0;
        var totalRequests = summary && summary.total_requests != null
            ? parseInt(summary.total_requests, 10) || 0
            : (summary && summary.totalRequests != null ? parseInt(summary.totalRequests, 10) || 0 : 0);
        var other = Math.max(totalRequests - approved - pending - declined, 0);
        var series = [approved, pending, declined, other];

        if (leaveChart && typeof leaveChart.destroy === "function") {
            leaveChart.destroy();
            leaveChart = null;
        }

        if (!window.ApexCharts) {
            chartEl.innerHTML = '<div class="text-muted small">Grafik belum tersedia.</div>';
            return;
        }

        if (series.reduce(function (a, b) { return a + b; }, 0) <= 0) {
            chartEl.innerHTML = '<div class="text-center text-muted small py-5">Belum ada data cuti untuk ditampilkan.</div>';
            return;
        }

        chartEl.innerHTML = "";
        leaveChart = new window.ApexCharts(chartEl, {
            chart: {
                type: "donut",
                height: 240,
            },
            series: series,
            labels: ["Approved", "Pending", "Declined", "Other"],
            colors: ["#0E9384", "#FFB534", "#E70D0D", "#6C757D"],
            legend: {
                position: "bottom",
            },
            dataLabels: {
                enabled: true,
            },
            stroke: {
                width: 1,
            },
        });
        leaveChart.render();
    }

    function renderSummary(summary, byStatus) {
        var statusMap = byStatus || {};
        var approved = statusMap.approved && statusMap.approved.count != null ? statusMap.approved.count : 0;
        var pending = statusMap.pending && statusMap.pending.count != null ? statusMap.pending.count : 0;
        var totalRequests = summary && summary.total_requests != null
            ? summary.total_requests
            : (summary && summary.totalRequests != null ? summary.totalRequests : 0);
        var totalDays = summary && summary.total_days != null
            ? summary.total_days
            : (summary && summary.totalDays != null ? summary.totalDays : 0);

        var totalEl = document.querySelector("[data-leave-report-total-requests]");
        var daysEl = document.querySelector("[data-leave-report-total-days]");
        var approvedEl = document.querySelector("[data-leave-report-approved]");
        var pendingEl = document.querySelector("[data-leave-report-pending]");
        if (totalEl) {
            totalEl.textContent = String(totalRequests || 0);
        }
        if (daysEl) {
            daysEl.textContent = String(totalDays || 0);
        }
        if (approvedEl) {
            approvedEl.textContent = String(approved || 0);
        }
        if (pendingEl) {
            pendingEl.textContent = String(pending || 0);
        }
        renderLeaveChart(summary || {}, statusMap);
    }

    function renderRows(rows) {
        if (!tbody) {
            return;
        }
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada data cuti.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (r) {
            var status = String(r.status || "pending");
            var badge = status === "approved" ? "success" : status === "declined" ? "danger" : "warning";
            return "<tr>"
                + "<td>" + esc(r.employeeName || "-") + "</td>"
                + "<td>" + esc(r.leaveType || "-") + "</td>"
                + "<td>" + esc(r.dateFrom || "-") + "</td>"
                + "<td>" + esc(r.dateTo || "-") + "</td>"
                + "<td>" + esc(String(r.days != null ? r.days : 0)) + "</td>"
                + '<td><span class="badge badge-' + badge + ' badge-xs">' + esc(status) + "</span></td>"
                + "</tr>";
        }).join("");
    }

    function fetchLiveLeaveReportPage(page, collected, firstMeta) {
        return apiRequest("get", "/v1/hcm/leave-requests?perPage=100&page=" + encodeURIComponent(String(page)), null)
            .then(function (payload) {
                if (!payload || payload.success !== true) {
                    return Promise.reject({ payload: payload });
                }

                var rows = Array.isArray(payload.data) ? payload.data : [];
                var meta = payload.meta || {};
                var pagination = meta.pagination || {};
                var totalPages = parseInt(pagination.totalPages, 10) || 1;
                var nextCollected = collected.concat(rows);
                var seedMeta = firstMeta || meta;

                if (page >= totalPages || rows.length < 1) {
                    return {
                        rows: nextCollected,
                        meta: seedMeta,
                    };
                }

                return fetchLiveLeaveReportPage(page + 1, nextCollected, seedMeta);
            });
    }

    function loadLiveReport() {
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Memuat data cuti...</td></tr>';
        }
        fetchLiveLeaveReportPage(1, [], null)
            .then(function (result) {
                var rows = Array.isArray(result && result.rows) ? result.rows : [];
                var meta = (result && result.meta) || {};
                renderRows(rows.map(function (item) {
                    return {
                        employeeName: item.employeeName,
                        leaveType: item.leaveTypeLabel || item.leaveType,
                        dateFrom: item.dateFrom,
                        dateTo: item.dateTo,
                        days: item.days,
                        status: item.status,
                    };
                }));
                var byStatus = {};
                rows.forEach(function (item) {
                    var key = String(item.status || "pending");
                    byStatus[key] = byStatus[key] || { count: 0 };
                    byStatus[key].count += 1;
                });
                var totalDays = rows.reduce(function (sum, item) {
                    return sum + (parseFloat(item.days || 0) || 0);
                }, 0);
                renderSummary({
                    totalRequests: meta.summary && meta.summary.totalRequests != null ? meta.summary.totalRequests : rows.length,
                    totalDays: totalDays,
                }, byStatus);
            })
            .catch(function (err) {
                notify(formatApiError(err && err.data, err && err.status), true);
                renderRows([]);
                renderSummary({}, {});
            });
    }

    function reload() {
        loadLiveReport();
    }

    document.addEventListener("click", function (event) {
        var trigger = event.target && event.target.closest ? event.target.closest("[data-leave-report-load]") : null;
        if (!trigger) {
            return;
        }
        event.preventDefault();
        reload();
    });

    reload();
}