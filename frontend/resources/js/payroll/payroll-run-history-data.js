(function (window, document) {
    "use strict";

    var page = 1;
    var totalPages = 1;

    function apiRequest(method, url) {
        var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        var authHeaders = { Accept: 'application/json' };
        if (token) { authHeaders['Authorization'] = 'Bearer ' + token; }
        if (window.axios) {
            return window.axios({ method: method, url: url, headers: authHeaders, withCredentials: true }).then(function (res) {
                return res.data;
            });
        }
        return fetch(url, { method: method, credentials: 'same-origin', headers: authHeaders }).then(function (res) {
            return res.json().then(function (payload) {
                if (!res.ok) {
                    throw { status: res.status, data: payload };
                }
                return payload;
            });
        });
    }

    function esc(v) {
        return String(v == null ? "" : v)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;");
    }

    function runStatusBadge(status) {
        if (status === "finalized") return '<span class="badge bg-success">FINALIZED</span>';
        if (status === "void") return '<span class="badge bg-danger">VOID</span>';
        return '<span class="badge bg-warning text-dark">DRAFT</span>';
    }

    function paymentStatusBadge(status) {
        if (status === "paid") return '<span class="badge bg-success">PAID</span>';
        if (status === "partial") return '<span class="badge bg-warning text-dark">PARTIAL</span>';
        return '<span class="badge bg-secondary">UNPAID</span>';
    }

    function paymentStatusText(status) {
        if (status === "paid") return "PAID";
        if (status === "partial") return "PARTIAL";
        return "UNPAID";
    }

    function formatIdr(value) {
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(Number(value || 0));
    }

    function lineAffectsNetPay(line) {
        if (!line || typeof line !== "object") return true;
        if (line.affectsNetPay === false) return false;
        if (line.meta && line.meta.affectsNetPay === false) return false;
        return true;
    }

    function isOvertimeLine(line) {
        if (!line || typeof line !== "object") return false;
        var componentCode = String(line.componentCode || line.component_code || "").toLowerCase();
        var componentName = String(line.componentName || line.component_name || "").toLowerCase();
        var category = String(line.category || "").toLowerCase();
        return category === "overtime" || componentCode === "upah_lembur" || componentName.indexOf("lembur") !== -1;
    }

    function buildEmployeeOvertimeMap(lines) {
        return (Array.isArray(lines) ? lines : []).reduce(function (acc, line) {
            var userId = Number(line && (line.userId || line.user_id));
            if (!Number.isFinite(userId) || userId <= 0 || !lineAffectsNetPay(line) || !isOvertimeLine(line)) {
                return acc;
            }

            acc[userId] = roundCurrency((acc[userId] || 0) + Number(line.amount || 0));
            return acc;
        }, {});
    }

    function roundCurrency(value) {
        return Math.round(Number(value || 0) * 100) / 100;
    }

    function getFilters() {
        var y = document.querySelector("[data-payroll-history-year]");
        var m = document.querySelector("[data-payroll-history-month]");
        var s = document.querySelector("[data-payroll-history-status]");
        return {
            year: y ? y.value : "",
            month: m ? m.value : "",
            status: s ? s.value : "",
        };
    }

    function buildUrl() {
        var f = getFilters();
        var q = new URLSearchParams();
        q.set("page", String(page));
        q.set("perPage", "20");
        if (f.year) q.set("periodYear", f.year);
        if (f.month) q.set("periodMonth", f.month);
        if (f.status) q.set("status", f.status);
        return "/v1/hcm/payroll-runs/history?" + q.toString();
    }

    function renderRows(rows) {
        var body = document.querySelector("[data-payroll-history-body]");
        if (!body) return;

        if (!rows || !rows.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada history payroll run.</td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (r) {
            var periodLabel = r.period ? (String(r.period.periodMonth).padStart(2, "0") + "/" + r.period.periodYear) : "-";
            var trail = Array.isArray(r.auditTrail) ? r.auditTrail : [];
            var trailText = trail.map(function (e) {
                return esc(e.event) + (e.at ? " @ " + esc(e.at) : "");
            }).join("<br>");
            return "<tr>" +
                "<td><div class=\"fw-semibold\">Run #" + esc(r.id) + "</div><div class=\"small text-muted\">" + esc(periodLabel) + "</div></td>" +
                "<td>" + runStatusBadge(r.status) + "</td>" +
                "<td>" + paymentStatusBadge(r.paymentStatus) + "</td>" +
                "<td>" + esc(String(r.paidEmployeeCount || 0)) + " / " + esc(String(r.employeeCount || 0)) + "</td>" +
                '<td class="text-end fw-medium">' + esc(formatIdr(r.totals && r.totals.netPay)) + "</td>" +
                "<td class=\"small text-muted\">" + (trailText || "-") + "</td>" +
                "<td class=\"text-end\"><button type=\"button\" class=\"btn btn-sm btn-outline-primary\" data-payroll-history-detail-open=\"" + esc(String(r.id)) + "\">Detail</button></td>" +
            "</tr>";
        }).join("");
    }

    function renderPagination(meta) {
        var foot = document.querySelector("[data-payroll-history-pagination]");
        var info = document.querySelector("[data-payroll-history-page-info]");
        if (!foot) return;

        var p = meta && meta.pagination ? meta.pagination : null;
        if (!p || (p.totalPages || 1) <= 1) {
            foot.style.display = "none";
            return;
        }
        foot.style.display = "";
        totalPages = p.totalPages || 1;
        if (info) {
            var from = p.total === 0 ? 0 : ((p.page - 1) * p.perPage + 1);
            var to = Math.min(p.page * p.perPage, p.total);
            info.textContent = "Menampilkan " + from + "-" + to + " dari " + p.total;
        }

        var prev = foot.querySelector("[data-payroll-history-prev]");
        var next = foot.querySelector("[data-payroll-history-next]");
        if (prev) prev.disabled = p.page <= 1;
        if (next) next.disabled = p.page >= totalPages;
    }

    function load() {
        var body = document.querySelector("[data-payroll-history-body]");
        if (body) {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Memuat...</td></tr>';
        }
        apiRequest("get", buildUrl()).then(function (resp) {
            if (!resp || resp.success !== true) {
                throw new Error("BAD_RESPONSE");
            }
            renderRows(resp.data || []);
            renderPagination(resp.meta || {});
        }).catch(function () {
            if (body) {
                body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Gagal memuat history payroll.</td></tr>';
            }
        });
    }

    function openDetail(runId) {
        var modalEl = document.getElementById("payroll_history_detail_modal");
        var target = document.querySelector("[data-payroll-history-detail]");
        if (!modalEl || !target) return;

        target.textContent = "Memuat detail...";
        apiRequest("get", "/v1/hcm/payroll-runs/" + encodeURIComponent(String(runId))).then(function (resp) {
            if (!resp || resp.success !== true) {
                throw new Error("BAD_RESPONSE");
            }
            var run = (resp.data && resp.data.run) || {};
            var lines = (resp.data && resp.data.lines) || [];
            var trail = (resp.data && resp.data.auditTrail) || [];
            var summary = (resp.data && resp.data.summary) || {};
            var totals = summary.totals || {};
            var employeeBreakdown = Array.isArray(summary.employeeBreakdown) ? summary.employeeBreakdown : [];
            var componentBreakdown = Array.isArray(summary.componentBreakdown) ? summary.componentBreakdown : [];
            var overtimeByUserId = buildEmployeeOvertimeMap(lines);
            var overtimeSummary = summary.overtime || {};
            var overtimeTotal = roundCurrency(totals.overtimeTotal != null ? totals.overtimeTotal : overtimeSummary.amountTotal);

            var employeeRows = employeeBreakdown.length
                ? ('<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Karyawan</th><th class="text-end">Penghasilan</th><th class="text-end">Overtime</th><th class="text-end">Potongan</th><th class="text-end">Net Pay</th><th class="text-end">Baris</th></tr></thead><tbody>' +
                    employeeBreakdown.map(function (row) {
                        var overtimeAmount = row.overtimeTotal != null ? row.overtimeTotal : overtimeByUserId[row.userId];
                        return '<tr><td>' + esc(row.userName || ("User #" + row.userId)) + '</td><td class="text-end">' + esc(formatIdr(row.earningsTotal)) + '</td><td class="text-end text-info">' + esc(formatIdr(overtimeAmount)) + '</td><td class="text-end">' + esc(formatIdr(row.deductionsTotal)) + '</td><td class="text-end fw-medium">' + esc(formatIdr(row.netPay)) + '</td><td class="text-end">' + esc(String(row.lineCount || 0)) + '</td></tr>';
                    }).join('') +
                    '</tbody></table></div>')
                : '<p class="text-muted small mb-0">Belum ada breakdown karyawan.</p>';

            var componentRows = componentBreakdown.length
                ? ('<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Komponen</th><th>Jenis</th><th class="text-end">Total</th><th class="text-end">Baris</th></tr></thead><tbody>' +
                    componentBreakdown.map(function (row) {
                        return '<tr><td>' + esc(row.componentName || row.componentCode || '-') + '</td><td>' + esc(row.kind || '-') + '</td><td class="text-end">' + esc(formatIdr(row.amountTotal)) + '</td><td class="text-end">' + esc(String(row.lineCount || 0)) + '</td></tr>';
                    }).join('') +
                    '</tbody></table></div>')
                : '<p class="text-muted small mb-0">Belum ada breakdown komponen.</p>';

            var detailHtml = "" +
                "<div class=\"mb-3\"><strong>Run #" + esc(run.id || "-") + "</strong><br>" +
                "Status: " + runStatusBadge(run.status) + " <span class=\"mx-1 text-muted\">|</span> Payment: " + paymentStatusBadge(run.paymentStatus) + "<br>" +
                "Finalized by: " + esc(run.finalizedByUserName || "-") + "</div>" +
                '<div class="row g-2 mb-3">' +
                '<div class="col-md-3"><div class="border rounded p-2"><div class="text-muted small">Total Penghasilan</div><div class="fw-semibold">' + esc(formatIdr(totals.earningsTotal)) + '</div></div></div>' +
                '<div class="col-md-3"><div class="border rounded p-2"><div class="text-muted small">Total Overtime</div><div class="fw-semibold text-info">' + esc(formatIdr(overtimeTotal)) + '</div></div></div>' +
                '<div class="col-md-3"><div class="border rounded p-2"><div class="text-muted small">Total Potongan</div><div class="fw-semibold">' + esc(formatIdr(totals.deductionsTotal)) + '</div></div></div>' +
                '<div class="col-md-3"><div class="border rounded p-2"><div class="text-muted small">Total Net Pay</div><div class="fw-semibold">' + esc(formatIdr(totals.netPay)) + '</div></div></div>' +
                '<div class="col-md-3"><div class="border rounded p-2"><div class="text-muted small">Jumlah Baris</div><div class="fw-semibold">' + esc(String(totals.lineCount || lines.length || 0)) + '</div></div></div>' +
                '</div>' +
                '<div class="small text-muted mb-3">Overtime ditampilkan terpisah agar histori run konsisten dengan reconciliation export dan checklist operasional payroll.</div>' +
                '<div class="small text-muted mb-3">Ringkasan status: ' + esc(String(run.status || '-').toUpperCase()) + ' / ' + esc(paymentStatusText(run.paymentStatus)) + '</div>' +
                "<div class=\"mb-3\"><strong>Audit Trail</strong><br>" + trail.map(function (t) {
                    return esc(t.event || "-") + (t.at ? " @ " + esc(t.at) : "");
                }).join("<br>") + "</div>" +
                '<div class="mb-3"><strong>Breakdown per karyawan</strong>' + employeeRows + '</div>' +
                '<div><strong>Breakdown per komponen</strong>' + componentRows + '</div>';
            target.innerHTML = detailHtml;
            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        }).catch(function () {
            target.textContent = "Gagal memuat detail history.";
            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });
    }

    function bind() {
        var panel = document.querySelector("[data-payroll-run-history-panel]");
        if (!panel || panel.getAttribute("data-bound") === "1") return;
        panel.setAttribute("data-bound", "1");

        var refresh = panel.querySelector("[data-payroll-history-refresh]");
        if (refresh) {
            refresh.addEventListener("click", function () {
                page = 1;
                load();
            });
        }

        var prev = panel.querySelector("[data-payroll-history-prev]");
        var next = panel.querySelector("[data-payroll-history-next]");
        if (prev) {
            prev.addEventListener("click", function () {
                if (page > 1) {
                    page -= 1;
                    load();
                }
            });
        }
        if (next) {
            next.addEventListener("click", function () {
                if (page < totalPages) {
                    page += 1;
                    load();
                }
            });
        }

        panel.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-payroll-history-detail-open]");
            if (!btn) return;
            e.preventDefault();
            var runId = Number(btn.getAttribute("data-payroll-history-detail-open") || 0);
            if (runId > 0) {
                openDetail(runId);
            }
        });

        load();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bind);
    } else {
        bind();
    }
})(window, document);
