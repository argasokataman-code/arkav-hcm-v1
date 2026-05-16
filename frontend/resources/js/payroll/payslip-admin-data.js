(function (window, document) {
    "use strict";

    var _slips = [];
    var _sendingRowKey = null;

    function getSourceMode() {
        var sel = document.querySelector("[data-payslip-admin-source]");
        var mode = sel ? String(sel.value || "live").toLowerCase() : "live";
        return mode === "archive" ? "archive" : "live";
    }

    function getSnapshotId() {
        var input = document.querySelector("[data-payslip-admin-snapshot-id]");
        if (!input) return 0;
        var id = parseInt(String(input.value || "0"), 10);
        return Number.isFinite(id) && id > 0 ? id : 0;
    }

    function syncSourceUi() {
        var mode = getSourceMode();
        var wrap = document.querySelector("[data-payslip-admin-snapshot-wrap]");
        var year = document.querySelector("[data-payslip-admin-year]");
        var month = document.querySelector("[data-payslip-admin-month]");
        var badge = document.querySelector("[data-payslip-admin-source-badge]");

        if (wrap) {
            wrap.classList.toggle("d-none", mode !== "archive");
        }
        if (year) {
            year.disabled = mode === "archive";
        }
        if (month) {
            month.disabled = mode === "archive";
        }
        if (badge) {
            var suffix = mode === "archive" && getSnapshotId() > 0 ? (" #" + String(getSnapshotId())) : "";
            badge.textContent = mode === "archive" ? ("Source: Archive" + suffix) : "Source: Live";
        }
    }

    function formatIdr(value) {
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(Number(value || 0));
    }

    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;");
    }

    function formatPeriod(year, month) {
        if (!year || !month) return "-";
        return String(month).padStart(2, "0") + "/" + String(year);
    }

    function toast(msg, danger) {
        if (window.ArcavUi && window.ArcavUi.showToast) {
            window.ArcavUi.showToast(msg, danger ? "danger" : "success");
            return;
        }
        var c = document.querySelector("[data-hcm-toast-container]") || (function () {
            var el = document.createElement("div");
            el.style.cssText = "position:fixed;top:16px;right:16px;z-index:3000";
            el.setAttribute("data-hcm-toast-container", "1");
            document.body.appendChild(el);
            return el;
        }());
        var t = document.createElement("div");
        t.className = "alert " + (danger ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = msg;
        c.appendChild(t);
        window.setTimeout(function () { t.remove(); }, 2600);
    }

    function apiRequest(method, url, body) {
        var headers = { Accept: "application/json" };
        if (body && typeof body === "object") {
            headers["Content-Type"] = "application/json";
        }
        if (window.axios) {
            return window.axios({ method: method, url: url, headers: headers, data: body, withCredentials: true })
                .then(function (r) { return r.data; })
                .catch(function (err) {
                    var d = err && err.response ? err.response.data : null;
                    if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi) {
                        if (window.AuthApi.handleUnauthorizedFromApi(err && err.response ? err.response.status : 0, d)) {
                            return null;
                        }
                    }
                    return Promise.reject({ status: err && err.response ? err.response.status : 0, data: d });
                });
        }
        return fetch(url, {
            method: method.toUpperCase(),
            headers: headers,
            credentials: "same-origin",
            body: body ? JSON.stringify(body) : undefined,
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) return Promise.reject({ status: res.status, data: data });
                return data;
            });
        });
    }

    function formatApiError(data, status) {
        if (data && data.error && data.error.message) return data.error.message;
        if (data && data.message) return data.message;
        return "Error " + (status || "");
    }

    function getSelectedRowKeys() {
        return Array.from(document.querySelectorAll("[data-payslip-admin-row-check]:checked"))
            .map(function (el) { return String(el.value || ""); })
            .filter(Boolean);
    }

    function refreshSendBtn() {
        var btn = document.querySelector("[data-payslip-admin-send-selected]");
        if (!btn) return;
        var selected = getSelectedRowKeys();
        btn.disabled = selected.length === 0;
        btn.textContent = selected.length > 0
            ? "Kirim Email Terpilih (" + selected.length + ")"
            : "Kirim Email Terpilih";
    }

    function paymentBadge(status) {
        var s = String(status || "unpaid").toLowerCase();
        if (s === "paid") return '<span class="badge bg-success-subtle text-success border border-success-subtle">Paid</span>';
        if (s === "partial") return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Partial</span>';
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Unpaid</span>';
    }

    function runBadge(status) {
        var s = String(status || "-").toLowerCase();
        if (s === "finalized") {
            return '<span class="badge bg-success-subtle text-success border border-success-subtle">FINALIZED</span>';
        }
        if (s === "draft") {
            return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">DRAFT</span>';
        }
        if (s === "archive") {
            return '<span class="badge bg-info-subtle text-info border border-info-subtle">ARCHIVE</span>';
        }
        return '<span class="badge bg-light text-dark border">' + esc(String(status || "-")) + "</span>";
    }

    function emailDeliveryBadge(delivery) {
        var status = String(delivery && delivery.status ? delivery.status : "not_sent").toLowerCase();
        var attemptedAt = delivery && delivery.attemptedAt ? String(delivery.attemptedAt) : "";
        var tooltip = attemptedAt ? (' title="Last attempt: ' + esc(attemptedAt) + '"') : "";
        if (status === "sent") {
            return '<span class="badge bg-success-subtle text-success border border-success-subtle"' + tooltip + '>Sent</span>';
        }
        if (status === "failed") {
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"' + tooltip + '>Failed</span>';
        }
        return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Not sent</span>';
    }

    function renderRows(slips) {
        var body = document.querySelector("[data-payslip-admin-body]");
        if (!body) return;
        if (!slips || !slips.length) {
            body.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Tidak ada slip untuk filter saat ini.</td></tr>';
            return;
        }
        body.innerHTML = slips.map(function (s) {
            var delivery = s.emailDelivery || { status: "not_sent", canResend: false, lastError: null };
            var isFailed = String(delivery.status || "").toLowerCase() === "failed";
            var isSent = String(delivery.status || "").toLowerCase() === "sent";
            var sendBtnClass = isFailed ? "btn-outline-warning" : "btn-outline-secondary";
            var sendBtnIcon = isFailed ? "ti ti-refresh" : "ti ti-mail";
            var sendBtnLabel = isFailed ? "Resend" : (isSent ? "Sent" : "Email");
            var sendBtnDisabled = isSent ? " disabled" : "";
            var failedHint = isFailed && delivery.lastError
                ? ('<div class="small text-danger mt-1">' + esc(String(delivery.lastError)) + '</div>')
                : "";

            return (
                "<tr>" +
                '<td><div class="form-check form-check-md mb-0">' +
                '<input class="form-check-input" type="checkbox" value="' + esc(String(s.rowKey)) + '" data-payslip-admin-row-check>' +
                "</div></td>" +
                '<td><span class="fw-semibold">' + esc(formatPeriod(s.periodYear, s.periodMonth)) + "</span></td>" +
                "<td>" + runBadge(s.runStatus) + "</td>" +
                "<td>" + paymentBadge(s.paymentStatus) + "</td>" +
                "<td><div class=\"fw-medium\">" + esc(s.employeeName) + "</div>" +
                (s.email ? '<div class="text-muted small">' + esc(s.email) + "</div>" : "") +
                "</td>" +
                "<td><div>" + esc(s.designation) + "</div>" +
                '<div class="text-muted small">' + esc(s.team) + "</div></td>" +
                '<td class="text-end">' + formatIdr(s.totals.earningsTotal) + "</td>" +
                '<td class="text-end">' + formatIdr(s.totals.deductionsTotal) + "</td>" +
                '<td class="text-end fw-semibold text-primary">' + formatIdr(s.totals.netPay) + "</td>" +
                '<td><div>' + emailDeliveryBadge(delivery) + '</div>' + failedHint + '</td>' +
                '<td class="text-end">' +
                '<div class="btn-group btn-group-sm" role="group">' +
                '<button type="button" class="btn btn-outline-primary" data-payslip-admin-preview="' + esc(String(s.rowKey)) + '"><i class="ti ti-eye me-1"></i>Preview</button>' +
                '<button type="button" class="btn ' + sendBtnClass + '" data-payslip-admin-send-one="' + esc(String(s.rowKey)) + '"' + sendBtnDisabled + '><i class="' + sendBtnIcon + ' me-1"></i>' + sendBtnLabel + '</button>' +
                "</div>" +
                "</td></tr>"
            );
        }).join("");
    }

    function updateSummary(slips, apiSummary) {
        var summaryEl = document.querySelector("[data-payslip-admin-summary]");
        var countEl = document.querySelector("[data-payslip-admin-count]");
        var employeesEl = document.querySelector("[data-payslip-admin-employees]");
        var periodsEl = document.querySelector("[data-payslip-admin-periods]");
        var netEl = document.querySelector("[data-payslip-admin-total-net]");

        if (summaryEl) summaryEl.style.removeProperty("display");
        if (countEl) countEl.textContent = String(apiSummary && apiSummary.totalRows ? apiSummary.totalRows : slips.length);
        if (employeesEl) {
            var employees = apiSummary && apiSummary.totalEmployees
                ? apiSummary.totalEmployees
                : new Set(slips.map(function (r) { return r.userId; })).size;
            employeesEl.textContent = String(employees);
        }
        if (periodsEl) {
            var periods = apiSummary && apiSummary.totalPeriods
                ? apiSummary.totalPeriods
                : new Set(slips.map(function (r) { return String(r.periodYear) + "-" + String(r.periodMonth); })).size;
            periodsEl.textContent = String(periods);
        }
        if (netEl) {
            netEl.textContent = formatIdr(slips.reduce(function (s, r) { return s + (r.totals.netPay || 0); }, 0));
        }
    }

    function normalizeArchivePayrollRows(snapshot) {
        var moduleData = snapshot && snapshot.dataByModule ? snapshot.dataByModule.payroll : null;
        if (!moduleData || typeof moduleData !== "object") {
            return [];
        }

        var keys = Object.keys(moduleData);
        var out = [];
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (key.indexOf("user_") !== 0) {
                continue;
            }
            var item = moduleData[key] || {};
            var userId = Number(item.user_id || 0);
            var gross = Number(item.total_gross || 0);
            var deductions = Number(item.total_deductions || 0);
            var net = Number(item.total_net || (gross - deductions));

            out.push({
                rowKey: "archive-user-" + String(userId || i + 1),
                periodYear: snapshot.periodEnd ? Number(String(snapshot.periodEnd).slice(0, 4)) : null,
                periodMonth: snapshot.periodEnd ? Number(String(snapshot.periodEnd).slice(5, 7)) : null,
                runStatus: "archive",
                paymentStatus: "paid",
                employeeName: item.user_name || "Unknown",
                email: "",
                designation: "Archive Snapshot",
                team: "-",
                userId: userId,
                totals: {
                    earningsTotal: gross,
                    deductionsTotal: deductions,
                    netPay: net,
                },
            });
        }

        return out;
    }

    function loadArchiveSlips(snapshotId) {
        var errEl = document.querySelector("[data-payslip-admin-error]");
        var runInfoEl = document.querySelector("[data-payslip-admin-run-info]");
        var body = document.querySelector("[data-payslip-admin-body]");
        var selectAll = document.querySelector("[data-payslip-admin-select-all]");
        var summaryEl = document.querySelector("[data-payslip-admin-summary]");

        if (errEl) { errEl.classList.add("d-none"); errEl.textContent = ""; }
        if (body) body.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Memuat snapshot archive…</td></tr>';
        if (summaryEl) summaryEl.style.setProperty("display", "none", "important");
        if (runInfoEl) runInfoEl.textContent = "";
        if (selectAll) selectAll.checked = false;
        refreshSendBtn();

        apiRequest("get", "/v1/hcm/reports/snapshots/" + encodeURIComponent(String(snapshotId)))
            .then(function (resp) {
                if (!resp || resp.success !== true || !resp.data) {
                    throw { status: 0, data: resp };
                }
                var snapshot = resp.data;
                if (String(snapshot.reportType || "").toLowerCase() !== "payroll") {
                    throw {
                        status: 422,
                        data: {
                            error: {
                                message: "Snapshot ini bukan payroll report.",
                            },
                        },
                    };
                }
                if (String(snapshot.status || "").toLowerCase() !== "completed") {
                    throw {
                        status: 422,
                        data: {
                            error: {
                                message: "Snapshot payroll belum siap digunakan.",
                            },
                        },
                    };
                }
                _slips = normalizeArchivePayrollRows(snapshot);

                if (runInfoEl) {
                    runInfoEl.textContent = _slips.length
                        ? (_slips.length + " baris payroll dari snapshot #" + snapshotId)
                        : "Tidak ada data payroll pada snapshot ini.";
                }

                renderRows(_slips);
                if (_slips.length) {
                    updateSummary(_slips, {
                        totalRows: _slips.length,
                        totalEmployees: _slips.length,
                        totalPeriods: 1,
                    });
                }
            })
            .catch(function (err) {
                if (err === null) return;
                var msg = formatApiError(err && err.data, err && err.status) || "Gagal memuat snapshot payroll.";
                if (errEl) { errEl.classList.remove("d-none"); errEl.textContent = msg; }
                if (body) body.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">' + esc(msg) + "</td></tr>";
            });
    }

    function loadSlips() {
        var yearEl = document.querySelector("[data-payslip-admin-year]");
        var monthEl = document.querySelector("[data-payslip-admin-month]");
        var errEl = document.querySelector("[data-payslip-admin-error]");
        var runInfoEl = document.querySelector("[data-payslip-admin-run-info]");
        var body = document.querySelector("[data-payslip-admin-body]");
        var selectAll = document.querySelector("[data-payslip-admin-select-all]");
        var summaryEl = document.querySelector("[data-payslip-admin-summary]");

        var mode = getSourceMode();
        var snapshotId = getSnapshotId();
        var year = parseInt(yearEl && yearEl.value ? yearEl.value : 0, 10);
        var month = parseInt(monthEl && monthEl.value ? monthEl.value : 0, 10);

        if (mode === "archive") {
            if (!snapshotId) {
                if (errEl) { errEl.classList.remove("d-none"); errEl.textContent = "Snapshot ID wajib diisi untuk mode Archive."; }
                if (body) body.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Isi Snapshot ID lalu klik Muat.</td></tr>';
                return;
            }
            loadArchiveSlips(snapshotId);
            return;
        }

        if (errEl) { errEl.classList.add("d-none"); errEl.textContent = ""; }
        if (body) body.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Memuat…</td></tr>';
        if (summaryEl) summaryEl.style.setProperty("display", "none", "important");
        if (runInfoEl) runInfoEl.textContent = "";
        if (selectAll) selectAll.checked = false;
        refreshSendBtn();

        var params = [];
        if (year > 0) params.push("periodYear=" + encodeURIComponent(String(year)));
        if (month > 0) params.push("periodMonth=" + encodeURIComponent(String(month)));
        var url = "/v1/hcm/payroll/admin-slips" + (params.length ? ("?" + params.join("&")) : "");

        apiRequest("get", url).then(function (resp) {
            if (!resp || resp.success !== true) {
                throw { status: 0, data: resp };
            }
            var data = resp.data || {};
            _slips = data.rows || [];

            if (runInfoEl) {
                runInfoEl.textContent = _slips.length
                    ? (_slips.length + " slip lintas periode ditemukan")
                    : "Tidak ada slip untuk filter saat ini.";
            }

            renderRows(_slips);
            if (_slips.length) updateSummary(_slips, data.summary || {});
        }).catch(function (err) {
            if (err === null) return;
            var msg = formatApiError(err && err.data, err && err.status) || "Gagal memuat data payslip.";
            if (errEl) { errEl.classList.remove("d-none"); errEl.textContent = msg; }
            if (body) body.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">' + esc(msg) + "</td></tr>";
        });
    }

    function buildPreviewHtml(slip) {
        var month = Number(slip.periodMonth || 0);
        var year = Number(slip.periodYear || 0);
        var periodLabel = String(month).padStart(2, "0") + "/" + String(year).padStart(4, "0");
        var runStatus = String(slip.runStatus || "-");
        var orgMeta = document.querySelector("[data-payslip-org-meta]");
        var appName = (orgMeta && orgMeta.getAttribute("data-app-name") ? String(orgMeta.getAttribute("data-app-name") || "").trim() : "")
            || (window.APP_NAME && String(window.APP_NAME).trim())
            || "Arcav";
        var orgAddress = orgMeta && orgMeta.getAttribute("data-org-address")
            ? String(orgMeta.getAttribute("data-org-address") || "").trim()
            : "";
        var orgSubtitle = orgAddress || "Divisi SDM / Payroll";
        var earnings = Array.isArray(slip.earnings) ? slip.earnings : [];
        var deductions = Array.isArray(slip.deductions) ? slip.deductions : [];

        var formatIdrPdf = function (value) {
            var n = Number(value || 0);
            var fixed = n.toFixed(2);
            var parts = fixed.split(".");
            var integer = Number(parts[0]).toLocaleString("id-ID");
            return "Rp " + integer + "," + parts[1];
        };

        var formatPrintedAt = function () {
            var d = new Date();
            var dd = String(d.getDate()).padStart(2, "0");
            var mm = String(d.getMonth() + 1).padStart(2, "0");
            var yyyy = d.getFullYear();
            var hh = String(d.getHours()).padStart(2, "0");
            var mi = String(d.getMinutes()).padStart(2, "0");
            return dd + "/" + mm + "/" + yyyy + " " + hh + ":" + mi;
        };

        var renderComponentRows = function (items, emptyMessage) {
            if (!items.length) {
                return '<div class="panel-row"><span class="muted">' + esc(emptyMessage) + "</span></div>";
            }
            return items.map(function (row) {
                return '<div class="panel-row"><table class="panel-row-flex"><tr>' +
                    '<td>' + esc(row.componentName || "-") + '</td>' +
                    '<td>' + formatIdrPdf(row.amount) + '</td>' +
                    '</tr></table></div>';
            }).join("");
        };

        return (
            '<style>' +
            '.payslip-invoice-preview *{box-sizing:border-box;}' +
            '.payslip-invoice-preview{font-family:DejaVu Sans, Arial, sans-serif;font-size:10.5px;color:#202c4b;margin:0;padding:22px 24px 28px;background:#fff;}' +
            '.payslip-invoice-preview .primary{color:#fc7f01;}' +
            '.payslip-invoice-preview .muted{color:#6b7280;font-size:9.5px;}' +
            '.payslip-invoice-preview .fw-bold{font-weight:bold;}' +
            '.payslip-invoice-preview .text-end{text-align:right;}' +
            '.payslip-invoice-preview .text-center{text-align:center;}' +
            '.payslip-invoice-preview .border-b{border-bottom:1px solid #e8ecf1;}' +
            '.payslip-invoice-preview .mb-0{margin-bottom:0;}' +
            '.payslip-invoice-preview .mb-1{margin-bottom:4px;}' +
            '.payslip-invoice-preview .mb-2{margin-bottom:8px;}' +
            '.payslip-invoice-preview .mb-3{margin-bottom:14px;}' +
            '.payslip-invoice-preview .logo{max-height:42px;max-width:160px;}' +
            '.payslip-invoice-preview .section-title{font-size:10px;font-weight:bold;color:#202c4b;margin:0 0 6px;}' +
            '.payslip-invoice-preview .panel-head{background:#f8f9fa;border:1px solid #e8ecf1;border-bottom:none;padding:8px 10px;font-weight:bold;font-size:10px;}' +
            '.payslip-invoice-preview .panel-row{border:1px solid #e8ecf1;border-top:none;padding:7px 10px;}' +
            '.payslip-invoice-preview .panel-row-flex{width:100%;border-collapse:collapse;}' +
            '.payslip-invoice-preview .panel-row-flex td{padding:0;vertical-align:middle;}' +
            '.payslip-invoice-preview .panel-row-flex td:last-child{text-align:right;font-weight:bold;}' +
            '.payslip-invoice-preview .total-bar{border:1px solid #e8ecf1;background:#fafbfc;padding:12px 14px;margin-top:16px;}' +
            '.payslip-invoice-preview .total-amount{font-size:13px;font-weight:bold;color:#202c4b;}' +
            '</style>' +
            '<div class="payslip-invoice-preview">' +
            '<div style="border:1px solid #e8ecf1; background:#fafbfc; padding:10px 14px; margin-bottom:14px; text-align:center;">' +
            '<div class="muted" style="font-size:9px; text-transform:uppercase; letter-spacing:0.04em;">Slip Gaji Bulanan</div>' +
            '<div class="primary" style="font-size:17px; font-weight:bold; margin-top:2px; letter-spacing:0.02em;">#' + esc(slip.slipNumber || "#") + '</div>' +
            '</div>' +

            '<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;" class="mb-3 border-b"><tr>' +
            '<td width="52%" valign="top" style="padding-bottom:14px;">' +
            '<img src="/build/img/image111.png" alt="Logo" class="logo" />' +
            '<p class="fw-bold mb-1" style="font-size:11px;margin-top:6px;">' + esc(appName) + '</p>' +
            '<p class="muted mb-0">' + esc(orgSubtitle) + '</p>' +
            '</td>' +
            '<td width="48%" valign="top" class="text-end" style="padding-bottom:14px;">' +
            '<p class="mb-1"><span class="muted">Periode:</span> <span class="fw-bold">' + esc(periodLabel) + '</span></p>' +
            '<p class="mb-1"><span class="muted">Status run:</span> <span class="fw-bold">' + esc(runStatus) + '</span></p>' +
            '<p class="mb-0"><span class="muted">Dicetak:</span> <span class="fw-bold">' + esc(formatPrintedAt()) + '</span></p>' +
            '</td>' +
            '</tr></table>' +

            '<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;" class="mb-3 border-b"><tr>' +
            '<td width="48%" valign="top" style="padding:0 12px 14px 0;">' +
            '<p class="section-title">Dari</p>' +
            '<p class="fw-bold mb-1" style="font-size:11px;">' + esc(appName) + '</p>' +
            '<p class="muted mb-0">' + esc(orgSubtitle) + '</p>' +
            '</td>' +
            '<td width="48%" valign="top" style="padding:0 0 14px 12px;">' +
            '<p class="section-title">Kepada</p>' +
            '<p class="fw-bold mb-1" style="font-size:11px;">' + esc(slip.employeeName || "-") + '</p>' +
            '<p class="mb-1"><span class="muted">Email:</span> ' + esc(slip.email || "-") + '</p>' +
            '<p class="mb-1"><span class="muted">Jabatan:</span> ' + esc(slip.designation || "-") + '</p>' +
            '<p class="mb-0"><span class="muted">Tim:</span> ' + esc(slip.team || "-") + '</p>' +
            '</td>' +
            '</tr></table>' +

            '<p class="text-center fw-bold mb-3" style="font-size:12px;">Slip gaji untuk periode ' + esc(periodLabel) + '</p>' +

            '<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>' +
            '<td width="49%" valign="top" style="padding-right:8px;">' +
            '<div class="panel-head">Additions</div>' +
            renderComponentRows(earnings, "Belum ada komponen pendapatan.") +
            '<div class="panel-row" style="background:#f8fafc;"><table class="panel-row-flex"><tr><td class="fw-bold">Total earnings</td><td class="primary fw-bold">' + formatIdrPdf(slip.totals.earningsTotal) + '</td></tr></table></div>' +
            '</td>' +
            '<td width="49%" valign="top" style="padding-left:8px;">' +
            '<div class="panel-head">Deductions</div>' +
            renderComponentRows(deductions, "Belum ada komponen potongan.") +
            '<div class="panel-row" style="background:#f8fafc;"><table class="panel-row-flex"><tr><td class="fw-bold">Total deductions</td><td class="fw-bold">' + formatIdrPdf(slip.totals.deductionsTotal) + '</td></tr></table></div>' +
            '</td>' +
            '</tr></table>' +

            '<div class="total-bar">' +
            '<p class="total-amount mb-0">Take home pay: ' + formatIdrPdf(slip.totals.netPay) + '</p>' +
            '<p class="muted mb-0" style="margin-top:6px;">Dokumen ini dihasilkan otomatis dari run payroll yang sudah difinalisasi.</p>' +
            '</div>' +
            '</div>'
        );
    }

    function openPreview(rowKey) {
        var slip = _slips.find(function (s) { return String(s.rowKey) === String(rowKey); });
        if (!slip) return;
        _sendingRowKey = String(rowKey);

        var nameEl = document.querySelector("[data-payslip-preview-name]");
        var bodyEl = document.querySelector("[data-payslip-preview-body]");
        if (nameEl) nameEl.textContent = slip.employeeName;
        if (bodyEl) bodyEl.innerHTML = buildPreviewHtml(slip);

        var modalEl = document.getElementById("payslip_admin_preview_modal");
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function sendEmails(rowKeys) {
        if (!rowKeys || !rowKeys.length) return;

        var selectedRows = _slips.filter(function (s) {
            return rowKeys.indexOf(String(s.rowKey)) !== -1;
        });
        if (!selectedRows.length) {
            toast("Tidak ada data slip yang dipilih.", true);
            return;
        }

        var byPeriod = {};
        selectedRows.forEach(function (s) {
            var key = String(s.periodYear) + "-" + String(s.periodMonth);
            if (!byPeriod[key]) byPeriod[key] = { periodYear: s.periodYear, periodMonth: s.periodMonth, userIds: [] };
            if (byPeriod[key].userIds.indexOf(s.userId) === -1) {
                byPeriod[key].userIds.push(s.userId);
            }
        });

        var jobs = Object.keys(byPeriod).map(function (k) { return byPeriod[k]; });

        Promise.all(jobs.map(function (payload) {
            return apiRequest("post", "/v1/hcm/payroll/send-slips", payload);
        })).then(function (responses) {
            var sentCount = 0;
            var skippedCount = 0;
            responses.forEach(function (resp) {
                sentCount += Array.isArray(resp && resp.data && resp.data.sentUserIds) ? resp.data.sentUserIds.length : 0;
                skippedCount += Array.isArray(resp && resp.data && resp.data.skipped) ? resp.data.skipped.length : 0;
            });
            toast(sentCount + " slip berhasil dikirim" + (skippedCount ? ", " + skippedCount + " dilewati" : "") + ".", false);
            loadSlips();
        }).catch(function (err) {
            toast(formatApiError(err && err.data, err && err.status) || "Gagal mengirim slip gaji.", true);
        });
    }

    function bind() {
        var loadBtn = document.querySelector("[data-payslip-admin-load]");
        if (loadBtn && !loadBtn.getAttribute("data-bound")) {
            loadBtn.setAttribute("data-bound", "1");
            loadBtn.addEventListener("click", loadSlips);
        }

        var sourceSel = document.querySelector("[data-payslip-admin-source]");
        if (sourceSel && !sourceSel.getAttribute("data-bound")) {
            sourceSel.setAttribute("data-bound", "1");
            sourceSel.addEventListener("change", function () {
                syncSourceUi();
                loadSlips();
            });
        }

        var snapshotInput = document.querySelector("[data-payslip-admin-snapshot-id]");
        if (snapshotInput && !snapshotInput.getAttribute("data-bound")) {
            snapshotInput.setAttribute("data-bound", "1");
            snapshotInput.addEventListener("change", function () {
                syncSourceUi();
            });
        }

        var selectAll = document.querySelector("[data-payslip-admin-select-all]");
        if (selectAll && !selectAll.getAttribute("data-bound")) {
            selectAll.setAttribute("data-bound", "1");
            selectAll.addEventListener("change", function () {
                document.querySelectorAll("[data-payslip-admin-row-check]").forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
                refreshSendBtn();
            });
        }

        var sendSelectedBtn = document.querySelector("[data-payslip-admin-send-selected]");
        if (sendSelectedBtn && !sendSelectedBtn.getAttribute("data-bound")) {
            sendSelectedBtn.setAttribute("data-bound", "1");
            sendSelectedBtn.addEventListener("click", function () {
                sendEmails(getSelectedRowKeys());
            });
        }

        var sendPreviewBtn = document.querySelector("[data-payslip-preview-send]");
        if (sendPreviewBtn && !sendPreviewBtn.getAttribute("data-bound")) {
            sendPreviewBtn.setAttribute("data-bound", "1");
            sendPreviewBtn.addEventListener("click", function () {
                if (_sendingRowKey) sendEmails([_sendingRowKey]);
            });
        }

        if (!document.body.getAttribute("data-payslip-admin-check-bound")) {
            document.body.setAttribute("data-payslip-admin-check-bound", "1");
            document.addEventListener("change", function (e) {
                if (e.target.matches("[data-payslip-admin-row-check]")) refreshSendBtn();
            });
        }

        document.addEventListener("click", function (e) {
            var previewBtn = e.target.closest("[data-payslip-admin-preview]");
            if (previewBtn) {
                e.preventDefault();
                openPreview(previewBtn.getAttribute("data-payslip-admin-preview"));
            }
            var sendOneBtn = e.target.closest("[data-payslip-admin-send-one]");
            if (sendOneBtn) {
                e.preventDefault();
                sendEmails([String(sendOneBtn.getAttribute("data-payslip-admin-send-one") || "")]);
            }
        });
    }

    function init() {
        if (!document.querySelector("[data-payslip-admin-body]")) return;

        var errEl = document.querySelector("[data-payslip-admin-error]");

        function showAccessError(message) {
            if (errEl) {
                errEl.classList.remove("d-none");
                errEl.textContent = message;
            }
            var body = document.querySelector("[data-payslip-admin-body]");
            if (body) {
                body.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">' + esc(message) + "</td></tr>";
            }
        }

        bind();
        syncSourceUi();

        if (window.AuthApi && window.AuthApi.request) {
            window.AuthApi.request("get", "/identity/auth/me").then(function (me) {
                // AuthApi.request can return axios response ({ data: payload }) or fetch-style payload directly.
                var payload = (me && me.data && typeof me.data === "object") ? me.data : me;
                var meData = payload && payload.success && payload.data ? payload.data : null;

                var isGlobalAdmin = !!(meData && meData.hcmGlobalAdmin === true);
                var isTenantAdmin = !!(meData && meData.hcmAdmin === true);
                var canViewPayroll = !!(meData && meData.permissions && meData.permissions['payroll.view']);
                if (meData && !isGlobalAdmin && !isTenantAdmin && !canViewPayroll) {
                    showAccessError("Akses ditolak. Halaman Payslip Report hanya untuk role payroll/admin yang memiliki izin.");
                    return;
                }
                loadSlips();
            }).catch(function () {
                showAccessError("Gagal memverifikasi sesi login. Silakan refresh halaman atau login ulang.");
            });
        } else {
            loadSlips();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

})(window, document);
