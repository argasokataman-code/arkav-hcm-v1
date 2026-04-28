(function (window, document) {
    "use strict";

    var editorState = {
        uuid: null,
        version: null,
        status: null,
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function setText(el, value) {
        if (el) {
            el.textContent = String(value == null ? "" : value);
        }
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function toTitleCase(value) {
        return String(value || "")
            .replace(/_/g, " ")
            .replace(/\b\w/g, function (char) { return char.toUpperCase(); });
    }

    function formatDate(value) {
        if (!value) {
            return "-";
        }
        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }
        return new Intl.DateTimeFormat("id-ID", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false,
            timeZone: "Asia/Jakarta",
        }).format(date) + " WIB";
    }

    function formatDateOnly(value) {
        if (!value) {
            return "-";
        }
        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }
        return new Intl.DateTimeFormat("id-ID", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            timeZone: "Asia/Jakarta",
        }).format(date);
    }

    function formatMoney(value) {
        var num = Number(value || 0);
        if (Number.isNaN(num)) {
            return "-";
        }
        return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(num);
    }

    function getCurrentMonthValue() {
        var now = new Date();
        return now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");
    }

    function getActiveScreen(root) {
        return String(root.getAttribute("data-tax-governance-screen") || "landing");
    }

    function platformAccessMessage(screen, context) {
        if (screen === "platform-tax-compliance") {
            return context === "report"
                ? "Akses ke laporan government tax compliance dibatasi pada akun global admin berizin."
                : "Akses ke kebijakan government tax compliance dibatasi pada akun global admin berizin.";
        }

        return context === "report"
            ? "Akses ke laporan billing dan revenue platform dibatasi pada akun global admin berizin."
            : "Akses ke kebijakan billing dan revenue platform dibatasi pada akun global admin berizin.";
    }

    function isPlatformScreen(screen) {
        return screen === "platform-billing" || screen === "platform-tax-compliance";
    }

    function getPolicyUuid(root) {
        var value = String(root.getAttribute("data-tax-governance-policy-uuid") || "").trim();
        return value || null;
    }

    function getSubscriptionStatus() {
        var wrapper = document.querySelector(".main-wrapper");
        if (!wrapper || !wrapper.dataset) {
            return "";
        }
        return String(wrapper.dataset.subscriptionStatus || "").trim();
    }

    function enforceSubscriptionLockIfNeeded() {
        if (!window.AuthApi || typeof window.AuthApi.showUpgradeRequiredModal !== "function") {
            return false;
        }
        var status = getSubscriptionStatus();
        if (status !== "trial" && status !== "pending_payment") {
            return false;
        }
        window.AuthApi.showUpgradeRequiredModal({
            title: "Aktivasi paket diperlukan",
            message: "Akses ke fitur ini memerlukan paket aktif. Lanjutkan aktivasi paket untuk membuka fitur ini.",
            mode: "upgrade-lock",
        });
        return true;
    }

    function showError(root, message) {
        var node = qs("[data-tax-governance-error]", root);
        if (!node) {
            return;
        }
        node.classList.remove("d-none");
        node.textContent = String(message || "Permintaan gagal diproses.");
    }

    function clearError(root) {
        var node = qs("[data-tax-governance-error]", root);
        if (!node) {
            return;
        }
        node.classList.add("d-none");
        node.textContent = "";
    }

    function showPlatformGate(root, message) {
        var node = qs("[data-tax-platform-gate]", root);
        if (!node) {
            return;
        }
        if (!message) {
            node.classList.add("d-none");
            node.textContent = "";
            return;
        }
        node.classList.remove("d-none");
        node.textContent = String(message);
    }

    function apiRequest(method, path, payloadOrParams) {
        if (!window.AuthApi || typeof window.AuthApi.request !== "function") {
            return Promise.reject(new Error("AuthApi unavailable."));
        }
        return window.AuthApi.request(method, path, payloadOrParams).then(function (response) {
            return response && response.data ? response.data : {};
        });
    }

    function apiGet(path, params) {
        return apiRequest("get", path, params);
    }

    function apiPost(path, payload) {
        return apiRequest("post", path, payload);
    }

    function apiPatch(path, payload) {
        return apiRequest("patch", path, payload);
    }

    function parseApiError(error, fallback) {
        var status = Number(error && (error.status || (error.response && error.response.status)));
        var payload = (error && error.response && error.response.data) || null;
        var message = payload && payload.error && payload.error.message
            ? payload.error.message
            : (error && error.message ? error.message : fallback || "Permintaan gagal diproses.");
        return {
            status: status,
            message: message,
            code: payload && payload.error ? payload.error.code : "",
        };
    }

    function getDefaultPeriodRange() {
        var today = new Date();
        var start = new Date(today.getFullYear(), today.getMonth(), 1);
        var end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        function toIsoDate(d) {
            return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
        }
        return { start: toIsoDate(start), end: toIsoDate(end) };
    }

    function buildAuditQuery() {
        var period = getDefaultPeriodRange();
        return {
            period_start: period.start,
            period_end: period.end,
            format: "json",
        };
    }

    function renderOverallStatus(root, compliance) {
        var status = compliance && compliance.compliance_status ? compliance.compliance_status : {};
        var overallRaw = String(status.overall_status || "unknown");
        var badge = qs("[data-tax-overall-badge]", root);

        setText(qs("[data-tax-overall-status]", root), toTitleCase(overallRaw));
        setText(badge, overallRaw === "compliant" ? "Patuh" : "Perlu Tindak Lanjut");
        if (badge) {
            badge.className = overallRaw === "compliant"
                ? "badge bg-success-subtle text-success"
                : "badge bg-warning-subtle text-warning";
        }

        setText(qs("[data-tax-next-review]", root), "Review berikutnya: " + formatDateOnly(status.next_review_date));
        setText(qs("[data-tax-reporting-period]", root), String(compliance && compliance.reporting_period ? compliance.reporting_period : "-"));

        var statutory = status.statutory_tax_compliance || {};
        var billing = status.billing_tax_compliance || {};
        setText(qs("[data-tax-policy-version]", root), statutory.policy_version ? "v" + statutory.policy_version : "Belum ada kebijakan aktif");
        setText(qs("[data-tax-policy-publication]", root), statutory.last_publication_date ? "Dipublikasikan: " + formatDateOnly(statutory.last_publication_date) : "Belum ada riwayat publikasi");
        setText(qs("[data-tax-anomaly-count]", root), Number(statutory.anomalies_unresolved || 0));
        setText(qs("[data-tax-anomaly-hint]", root), Number(statutory.anomalies_unresolved || 0) > 0 ? "Perlu tindak lanjut penyelesaian" : "Tidak ada anomali aktif");
        setText(qs("[data-tax-billing-outstanding]", root), formatMoney(billing.amount_outstanding || 0));
        setText(qs("[data-tax-billing-status]", root), "Status pembayaran: " + toTitleCase(billing.payment_status || "unknown"));
    }

    function renderRecommendedActions(root, compliance) {
        var list = qs("[data-tax-action-list]", root);
        if (!list) {
            return;
        }
        var actions = Array.isArray(compliance && compliance.recommended_actions) ? compliance.recommended_actions : [];
        if (!actions.length) {
            list.innerHTML = "<li class=\"list-group-item\">Tidak ada tindakan mendesak saat ini.</li>";
            return;
        }
        list.innerHTML = actions.map(function (action) {
            return "<li class=\"list-group-item d-flex justify-content-between\"><span>" + escapeHtml(action.action || "Aksi") + "</span><span class=\"badge bg-warning-subtle text-warning\">" + escapeHtml(String(action.priority || "medium").toUpperCase()) + "</span></li>";
        }).join("");
    }

    function renderAnomalyTable(root, auditData) {
        var tbody = qs("[data-tax-anomaly-table]", root);
        if (!tbody) {
            return;
        }
        var table = tbody.closest("table");
        var columnCount = table && table.tHead && table.tHead.rows && table.tHead.rows[0]
            ? table.tHead.rows[0].cells.length
            : 4;
        var anomalies = Array.isArray(auditData && auditData.anomalies_detected) ? auditData.anomalies_detected : [];
        if (!anomalies.length) {
            tbody.innerHTML = "<tr><td colspan=\"" + columnCount + "\" class=\"text-center text-muted py-4\">Tidak ada anomali terdeteksi.</td></tr>";
            return;
        }
        tbody.innerHTML = anomalies.slice(0, 10).map(function (item) {
            var severityClass = item.severity === "critical" ? "badge bg-danger-subtle text-danger" : (item.severity === "warning" ? "badge bg-warning-subtle text-warning" : "badge bg-secondary-subtle text-secondary");
            if (columnCount <= 3) {
                return "<tr><td><div class=\"fw-semibold\">" + escapeHtml(toTitleCase(item.type)) + "</div><small class=\"text-muted\">" + escapeHtml(item.description || "") + "</small></td><td><span class=\"" + severityClass + "\">" + escapeHtml(toTitleCase(item.severity)) + "</span></td><td>" + escapeHtml(item.resolved ? "Selesai" : "Terbuka") + "</td></tr>";
            }
            return "<tr><td><div class=\"fw-semibold\">" + escapeHtml(toTitleCase(item.type)) + "</div><small class=\"text-muted\">" + escapeHtml(item.description || "") + "</small></td><td><span class=\"" + severityClass + "\">" + escapeHtml(toTitleCase(item.severity)) + "</span></td><td>" + escapeHtml(formatDate(item.detected_at)) + "</td><td>" + escapeHtml(item.resolved ? "Selesai" : "Terbuka") + "</td></tr>";
        }).join("");
    }

    function renderEventTable(root, auditData) {
        var tbody = qs("[data-tax-event-table]", root);
        if (!tbody) {
            return;
        }
        var events = Array.isArray(auditData && auditData.change_history) ? auditData.change_history : [];
        var period = auditData && auditData.period ? auditData.period : {};
        setText(qs("[data-tax-audit-period]", root), "Periode " + (period.start || "-") + " sampai " + (period.end || "-"));
        if (!events.length) {
            tbody.innerHTML = "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">Tidak ada perubahan kebijakan pada periode ini.</td></tr>";
            return;
        }
        tbody.innerHTML = events.slice(0, 12).map(function (event) {
            return "<tr><td>v" + escapeHtml(event.version || "-") + "</td><td>" + escapeHtml(toTitleCase(event.action)) + "</td><td>" + escapeHtml(event.actor_name || "Sistem") + "</td><td>" + escapeHtml(formatDate(event.timestamp)) + "</td><td>" + escapeHtml(event.change_summary || "-") + "</td></tr>";
        }).join("");
    }

    function renderTenantAuditReportTable(root, auditData) {
        var tbody = qs("[data-tax-report-audit-table]", root);
        if (!tbody) {
            return;
        }

        var anomalies = Array.isArray(auditData && auditData.anomalies_detected) ? auditData.anomalies_detected : [];
        if (!anomalies.length) {
            tbody.innerHTML = "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">Tidak ada anomali pada periode terpilih.</td></tr>";
            return;
        }

        tbody.innerHTML = anomalies.slice(0, 25).map(function (item) {
            return "<tr><td>" + escapeHtml(item.tenant_name || "Tenant Aktif") + "</td><td>" + escapeHtml(toTitleCase(item.type || "unknown")) + "</td><td>" + escapeHtml(String(item.count || 1)) + "</td><td>" + escapeHtml(item.resolved ? "Resolved" : "Open") + "</td><td>" + escapeHtml(formatDate(item.detected_at)) + "</td></tr>";
        }).join("");
    }

    function renderTenantPolicies(root, policiesResponse) {
        var tbody = qs("[data-tax-tenant-policy-table]", root);
        if (!tbody) {
            return;
        }
        var rows = Array.isArray(policiesResponse && policiesResponse.data && policiesResponse.data.items) ? policiesResponse.data.items : [];
        if (!rows.length) {
            tbody.innerHTML = "<tr><td colspan=\"7\" class=\"text-center text-muted py-4\">Belum ada kebijakan PPh 21 tenant.</td></tr>";
            return;
        }
        tbody.innerHTML = rows.map(function (item) {
            var statusClass = item.status === "published" ? "badge bg-success-subtle text-success" : (item.status === "submitted" || item.status === "approved" ? "badge bg-warning-subtle text-warning" : "badge bg-secondary-subtle text-secondary");
            var editUrl = "/tax-rates/policies/" + encodeURIComponent(item.uuid) + "/edit";
            return "<tr><td>" + escapeHtml(item.policyCode || "-") + "</td><td>" + escapeHtml(item.name || "-") + "</td><td><span class=\"" + statusClass + "\">" + escapeHtml(toTitleCase(item.status || "-")) + "</span></td><td>" + escapeHtml(String(item.version || 0)) + "</td><td>" + escapeHtml(item.effectiveStartDate || "-") + "</td><td>" + escapeHtml(formatDate(item.updatedAt)) + "</td><td><a class=\"btn btn-sm btn-outline-primary\" href=\"" + editUrl + "\">Ubah</a></td></tr>";
        }).join("");
    }

    function renderPlatformPolicies(root, response) {
        var tbody = qs("[data-tax-platform-policy-table]", root);
        if (!tbody) {
            return;
        }
        var screen = getActiveScreen(root);
        var emptyLabel = screen === "platform-tax-compliance"
            ? "Belum ada kebijakan government tax compliance platform."
            : "Belum ada kebijakan billing dan revenue platform.";
        var data = response && response.data ? response.data : {};
        var rows = Array.isArray(data.items_global) && data.items_global.length ? data.items_global : (Array.isArray(data.items) ? data.items : []);
        if (!rows.length) {
            tbody.innerHTML = "<tr><td colspan=\"7\" class=\"text-center text-muted py-4\">" + escapeHtml(emptyLabel) + "</td></tr>";
            return;
        }
        tbody.innerHTML = rows.map(function (item) {
            return "<tr><td>" + escapeHtml(item.version || "-") + "</td><td>" + escapeHtml(String(item.subscription_tax_rate ?? item.tax_rate_percentage ?? 0)) + "%</td><td>" + escapeHtml(String(item.payroll_service_fee ?? 0)) + "%</td><td>" + escapeHtml(String(item.addon_markup_rate ?? 0)) + "%</td><td>" + escapeHtml(toTitleCase(item.status || "-")) + "</td><td>" + escapeHtml(formatDate(item.created_at)) + "</td><td>" + escapeHtml(item.effective_from || "-") + "</td></tr>";
        }).join("");
    }

    function renderPlatformReport(root, reportResponse) {
        var data = reportResponse && reportResponse.data ? reportResponse.data : {};
        var summary = data.summary_global || data.summary || {};
        var rows = Array.isArray(data.tenants_global) ? data.tenants_global : (Array.isArray(data.tenants) ? data.tenants : []);

        var grossRevenueTotal = Number(summary.total_gross_revenue || 0);
        var taxableRevenueTotal = Number(summary.total_taxable_revenue_amount || 0);
        var effectiveGrossRevenueTotal = grossRevenueTotal > 0 ? grossRevenueTotal : taxableRevenueTotal;
        var effectiveNetRevenueTotal = Number(summary.total_net_revenue || 0);
        if (effectiveNetRevenueTotal <= 0 && effectiveGrossRevenueTotal > 0) {
            effectiveNetRevenueTotal = Math.max(0, effectiveGrossRevenueTotal - Number(summary.total_tax_due || 0));
        }
        var complianceSummary = data.summary_compliance || {};

        setText(qs("[data-tax-platform-summary-subscription-revenue]", root), formatMoney(summary.total_subscription_revenue || summary.total_invoice_amount || 0));
        setText(qs("[data-tax-platform-summary-payroll-fee]", root), formatMoney(summary.total_payroll_service_fee || summary.total_cleared_revenue_amount || 0));
        setText(qs("[data-tax-platform-summary-addon-revenue]", root), formatMoney(summary.total_addon_revenue || summary.total_uncleared_revenue_amount || 0));
        setText(qs("[data-tax-platform-summary-net-revenue]", root), formatMoney(effectiveGrossRevenueTotal));

        setText(qs("[data-tax-compliance-summary-gross]", root), formatMoney(effectiveGrossRevenueTotal));
        setText(qs("[data-tax-compliance-summary-tax-due]", root), formatMoney(summary.total_tax_due || 0));
        setText(qs("[data-tax-compliance-summary-net-profit]", root), formatMoney(effectiveNetRevenueTotal));
        setText(qs("[data-tax-compliance-summary-effective-rate]", root), String(Number(complianceSummary.effective_tax_rate || summary.effective_tax_rate || 0)) + "%");

        var tbody = qs("[data-tax-platform-report-table]", root);
        if (!tbody) {
            return;
        }
        if (!rows.length) {
            var emptyColspan = tbody.closest("table") && tbody.closest("table").tHead && tbody.closest("table").tHead.rows[0]
                ? tbody.closest("table").tHead.rows[0].cells.length
                : 8;
            tbody.innerHTML = "<tr><td colspan=\"" + emptyColspan + "\" class=\"text-center text-muted py-4\">Tidak ada data laporan pada bulan terpilih.</td></tr>";
            return;
        }
        var isGovernmentTable = tbody.closest("table") && tbody.closest("table").tHead && tbody.closest("table").tHead.rows[0]
            ? tbody.closest("table").tHead.rows[0].cells.length === 7
            : false;
        tbody.innerHTML = rows.map(function (item) {
            var taxableRevenue = Number(item.taxable_revenue_amount || 0);
            var grossRevenue = Number(item.gross_revenue || 0);
            var effectiveGrossRevenue = grossRevenue > 0 ? grossRevenue : taxableRevenue;
            var netRevenue = Number(item.net_revenue || 0);
            var effectiveNetRevenue = netRevenue > 0 ? netRevenue : Math.max(0, effectiveGrossRevenue - Number(item.tax_amount_due || 0));
            var firstCol = item.tenant || item.company_name || "-";
            var secondCol = item.plan || item.plan_name || "-";
            if (isGovernmentTable) {
                var complianceStatus = Number(item.tax_amount_due || 0) > 0 ? "Calculated" : "No Tax Due";
                return "<tr><td><div class=\"fw-semibold\">" + escapeHtml(firstCol) + "</div><small class=\"text-muted\">ID " + escapeHtml(item.company_id || "-") + "</small></td><td>" + escapeHtml(formatMoney(item.taxable_revenue || effectiveGrossRevenue)) + "</td><td>" + escapeHtml(formatMoney(item.payroll_component || item.payroll_service_fee || 0)) + "</td><td>" + escapeHtml(formatMoney(item.addon_component || item.addon_revenue || 0)) + "</td><td>" + escapeHtml(formatMoney(item.total_tax_payable || item.tax_amount_due || 0)) + "</td><td>" + escapeHtml(formatMoney(effectiveNetRevenue)) + "</td><td>" + escapeHtml(complianceStatus) + "</td></tr>";
            }
            return "<tr><td><div class=\"fw-semibold\">" + escapeHtml(firstCol) + "</div><small class=\"text-muted\">ID " + escapeHtml(item.company_id || "-") + "</small></td><td>" + escapeHtml(secondCol) + "</td><td>" + escapeHtml(formatMoney(item.subscription_revenue || 0)) + "</td><td>" + escapeHtml(formatMoney(item.payroll_service_fee || 0)) + "</td><td>" + escapeHtml(formatMoney(item.addon_revenue || 0)) + "</td><td>" + escapeHtml(formatMoney(effectiveGrossRevenue)) + "</td><td>" + escapeHtml(formatMoney(item.tax_amount_due || 0)) + "</td><td>" + escapeHtml(formatMoney(effectiveNetRevenue)) + "</td></tr>";
        }).join("");
    }

    function buildPolicyEditorPayload(root) {
        var form = qs("[data-tax-policy-editor-form]", root);
        if (!form) {
            return null;
        }
        var rate = Number(form.rateValue.value || 0);
        var bracket = String(form.rateBracket.value || "A").trim();
        return {
            policyCode: String(form.policyCode.value || "").trim().toUpperCase(),
            name: String(form.name.value || "").trim(),
            effectiveStartDate: String(form.effectiveStartDate.value || "").trim(),
            effectiveEndDate: String(form.effectiveEndDate.value || "").trim() || null,
            rules: {
                scheme: "TER",
                currency: "IDR",
                preview_rate_percentage: rate,
            },
            rateSchedules: [
                {
                    bracket: bracket,
                    rate: rate,
                },
            ],
            version: editorState.version,
        };
    }

    function renderValidationPreview(root, payload) {
        var node = qs("[data-tax-policy-validation-preview]", root);
        if (!node) {
            return;
        }
        var errors = [];
        if (!payload.policyCode) {
            errors.push("Kode kebijakan wajib diisi.");
        }
        if (!payload.name) {
            errors.push("Nama kebijakan wajib diisi.");
        }
        if (!payload.effectiveStartDate) {
            errors.push("Tanggal mulai berlaku wajib diisi.");
        }
        var rate = Number(payload.rateSchedules[0].rate || 0);
        if (Number.isNaN(rate) || rate < 0 || rate > 100) {
            errors.push("Tarif harus 0-100.");
        }
        node.classList.remove("d-none", "alert-info", "alert-danger", "alert-success");
        if (errors.length) {
            node.classList.add("alert-danger");
            node.innerHTML = "<strong>Validasi gagal:</strong><br>" + errors.map(escapeHtml).join("<br>");
            return false;
        }
        node.classList.add("alert-success");
        node.innerHTML = "<strong>Validasi OK.</strong> Draft siap disimpan/diajukan.<br>" +
            "Kebijakan: <strong>" + escapeHtml(payload.policyCode) + "</strong>, Tarif: <strong>" + escapeHtml(String(rate)) + "%</strong>, Berlaku mulai: <strong>" + escapeHtml(payload.effectiveStartDate) + "</strong>";
        return true;
    }

    function populatePolicyEditor(root, data) {
        var form = qs("[data-tax-policy-editor-form]", root);
        if (!form) {
            return;
        }
        editorState.uuid = data.uuid;
        editorState.version = Number(data.version || 1);
        editorState.status = data.status || "draft";

        form.policyCode.value = data.policyCode || "";
        form.name.value = data.name || "";
        form.effectiveStartDate.value = data.effectiveStartDate || "";
        form.effectiveEndDate.value = data.effectiveEndDate || "";
        form.rateBracket.value = data.rateSchedules && data.rateSchedules[0] ? (data.rateSchedules[0].bracket || "A") : "A";
        form.rateValue.value = data.rateSchedules && data.rateSchedules[0] ? Number(data.rateSchedules[0].rate || 0) : 0;

        setText(qs("[data-tax-editor-policy-ref]", root), data.uuid || "draft-baru");
        setText(qs("[data-tax-editor-mode]", root), toTitleCase(data.status || "draft"));
    }

    function bindPolicyEditor(root, refreshAll) {
        var form = qs("[data-tax-policy-editor-form]", root);
        if (!form) {
            return;
        }

        var policyUuid = getPolicyUuid(root);
        if (policyUuid) {
            apiGet("/hcm/tax-governance/policies/" + policyUuid)
                .then(function (response) {
                    if (!response.success) {
                        throw new Error("Gagal memuat detail kebijakan.");
                    }
                    populatePolicyEditor(root, response.data || {});
                })
                .catch(function (error) {
                    var parsed = parseApiError(error, "Gagal memuat policy editor.");
                    showError(root, parsed.message);
                });
        }

        var validateBtn = qs("[data-tax-policy-validate]", root);
        if (validateBtn) {
            validateBtn.addEventListener("click", function () {
                var payload = buildPolicyEditorPayload(root);
                renderValidationPreview(root, payload);
            });
        }

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            clearError(root);
            var payload = buildPolicyEditorPayload(root);
            if (!renderValidationPreview(root, payload)) {
                return;
            }
            var isCreate = !editorState.uuid;

            var saveButton = qs("[data-tax-policy-save]", root);
            if (saveButton) {
                saveButton.disabled = true;
            }

            var request = editorState.uuid
                ? apiPatch("/hcm/tax-governance/policies/" + editorState.uuid, payload)
                : apiPost("/hcm/tax-governance/policies", payload);

            request.then(function (response) {
                if (!response.success) {
                    throw new Error("Gagal menyimpan draft.");
                }
                populatePolicyEditor(root, response.data || {});
                if (isCreate && response.data && response.data.uuid) {
                    window.location.href = "/tax-rates/policies/" + encodeURIComponent(response.data.uuid) + "/edit";
                    return;
                }
                refreshAll();
            }).catch(function (error) {
                var parsed = parseApiError(error, "Gagal menyimpan draft kebijakan.");
                showError(root, parsed.message);
            }).finally(function () {
                if (saveButton) {
                    saveButton.disabled = false;
                }
            });
        });

        var submitBtn = qs("[data-tax-policy-submit]", root);
        if (submitBtn) {
            submitBtn.addEventListener("click", function () {
                clearError(root);
                if (!editorState.uuid) {
                    showError(root, "Simpan draft dulu sebelum ajukan persetujuan.");
                    return;
                }
                var submissionNote = String((form.submissionNote && form.submissionNote.value) || "").trim();
                apiPost("/hcm/tax-governance/policies/" + editorState.uuid + "/submit", {
                    submissionNote: submissionNote,
                }).then(function (response) {
                    if (!response.success) {
                        throw new Error("Gagal mengajukan kebijakan.");
                    }
                    populatePolicyEditor(root, response.data || {});
                    if (window.ArcavUi && typeof window.ArcavUi.showInfo === "function") {
                        window.ArcavUi.showInfo("Pengajuan Berhasil", "Kebijakan berhasil diajukan ke kotak persetujuan.");
                    }
                }).catch(function (error) {
                    var parsed = parseApiError(error, "Gagal mengajukan kebijakan.");
                    showError(root, parsed.message);
                });
            });
        }
    }

    function bindPolicyCreateButton(root) {
        var createBtn = qs("[data-tax-policy-create]", root);
        if (!createBtn) {
            return;
        }
        createBtn.addEventListener("click", function () {
            clearError(root);
            var payload = {
                policyCode: "PPh21-TER-" + String(Date.now()).slice(-6),
                name: "Draft PPh 21 " + new Date().toISOString().slice(0, 10),
                effectiveStartDate: new Date().toISOString().slice(0, 10),
                effectiveEndDate: null,
                rules: { scheme: "TER", currency: "IDR" },
                rateSchedules: [{ bracket: "A", rate: 5 }],
            };
            apiPost("/hcm/tax-governance/policies", payload)
                .then(function (response) {
                    if (!response.success || !response.data || !response.data.uuid) {
                        throw new Error("Gagal membuat draft kebijakan.");
                    }
                    window.location.href = "/tax-rates/policies/" + encodeURIComponent(response.data.uuid) + "/edit";
                })
                .catch(function (error) {
                    var parsed = parseApiError(error, "Gagal membuat draft kebijakan.");
                    showError(root, parsed.message);
                });
        });
    }

    function handlePolicyAction(root, action, uuid, payload) {
        return apiPost("/hcm/tax-governance/policies/" + uuid + "/" + action, payload).catch(function (error) {
            var parsed = parseApiError(error, "Aksi kebijakan gagal diproses.");
            showError(root, parsed.message);
            throw error;
        });
    }

    function bindApprovalActions(root, refreshAll) {
        var tbody = qs("[data-tax-approval-table]", root);
        if (!tbody) {
            return;
        }
        apiGet("/hcm/tax-governance/policies", { status: "submitted", per_page: 50 }).then(function (response) {
            if (!response.success) {
                throw new Error("Gagal memuat data persetujuan.");
            }
            var rows = Array.isArray(response.data && response.data.items) ? response.data.items : [];
            if (!rows.length) {
                tbody.innerHTML = "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">Tidak ada pengajuan menunggu persetujuan.</td></tr>";
                return;
            }
            tbody.innerHTML = rows.map(function (item) {
                return "<tr data-tax-approval-row=\"" + escapeHtml(item.uuid) + "\"><td><div class=\"fw-semibold\">" + escapeHtml(item.policyCode || "-") + "</div><small class=\"text-muted\">" + escapeHtml(item.name || "-") + "</small></td><td>" + escapeHtml(item.createdAt || "-") + "</td><td>" + escapeHtml(toTitleCase(item.status || "-")) + "</td><td><textarea class=\"form-control form-control-sm\" rows=\"2\" data-tax-approval-note placeholder=\"Alasan keputusan\"></textarea></td><td><div class=\"d-flex gap-2\"><button type=\"button\" class=\"btn btn-sm btn-success\" data-tax-approval-approve>Setujui</button><button type=\"button\" class=\"btn btn-sm btn-outline-danger\" data-tax-approval-reject>Tolak</button></div></td></tr>";
            }).join("");

            Array.prototype.slice.call(tbody.querySelectorAll("[data-tax-approval-approve]")).forEach(function (button) {
                button.addEventListener("click", function () {
                    var row = button.closest("[data-tax-approval-row]");
                    var uuid = row ? row.getAttribute("data-tax-approval-row") : "";
                    var note = row && qs("[data-tax-approval-note]", row) ? qs("[data-tax-approval-note]", row).value.trim() : "";
                    if (!note) {
                        showError(root, "Catatan persetujuan wajib diisi.");
                        return;
                    }
                    handlePolicyAction(root, "approve", uuid, { approvalNote: note }).then(function () { refreshAll(); });
                });
            });

            Array.prototype.slice.call(tbody.querySelectorAll("[data-tax-approval-reject]")).forEach(function (button) {
                button.addEventListener("click", function () {
                    var row = button.closest("[data-tax-approval-row]");
                    var uuid = row ? row.getAttribute("data-tax-approval-row") : "";
                    var note = row && qs("[data-tax-approval-note]", row) ? qs("[data-tax-approval-note]", row).value.trim() : "";
                    if (!note) {
                        showError(root, "Catatan penolakan wajib diisi.");
                        return;
                    }
                    handlePolicyAction(root, "reject", uuid, { rejectionNote: note }).then(function () { refreshAll(); });
                });
            });
        }).catch(function (error) {
            var parsed = parseApiError(error, "Gagal memuat kotak persetujuan.");
            showError(root, parsed.message);
        });
    }

    function bindPublicationActions(root, refreshAll) {
        var tbody = qs("[data-tax-publication-table]", root);
        if (!tbody) {
            return;
        }
        apiGet("/hcm/tax-governance/policies", { status: "approved", per_page: 50 }).then(function (response) {
            if (!response.success) {
                throw new Error("Gagal memuat antrian publikasi.");
            }
            var rows = Array.isArray(response.data && response.data.items) ? response.data.items : [];
            if (!rows.length) {
                tbody.innerHTML = "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">Tidak ada kebijakan disetujui yang menunggu publikasi.</td></tr>";
                return;
            }
            tbody.innerHTML = rows.map(function (item) {
                return "<tr data-tax-publication-row=\"" + escapeHtml(item.uuid) + "\"><td><div class=\"fw-semibold\">" + escapeHtml(item.policyCode || "-") + "</div><small class=\"text-muted\">" + escapeHtml(item.name || "-") + "</small></td><td>" + escapeHtml(toTitleCase(item.status || "-")) + "</td><td>" + escapeHtml(String(item.version || 0)) + "</td><td><textarea class=\"form-control form-control-sm mb-2\" rows=\"2\" data-tax-publish-reason placeholder=\"Alasan publikasi\"></textarea><input type=\"date\" class=\"form-control form-control-sm\" data-tax-publish-effective value=\"" + escapeHtml(item.effectiveStartDate || new Date().toISOString().slice(0, 10)) + "\"></td><td><button type=\"button\" class=\"btn btn-sm btn-primary\" data-tax-publish-action>Publikasikan</button></td></tr>";
            }).join("");

            Array.prototype.slice.call(tbody.querySelectorAll("[data-tax-publish-action]")).forEach(function (button) {
                button.addEventListener("click", function () {
                    var row = button.closest("[data-tax-publication-row]");
                    var uuid = row ? row.getAttribute("data-tax-publication-row") : "";
                    var reason = row && qs("[data-tax-publish-reason]", row) ? qs("[data-tax-publish-reason]", row).value.trim() : "";
                    var effectiveDate = row && qs("[data-tax-publish-effective]", row) ? qs("[data-tax-publish-effective]", row).value.trim() : "";
                    if (!reason || !effectiveDate) {
                        showError(root, "Alasan publikasi dan tanggal berlaku wajib diisi.");
                        return;
                    }
                    handlePolicyAction(root, "publish", uuid, {
                        publishReason: reason,
                        effectiveStartDate: effectiveDate,
                    }).then(function () { refreshAll(); });
                });
            });
        }).catch(function (error) {
            var parsed = parseApiError(error, "Gagal memuat timeline publikasi.");
            showError(root, parsed.message);
        });
    }

    function bindGovernanceDrilldown(root) {
        var tbody = qs("[data-tax-governance-drilldown-table]", root);
        if (!tbody) {
            return;
        }
        var filter = qs("[data-tax-governance-risk-filter]", root);

        function load() {
            clearError(root);
            var risk = filter ? String(filter.value || "").trim() : "";
            apiGet("/hcm/tax-governance/governance/dashboard", {
                risk_level_filter: risk || undefined,
                per_page: 50,
            }).then(function (response) {
                if (!response.success) {
                    throw new Error("Gagal memuat drilldown governance.");
                }
                var tenants = Array.isArray(response.data && response.data.tenants) ? response.data.tenants : [];
                if (!tenants.length) {
                    tbody.innerHTML = "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">Tidak ada data tenant untuk filter risiko ini.</td></tr>";
                    return;
                }
                tbody.innerHTML = tenants.map(function (item) {
                    var riskClass = item.risk_level === "red" ? "badge bg-danger-subtle text-danger" : (item.risk_level === "yellow" ? "badge bg-warning-subtle text-warning" : "badge bg-success-subtle text-success");
                    return "<tr><td><div class=\"fw-semibold\">" + escapeHtml(item.company_name || "-") + "</div><small class=\"text-muted\">ID " + escapeHtml(item.company_id || "-") + "</small></td><td>" + escapeHtml(toTitleCase(item.latest_policy_status || "-")) + "</td><td><span class=\"" + riskClass + "\">" + escapeHtml(toTitleCase(item.risk_level || "unknown")) + "</span></td><td>" + escapeHtml(String(item.anomaly_count || 0)) + "</td><td>Lihat di laporan billing</td></tr>";
                }).join("");
            }).catch(function (error) {
                var parsed = parseApiError(error, "Gagal memuat governance drilldown.");
                showError(root, parsed.message);
                tbody.innerHTML = "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">Terjadi kesalahan saat memuat data governance.</td></tr>";
            });
        }

        if (filter && !filter.dataset.taxGovernanceBound) {
            filter.addEventListener("change", load);
            filter.dataset.taxGovernanceBound = "1";
        }
        load();
    }

    function bindPlatformActions(root, refreshAll) {
        var policyForm = qs("[data-tax-platform-policy-form]", root);
        var monthInput = qs("[data-tax-platform-report-month]", root);
        var reportRefreshBtn = qs("[data-tax-platform-report-refresh]", root);
        var screen = getActiveScreen(root);
        var policyEndpoint = screen === "platform-tax-compliance"
            ? "/hcm/tax-governance/platform-tax-compliance/policies"
            : "/hcm/tax-governance/platform-billing/policies";

        if (monthInput && !monthInput.value) {
            monthInput.value = getCurrentMonthValue();
        }

        if (policyForm) {
            var formMonth = qs("input[name=\"billing_month\"]", policyForm);
            var formEffective = qs("input[name=\"effective_from\"]", policyForm);
            if (formMonth && !formMonth.value) {
                formMonth.value = getCurrentMonthValue();
            }
            if (formEffective && !formEffective.value) {
                formEffective.value = new Date().toISOString().slice(0, 10);
            }

            policyForm.addEventListener("submit", function (event) {
                event.preventDefault();
                clearError(root);
                showPlatformGate(root, "");

                var submitBtn = qs("[data-tax-platform-policy-submit]", policyForm);
                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                var subscriptionField = qs("[name=\"subscription_tax_rate\"]", policyForm);
                var payload = subscriptionField
                    ? {
                        subscription_tax_rate: Number(subscriptionField.value || 0),
                        payroll_service_fee: Number((qs("[name=\"payroll_service_fee\"]", policyForm) || {}).value || 0),
                        addon_markup_rate: Number((qs("[name=\"addon_markup_rate\"]", policyForm) || {}).value || 0),
                        status: String((qs("[name=\"status\"]", policyForm) || {}).value || "active"),
                        notes: String((qs("[name=\"notes\"]", policyForm) || {}).value || "").trim(),
                        effective_from: String((qs("[name=\"effective_from\"]", policyForm) || {}).value || "").trim(),
                        billing_month: monthInput && monthInput.value ? monthInput.value : getCurrentMonthValue(),
                    }
                    : {
                        company_id: Number(policyForm.company_id.value),
                        billing_month: String(policyForm.billing_month.value || "").trim(),
                        billing_cycle_type: String(policyForm.billing_cycle_type.value || "monthly"),
                        tax_rate_percentage: Number(policyForm.tax_rate_percentage.value),
                        base_calculation_method: "invoice_amount_due",
                        effective_from: String(policyForm.effective_from.value || "").trim(),
                        status: String(policyForm.status.value || "active"),
                    };

                apiPost(policyEndpoint, payload).then(function (response) {
                    if (!response.success) {
                        throw new Error("Gagal menyimpan kebijakan platform.");
                    }
                    refreshAll();
                }).catch(function (error) {
                    var parsed = parseApiError(error, screen === "platform-tax-compliance"
                        ? "Gagal menyimpan kebijakan government tax compliance platform."
                        : "Gagal menyimpan kebijakan billing dan revenue platform.");
                    if (parsed.status === 403) {
                        showPlatformGate(root, platformAccessMessage(screen, "policy"));
                        return;
                    }
                    showError(root, parsed.message);
                }).finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
            });
        }

        if (reportRefreshBtn) {
            reportRefreshBtn.addEventListener("click", refreshAll);
        }
    }

    function bindExportButtons(root) {
        var exportJsonBtn = qs("[data-tax-governance-export-json]", root);
        var exportPdfBtn = qs("[data-tax-governance-export-pdf]", root);
        if (exportJsonBtn) {
            exportJsonBtn.addEventListener("click", function () {
                var query = new URLSearchParams(buildAuditQuery());
                window.open("/v1/hcm/tax-governance/reports/tenant-self-audit-export?" + query.toString(), "_blank");
            });
        }
        if (exportPdfBtn) {
            exportPdfBtn.addEventListener("click", function () {
                var query = new URLSearchParams(buildAuditQuery());
                query.set("format", "pdf");
                window.open("/v1/hcm/tax-governance/reports/tenant-self-audit-export?" + query.toString(), "_blank");
            });
        }
    }

    // ─────────────────────────────────────────────────────
    // Komponen Pajak screen
    // ─────────────────────────────────────────────────────
    var komponentData = [];
    var komponentFilter = "all";

    function loadKomponenPajak(root) {
        var tbody = qs("[data-tax-komponen-table]", root);
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Memuat komponen gaji...</td></tr>';

        apiGet("/hcm/salary-components", { per_page: 200 }).then(function (resp) {
            if (!resp || resp.success !== true) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Gagal memuat data.</td></tr>';
                return;
            }
            komponentData = resp.data || [];
            renderKomponenPajak(root);
        }).catch(function () {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Gagal memuat data.</td></tr>';
        });

        // Bind filter buttons
        var filterBtns = root.querySelectorAll("[data-tax-component-filter]");
        filterBtns.forEach(function (btn) {
            btn.addEventListener("click", function () {
                filterBtns.forEach(function (b) { b.classList.remove("btn-success", "btn-danger", "btn-secondary"); b.classList.add("btn-outline-secondary", "btn-outline-success", "btn-outline-danger"); });
                komponentFilter = btn.getAttribute("data-tax-component-filter") || "all";
                renderKomponenPajak(root);
            });
        });
    }

    function renderKomponenPajak(root) {
        var tbody = qs("[data-tax-komponen-table]", root);
        if (!tbody) return;

        var rows = komponentFilter === "all" ? komponentData : komponentData.filter(function (c) { return c.kind === komponentFilter; });

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada komponen ditemukan.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (c) {
            var kindBadge = c.kind === "addition"
                ? '<span class="badge bg-success-subtle text-success">Pendapatan</span>'
                : '<span class="badge bg-danger-subtle text-danger">Potongan</span>';
            var statusBadge = c.isActive
                ? '<span class="badge bg-success-subtle text-success">Aktif</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>';
            var terToggle = buildKomponenToggle(c.id, "includePph21TerGross", c.includePph21TerGross);
            var reconToggle = buildKomponenToggle(c.id, "includePph21AnnualReconciliation", c.includePph21AnnualReconciliation);
            return "<tr>" +
                "<td><code>" + escapeHtml(c.code) + "</code></td>" +
                "<td>" + escapeHtml(c.name) + "</td>" +
                "<td>" + kindBadge + "</td>" +
                "<td><span class='text-muted small'>" + escapeHtml(c.category || "-") + "</span></td>" +
                "<td class='text-center'>" + terToggle + "</td>" +
                "<td class='text-center'>" + reconToggle + "</td>" +
                "<td class='text-center'>" + statusBadge + "</td>" +
                "</tr>";
        }).join("");

        // Bind toggle events
        tbody.querySelectorAll("[data-komponen-toggle]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var compId = parseInt(btn.getAttribute("data-komponen-id"), 10);
                var field = btn.getAttribute("data-komponen-field");
                var currentVal = btn.getAttribute("data-komponen-val") === "1";
                var newVal = !currentVal;
                btn.disabled = true;
                btn.textContent = "...";
                var payload = {};
                payload[field] = newVal;
                apiPatch("/hcm/salary-components/" + compId + "/tax-flags", payload).then(function (resp) {
                    if (resp && resp.success) {
                        var idx = -1;
                        for (var i = 0; i < komponentData.length; i++) {
                            if (komponentData[i].id === compId) { idx = i; break; }
                        }
                        if (idx >= 0) {
                            if (field === "includePph21TerGross") komponentData[idx].includePph21TerGross = newVal;
                            if (field === "includePph21AnnualReconciliation") komponentData[idx].includePph21AnnualReconciliation = newVal;
                        }
                        renderKomponenPajak(root);
                    } else {
                        showError(root, (resp && resp.message) || "Gagal memperbarui flag.");
                        btn.disabled = false;
                        btn.textContent = currentVal ? "Ya" : "Tidak";
                    }
                }).catch(function () {
                    showError(root, "Gagal memperbarui flag pajak.");
                    btn.disabled = false;
                    btn.textContent = currentVal ? "Ya" : "Tidak";
                });
            });
        });
    }

    function buildKomponenToggle(compId, field, value) {
        var label = value ? "Ya" : "Tidak";
        var cls = value ? "btn-success" : "btn-outline-secondary";
        return '<button class="btn btn-sm ' + cls + '" data-komponen-toggle data-komponen-id="' + escapeHtml(String(compId)) + '" data-komponen-field="' + escapeHtml(field) + '" data-komponen-val="' + (value ? "1" : "0") + '">' + label + '</button>';
    }

    function loadLandingAndPlatform(root, activeScreen) {
        var reportMonthInput = qs("[data-tax-platform-report-month]", root);
        var reportMonth = reportMonthInput && reportMonthInput.value ? reportMonthInput.value : getCurrentMonthValue();
        var policyPath = activeScreen === "platform-tax-compliance"
            ? "/hcm/tax-governance/platform-tax-compliance/policies"
            : "/hcm/tax-governance/platform-billing/policies";
        var reportPath = activeScreen === "platform-tax-compliance"
            ? "/hcm/tax-governance/platform-tax-compliance/reports"
            : "/hcm/tax-governance/platform-billing/reports";

        if (activeScreen === "komponen-pajak") {
            loadKomponenPajak(root);
            return;
        }

        Promise.allSettled([
            (activeScreen === "landing" || activeScreen === "tenant-reports" || activeScreen === "global-governance")
                ? apiGet("/hcm/tax-governance/reports/tenant-compliance-status")
                : Promise.resolve({ success: true, data: {} }),
            (activeScreen === "landing" || activeScreen === "tenant-reports" || activeScreen === "global-governance")
                ? apiGet("/hcm/tax-governance/reports/tenant-self-audit-export", buildAuditQuery())
                : Promise.resolve({ success: true, data: {} }),
            (activeScreen === "tenant-policies")
                ? apiGet("/hcm/tax-governance/policies", { per_page: 50 })
                : Promise.resolve({ success: true, data: { items: [] } }),
            (isPlatformScreen(activeScreen))
                ? apiGet(policyPath, { per_page: 20, global_mode: 1 })
                : Promise.resolve({ success: true, data: { items: [] } }),
            (isPlatformScreen(activeScreen))
                ? apiGet(reportPath, { month: reportMonth })
                : Promise.resolve({ success: true, data: { summary: {}, tenants: [] } }),
        ]).then(function (responses) {
            var complianceResponse = responses[0] && responses[0].status === "fulfilled" ? responses[0].value : {};
            var auditResponse = responses[1] && responses[1].status === "fulfilled" ? responses[1].value : {};
            if ((activeScreen === "landing" || activeScreen === "tenant-reports" || activeScreen === "global-governance") && complianceResponse.success) {
                renderOverallStatus(root, complianceResponse.data || {});
                renderRecommendedActions(root, complianceResponse.data || {});
                renderAnomalyTable(root, auditResponse.data || {});
                renderEventTable(root, auditResponse.data || {});
                renderTenantAuditReportTable(root, auditResponse.data || {});
            }

            var tenantPoliciesResult = responses[2] || {};
            if (tenantPoliciesResult.status === "fulfilled" && tenantPoliciesResult.value && tenantPoliciesResult.value.success) {
                renderTenantPolicies(root, tenantPoliciesResult.value);
            }

            var platformPoliciesResult = responses[3] || {};
            if (platformPoliciesResult.status === "fulfilled" && platformPoliciesResult.value && platformPoliciesResult.value.success) {
                renderPlatformPolicies(root, platformPoliciesResult.value);
            } else {
                var policyError = parseApiError(platformPoliciesResult.reason || {}, "");
                if (policyError.status === 403) {
                    showPlatformGate(root, platformAccessMessage(activeScreen, "policy"));
                }
            }

            var platformReportResult = responses[4] || {};
            if (platformReportResult.status === "fulfilled" && platformReportResult.value && platformReportResult.value.success) {
                renderPlatformReport(root, platformReportResult.value);
            } else {
                var reportError = parseApiError(platformReportResult.reason || {}, "");
                if (reportError.status === 403) {
                    showPlatformGate(root, platformAccessMessage(activeScreen, "report"));
                }
            }
        }).catch(function (error) {
            var parsed = parseApiError(error, "Gagal memuat data pengaturan pajak.");
            showError(root, parsed.message);
        });
    }

    function initPage() {
        var root = qs("[data-tax-governance-page]");
        if (!root) {
            return;
        }

        if (enforceSubscriptionLockIfNeeded()) {
            return;
        }

        function refreshAll() {
            clearError(root);
            showPlatformGate(root, "");
            var screen = getActiveScreen(root);

            loadLandingAndPlatform(root, screen);
            bindApprovalActions(root, refreshAll);
            bindPublicationActions(root, refreshAll);
            bindGovernanceDrilldown(root);
        }

        var refreshBtn = qs("[data-tax-governance-refresh]", root);
        if (refreshBtn) {
            refreshBtn.addEventListener("click", refreshAll);
        }

        bindExportButtons(root);
        bindPlatformActions(root, refreshAll);
        bindPolicyCreateButton(root);
        bindPolicyEditor(root, refreshAll);
        refreshAll();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initPage);
    } else {
        initPage();
    }
})(window, document);
