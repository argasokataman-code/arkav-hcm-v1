(function (window, document) {
    "use strict";

    var currentRun = null;
    var pendingPayRunId = null;
    var _pkwtPreviewLines = [];
    var _pkwtPreviewSummary = null;

    function onAuthFailure(status, data) {
        if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
            return window.AuthApi.handleUnauthorizedFromApi(status, data);
        }
        return false;
    }

    function apiRequest(method, url, body) {
        var headers = { Accept: "application/json" };
        if (body && typeof body === "object" && !(body instanceof FormData)) {
            headers["Content-Type"] = "application/json";
        }
        var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        if (token) { headers['Authorization'] = 'Bearer ' + token; }
        if (window.axios) {
            return window.axios({ method: method, url: url, headers: headers, data: body, withCredentials: true })
                .then(function (res) { return res.data; })
                .catch(function (err) {
                    var st = err && err.response ? err.response.status : 0;
                    var d = err && err.response ? err.response.data : null;
                    if (onAuthFailure(st, d)) {
                        return null;
                    }
                    return Promise.reject({ status: st, data: d });
                });
        }
        var opts = { method: method, headers: headers, credentials: "same-origin" };
        if (body && method !== "GET") {
            opts.body = body instanceof FormData ? body : JSON.stringify(body);
        }
        return fetch(url, opts).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
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

    function reconciliationExportFileName(filePath, evidenceId) {
        if (filePath && typeof filePath === "string") {
            var parts = filePath.split("/").filter(Boolean);
            var last = parts[parts.length - 1];
            if (last) {
                return last;
            }
        }
        return "reconciliation-pkwt-" + evidenceId + ".xlsx";
    }

    function downloadReconciliationEvidenceFile(evidenceId, filePath) {
        if (!window.AuthApi || typeof window.AuthApi.downloadV1Binary !== "function") {
            return Promise.reject(new Error("AuthApi.downloadV1Binary tidak tersedia"));
        }
        var name = reconciliationExportFileName(filePath, evidenceId);
        return window.AuthApi.downloadV1Binary("/reconciliation/exports/" + evidenceId + "/download", name);
    }

    function escapeHtml(v) {
        return String(v == null ? "" : v)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatIdr(value) {
        var n = Number(value || 0);
        return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(n);
    }

    function formatEmployeeCode(value) {
        var n = Number(value);
        if (!Number.isFinite(n) || n <= 0) {
            return "";
        }
        return "EMP-" + String(Math.trunc(n));
    }

    function formatApiError(data, status) {
        var reconciliationMessages = {
            EXPORT_RECON_REQUIRED: "Sebelum lanjut, lakukan export reconciliation PKWT untuk periode yang sama.",
            EXPORT_RECON_EXPIRED: "Evidence reconciliation PKWT sudah kedaluwarsa. Silakan export ulang.",
            EXPORT_RECON_SCOPE_MISMATCH: "Evidence reconciliation tidak cocok dengan periode PKWT yang diproses.",
            EXPORT_RECON_STALE_DATA: "Data PKWT sudah berubah sejak export terakhir. Silakan export ulang.",
        };
        var code = data && data.error ? data.error.code : null;
        if (code && reconciliationMessages[code]) {
            return reconciliationMessages[code];
        }
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            return window.ApiErrorHelper.format(data, status);
        }
        if (data && data.error && data.error.code === "PKWT_COMPENSATION_FINALIZED_EXISTS") {
            return "Payroll kompensasi PKWT untuk periode ini sudah finalized.";
        }
        if (data && data.error && data.error.message) {
            return String(data.error.message);
        }
        if (status === 403) {
            return "Anda tidak punya akses untuk fitur ini.";
        }
        if (status === 401) {
            return "Sesi habis. Silakan login ulang.";
        }
        return "Terjadi kesalahan. Coba lagi.";
    }

    function notify(message, isError) {
        if (window.ApiClient && typeof window.ApiClient.toast === "function") {
            window.ApiClient.toast(message, isError);
            return;
        }
        var c = document.querySelector("[data-hcm-toast-container]");
        if (!c) {
            c = document.createElement("div");
            c.setAttribute("data-hcm-toast-container", "1");
            c.style.position = "fixed";
            c.style.top = "16px";
            c.style.right = "16px";
            c.style.zIndex = "3000";
            document.body.appendChild(c);
        }
        var t = document.createElement("div");
        t.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = String(message || "");
        c.appendChild(t);
        window.setTimeout(function () {
            t.remove();
        }, 2600);
    }

    function getApiErrorCode(data) {
        return data && data.error && typeof data.error.code === "string" ? data.error.code : null;
    }

    function setPkwtReconciliationHint(message) {
        var el = document.querySelector("[data-pkwt-reconciliation-hint]");
        if (!el) {
            return;
        }
        if (!message) {
            el.textContent = "";
            el.classList.add("d-none");
            return;
        }
        el.textContent = String(message);
        el.classList.remove("d-none");
    }

    function showPkwtEvidenceIndicator(evidence) {
        var indicatorEl = document.querySelector("[data-pkwt-evidence-indicator]");
        if (!indicatorEl) return;

        var statusBadge = indicatorEl.querySelector("[data-evidence-status]");
        var timestampEl = indicatorEl.querySelector("[data-evidence-timestamp]");

        if (!evidence) {
            indicatorEl.classList.add("d-none");
            return;
        }

        var now = new Date().getTime();
        var expiresAt = new Date(evidence.expiresAt || 0).getTime();
        var status = "valid";
        var statusClass = "bg-success";

        if (now > expiresAt) {
            status = "expired";
            statusClass = "bg-danger";
        } else if (evidence.isExpired) {
            status = "expired";
            statusClass = "bg-danger";
        }

        if (statusBadge) {
            statusBadge.textContent = status.toUpperCase();
            statusBadge.className = "badge " + statusClass;
        }

        if (timestampEl && evidence.exportedAt) {
            var date = new Date(evidence.exportedAt).toLocaleString("id-ID");
            var user = evidence.exportedByUserName || evidence.exportedByUserId || "—";
            timestampEl.textContent = "Exported: " + date + " oleh " + user;
        }

        indicatorEl.classList.remove("d-none");
    }

    function fetchPkwtLatestEvidence(yearMonth) {
        if (!yearMonth) return Promise.resolve();
        var params = new URLSearchParams();
        params.append("featureKey", "pkwt_compensation");
        params.append("actionKey", "post_payroll");
        params.append("scopeRef", String(yearMonth));
        return apiRequest("GET", "/v1/reconciliation/exports?" + params.toString(), null)
            .then(function (res) {
                if (res && res.data && Array.isArray(res.data) && res.data.length > 0) {
                    showPkwtEvidenceIndicator(res.data[0]);
                } else {
                    showPkwtEvidenceIndicator(null);
                }
            })
            .catch(function (error) {
                console.warn("Failed to fetch PKWT evidence status:", error);
                showPkwtEvidenceIndicator(null);
            });
    }

    function triggerPkwtExportReconciliation(yearMonth, lines) {
        if (!yearMonth) {
            notify("No PKWT period selected", true);
            return Promise.resolve();
        }

        var exportBtn = document.querySelector("[data-pkwt-export-evidence]");
        setButtonBusy(exportBtn, true, "Exporting…");

        var filterPayload = {
            lineIds: (lines || []).filter(function (l) { return l && l.eligible; }).map(function (l) { return l.id; }),
        };

        return apiRequest("POST", "/v1/reconciliation/exports", {
            featureKey: "pkwt_compensation",
            actionKey: "post_payroll",
            scopeRef: String(yearMonth),
            filterPayload: filterPayload,
            fileFormat: "xlsx",
        })
            .then(function (res) {
                if (res && res.data && res.data.id) {
                    notify("Export reconciliation PKWT berhasil dibuat", false);
                    return downloadReconciliationEvidenceFile(res.data.id, res.data.filePath)
                        .catch(function (dlErr) {
                            console.warn("PKWT reconciliation file download failed:", dlErr);
                            notify("Evidence tersimpan, tetapi unduh file gagal. Silakan coba lagi dari daftar evidence.", true);
                        })
                        .then(function () {
                            return fetchPkwtLatestEvidence(yearMonth);
                        });
                } else {
                    notify("Gagal membuat export reconciliation PKWT", true);
                }
            })
            .catch(function (error) {
                var errorCode = getApiErrorCode(error && error.data ? error.data : {});
                if (errorCode && errorCode.indexOf("EXPORT_RECON_") === 0) {
                    var msg = formatApiError(error && error.data ? error.data : {}, 400);
                    if (msg) {
                        setPkwtReconciliationHint(msg);
                        setButtonBusy(exportBtn, false);
                        return;
                    }
                }
                notify("Error: " + (error && error.data && error.data.error && error.data.error.message ? error.data.error.message : "Unknown error"), true);
            })
            .finally(function () {
                setButtonBusy(exportBtn, false);
            });
    }

    function validatePeriodInputs(yearValue, monthValue) {
        var year = Number(yearValue);
        var month = Number(monthValue);
        if (!Number.isInteger(year) || year < 2000 || year > 2100) {
            return "Tahun harus di antara 2000 sampai 2100.";
        }
        if (!Number.isInteger(month) || month < 1 || month > 12) {
            return "Bulan harus di antara 1 sampai 12.";
        }
        return "";
    }

    function setButtonBusy(button, busy, busyLabel) {
        if (!button) {
            return;
        }
        if (!button.getAttribute("data-default-label")) {
            button.setAttribute("data-default-label", button.textContent || "");
        }
        button.disabled = !!busy;
        button.textContent = busy ? (busyLabel || "Memproses…") : (button.getAttribute("data-default-label") || "");
    }

    function getPayConfirmModal() {
        var el = document.getElementById("pkwt_pay_confirm_modal");
        if (!el || !window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(el);
    }

    function openPayConfirmModal(run) {
        var modal = getPayConfirmModal();
        if (!modal) {
            return false;
        }
        pendingPayRunId = run && run.id ? run.id : null;
        var detail = document.querySelector("[data-pkwt-pay-confirm-detail]");
        if (detail) {
            var employeeCount = run && run.payment ? Number(run.payment.employeeCount || 0) : 0;
            detail.textContent = "Run #" + String(run.id) + " • Karyawan: " + String(employeeCount) + ". Lanjutkan pencatatan pembayaran manual sekarang?";
        }
        modal.show();
        return true;
    }

    function updateRunState(run) {
        currentRun = run || null;
        var box = document.querySelector("[data-pkwt-run-state]");
        var payBtn = document.querySelector("[data-pkwt-pay-run]");
        if (payBtn) {
            payBtn.disabled = !run || !run.id || !run.payment || run.payment.employeeCount === 0 || run.payment.status === "paid";
        }
        if (!box) {
            return;
        }
        if (!run) {
            box.innerHTML = '<span class="text-muted">Belum ada payroll kompensasi PKWT untuk periode ini.</span>';
            return;
        }

        box.innerHTML = '<div class="d-flex flex-wrap gap-3 align-items-center">' +
            '<span><span class="text-muted">Run ID:</span> <strong>#' + escapeHtml(run.id) + '</strong></span>' +
            '<span><span class="text-muted">Status:</span> <strong>' + escapeHtml(run.status || 'draft') + '</strong></span>' +
            '<span><span class="text-muted">Pembayaran:</span> <strong>' + escapeHtml((run.payment && run.payment.status) || 'unpaid') + '</strong></span>' +
            '<span><span class="text-muted">Karyawan:</span> <strong>' + escapeHtml(String((run.payment && run.payment.employeeCount) || 0)) + '</strong></span>' +
            '<span><span class="text-muted">Paid:</span> <strong>' + escapeHtml(String((run.payment && run.payment.paidEmployeeCount) || 0)) + '</strong></span>' +
            '</div>';
    }

    function renderList(payload, run) {
        var body = document.querySelector("[data-pkwt-list-body]");
        var empty = document.querySelector("[data-pkwt-list-empty]");
        var total = document.querySelector("[data-pkwt-summary-total]");
        var eligible = document.querySelector("[data-pkwt-summary-eligible]");
        var grand = document.querySelector("[data-pkwt-summary-grand]");
        var note = document.querySelector("[data-pkwt-regulation-note]");
        if (!body) {
            return;
        }
        updateRunState(run);
        var lines = payload && Array.isArray(payload.lines) ? payload.lines : [];
        // Store for preview modal
        _pkwtPreviewLines = lines;
        _pkwtPreviewSummary = payload && payload.summary ? payload.summary : null;
        var paidUserIds = run && run.payment && Array.isArray(run.payment.paidUserIds) ? run.payment.paidUserIds : [];
        if (total) total.textContent = String(payload && payload.summary ? payload.summary.totalEmployees || 0 : 0);
        if (eligible) eligible.textContent = String(payload && payload.summary ? payload.summary.eligibleEmployees || 0 : 0);
        if (grand) grand.textContent = formatIdr(payload && payload.summary ? payload.summary.grandTotal || 0 : 0);
        if (note && payload && payload.regulationReference) note.textContent = payload.regulationReference;

        if (!lines.length) {
            body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Tidak ada karyawan contract yang jatuh tempo di periode ini.</td></tr>';
            if (empty) empty.classList.remove("d-none");
            return;
        }
        if (empty) empty.classList.add("d-none");

        body.innerHTML = lines.map(function (row) {
            var payBadge = paidUserIds.indexOf(row.userId) >= 0
                ? '<span class="badge bg-success">Paid</span>'
                : '<span class="badge bg-light text-dark border">Belum dibayar</span>';
            var isEligible = row.eligible ? "1" : "0";
            var identity = formatEmployeeCode(row.userId);
            return "<tr data-line-id=\"" + escapeHtml(String(row.userId || "")) + "\" data-eligible=\"" + isEligible + "\">" +
                "<td><div class=\"fw-medium\">" + escapeHtml(row.fullName) + "</div><div class=\"small text-muted\">" + escapeHtml(identity) + " · " + escapeHtml(row.email || "") + "</div></td>" +
                "<td>" + escapeHtml(row.designation || "Employee") + "</td>" +
                "<td><span class=\"badge bg-light text-dark border\">" + escapeHtml(row.employmentStatus || "active") + "</span></td>" +
                "<td>" + escapeHtml(row.contractStartDate || "—") + "</td>" +
                "<td>" + escapeHtml(row.contractEndDate || "—") + "</td>" +
                "<td class=\"text-center\">" + escapeHtml(String(row.monthsOfService || 0)) + "</td>" +
                "<td class=\"text-end\">" + escapeHtml(formatIdr(row.referenceMonthlyWage || 0)) + "</td>" +
                "<td class=\"text-end\">" + escapeHtml(((Number(row.multiplier || 0) * 100).toFixed(2)).replace(/\.00$/, "")) + "%</td>" +
                "<td class=\"text-end fw-semibold\">" + escapeHtml(formatIdr(row.compensationAmount || 0)) + "</td>" +
                "<td class=\"text-center\">" + payBadge + "</td>" +
                "</tr>";
        }).join("");
    }

    function loadList() {
        var year = document.querySelector("[data-pkwt-period-year]");
        var month = document.querySelector("[data-pkwt-period-month]");
        var err = document.querySelector("[data-pkwt-list-error]");
        var body = document.querySelector("[data-pkwt-list-body]");
        var exportBtn = document.querySelector("[data-pkwt-export-evidence]");
        var postBtn = document.querySelector("[data-pkwt-post-payroll]");
        
        if (!year || !month || !body) {
            return;
        }
        if (err) {
            err.textContent = "";
            err.classList.add("d-none");
        }
        var periodError = validatePeriodInputs(year.value, month.value);
        if (periodError) {
            body.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Input periode tidak valid.</td></tr>';
            if (err) {
                err.textContent = periodError;
                err.classList.remove("d-none");
            }
            updateRunState(null);
            if (exportBtn) exportBtn.disabled = true;
            if (postBtn) postBtn.disabled = true;
            return;
        }
        body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Memuat data…</td></tr>';
        if (exportBtn) exportBtn.disabled = true;
        if (postBtn) postBtn.disabled = true;
        var yearMonth = String(year.value) + "-" + String(month.value).padStart(2, "0");
        apiRequest("get", "/v1/hcm/payroll/pkwt-compensations?periodYear=" + encodeURIComponent(year.value) + "&periodMonth=" + encodeURIComponent(month.value), null)
            .then(function (resp) {
                if (!resp || resp.success !== true) {
                    throw { status: 0, data: resp };
                }
                renderList((resp.data && resp.data.preview) || {}, resp.data && resp.data.run ? resp.data.run : null);
                if (exportBtn) exportBtn.disabled = false;
                if (postBtn) postBtn.disabled = false;
                return fetchPkwtLatestEvidence(yearMonth);
            })
            .catch(function (errObj) {
                body.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Gagal memuat data.</td></tr>';
                if (err) {
                    err.textContent = formatApiError(errObj && errObj.data, errObj && errObj.status);
                    err.classList.remove("d-none");
                }
                if (exportBtn) exportBtn.disabled = true;
                if (postBtn) postBtn.disabled = true;
            });
    }

    function openPkwtReconciliationPreviewModal() {
        var year = document.querySelector("[data-pkwt-period-year]");
        var month = document.querySelector("[data-pkwt-period-month]");
        if (!year || !month) {
            notify("Pilih periode terlebih dahulu", true);
            return;
        }
        var periodStr = String(year.value) + "-" + String(month.value).padStart(2, "0");

        var modal = document.getElementById("pkwt_reconciliation_preview_modal");
        if (!modal) return;

        var eligibleLines = _pkwtPreviewLines.filter(function (l) { return l.eligible; });
        var grandTotal = _pkwtPreviewSummary ? (_pkwtPreviewSummary.grandTotal || 0) : 0;
        var totalEmployees = _pkwtPreviewSummary ? (_pkwtPreviewSummary.totalEmployees || 0) : 0;

        var periodEl = modal.querySelector("[data-pkwt-recon-preview-period]");
        var countEl = modal.querySelector("[data-pkwt-recon-preview-count]");
        var totalEl = modal.querySelector("[data-pkwt-recon-preview-total]");
        var allCountEl = modal.querySelector("[data-pkwt-recon-preview-all-count]");
        var tbody = modal.querySelector("[data-pkwt-recon-preview-body]");

        if (periodEl) periodEl.textContent = periodStr;
        if (countEl) countEl.textContent = String(eligibleLines.length);
        if (totalEl) totalEl.textContent = formatIdr(grandTotal);
        if (allCountEl) allCountEl.textContent = String(totalEmployees);

        if (tbody) {
            if (eligibleLines.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada karyawan eligible.</td></tr>';
            } else {
                tbody.innerHTML = eligibleLines.map(function (l) {
                    var multiplierPct = ((Number(l.multiplier || 0) * 100).toFixed(2)).replace(/\.00$/, "") + "%";
                    return "<tr>" +
                        "<td><div class=\"fw-semibold\">" + escapeHtml(l.fullName || "—") + "</div>" +
                        "<div class=\"text-muted small\">" + escapeHtml(formatEmployeeCode(l.userId)) + "</div></td>" +
                        "<td class=\"text-end\">" + escapeHtml(formatIdr(l.referenceMonthlyWage || 0)) + "</td>" +
                        "<td class=\"text-center\">" + escapeHtml(String(l.monthsOfService || 0)) + " bln</td>" +
                        "<td class=\"text-center\">" + escapeHtml(multiplierPct) + "</td>" +
                        "<td class=\"text-end fw-semibold text-primary\">" + escapeHtml(formatIdr(l.compensationAmount || 0)) + "</td>" +
                        "<td class=\"text-center\"><span class=\"badge bg-light text-dark border\">" + escapeHtml(l.eligible ? "eligible" : "tidak eligible") + "</span></td>" +
                        "</tr>";
                }).join("");
                tbody.innerHTML += "<tr class=\"table-light fw-semibold\">" +
                    "<td>Total (" + eligibleLines.length + " karyawan eligible)</td>" +
                    "<td colspan=\"3\"></td>" +
                    "<td class=\"text-end text-primary\">" + escapeHtml(formatIdr(grandTotal)) + "</td>" +
                    "<td></td></tr>";
            }
        }

        var Bootstrap = window.bootstrap;
        if (Bootstrap && Bootstrap.Modal) {
            Bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    }

    function initList() {
        var form = document.querySelector("[data-pkwt-list-form]");
        var exportBtn = document.querySelector("[data-pkwt-export-evidence]");
        var postBtn = document.querySelector("[data-pkwt-post-payroll]");
        var payBtn = document.querySelector("[data-pkwt-pay-run]");
        
        // Initialize buttons as disabled (no period selected yet)
        if (exportBtn) {
            exportBtn.disabled = true;
        }
        if (postBtn) {
            postBtn.disabled = true;
        }
        if (payBtn) {
            payBtn.disabled = true;
        }
        
        if (!form) {
            return;
        }
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            loadList();
        });

        if (exportBtn) {
            exportBtn.addEventListener("click", function () {
                openPkwtReconciliationPreviewModal();
            });
        }

        // Wire preview modal download button
        var previewDownloadBtn = document.querySelector("[data-pkwt-recon-preview-download]");
        if (previewDownloadBtn) {
            previewDownloadBtn.addEventListener("click", function () {
                var year = document.querySelector("[data-pkwt-period-year]");
                var month = document.querySelector("[data-pkwt-period-month]");
                if (!year || !month) return;
                var yearMonth = String(year.value) + "-" + String(month.value).padStart(2, "0");
                var modal = document.getElementById("pkwt_reconciliation_preview_modal");
                if (modal && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modal).hide();
                }
                var domLines = [];
                _pkwtPreviewLines.forEach(function (l) {
                    domLines.push({ id: l.userId, eligible: l.eligible });
                });
                void triggerPkwtExportReconciliation(yearMonth, domLines);
            });
        }

        if (postBtn) {
            postBtn.addEventListener("click", function () {
                var year = document.querySelector("[data-pkwt-period-year]");
                var month = document.querySelector("[data-pkwt-period-month]");
                if (!year || !month) return;
                var periodError = validatePeriodInputs(year.value, month.value);
                if (periodError) {
                    notify(periodError, true);
                    return;
                }
                setButtonBusy(postBtn, true, "Generating…");
                apiRequest("post", "/v1/hcm/payroll/pkwt-compensations/post-payroll", {
                    periodYear: parseInt(year.value, 10),
                    periodMonth: parseInt(month.value, 10)
                }).then(function (resp) {
                    if (!resp || resp.success !== true) {
                        var code = getApiErrorCode(resp);
                        if (code && code.indexOf("EXPORT_RECON_") === 0) {
                            setPkwtReconciliationHint(formatApiError(resp, 422));
                        }
                        throw { status: 0, data: resp };
                    }
                    setPkwtReconciliationHint("");
                    notify("Draft payroll kompensasi PKWT berhasil dibuat.", false);
                    loadList();
                }).catch(function (errObj) {
                    var code = getApiErrorCode(errObj && errObj.data);
                    if (code && code.indexOf("EXPORT_RECON_") === 0) {
                        setPkwtReconciliationHint(formatApiError(errObj && errObj.data, errObj && errObj.status));
                    }
                    notify(formatApiError(errObj && errObj.data, errObj && errObj.status), true);
                }).finally(function () {
                    setButtonBusy(postBtn, false);
                });
            });
        }

        if (payBtn) {
            payBtn.addEventListener("click", function () {
                if (!currentRun || !currentRun.id) {
                    notify("Buat draft payroll kompensasi PKWT dulu.", true);
                    return;
                }
                if (!openPayConfirmModal(currentRun)) {
                    notify("Modal konfirmasi tidak tersedia.", true);
                    return;
                }
            });
        }

        var confirmBtn = document.querySelector("[data-pkwt-pay-confirm-submit]");
        if (confirmBtn) {
            confirmBtn.addEventListener("click", function () {
                if (!pendingPayRunId) {
                    notify("Run payroll tidak ditemukan.", true);
                    return;
                }
                var modal = getPayConfirmModal();
                setButtonBusy(confirmBtn, true, "Menyimpan…");
                setButtonBusy(payBtn, true, "Mencatat…");
                apiRequest("post", "/v1/hcm/payroll-runs/" + encodeURIComponent(pendingPayRunId) + "/disburse", { applyAll: true })
                    .then(function (resp) {
                        if (!resp || resp.success !== true) {
                            var code = getApiErrorCode(resp);
                            if (code && code.indexOf("EXPORT_RECON_") === 0) {
                                setPkwtReconciliationHint(formatApiError(resp, 422));
                            }
                            throw { status: 0, data: resp };
                        }
                        setPkwtReconciliationHint("");
                        if (modal) {
                            modal.hide();
                        }
                        notify("Pembayaran kompensasi PKWT berhasil dicatat manual.", false);
                        loadList();
                    })
                    .catch(function (errObj) {
                        var code = getApiErrorCode(errObj && errObj.data);
                        if (code && code.indexOf("EXPORT_RECON_") === 0) {
                            setPkwtReconciliationHint(formatApiError(errObj && errObj.data, errObj && errObj.status));
                        }
                        notify(formatApiError(errObj && errObj.data, errObj && errObj.status), true);
                    })
                    .finally(function () {
                        setButtonBusy(confirmBtn, false);
                        setButtonBusy(payBtn, false);
                        pendingPayRunId = null;
                    });
            });
        }

        loadList();
    }

    function initCalculator() {
        var form = document.querySelector("[data-pkwt-calc-form]");
        var out = document.querySelector("[data-pkwt-calc-result]");
        var err = document.querySelector("[data-pkwt-calc-error]");
        var submitBtn = form ? form.querySelector('button[type="submit"]') : null;
        if (!form || !out) {
            return;
        }
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            if (typeof form.reportValidity === "function" && !form.reportValidity()) {
                return;
            }
            if (err) {
                err.textContent = "";
                err.classList.add("d-none");
            }
            out.innerHTML = '<p class="text-muted mb-0">Menghitung…</p>';
            var fd = new FormData(form);
            var startDate = String(fd.get("contractStartDate") || "").trim();
            var endDate = String(fd.get("contractEndDate") || "").trim();
            var baseSalary = Number(fd.get("baseMonthlySalary"));

            if (!startDate || !endDate) {
                if (err) {
                    err.textContent = "Tanggal mulai dan akhir kontrak wajib diisi.";
                    err.classList.remove("d-none");
                }
                out.innerHTML = '';
                return;
            }
            if (Number.isNaN(baseSalary) || baseSalary < 0) {
                if (err) {
                    err.textContent = "Gaji pokok wajib angka >= 0.";
                    err.classList.remove("d-none");
                }
                out.innerHTML = '';
                return;
            }
            if (new Date(endDate) < new Date(startDate)) {
                if (err) {
                    err.textContent = "Akhir kontrak tidak boleh lebih kecil dari mulai kontrak.";
                    err.classList.remove("d-none");
                }
                out.innerHTML = '';
                return;
            }

            setButtonBusy(submitBtn, true, "Menghitung…");
            apiRequest("post", "/v1/hcm/payroll/pkwt-calculate", {
                contractStartDate: startDate,
                contractEndDate: endDate,
                baseMonthlySalary: baseSalary,
                fixedMonthlyAllowance: 0,
            })
                .then(function (resp) {
                    if (!resp || resp.success !== true || !resp.data) {
                        throw { status: 0, data: resp };
                    }
                    var d = resp.data;
                    out.innerHTML =
                        '<dl class="row mb-0 small">' +
                        '<dt class="col-sm-4">Status</dt><dd class="col-sm-8">' + escapeHtml(d.status) + ' · eligible: ' + (d.eligible ? 'ya' : 'tidak') + '</dd>' +
                        '<dt class="col-sm-4">Bulan masa kerja</dt><dd class="col-sm-8">' + escapeHtml(String(d.monthsOfService || 0)) + '</dd>' +
                        '<dt class="col-sm-4">Pengali</dt><dd class="col-sm-8">' + escapeHtml(String(d.multiplier || 0)) + '</dd>' +
                        '<dt class="col-sm-4">Upah acuan</dt><dd class="col-sm-8">' + escapeHtml(formatIdr(d.referenceMonthlyWage || 0)) + '</dd>' +
                        '<dt class="col-sm-4">Kompensasi</dt><dd class="col-sm-8 fw-semibold">' + escapeHtml(formatIdr(d.compensationAmount || 0)) + '</dd>' +
                        '<dt class="col-sm-4">Referensi</dt><dd class="col-sm-8">' + escapeHtml(d.regulationReference || '') + '</dd>' +
                        '</dl>';
                })
                .catch(function (errObj) {
                    out.innerHTML = '';
                    if (err) {
                        err.textContent = formatApiError(errObj && errObj.data, errObj && errObj.status);
                        err.classList.remove("d-none");
                    }
                })
                .finally(function () {
                    setButtonBusy(submitBtn, false);
                });
        });
    }

    function init() {
        if (!document.querySelector("[data-pkwt-list-body]")) {
            return;
        }
        initList();
        initCalculator();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
