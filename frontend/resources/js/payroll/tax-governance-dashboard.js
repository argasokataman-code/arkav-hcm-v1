var __arcavRenderPlatformReportModuleRef = null;
var __arcavRenderPlatformReportModulePromise = null;
var __arcavLoadPricingPlansScreenModuleRef = null;
var __arcavLoadPricingPlansScreenModulePromise = null;

function resolveRenderPlatformReportModule() {
    if (typeof __arcavRenderPlatformReportModuleRef === "function") {
        return __arcavRenderPlatformReportModuleRef;
    }
    if (window.ArcavTaxGovernanceModules && typeof window.ArcavTaxGovernanceModules.renderPlatformReportModule === "function") {
        __arcavRenderPlatformReportModuleRef = window.ArcavTaxGovernanceModules.renderPlatformReportModule;
        return __arcavRenderPlatformReportModuleRef;
    }
    return null;
}

function loadRenderPlatformReportModule() {
    var resolved = resolveRenderPlatformReportModule();
    if (resolved) {
        return Promise.resolve(resolved);
    }
    if (__arcavRenderPlatformReportModulePromise) {
        return __arcavRenderPlatformReportModulePromise;
    }
    try {
        var dynamicImport = new Function("modulePath", "return import(modulePath);");
        __arcavRenderPlatformReportModulePromise = dynamicImport("../tax-governance/tax-governance-platform-report.js")
            .then(function (mod) {
                if (mod && typeof mod.renderPlatformReportModule === "function") {
                    __arcavRenderPlatformReportModuleRef = mod.renderPlatformReportModule;
                }
                return resolveRenderPlatformReportModule();
            })
            .catch(function () {
                return null;
            });
    } catch (_error) {
        __arcavRenderPlatformReportModulePromise = Promise.resolve(null);
    }
    return __arcavRenderPlatformReportModulePromise;
}

function resolveLoadPricingPlansScreenModule() {
    if (typeof __arcavLoadPricingPlansScreenModuleRef === "function") {
        return __arcavLoadPricingPlansScreenModuleRef;
    }
    if (window.ArcavTaxGovernanceModules && typeof window.ArcavTaxGovernanceModules.loadPricingPlansScreenModule === "function") {
        __arcavLoadPricingPlansScreenModuleRef = window.ArcavTaxGovernanceModules.loadPricingPlansScreenModule;
        return __arcavLoadPricingPlansScreenModuleRef;
    }
    return null;
}

function loadPricingPlansScreenModuleLoader() {
    var resolved = resolveLoadPricingPlansScreenModule();
    if (resolved) {
        return Promise.resolve(resolved);
    }
    if (__arcavLoadPricingPlansScreenModulePromise) {
        return __arcavLoadPricingPlansScreenModulePromise;
    }
    try {
        var dynamicImport = new Function("modulePath", "return import(modulePath);");
        __arcavLoadPricingPlansScreenModulePromise = dynamicImport("../tax-governance/tax-governance-platform-pricing.js")
            .then(function (mod) {
                if (mod && typeof mod.loadPricingPlansScreenModule === "function") {
                    __arcavLoadPricingPlansScreenModuleRef = mod.loadPricingPlansScreenModule;
                }
                return resolveLoadPricingPlansScreenModule();
            })
            .catch(function () {
                return null;
            });
    } catch (_error) {
        __arcavLoadPricingPlansScreenModulePromise = Promise.resolve(null);
    }
    return __arcavLoadPricingPlansScreenModulePromise;
}

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

    function getPolicyUuid(root) {
        // root is [data-tax-governance-page] itself; read directly from it
        var page = (root && root.hasAttribute && root.hasAttribute('data-tax-governance-page'))
            ? root
            : qs("[data-tax-governance-page]", root || document);
        return page ? (page.getAttribute('data-tax-governance-policy-uuid') || '').trim() || null : null;
    }

    var dashboardUtils = window.ArcavTaxGovernanceDashboardUtils || {};
    var setText = dashboardUtils.setText;
    var formatPolicyReference = dashboardUtils.formatPolicyReference;
    var escapeHtml = dashboardUtils.escapeHtml;
    var formatMoney = dashboardUtils.formatMoney;
    var formatDate = dashboardUtils.formatDate;
    var formatDateOnly = dashboardUtils.formatDateOnly;
    var formatMonthOnly = dashboardUtils.formatMonthOnly;
    var normalizeBillingCycleType = dashboardUtils.normalizeBillingCycleType;
    var computeNextRenewalMonth = dashboardUtils.computeNextRenewalMonth;
    var renderBillingCycleDetail = dashboardUtils.renderBillingCycleDetail;
    var toTitleCase = dashboardUtils.toTitleCase;
    var getPolicyRuleValue = dashboardUtils.getPolicyRuleValue;
    var summarizePolicySchedules = dashboardUtils.summarizePolicySchedules;
    var getActiveScreen = dashboardUtils.getActiveScreen;
    var isPlatformScreen = dashboardUtils.isPlatformScreen;
    var platformAccessMessage = dashboardUtils.platformAccessMessage;
    var enforceSubscriptionLockIfNeeded = dashboardUtils.enforceSubscriptionLockIfNeeded;
    var showError = dashboardUtils.showError;
    var clearError = dashboardUtils.clearError;
    var showPlatformGate = dashboardUtils.showPlatformGate;

    if (typeof setText !== "function") {
        setText = function (el, value) {
            if (el) {
                el.textContent = String(value == null ? "" : value);
            }
        };
    }
    if (typeof formatPolicyReference !== "function") {
        formatPolicyReference = function () { return "draft-baru"; };
    }
    if (typeof escapeHtml !== "function") {
        escapeHtml = function (value) { return String(value == null ? "" : value); };
    }
    if (typeof toTitleCase !== "function") {
        toTitleCase = function (value) { return String(value || ""); };
    }
    if (typeof formatMoney !== "function") {
        formatMoney = function (amount) { return amount == null ? "-" : String(amount); };
    }
    if (typeof formatDate !== "function") {
        formatDate = function (value) { return value ? String(value) : "-"; };
    }
    if (typeof formatDateOnly !== "function") {
        formatDateOnly = function (value) { return value ? String(value) : "-"; };
    }
    if (typeof formatMonthOnly !== "function") {
        formatMonthOnly = function (value) { return value ? String(value) : "-"; };
    }
    if (typeof normalizeBillingCycleType !== "function") {
        normalizeBillingCycleType = function (value) { return String(value || "monthly").toLowerCase(); };
    }
    if (typeof computeNextRenewalMonth !== "function") {
        computeNextRenewalMonth = function () { return ""; };
    }
    if (typeof renderBillingCycleDetail !== "function") {
        renderBillingCycleDetail = function () { return ""; };
    }
    if (typeof getPolicyRuleValue !== "function") {
        getPolicyRuleValue = function () { return ""; };
    }
    if (typeof summarizePolicySchedules !== "function") {
        summarizePolicySchedules = function () { return "Belum ada statutory schedule."; };
    }
    if (typeof getActiveScreen !== "function") {
        getActiveScreen = function (root) { return root && root.dataset ? root.dataset.taxGovernanceScreen : null; };
    }
    if (typeof isPlatformScreen !== "function") {
        isPlatformScreen = function (screen) { return screen === "platform-billing" || screen === "platform-tax-compliance"; };
    }
    if (typeof platformAccessMessage !== "function") {
        platformAccessMessage = function () { return "Akses dibatasi pada akun berizin."; };
    }
    if (typeof enforceSubscriptionLockIfNeeded !== "function") {
        enforceSubscriptionLockIfNeeded = function () { return false; };
    }
    if (typeof showError !== "function") {
        showError = function (root, message) {
            var node = qs("[data-tax-governance-error]", root);
            if (node) {
                node.classList.remove("d-none");
                node.textContent = String(message || "Permintaan gagal diproses.");
            }
        };
    }
    if (typeof clearError !== "function") {
        clearError = function (root) {
            var node = qs("[data-tax-governance-error]", root);
            if (node) {
                node.classList.add("d-none");
                node.textContent = "";
            }
        };
    }
    if (typeof showPlatformGate !== "function") {
        showPlatformGate = function (root, message) {
            var node = qs("[data-tax-platform-gate]", root);
            if (node) {
                node.classList.toggle("d-none", !message);
                node.textContent = message ? String(message) : "";
            }
        };
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
        var validationMessage = "";
        if (payload && payload.errors && typeof payload.errors === "object") {
            var firstKey = Object.keys(payload.errors)[0];
            var firstVal = firstKey ? payload.errors[firstKey] : null;
            if (Array.isArray(firstVal) && firstVal.length) {
                validationMessage = String(firstVal[0]);
            } else if (typeof firstVal === "string" && firstVal) {
                validationMessage = firstVal;
            }
        }
        var message = payload && payload.error && payload.error.message
            ? payload.error.message
            : (validationMessage || "")
                ? validationMessage
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

    function getCurrentMonthValue() {
        var today = new Date();
        return today.getFullYear() + "-" + String(today.getMonth() + 1).padStart(2, "0");
    }

    function buildAuditQuery() {
        var period = getDefaultPeriodRange();
        return {
            period_start: period.start,
            period_end: period.end,
            format: "json",
        };
    }

    var complianceRenderersFactory = window.ArcavTaxGovernanceModules && typeof window.ArcavTaxGovernanceModules.createComplianceRenderers === "function"
        ? window.ArcavTaxGovernanceModules.createComplianceRenderers
        : null;
    var complianceRenderers = complianceRenderersFactory
        ? complianceRenderersFactory({
            escapeHtml: escapeHtml,
            formatDate: formatDate,
            formatDateOnly: formatDateOnly,
            formatMoney: formatMoney,
            qs: qs,
            setText: setText,
            toTitleCase: toTitleCase,
        })
        : {};

    var renderOverallStatus = complianceRenderers.renderOverallStatus || function () {};
    var renderRecommendedActions = complianceRenderers.renderRecommendedActions || function () {};
    var renderNonCompliantEmployees = complianceRenderers.renderNonCompliantEmployees || function () {};
    var extractEmployeeTotal = complianceRenderers.extractEmployeeTotal || function () { return 0; };
    var renderRegisteredEmployeeCount = complianceRenderers.renderRegisteredEmployeeCount || function () {};
    var renderAnomalyTable = complianceRenderers.renderAnomalyTable || function () {};
    var renderEventTable = complianceRenderers.renderEventTable || function () {};
    var renderTenantAuditReportTable = complianceRenderers.renderTenantAuditReportTable || function () {};
    var renderTenantPolicies = complianceRenderers.renderTenantPolicies || function () {};

    function parsePlatformPolicyNotes(item) {
        var rawNotes = item && item.notes ? item.notes : "";
        if (!rawNotes) {
            return {};
        }

        try {
            var decoded = typeof rawNotes === "string" ? JSON.parse(rawNotes) : rawNotes;
            if (!decoded || typeof decoded !== "object") {
                return {};
            }

            if (decoded.transaction_tax || decoded.user_note || decoded.corporate_tax) {
                return decoded;
            }

            if (decoded.notes && typeof decoded.notes === "string") {
                var nested = JSON.parse(decoded.notes);
                if (nested && typeof nested === "object") {
                    return nested;
                }
            }
        } catch (_error) {
            return {};
        }

        return {};
    }

    function getBillingCycleLabel(value) {
        var normalized = String(value || "monthly").toLowerCase();
        if (normalized === "yearly") {
            return "Tahunan";
        }
        if (normalized === "custom") {
            return "Custom";
        }

        return "Bulanan";
    }

    function fillPlatformPolicyForm(root, item, forceNewDraft) {
        var form = qs("[data-tax-platform-policy-form]", root);
        if (!form) {
            return;
        }

        var notesMeta = parsePlatformPolicyNotes(item);
        var transactionTaxMeta = notesMeta.transaction_tax && typeof notesMeta.transaction_tax === "object"
            ? notesMeta.transaction_tax
            : {};

        var monthInput = qs("[data-tax-platform-report-month]", root);
        var billingMonth = String(item && item.billing_month || "").trim() || getCurrentMonthValue();
        if (monthInput) {
            monthInput.value = billingMonth;
        }

        var transactionRateNode = qs("[name=\"transaction_tax_rate\"]", form);
        var corporateRateNode = qs("[name=\"subscription_tax_rate\"]", form);
        var descriptionNode = qs("[name=\"transaction_tax_description\"]", form);
        var notesNode = qs("[name=\"notes\"]", form);
        var statusNode = qs("[name=\"status\"]", form);
        var cycleNode = qs("[name=\"billing_cycle_type\"]", form);
        var effectiveNode = qs("[name=\"effective_from\"]", form);

        var transactionRate = Number(transactionTaxMeta.tax_rate);
        if (!Number.isFinite(transactionRate)) {
            transactionRate = Number(item && item.transaction_tax_rate || 0);
        }

        var corporateRate = Number(item && (item.government_tax_rate ?? item.subscription_tax_rate ?? item.tax_rate_percentage));
        if (!Number.isFinite(corporateRate)) {
            corporateRate = 0;
        }

        if (transactionRateNode) {
            transactionRateNode.value = String(transactionRate || 0);
        }
        if (corporateRateNode) {
            corporateRateNode.value = String(corporateRate || 0);
        }
        if (descriptionNode) {
            descriptionNode.value = String(transactionTaxMeta.description || "");
        }
        if (notesNode) {
            notesNode.value = String(notesMeta.user_note || "");
        }
        if (statusNode) {
            statusNode.value = forceNewDraft ? "draft" : String(item && item.status || "active");
        }
        if (cycleNode) {
            cycleNode.value = String(item && item.billing_cycle_type || "monthly");
        }
        if (effectiveNode) {
            effectiveNode.value = String(item && item.effective_from || "").slice(0, 10) || new Date().toISOString().slice(0, 10);
        }
    }

    function setPlatformFormMode(root, mode, activeRule) {
        var panel = qs("[data-tax-platform-form-panel]", root);
        var cancelBtn = qs("[data-tax-platform-cancel-edit]", root);
        var editBtn = qs("[data-tax-platform-edit-current]", root);
        var createBtn = qs("[data-tax-platform-new-config]", root);
        var badge = qs("[data-tax-platform-edit-mode-badge]", root);
        var submitBtn = qs("[data-tax-platform-policy-submit]", root);
        var form = qs("[data-tax-platform-policy-form]", root);

        root.dataset.taxPlatformMode = mode;

        var isOverview = mode === "overview";
        if (panel) {
            panel.classList.toggle("d-none", isOverview);
        }
        if (cancelBtn) {
            cancelBtn.classList.toggle("d-none", isOverview);
        }
        if (editBtn) {
            editBtn.disabled = !activeRule;
        }
        if (createBtn) {
            createBtn.disabled = false;
        }

        if (badge) {
            if (mode === "edit-current") {
                badge.textContent = "Mode edit aturan aktif";
                badge.className = "badge bg-warning-subtle text-warning";
            } else if (mode === "new-config") {
                badge.textContent = "Mode buat konfigurasi baru";
                badge.className = "badge bg-info-subtle text-info";
            } else {
                badge.textContent = "Mode edit";
                badge.className = "badge bg-warning-subtle text-warning";
            }
        }

        if (submitBtn) {
            submitBtn.innerHTML = mode === "new-config"
                ? '<i class="ti ti-device-floppy me-1"></i>Simpan Konfigurasi Baru'
                : '<i class="ti ti-device-floppy me-1"></i>Simpan Revisi Konfigurasi';
        }

        if (form) {
            Array.prototype.slice.call(form.querySelectorAll("input, select, textarea, button")).forEach(function (el) {
                if (el.type === "hidden") {
                    return;
                }
                if (el.hasAttribute("data-tax-platform-policy-submit")) {
                    el.disabled = isOverview;
                    return;
                }
                el.disabled = isOverview;
            });
        }
    }

    function renderPlatformOverview(root, activeRule) {
        var statusNode = qs("[data-tax-platform-overview-status]", root);
        var transactionNode = qs("[data-tax-platform-overview-transaction-rate]", root);
        var corporateNode = qs("[data-tax-platform-overview-corporate-rate]", root);
        var cycleNode = qs("[data-tax-platform-overview-cycle]", root);
        var effectiveNode = qs("[data-tax-platform-overview-effective]", root);
        var notesNode = qs("[data-tax-platform-overview-notes]", root);
        var badgeNode = qs("[data-tax-platform-overview-status-badge]", root);

        if (!activeRule) {
            setText(statusNode, "Belum ada aturan aktif");
            setText(transactionNode, "-");
            setText(corporateNode, "-");
            setText(cycleNode, "-");
            setText(effectiveNode, "-");
            setText(notesNode, "-");

            if (badgeNode) {
                badgeNode.className = "badge bg-secondary-subtle text-secondary";
                badgeNode.textContent = "Belum ada aturan aktif";
            }

            setPlatformFormMode(root, "overview", null);
            return;
        }

        var notesMeta = parsePlatformPolicyNotes(activeRule);
        var transactionRate = Number(activeRule.transaction_tax_rate ?? 0);
        var corporateRate = Number(activeRule.government_tax_rate ?? activeRule.subscription_tax_rate ?? activeRule.tax_rate_percentage ?? 0);
        var effectiveText = String(activeRule.effective_from || "-");
        var statusLabel = "Aktif (v" + String(activeRule.version || "-") + ")";

        setText(statusNode, statusLabel);
        setText(transactionNode, Number.isFinite(transactionRate) ? transactionRate.toFixed(2) + "%" : "-");
        setText(corporateNode, Number.isFinite(corporateRate) ? corporateRate.toFixed(2) + "%" : "-");
        setText(cycleNode, getBillingCycleLabel(activeRule.billing_cycle_type));
        setText(effectiveNode, effectiveText);
        setText(notesNode, String(notesMeta.user_note || "Tidak ada catatan"));

        if (badgeNode) {
            badgeNode.className = "badge bg-success-subtle text-success";
            badgeNode.textContent = statusLabel;
        }

        root.__taxPlatformActiveRule = activeRule;
        if (!root.dataset.taxPlatformMode) {
            setPlatformFormMode(root, "overview", activeRule);
        } else {
            setPlatformFormMode(root, root.dataset.taxPlatformMode, activeRule);
        }
    }

    function requestPlatformSaveConfirmation(payload) {
        var corporateRate = Number(payload && payload.subscription_tax_rate || 0);
        var transactionTaxMeta = payload && payload.notes ? parsePlatformPolicyNotes({ notes: payload.notes }).transaction_tax : {};
        var transactionRate = Number(transactionTaxMeta && transactionTaxMeta.tax_rate || 0);
        var month = String(payload && payload.billing_month || "-");
        var effective = String(payload && payload.effective_from || "-");
        var status = String(payload && payload.status || "draft");

        var message = "Konfirmasi simpan konfigurasi pajak?\n"
            + "- PPN: " + (Number.isFinite(transactionRate) ? transactionRate.toFixed(2) : "0.00") + "%\n"
            + "- Tarif Pajak Platform: " + (Number.isFinite(corporateRate) ? corporateRate.toFixed(2) : "0.00") + "%\n"
            + "- Billing Month: " + month + "\n"
            + "- Effective Date: " + effective + "\n"
            + "- Status: " + status;

        if (window.ArcavUi && typeof window.ArcavUi.confirm === "function") {
            return Promise.resolve(window.ArcavUi.confirm(message, "Konfirmasi Simpan Konfigurasi"));
        }

        return Promise.resolve(window.confirm(message));
    }

    function renderPlatformPolicies(root, response) {
        var tbody = qs("[data-tax-platform-policy-table]", root);
        if (!tbody) {
            return;
        }
        var activeRuleBadge = qs("[data-tax-platform-active-rule]", root);
        var screen = getActiveScreen(root);
        var emptyLabel = screen === "platform-tax-compliance"
            ? "Belum ada kebijakan government tax compliance platform."
            : "Belum ada kebijakan billing dan revenue platform.";
        var data = response && response.data ? response.data : {};
        var rows = Array.isArray(data.items_global) && data.items_global.length ? data.items_global : (Array.isArray(data.items) ? data.items : []);
        var table = tbody.closest("table");
        var headerCells = table && table.tHead && table.tHead.rows[0] ? table.tHead.rows[0].cells.length : 0;
        var isGovernmentHistory = screen === "platform-tax-compliance";
        var hasPayrollColumn = false;

        function getStatusMeta(statusValue) {
            var status = String(statusValue || "").toLowerCase();
            if (status === "active") {
                return {
                    className: "badge bg-success-subtle text-success",
                    label: "Aktif Saat Ini",
                };
            }
            if (status === "draft") {
                return {
                    className: "badge bg-warning-subtle text-warning",
                    label: "Draft",
                };
            }
            return {
                className: "badge bg-secondary-subtle text-secondary",
                label: toTitleCase(status || "inactive"),
            };
        }

        var activeRule = rows.find(function (item) {
            return item && item.is_current_active_rule === true;
        }) || rows.find(function (item) {
            return String(item && item.status || "").toLowerCase() === "active";
        }) || null;

        if (activeRuleBadge) {
            if (activeRule) {
                activeRuleBadge.className = "badge bg-success-subtle text-success";
                activeRuleBadge.textContent = "Aturan aktif saat ini: v" + String(activeRule.version || "-")
                    + " (Bulan " + String(activeRule.billing_month || "-") + ", Efektif " + String(activeRule.effective_from || "-") + ")";
            } else {
                activeRuleBadge.className = "badge bg-secondary-subtle text-secondary";
                activeRuleBadge.textContent = "Aturan aktif saat ini: belum tersedia";
            }
        }

        if (screen === "platform-tax-compliance") {
            renderPlatformOverview(root, activeRule);
        }

        if (!rows.length) {
            var emptyColspan = hasPayrollColumn ? 7 : 6;
            tbody.innerHTML = "<tr><td colspan=\"" + emptyColspan + "\" class=\"text-center text-muted py-4\">" + escapeHtml(emptyLabel) + "</td></tr>";
            return;
        }
        tbody.innerHTML = rows.map(function (item) {
            var statusMeta = getStatusMeta(item.status);
            var isCurrentActive = item && item.is_current_active_rule === true;
            if (String(item && item.status || "").toLowerCase() === "active" && !isCurrentActive) {
                statusMeta = {
                    className: "badge bg-info-subtle text-info",
                    label: "Active (Riwayat)",
                };
            }
            var statusCell = "<span class=\"" + statusMeta.className + "\">" + escapeHtml(statusMeta.label) + "</span>";

            if (isGovernmentHistory) {
                return "<tr><td>" + escapeHtml(item.version || "-") + "</td><td>" + escapeHtml(String(item.government_tax_rate ?? item.subscription_tax_rate ?? item.tax_rate_percentage ?? 0)) + "%</td><td>" + escapeHtml(String(item.transaction_tax_rate ?? 0)) + "%</td><td>" + statusCell + "</td><td>" + escapeHtml(formatDate(item.created_at)) + "</td><td>" + escapeHtml(item.effective_from || "-") + "</td></tr>";
            }

            return "<tr><td>" + escapeHtml(item.version || "-") + "</td><td>" + escapeHtml(String(item.subscription_tax_rate ?? item.tax_rate_percentage ?? 0)) + "%</td><td>" + escapeHtml(String(item.addon_markup_rate ?? 0)) + "%</td><td>" + statusCell + "</td><td>" + escapeHtml(formatDate(item.created_at)) + "</td><td>" + escapeHtml(item.effective_from || "-") + "</td></tr>";
        }).join("");
    }

    function renderPlatformReport(root, reportResponse) {
        var moduleFn = resolveRenderPlatformReportModule();
        var moduleArgs = [
            {
                qs: qs,
                setText: setText,
                formatMoney: formatMoney,
                getActiveScreen: getActiveScreen,
                showPlatformGate: showPlatformGate,
                escapeHtml: escapeHtml,
                renderBillingCycleDetail: renderBillingCycleDetail,
            },
            root,
            reportResponse
        ];

        if (moduleFn) {
            return moduleFn.apply(null, moduleArgs);
        }

        loadRenderPlatformReportModule().then(function (loadedFn) {
            if (typeof loadedFn === "function") {
                loadedFn.apply(null, moduleArgs);
            }
        });
        return null;
    }

    function buildPolicyEditorPayload(root) {
        var form = qs("[data-tax-policy-editor-form]", root);
        if (!form) {
            return null;
        }
        var regulationReference = "PP 58/2023 & PMK 168/PMK.03/2023";
        var regulationSourceType = "ministry_regulation";
        var effectiveStartDate = String(form.effectiveStartDate.value || "").trim();
        var effectiveEndDate = String(form.effectiveEndDate.value || "").trim() || null;
        return {
            policyCode: String(form.policyCode.value || "").trim().toUpperCase(),
            name: String(form.name.value || "").trim(),
            draftKey: "tenant-pph21-statutory-default",
            effectiveStartDate: effectiveStartDate,
            effectiveEndDate: effectiveEndDate,
            rules: {
                scheme: "STATUTORY_PPH21",
                currency: "IDR",
                calculationMethod: "monthly_ter_lookup",
                regulationReference: regulationReference,
                regulationSourceType: regulationSourceType,
            },
            rateSchedules: ["A", "B", "C"].map(function (category) {
                return {
                    category: category,
                    lookupTableCode: category,
                    calculationMode: "ter_lookup",
                    effectiveStartDate: effectiveStartDate,
                    effectiveEndDate: effectiveEndDate,
                    regulationReference: regulationReference,
                    regulationSourceType: regulationSourceType,
                };
            }),
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
        node.classList.remove("d-none", "alert-info", "alert-danger", "alert-success");
        if (errors.length) {
            node.classList.add("alert-danger");
            node.innerHTML = "<strong>Validasi gagal:</strong><br>" + errors.map(escapeHtml).join("<br>");
            return false;
        }
        node.classList.add("alert-success");
        node.innerHTML = "<strong>Validasi OK.</strong> Konfigurasi siap disimpan.<br>" +
            "Kebijakan: <strong>" + escapeHtml(payload.policyCode) + "</strong>, Berlaku mulai: <strong>" + escapeHtml(payload.effectiveStartDate) + "</strong>";
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

        setText(qs("[data-tax-editor-policy-ref]", root), formatPolicyReference(data));
        setText(qs("[data-tax-editor-mode]", root), toTitleCase(data.status || "draft"));

        // Show publish button for all editable statuses (hide only for superseded/void)
        var wfStatus = data.status || "draft";
        var workflowArea = qs("[data-tax-policy-workflow-actions]", root);
        if (workflowArea) {
            Array.prototype.slice.call(workflowArea.querySelectorAll("[data-tax-policy-workflow-btn]")).forEach(function (btn) {
                var action = btn.getAttribute("data-tax-policy-workflow-btn") || "";
                var visible = false;
                if (wfStatus === "draft") {
                    visible = action === "submit" || action === "publish";
                } else if (wfStatus === "submitted") {
                    visible = action === "approve" || action === "reject" || action === "publish";
                } else if (wfStatus === "approved") {
                    visible = action === "reject" || action === "publish";
                }
                btn.classList.toggle("d-none", !visible);
                btn.disabled = false;
            });
        }
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

        // Bind Submit / Approve / Publish workflow buttons
        var workflowContainer = qs("[data-tax-policy-workflow-actions]", root);
        if (workflowContainer) {
            Array.prototype.slice.call(workflowContainer.querySelectorAll("[data-tax-policy-workflow-btn]")).forEach(function (btn) {
                btn.addEventListener("click", function () {
                    var action = btn.getAttribute("data-tax-policy-workflow-btn");
                    var uuid = editorState.uuid;
                    if (!uuid) {
                        showError(root, "Simpan kebijakan terlebih dahulu sebelum mengubah status.");
                        return;
                    }
                    clearError(root);
                    btn.disabled = true;
                    handlePolicyAction(root, action, uuid, {})
                        .then(function (response) {
                            if (!response.success) {
                                throw new Error("Aksi gagal.");
                            }
                            populatePolicyEditor(root, response.data || {});
                        })
                        .catch(function () {})
                        .finally(function () { btn.disabled = false; });
                });
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
                    throw new Error("Gagal menyimpan konfigurasi.");
                }
                populatePolicyEditor(root, response.data || {});
                if (isCreate && response.data && response.data.uuid) {
                    window.location.href = "/tax-employees/policies/" + encodeURIComponent(response.data.uuid) + "/edit";
                    return;
                }
                refreshAll();
            }).catch(function (error) {
                var parsed = parseApiError(error, "Gagal menyimpan konfigurasi kebijakan.");
                showError(root, parsed.message);
            }).finally(function () {
                if (saveButton) {
                    saveButton.disabled = false;
                }
            });
        });

    }

    function bindPolicyCreateButton(root) {
        var createButtons = Array.prototype.slice.call(root.querySelectorAll("[data-tax-policy-create]"));
        if (!createButtons.length) {
            return;
        }
        createButtons.forEach(function (createBtn) {
            createBtn.addEventListener("click", function () {
            clearError(root);
            var payload = {
                policyCode: "PPH21-STAT-" + String(Date.now()).slice(-6),
                name: "Draft Statutory PPh 21 " + new Date().toISOString().slice(0, 10),
                draftKey: "tenant-pph21-statutory-default",
                effectiveStartDate: new Date().toISOString().slice(0, 10),
                effectiveEndDate: null,
                rules: {
                    scheme: "STATUTORY_PPH21",
                    currency: "IDR",
                    regulationReference: "PP 58/2023 & PMK 168/PMK.03/2023",
                    regulationSourceType: "ministry_regulation",
                    calculationMethod: "monthly_ter_lookup",
                },
                rateSchedules: ["A", "B", "C"].map(function (category) {
                    return {
                        category: category,
                        lookupTableCode: category,
                        calculationMode: "ter_lookup",
                        effectiveStartDate: new Date().toISOString().slice(0, 10),
                        effectiveEndDate: null,
                        regulationReference: "PP 58/2023 & PMK 168/PMK.03/2023",
                        regulationSourceType: "ministry_regulation",
                    };
                }),
            };
            apiPost("/hcm/tax-governance/policies", payload)
                .then(function (response) {
                    if (!response.success || !response.data || !response.data.uuid) {
                        throw new Error("Gagal membuat draft kebijakan.");
                    }
                    window.location.href = "/tax-employees/policies/" + encodeURIComponent(response.data.uuid) + "/edit";
                })
                .catch(function (error) {
                    var parsed = parseApiError(error, "Gagal membuat draft kebijakan.");
                    showError(root, parsed.message);
                });
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
                var userNotes = String((qs("[name=\"notes\"]", policyForm) || {}).value || "").trim();
                var userNotesCompact = userNotes.slice(0, 400);
                var transactionTaxMeta = {
                    tax_rate: Number((qs("[name=\"transaction_tax_rate\"]", policyForm) || {}).value || 0),
                    description: String((qs("[name=\"transaction_tax_description\"]", policyForm) || {}).value || "").trim().slice(0, 220),
                };
                var corporateTaxMeta = {
                    tax_rate: Number(subscriptionField ? subscriptionField.value || 0 : 0),
                };
                var notesPayload = JSON.stringify({
                    user_note: userNotesCompact,
                    transaction_tax: transactionTaxMeta,
                    corporate_tax: corporateTaxMeta,
                });
                var payload = subscriptionField
                    ? {
                        subscription_tax_rate: Number(subscriptionField.value || 0),
                        addon_markup_rate: Number((qs("[name=\"addon_markup_rate\"]", policyForm) || {}).value || 0),
                        billing_cycle_type: String((qs("[name=\"billing_cycle_type\"]", policyForm) || {}).value || "monthly"),
                        status: String((qs("[name=\"status\"]", policyForm) || {}).value || "active"),
                        notes: notesPayload,
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

                requestPlatformSaveConfirmation(payload).then(function (confirmed) {
                    if (!confirmed) {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                        }
                        return null;
                    }

                    return apiPost(policyEndpoint, payload).then(function (response) {
                        if (!response.success) {
                            throw new Error("Gagal menyimpan kebijakan platform.");
                        }
                        if (window.ArcavUi && typeof window.ArcavUi.showSuccess === "function") {
                            window.ArcavUi.showSuccess(
                                "Konfigurasi tersimpan",
                                screen === "platform-tax-compliance"
                                    ? "Konfigurasi Government Tax & Compliance berhasil diperbarui."
                                    : "Konfigurasi Billing & Revenue platform berhasil diperbarui."
                            );
                        }
                        if (screen === "platform-tax-compliance") {
                            setPlatformFormMode(root, "overview", root.__taxPlatformActiveRule || null);
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
            });
        }

        if (screen === "platform-tax-compliance") {
            var editCurrentBtn = qs("[data-tax-platform-edit-current]", root);
            var newConfigBtn = qs("[data-tax-platform-new-config]", root);
            var cancelEditBtn = qs("[data-tax-platform-cancel-edit]", root);

            if (editCurrentBtn && !editCurrentBtn.dataset.boundModeToggle) {
                editCurrentBtn.dataset.boundModeToggle = "1";
                editCurrentBtn.addEventListener("click", function () {
                    var activeRule = root.__taxPlatformActiveRule || null;
                    if (!activeRule) {
                        showError(root, "Aturan aktif belum tersedia. Gunakan Buat Konfigurasi Baru.");
                        return;
                    }

                    clearError(root);
                    fillPlatformPolicyForm(root, activeRule, false);
                    setPlatformFormMode(root, "edit-current", activeRule);
                });
            }

            if (newConfigBtn && !newConfigBtn.dataset.boundModeToggle) {
                newConfigBtn.dataset.boundModeToggle = "1";
                newConfigBtn.addEventListener("click", function () {
                    clearError(root);
                    fillPlatformPolicyForm(root, root.__taxPlatformActiveRule || null, true);
                    setPlatformFormMode(root, "new-config", root.__taxPlatformActiveRule || null);
                });
            }

            if (cancelEditBtn && !cancelEditBtn.dataset.boundModeToggle) {
                cancelEditBtn.dataset.boundModeToggle = "1";
                cancelEditBtn.addEventListener("click", function () {
                    clearError(root);
                    setPlatformFormMode(root, "overview", root.__taxPlatformActiveRule || null);
                });
            }
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
    // Pricing & Plans screen (platform-billing)
    // ─────────────────────────────────────────────────────

    function apiPut(path, payload) {
        return apiRequest("put", path, payload);
    }

    function inferLegacyTaxTreatment(component) {
        if (component.kind === "deduction" && component.includePph21AnnualReconciliation && !component.includePph21TerGross) {
            return "deductible";
        }
        if (component.includePph21TerGross && component.includePph21AnnualReconciliation) {
            return "pph21_taxable_full";
        }
        if (component.includePph21TerGross && !component.includePph21AnnualReconciliation) {
            return "pph21_taxable_partial";
        }
        if (!component.includePph21TerGross && !component.includePph21AnnualReconciliation) {
            return component.employerCostLine ? "employer_display_only" : "non_object";
        }
        return null;
    }

    function inferTaxTreatment(component) {
        if (component.taxTreatmentCode) {
            return String(component.taxTreatmentCode);
        }
        return inferLegacyTaxTreatment(component);
    }

    function isBpjsComponent(component) {
        var category = String(component.category || "").toLowerCase();
        var code = String(component.code || "").toLowerCase();
        return category.indexOf("bpjs") >= 0 || code.indexOf("bpjs") >= 0 || code.indexOf("jht") >= 0 || code.indexOf("jp") >= 0;
    }

    function contributionType(component) {
        if (!isBpjsComponent(component)) {
            return null;
        }
        if (component.employerCostLine || String(component.category || "").toLowerCase().indexOf("employer") >= 0) {
            return "employer";
        }
        return "employee";
    }

    function describeTaxTreatment(taxTreatment) {
        if (taxTreatment === "pph21_taxable_full") {
            return "Included in TER grossing-up and annual reconciliation.";
        }
        if (taxTreatment === "pph21_taxable_partial") {
            return "Included in monthly TER, excluded from year-end reconciliation override.";
        }
        if (taxTreatment === "deductible") {
            return "Reduces taxable base as deductible employee-side item.";
        }
        if (taxTreatment === "pph21_final") {
            return "Handled as final tax object outside regular PPh 21 TER grossing.";
        }
        if (taxTreatment === "pph21_separate") {
            return "Requires separate tax handling outside the standard monthly TER flow.";
        }
        if (taxTreatment === "employer_display_only") {
            return "Displayed for employer cost visibility only, excluded from employee withholding.";
        }
        if (taxTreatment === "non_object") {
            return "Excluded from employee PPh 21 object and annual reconciliation.";
        }
        return "Classification is still missing and must be confirmed before payroll cut-off.";
    }

    function getComputedRow(component) {
        var treatment = inferTaxTreatment(component);
        return {
            id: component.id,
            code: component.code || "",
            name: component.name || "",
            type: component.kind === "addition" ? "income" : "deduction",
            category: component.category || "-",
            categoryName: component.categoryName || component.category || "-",
            taxTreatment: treatment,
            taxTreatmentDescription: describeTaxTreatment(treatment),
            isBpjs: isBpjsComponent(component),
            contributionType: contributionType(component),
            status: component.isActive ? "active" : "inactive",
            source: component,
        };
    }

    function collectCategories(rows) {
        var map = {};
        rows.forEach(function (row) {
            map[row.category] = row.categoryName || row.category;
        });
        return Object.keys(map).sort().map(function (key) { return { code: key, label: map[key] || key }; });
    }

    function setAuditFilter(root) {
        komponentState.onlyIncomplete = true;
        komponentState.chip = "unmapped";
        var toggle = qs("[data-tax-map-only-incomplete]", root);
        if (toggle) {
            toggle.checked = true;
        }
        setActiveChip(root, "unmapped");
        renderKomponenPajak(root);
    }

    function setActiveChip(root, chip) {
        var chips = root.querySelectorAll("[data-tax-map-chip]");
        Array.prototype.slice.call(chips).forEach(function (btn) {
            var isActive = btn.getAttribute("data-tax-map-chip") === chip;
            btn.classList.toggle("active", isActive);
        });
    }

    function setActiveSummaryCard(root, key) {
        var cards = root.querySelectorAll("[data-tax-map-card]");
        Array.prototype.slice.call(cards).forEach(function (card) {
            var active = card.getAttribute("data-tax-map-card") === key;
            card.classList.toggle("border-primary", active);
            card.classList.toggle("shadow", active);
        });
    }

    function renderSummary(root, computedRows) {
        var total = computedRows.length;
        var taxable = computedRows.filter(function (row) {
            return row.taxTreatment === "pph21_taxable_full" || row.taxTreatment === "pph21_taxable_partial" || row.taxTreatment === "pph21_final" || row.taxTreatment === "pph21_separate";
        }).length;
        var nonTaxable = computedRows.filter(function (row) {
            return row.taxTreatment === "non_object" || row.taxTreatment === "employer_display_only";
        }).length;
        var unmapped = computedRows.filter(function (row) {
            return !row.taxTreatment;
        }).length;
        var bpjs = computedRows.filter(function (row) {
            return row.isBpjs;
        }).length;

        var totalNode = qs("[data-tax-map-total]", root);
        var taxableNode = qs("[data-tax-map-taxable]", root);
        var nonTaxableNode = qs("[data-tax-map-non-taxable]", root);
        var unmappedNode = qs("[data-tax-map-unmapped]", root);
        var bpjsNode = qs("[data-tax-map-bpjs]", root);

        if (totalNode) totalNode.textContent = String(total);
        if (taxableNode) taxableNode.textContent = String(taxable);
        if (nonTaxableNode) nonTaxableNode.textContent = String(nonTaxable);
        if (unmappedNode) unmappedNode.textContent = String(unmapped);
        if (bpjsNode) bpjsNode.textContent = String(bpjs);

        var allMappedBanner = qs("[data-tax-map-all-mapped]", root);
        if (allMappedBanner) {
            allMappedBanner.classList.toggle("d-none", !(total > 0 && unmapped === 0));
        }
    }

    function filterRows(computedRows) {
        return computedRows.filter(function (row) {
            var chipMatch = true;
            if (komponentState.chip === "income") {
                chipMatch = row.type === "income";
            } else if (komponentState.chip === "deduction") {
                chipMatch = row.type === "deduction";
            } else if (komponentState.chip === "bpjs") {
                chipMatch = row.isBpjs;
            } else if (komponentState.chip === "unmapped") {
                chipMatch = !row.taxTreatment;
            }

            var treatmentMatch = true;
            if (komponentState.treatment === "unmapped") {
                treatmentMatch = !row.taxTreatment;
            } else if (komponentState.treatment) {
                treatmentMatch = row.taxTreatment === komponentState.treatment;
            }

            var categoryMatch = !komponentState.category || row.category === komponentState.category;

            var searchTerm = komponentState.search.trim().toLowerCase();
            var searchMatch = !searchTerm
                || String(row.name).toLowerCase().indexOf(searchTerm) >= 0
                || String(row.code).toLowerCase().indexOf(searchTerm) >= 0;

            var incompleteMatch = !komponentState.onlyIncomplete || !row.taxTreatment;

            return chipMatch && treatmentMatch && categoryMatch && searchMatch && incompleteMatch;
        });
    }

    function bindKomponenMappingControls(root) {
        var syncBtn = qs("[data-tax-map-sync]", root);
        var auditBtn = qs("[data-tax-map-audit]", root);
        var searchInput = qs("[data-tax-map-search]", root);
        var treatmentSelect = qs("[data-tax-map-treatment]", root);
        var categorySelect = qs("[data-tax-map-category]", root);
        var incompleteToggle = qs("[data-tax-map-only-incomplete]", root);
        var chips = root.querySelectorAll("[data-tax-map-chip]");
        var summaryCards = root.querySelectorAll("[data-tax-map-card]");

        if (syncBtn && !syncBtn.dataset.bound) {
            syncBtn.dataset.bound = "1";
            syncBtn.addEventListener("click", function () {
                loadKomponenPajak(root);
            });
        }

        if (auditBtn && !auditBtn.dataset.bound) {
            auditBtn.dataset.bound = "1";
            auditBtn.addEventListener("click", function () {
                setAuditFilter(root);
            });
        }

        if (searchInput && !searchInput.dataset.bound) {
            searchInput.dataset.bound = "1";
            searchInput.addEventListener("input", function () {
                komponentState.search = String(searchInput.value || "");
                renderKomponenPajak(root);
            });
        }

        if (treatmentSelect && !treatmentSelect.dataset.bound) {
            treatmentSelect.dataset.bound = "1";
            treatmentSelect.addEventListener("change", function () {
                komponentState.treatment = String(treatmentSelect.value || "");
                renderKomponenPajak(root);
            });
        }

        if (categorySelect && !categorySelect.dataset.bound) {
            categorySelect.dataset.bound = "1";
            categorySelect.addEventListener("change", function () {
                komponentState.category = String(categorySelect.value || "");
                renderKomponenPajak(root);
            });
        }

        if (incompleteToggle && !incompleteToggle.dataset.bound) {
            incompleteToggle.dataset.bound = "1";
            incompleteToggle.addEventListener("change", function () {
                komponentState.onlyIncomplete = !!incompleteToggle.checked;
                renderKomponenPajak(root);
            });
        }

        Array.prototype.slice.call(chips).forEach(function (chipBtn) {
            if (chipBtn.dataset.bound) {
                return;
            }
            chipBtn.dataset.bound = "1";
            chipBtn.addEventListener("click", function () {
                var nextChip = chipBtn.getAttribute("data-tax-map-chip") || "all";
                komponentState.chip = nextChip;
                setActiveChip(root, nextChip);
                renderKomponenPajak(root);
            });
        });

        Array.prototype.slice.call(summaryCards).forEach(function (cardBtn) {
            if (cardBtn.dataset.bound) {
                return;
            }
            cardBtn.dataset.bound = "1";
            cardBtn.addEventListener("click", function () {
                var key = cardBtn.getAttribute("data-tax-map-card") || "all";
                if (key === "taxable") {
                    komponentState.treatment = "";
                    komponentState.chip = "all";
                    var treatmentNode = qs("[data-tax-map-treatment]", root);
                    if (treatmentNode) treatmentNode.value = "";
                    setActiveChip(root, "all");
                    renderKomponenPajak(root, function (rows) {
                        return rows.filter(function (row) {
                            return row.taxTreatment === "pph21_taxable_full" || row.taxTreatment === "pph21_taxable_partial" || row.taxTreatment === "pph21_final" || row.taxTreatment === "pph21_separate";
                        });
                    });
                    setActiveSummaryCard(root, "taxable");
                    return;
                }
                if (key === "non-taxable") {
                    komponentState.treatment = "non_object";
                    var treatmentNonTax = qs("[data-tax-map-treatment]", root);
                    if (treatmentNonTax) treatmentNonTax.value = "non_object";
                    setActiveSummaryCard(root, "non-taxable");
                    renderKomponenPajak(root);
                    return;
                }
                if (key === "unmapped") {
                    setAuditFilter(root);
                    setActiveSummaryCard(root, "unmapped");
                    return;
                }
                if (key === "bpjs") {
                    komponentState.chip = "bpjs";
                    setActiveChip(root, "bpjs");
                    setActiveSummaryCard(root, "bpjs");
                    renderKomponenPajak(root);
                    return;
                }
                komponentState.chip = "all";
                komponentState.treatment = "";
                komponentState.onlyIncomplete = false;
                setActiveChip(root, "all");
                setActiveSummaryCard(root, "all");
                var treatmentReset = qs("[data-tax-map-treatment]", root);
                var incompleteReset = qs("[data-tax-map-only-incomplete]", root);
                if (treatmentReset) treatmentReset.value = "";
                if (incompleteReset) incompleteReset.checked = false;
                renderKomponenPajak(root);
            });
        });
    }

    function loadKomponenPajak(root) {
        var tbody = qs("[data-tax-komponen-table]", root);
        if (!tbody) return;
        bindKomponenMappingControls(root);
        setActiveChip(root, komponentState.chip);
        setActiveSummaryCard(root, "all");
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading payroll components...</td></tr>';
    bindKomponentCrud(root);

        apiGet("/hcm/salary-components", { per_page: 200 }).then(function (resp) {
            if (!resp || resp.success !== true) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load data.</td></tr>';
                return;
            }
            komponentData = resp.data || [];
            bindCategoryFilter(root, komponentData);
            renderKomponenPajak(root);
        }).catch(function () {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load data.</td></tr>';
        });
    }

    function bindCategoryFilter(root, rows) {
        var categorySelect = qs("[data-tax-map-category]", root);
        if (!categorySelect) {
            return;
        }
        var categories = collectCategories(rows.map(getComputedRow));
        var opts = ['<option value="">All Categories</option>'];
        categories.forEach(function (cat) {
            opts.push('<option value="' + escapeHtml(cat.code) + '">' + escapeHtml(cat.label) + '</option>');
        });
        categorySelect.innerHTML = opts.join("");
        if (komponentState.category) {
            categorySelect.value = komponentState.category;
        }
    }

    function renderKomponenPajak(root, customFilterFn) {
        var tbody = qs("[data-tax-komponen-table]", root);
        if (!tbody) return;
        var emptyState = qs("[data-tax-map-empty]", root);

        var computedRows = komponentData.map(getComputedRow);
        renderSummary(root, computedRows);

        var rows = filterRows(computedRows);
        if (typeof customFilterFn === "function") {
            rows = customFilterFn(rows);
        }

        if (emptyState) {
            emptyState.classList.toggle("d-none", komponentData.length > 0);
        }

        if (!komponentData.length) {
            tbody.innerHTML = "";
            return;
        }

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No components match your current filters.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (row) {
            var kindBadge = row.type === "income"
                ? '<span class="badge bg-success-subtle text-success">Income</span>'
                : '<span class="badge bg-danger-subtle text-danger">Deduction</span>';
            var statusBadge = row.status === "active"
                ? '<span class="badge bg-success-subtle text-success">Active</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>';
            var treatmentMeta = row.taxTreatment ? TAX_TREATMENT_META[row.taxTreatment] : null;
            var treatmentLabel = treatmentMeta
                ? '<span class="badge ' + treatmentMeta.badge + '">' + escapeHtml(treatmentMeta.label) + '</span>'
                : '<span class="badge bg-danger-subtle text-danger"><i class="ti ti-alert-triangle me-1"></i>Unmapped</span>';
            var rowClass = row.taxTreatment ? "" : ' style="background: rgba(220, 53, 69, 0.08);"';
            var bpjsInfo = row.isBpjs
                ? '<span class="badge bg-info-subtle text-info me-1"><i class="ti ti-shield me-1"></i>BPJS</span>'
                    + '<span class="badge bg-light text-dark text-uppercase">' + escapeHtml(row.contributionType || "-") + '</span>'
                : '<span class="text-muted">-</span>';
            var treatmentSelect = buildTaxTreatmentSelect(row.id, row.taxTreatment);
            var isLocked = row.source && (row.source.isSystemLocked || row.source.integrationLocked);
            var editBtn = "<button type='button' class='btn btn-sm btn-outline-primary'"
                + " data-komponen-edit-id='" + escapeHtml(String(row.id)) + "'"
                + " data-bs-toggle='modal' data-bs-target='#arcav_edit_salary_component'"
                + " title='Edit komponen'><i class='ti ti-edit'></i></button>";
            var delBtn = !isLocked
                ? "<button type='button' class='btn btn-sm btn-outline-danger'"
                    + " data-komponen-delete-id='" + escapeHtml(String(row.id)) + "'"
                    + " title='Hapus komponen'><i class='ti ti-trash'></i></button>"
                : "";

            return "<tr" + rowClass + ">" +
                "<td><div class='fw-semibold'>" + escapeHtml(row.name) + "</div><small class='text-muted'>" + escapeHtml(row.code) + "</small></td>" +
                "<td>" + kindBadge + "</td>" +
                "<td><span class='badge bg-light text-dark'>" + escapeHtml(row.categoryName || row.category) + "</span></td>" +
                "<td><div class='d-flex flex-column gap-1'>" + treatmentSelect + treatmentLabel + "</div></td>" +
                "<td><span class='text-muted small'>" + escapeHtml(row.taxTreatmentDescription) + "</span></td>" +
                "<td>" + bpjsInfo + "</td>" +
                "<td>" + statusBadge + "</td>" +
                "<td class='text-center'><div class='d-inline-flex gap-1'>" + editBtn + delBtn + "</div></td>" +
                "</tr>";
        }).join("");

        Array.prototype.slice.call(tbody.querySelectorAll("[data-komponen-treatment]"))
            .forEach(function (selectNode) {
                selectNode.addEventListener("change", function () {
                    var compId = parseInt(selectNode.getAttribute("data-komponen-id"), 10);
                    var treatment = String(selectNode.value || "");
                    if (!treatment) {
                        showError(root, "Invalid tax treatment selection.");
                        return;
                    }
                    submitKomponenPatch(root, compId, { taxTreatmentCode: treatment }, selectNode);
                });
            });
    }

    function submitKomponenPatch(root, compId, payload, inputNode) {
        var originalDisabled = !!inputNode.disabled;
        inputNode.disabled = true;
        inputNode.classList.add("opacity-50");

        apiPatch("/hcm/salary-components/" + compId + "/tax-flags", payload).then(function (resp) {
            if (!resp || !resp.success) {
                showError(root, (resp && resp.message) || "Failed to update tax mapping.");
                inputNode.disabled = originalDisabled;
                inputNode.classList.remove("opacity-50");
                return;
            }

            var idx = -1;
            for (var i = 0; i < komponentData.length; i++) {
                if (komponentData[i].id === compId) {
                    idx = i;
                    break;
                }
            }

            if (idx >= 0) {
                if (Object.prototype.hasOwnProperty.call(payload, "taxTreatmentCode")) {
                    komponentData[idx].taxTreatmentCode = String(payload.taxTreatmentCode || "");
                }
            }

            renderKomponenPajak(root);
        }).catch(function () {
            showError(root, "Failed to update tax mapping.");
            inputNode.disabled = originalDisabled;
            inputNode.classList.remove("opacity-50");
        });
    }

    function buildTaxTreatmentSelect(compId, value) {
        var current = value || "";
        var options = [
            '<option value="">Select treatment</option>',
            '<option value="pph21_taxable_full">PPh 21 Taxable Full</option>',
            '<option value="pph21_taxable_partial">PPh 21 Taxable Partial</option>',
            '<option value="non_object">Non-Object</option>',
            '<option value="deductible">Deductible</option>',
            '<option value="pph21_final">PPh 21 Final</option>',
            '<option value="pph21_separate">Separate Handling</option>',
            '<option value="employer_display_only">Employer Display Only</option>',
        ];
        return '<select class="form-select form-select-sm" data-komponen-treatment data-komponen-id="' + escapeHtml(String(compId)) + '">' + options.join("") + '</select>'
            .replace('value="' + current + '"', 'value="' + current + '" selected');
    }

    // ─────────────────────────────────────────────────────
    // Komponen Pajak CRUD (full create / edit / delete)
    // ─────────────────────────────────────────────────────

    function apiDelete(path) {
        return apiRequest("delete", path, null);
    }

    function bindKomponentCrud(root) {
        var moduleFn = resolveBindKomponentCrudModule();
        var moduleArgs = {
            escapeHtml: escapeHtml,
            apiGet: apiGet,
            apiPost: apiPost,
            apiPut: apiPut,
            apiDelete: apiDelete,
            parseApiError: parseApiError,
            loadKomponenPajak: loadKomponenPajak,
            getKomponentData: function () {
                return komponentData;
            },
        };

        if (moduleFn) {
            return moduleFn(moduleArgs, root);
        }

        loadBindKomponentCrudModule().then(function (loadedFn) {
            if (typeof loadedFn === "function") {
                loadedFn(moduleArgs, root);
            }
        });
        return null;
    }

    function loadPricingPlansScreen(root) {
        var moduleFn = resolveLoadPricingPlansScreenModule();
        var moduleArgs = {
            qs: qs,
            apiGet: apiGet,
            apiPost: apiPost,
            apiPut: apiPut,
            parseApiError: parseApiError,
            showError: showError,
            getCurrentMonthValue: getCurrentMonthValue,
            formatMoney: formatMoney,
            formatDate: formatDate,
            toTitleCase: toTitleCase,
            escapeHtml: escapeHtml,
            renderPlatformReport: renderPlatformReport,
        };

        if (moduleFn) {
            return moduleFn(moduleArgs, root);
        }

        loadPricingPlansScreenModuleLoader().then(function (loadedFn) {
            if (typeof loadedFn === "function") {
                loadedFn(moduleArgs, root);
            }
        });
        return null;
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

        if (activeScreen === "platform-billing") {
            loadPricingPlansScreen(root);
            return;
        }

        Promise.allSettled([
            (activeScreen === "landing" || activeScreen === "tenant-reports" || activeScreen === "global-governance")
                ? apiGet("/hcm/tax-governance/reports/tenant-compliance-status")
                : Promise.resolve({ success: true, data: {} }),
            (activeScreen === "landing" || activeScreen === "tenant-reports" || activeScreen === "global-governance")
                ? apiGet("/hcm/tax-governance/reports/tenant-self-audit-export", buildAuditQuery())
                : Promise.resolve({ success: true, data: {} }),
            (activeScreen === "landing" || activeScreen === "global-governance")
                ? apiGet("/hcm/employees", { perPage: 1, page: 1, scope: "active_company" })
                : Promise.resolve({ success: true, data: { data: [], meta: { total: 0 } } }),
            (activeScreen === "tenant-policies")
                ? apiGet("/hcm/tax-governance/policies", { per_page: 50 })
                : Promise.resolve({ success: true, data: { items: [] } }),
            (isPlatformScreen(activeScreen))
                ? apiGet(policyPath, { per_page: 20, global_mode: 1, billing_month: reportMonth })
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
                renderNonCompliantEmployees(root, complianceResponse.data || {});
                renderAnomalyTable(root, auditResponse.data || {});
                renderEventTable(root, auditResponse.data || {});
                renderTenantAuditReportTable(root, auditResponse.data || {});
            }

            var employeeCountResult = responses[2] || {};
            if ((activeScreen === "landing" || activeScreen === "global-governance") && employeeCountResult.status === "fulfilled") {
                renderRegisteredEmployeeCount(root, employeeCountResult.value || {});
            }

            var tenantPoliciesResult = responses[3] || {};
            if (tenantPoliciesResult.status === "fulfilled" && tenantPoliciesResult.value && tenantPoliciesResult.value.success) {
                renderTenantPolicies(root, tenantPoliciesResult.value);
            }

            var platformPoliciesResult = responses[4] || {};
            if (platformPoliciesResult.status === "fulfilled" && platformPoliciesResult.value && platformPoliciesResult.value.success) {
                renderPlatformPolicies(root, platformPoliciesResult.value);
            } else {
                var policyError = parseApiError(platformPoliciesResult.reason || {}, "");
                if (policyError.status === 403) {
                    showPlatformGate(root, platformAccessMessage(activeScreen, "policy"));
                }
            }

            var platformReportResult = responses[5] || {};
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
