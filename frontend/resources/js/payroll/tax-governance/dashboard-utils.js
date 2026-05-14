(function (window, document) {
    "use strict";

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function setText(el, value) {
        if (el) {
            el.textContent = String(value == null ? "" : value);
        }
    }

    function formatPolicyReference(data) {
        if (!data) {
            return "draft-baru";
        }
        var policyCode = data.policyCode != null ? String(data.policyCode).trim() : "";
        if (policyCode) {
            return policyCode;
        }
        var policyId = Number(data.id);
        if (Number.isFinite(policyId) && policyId > 0) {
            return "TAXPOL-" + String(Math.trunc(policyId));
        }
        return "tersimpan";
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatMoney(amount) {
        if (amount == null || amount === "") {
            return "-";
        }
        var number = Number(amount);
        if (Number.isNaN(number)) {
            return String(amount);
        }
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(number);
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

    function formatMonthOnly(value) {
        if (!value) {
            return "-";
        }
        var normalized = /^\d{4}-\d{2}$/.test(String(value)) ? String(value) + "-01" : String(value);
        var date = new Date(normalized);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }
        return new Intl.DateTimeFormat("id-ID", {
            month: "long",
            year: "numeric",
            timeZone: "Asia/Jakarta",
        }).format(date);
    }

    function normalizeBillingCycleType(value) {
        var normalized = String(value || "monthly").toLowerCase();
        if (normalized === "yearly" || normalized === "custom") {
            return normalized;
        }
        return "monthly";
    }

    function computeNextRenewalMonth(billingMonth, billingCycleType) {
        if (!billingMonth || !/^\d{4}-\d{2}$/.test(String(billingMonth))) {
            return "";
        }
        var baseDate = new Date(String(billingMonth) + "-01T00:00:00+07:00");
        if (Number.isNaN(baseDate.getTime())) {
            return "";
        }
        var type = normalizeBillingCycleType(billingCycleType);
        if (type === "custom") {
            return "";
        }
        var nextDate = new Date(baseDate.getTime());
        nextDate.setMonth(nextDate.getMonth() + (type === "yearly" ? 12 : 1));
        return nextDate.getFullYear() + "-" + String(nextDate.getMonth() + 1).padStart(2, "0");
    }

    function renderBillingCycleDetail(item) {
        var cycleType = normalizeBillingCycleType(item && item.billing_cycle_type);
        var cycleLabel = cycleType === "yearly"
            ? "Tahunan"
            : (cycleType === "custom" ? "Custom" : "Bulanan");
        var billingMonthLabel = formatMonthOnly(item && item.billing_month);
        var nextRenewalMonth = item && item.next_renewal_month
            ? String(item.next_renewal_month)
            : computeNextRenewalMonth(item && item.billing_month, cycleType);
        var renewalLabel = nextRenewalMonth
            ? formatMonthOnly(nextRenewalMonth)
            : "Menyesuaikan policy";
        var cycleClass = cycleType === "yearly"
            ? "bg-primary-subtle text-primary"
            : (cycleType === "custom" ? "bg-secondary-subtle text-secondary" : "bg-success-subtle text-success");
        var renewalTone = cycleType === "yearly"
            ? "text-primary"
            : (cycleType === "monthly" ? "text-success" : "text-secondary");

        return "<div class=\"tax-cycle-card\">"
            + "<div class=\"tax-cycle-card__head\">"
            + "<span class=\"tax-cycle-card__title\">Siklus Kewajiban</span>"
            + "<span class=\"badge " + cycleClass + "\">" + escapeHtml(cycleLabel) + "</span>"
            + "</div>"
            + "<div class=\"tax-cycle-card__meta\">"
            + "<div class=\"tax-cycle-card__item\">"
            + "<span class=\"tax-cycle-card__label\">Periode acuan</span>"
            + "<span class=\"tax-cycle-card__value tax-cycle-card__value--muted\">" + escapeHtml(billingMonthLabel) + "</span>"
            + "</div>"
            + "<div class=\"tax-cycle-card__item\">"
            + "<span class=\"tax-cycle-card__label\">Renewal berikutnya</span>"
            + "<span class=\"tax-cycle-card__value " + renewalTone + "\">" + escapeHtml(renewalLabel) + "</span>"
            + "</div>"
            + "</div>"
            + "</div>";
    }

    function toTitleCase(value) {
        return String(value || "")
            .replace(/_/g, " ")
            .replace(/\b\w/g, function (char) { return char.toUpperCase(); });
    }

    function getPolicyRuleValue(data, key, fallbackKey) {
        var rules = data && data.rules ? data.rules : {};
        if (Object.prototype.hasOwnProperty.call(rules, key) && rules[key]) {
            return rules[key];
        }
        if (fallbackKey && Object.prototype.hasOwnProperty.call(rules, fallbackKey) && rules[fallbackKey]) {
            return rules[fallbackKey];
        }
        return "";
    }

    function summarizePolicySchedules(rateSchedules) {
        if (!Array.isArray(rateSchedules) || !rateSchedules.length) {
            return "Belum ada statutory schedule.";
        }

        var categories = rateSchedules.map(function (schedule) {
            return String(schedule.category || schedule.lookupTableCode || schedule.bracket || "").trim();
        }).filter(Boolean);

        return "Schedule kategori: <strong>" + escapeHtml(categories.join(", ")) + "</strong>";
    }

    function getActiveScreen(root) {
        var value = root && root.dataset ? root.dataset.taxGovernanceScreen : "";
        return value || null;
    }

    function isPlatformScreen(screen) {
        return screen === "platform-billing" || screen === "platform-tax-compliance";
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

    function parseApiError(error, fallback) {
        if (error && error.response && error.response.status === 401) {
            return { message: "Sesi login berakhir. Silakan login ulang.", status: 401 };
        }
        if (error && error.response && error.response.status === 403) {
            return { message: "Anda tidak memiliki akses untuk aksi ini.", status: 403 };
        }
        var payload = error && error.response ? error.response.data : null;
        var message = payload && payload.error && payload.error.message
            ? payload.error.message
            : (payload && payload.message ? payload.message : fallback);

        return {
            message: message || "Permintaan gagal diproses.",
            status: error && error.response ? error.response.status : 0,
            payload: payload,
        };
    }

    function getDefaultPeriodRange() {
        return { startDate: "", endDate: "" };
    }

    function getCurrentMonthValue() {
        var now = new Date();
        return now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");
    }

    function buildAuditQuery() {
        return {
            month: getCurrentMonthValue(),
            period: "monthly",
            startDate: "",
            endDate: "",
        };
    }

    window.ArcavTaxGovernanceDashboardUtils = {
        buildAuditQuery: buildAuditQuery,
        clearError: clearError,
        computeNextRenewalMonth: computeNextRenewalMonth,
        enforceSubscriptionLockIfNeeded: enforceSubscriptionLockIfNeeded,
        escapeHtml: escapeHtml,
        formatDate: formatDate,
        formatDateOnly: formatDateOnly,
        formatMoney: formatMoney,
        formatMonthOnly: formatMonthOnly,
        formatPolicyReference: formatPolicyReference,
        getActiveScreen: getActiveScreen,
        getCurrentMonthValue: getCurrentMonthValue,
        getDefaultPeriodRange: getDefaultPeriodRange,
        getPolicyRuleValue: getPolicyRuleValue,
        isPlatformScreen: isPlatformScreen,
        normalizeBillingCycleType: normalizeBillingCycleType,
        parseApiError: parseApiError,
        platformAccessMessage: platformAccessMessage,
        qs: qs,
        renderBillingCycleDetail: renderBillingCycleDetail,
        setText: setText,
        showError: showError,
        showPlatformGate: showPlatformGate,
        summarizePolicySchedules: summarizePolicySchedules,
        toTitleCase: toTitleCase,
    };
})(window, document);
