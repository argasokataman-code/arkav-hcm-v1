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
        if (s === "paid") return '<span class="badge bg-success">Paid</span>';
        if (s === "partial") return '<span class="badge bg-warning text-dark">Partial</span>';
        return '<span class="badge bg-danger">Unpaid</span>';
    }

    function renderRows(slips) {
        var body = document.querySelector("[data-payslip-admin-body]");
        if (!body) return;
        if (!slips || !slips.length) {
            body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Tidak ada slip untuk filter saat ini.</td></tr>';
            return;
        }
        body.innerHTML = slips.map(function (s) {
            return (
                "<tr>" +
                '<td><div class="form-check form-check-md mb-0">' +
                '<input class="form-check-input" type="checkbox" value="' + esc(String(s.rowKey)) + '" data-payslip-admin-row-check>' +
                "</div></td>" +
                "<td>" + esc(formatPeriod(s.periodYear, s.periodMonth)) + "</td>" +
                "<td><span class=\"badge bg-primary\">" + esc(String(s.runStatus || "-")) + "</span></td>" +
                "<td>" + paymentBadge(s.paymentStatus) + "</td>" +
                "<td><div class=\"fw-medium\">" + esc(s.employeeName) + "</div>" +
                (s.email ? '<div class="text-muted small">' + esc(s.email) + "</div>" : "") +
                "</td>" +
                "<td><div>" + esc(s.designation) + "</div>" +
                '<div class="text-muted small">' + esc(s.team) + "</div></td>" +
                '<td class="text-end">' + formatIdr(s.totals.earningsTotal) + "</td>" +
                '<td class="text-end">' + formatIdr(s.totals.deductionsTotal) + "</td>" +
                '<td class="text-end fw-medium">' + formatIdr(s.totals.netPay) + "</td>" +
                '<td class="text-end">' +
                '<button type="button" class="btn btn-sm btn-outline-primary me-1" data-payslip-admin-preview="' + esc(String(s.rowKey)) + '"><i class="ti ti-eye me-1"></i>Preview</button>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" data-payslip-admin-send-one="' + esc(String(s.rowKey)) + '"><i class="ti ti-mail me-1"></i>Email</button>' +
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
        if (body) body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Memuat snapshot archive…</td></tr>';
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
                if (body) body.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">' + esc(msg) + "</td></tr>";
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
                if (body) body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Isi Snapshot ID lalu klik Muat.</td></tr>';
                return;
            }
            loadArchiveSlips(snapshotId);
            return;
        }

        if (errEl) { errEl.classList.add("d-none"); errEl.textContent = ""; }
        if (body) body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Memuat…</td></tr>';
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
            if (body) body.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">' + esc(msg) + "</td></tr>";
        });
    }

    function buildPreviewHtml(slip) {
        var lines = function (items, emptyMsg) {
            if (!items || !items.length) return '<div class="list-group-item"><span class="text-muted small">' + emptyMsg + "</span></div>";
            return items.map(function (l) {
                return '<div class="list-group-item d-flex justify-content-between align-items-center">' +
                    '<span>' + esc(l.componentName || l.category || "-") + '</span>' +
                    '<strong>' + formatIdr(l.amount) + '</strong>' +
                '</div>';
            }).join("");
        };

        var runStatus = String(slip.runStatus || "-").toLowerCase();
        var runStatusBadgeClass = runStatus === "finalized"
            ? "bg-success"
            : (runStatus === "draft" ? "bg-warning text-dark" : "bg-secondary");

        var appName = (window.APP_NAME && String(window.APP_NAME).trim()) || "Arcav";

        return (
            '<div class="card mb-0 border-0">' +
            '<div class="card-body p-0">' +
            '<div class="row justify-content-between align-items-center border-bottom mb-3 px-1">' +
            '<div class="col-md-6">' +
            '<div class="mb-3">' +
            '<div class="mb-2"><img src="/build/img/image111.png" class="img-fluid" style="max-height:44px;" alt="logo"></div>' +
            '<p class="mb-1">' + esc(appName) + '</p>' +
            '<p class="mb-0 text-muted">Divisi SDM / Payroll</p>' +
            '</div>' +
            '</div>' +
            '<div class="col-md-6 text-end">' +
            '<div class="mb-3">' +
            '<h5 class="text-gray mb-1">Payslip No <span class="text-primary">#' + esc(slip.slipNumber || "-") + '</span></h5>' +
            '<p class="fw-medium mb-1">Salary Month : <span class="text-dark">' + esc(formatPeriod(slip.periodYear, slip.periodMonth)) + '</span></p>' +
            '<p class="mb-0">Status run: <span class="badge ' + runStatusBadgeClass + '">' + esc(String(slip.runStatus || "-")) + '</span></p>' +
            '</div>' +
            '</div>' +
            '</div>' +

            '<div class="row border-bottom align-items-center mb-3 px-1">' +
            '<div class="col-md-6">' +
            '<div class="mb-3">' +
            '<p class="text-dark mb-2 fw-semibold">Employee</p>' +
            '<div>' +
            '<h4 class="mb-1">' + esc(slip.employeeName || "-") + '</h4>' +
            '<p class="mb-1">Jabatan : <span class="text-dark">' + esc(slip.designation || "-") + '</span></p>' +
            '<p class="mb-1">Email : <span class="text-dark">' + esc(slip.email || "-") + '</span></p>' +
            '<p class="mb-0">Tim : <span class="text-dark">' + esc(slip.team || "-") + '</span></p>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div class="col-md-6 text-md-end">' +
            '<div class="mb-3">' +
            '<p class="mb-1">Total Additions: <strong>' + formatIdr(slip.totals.earningsTotal) + '</strong></p>' +
            '<p class="mb-1">Total Deductions: <strong>' + formatIdr(slip.totals.deductionsTotal) + '</strong></p>' +
            '<p class="mb-0" style="font-size:1.35rem;">Take Home Pay: <strong class="text-primary">' + formatIdr(slip.totals.netPay) + '</strong></p>' +
            '</div>' +
            '</div>' +
            '</div>' +

            '<div class="row px-1">' +
            '<div class="col-md-6">' +
            '<div class="list-group mb-3">' +
            '<div class="list-group-item bg-light p-3 border-bottom-0"><h6 class="mb-0">Additions</h6></div>' +
            lines(slip.earnings, "Belum ada komponen penghasilan.") +
            '<div class="list-group-item d-flex justify-content-between align-items-center fw-semibold"><span>Total</span><span class="text-success">' + formatIdr(slip.totals.earningsTotal) + '</span></div>' +
            '</div>' +
            '</div>' +
            '<div class="col-md-6">' +
            '<div class="list-group mb-3">' +
            '<div class="list-group-item bg-light p-3 border-bottom-0"><h6 class="mb-0">Deductions</h6></div>' +
            lines(slip.deductions, "Belum ada komponen potongan.") +
            '<div class="list-group-item d-flex justify-content-between align-items-center fw-semibold"><span>Total</span><span class="text-danger">' + formatIdr(slip.totals.deductionsTotal) + '</span></div>' +
            '</div>' +
            '</div>' +
            '</div>' +
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

        bind();
        syncSourceUi();

        if (window.AuthApi && window.AuthApi.request) {
            window.AuthApi.request("get", "/identity/auth/me").then(function (me) {
                if (!me || !me.success || !me.data || !me.data.permissions || !me.data.permissions['payroll.view']) {
                    window.location.replace("/employee-dashboard");
                    return;
                }
                loadSlips();
            }).catch(function () {
                window.location.replace("/employee-dashboard");
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
