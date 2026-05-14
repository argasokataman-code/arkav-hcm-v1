(function (window) {
  "use strict";

  function formatCompanyReference(row) {
    const companyCode = row && row.company_code ? String(row.company_code).trim() : "";
    if (companyCode) {
      return companyCode;
    }
    const companyId = Number(row && row.company_id);
    if (Number.isFinite(companyId) && companyId > 0) {
      return "CMP-" + String(Math.trunc(companyId));
    }
    return "Company tercatat";
  }

  function esc(v) {
    return String(v || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function normalizeAnomalyBadges(flags) {
    const list = Array.isArray(flags) ? flags : [];
    if (!list.length) {
      return '<span class="badge bg-success-subtle text-success">No anomaly</span>';
    }

    return list.map(function (rawFlag) {
      const flag = String(rawFlag || "").toUpperCase();
      let cls = "bg-warning-subtle text-warning";
      let label = flag;

      if (flag === "BILLING_OVERDUE_INVOICE") {
        cls = "bg-danger-subtle text-danger";
        label = "Invoice overdue";
      } else if (flag === "BILLING_PARTIAL_PAYMENT") {
        cls = "bg-warning-subtle text-warning";
        label = "Partial payment";
      } else if (flag === "BILLING_UNPAID_INVOICE") {
        cls = "bg-warning-subtle text-warning";
        label = "Invoice unpaid";
      } else if (flag === "SUBSCRIPTION_NOT_ACTIVE") {
        cls = "bg-info-subtle text-info";
        label = "Subscription non-active";
      }

      return '<span class="badge ' + cls + '">' + esc(label) + '</span>';
    }).join(" ");
  }

  function formatDate(dateStr) {
    if (!dateStr) return "-";
    const d = new Date(dateStr);
    return (
      ("0" + d.getDate()).slice(-2) +
      "/" +
      ("0" + (d.getMonth() + 1)).slice(-2) +
      "/" +
      d.getFullYear()
    );
  }

  function formatDateTime(dateStr) {
    if (!dateStr) return "-";
    const d = new Date(dateStr);
    if (Number.isNaN(d.getTime())) return String(dateStr);
    return (
      ("0" + d.getDate()).slice(-2) +
      "/" +
      ("0" + (d.getMonth() + 1)).slice(-2) +
      "/" +
      d.getFullYear() +
      " " +
      ("0" + d.getHours()).slice(-2) +
      ":" +
      ("0" + d.getMinutes()).slice(-2)
    );
  }

  function formatCurrency(amount) {
    if (!amount) return "Rp 0";
    return "Rp " + parseInt(amount, 10).toLocaleString("id-ID");
  }

  function subscriptionRouteKey(sub) {
    if (!sub || typeof sub !== "object") return "";
    return String(sub.uuid || sub.id || "");
  }

  function defaultRenewEndDateFromBillingCycle(billingCycle) {
    const d = new Date();
    if (billingCycle === "yearly") {
      d.setFullYear(d.getFullYear() + 1);
    } else {
      d.setMonth(d.getMonth() + 1);
    }
    return d.toISOString().slice(0, 10);
  }

  function isRenewableSubscriptionStatus(status) {
    return (
      status === "expired" ||
      status === "cancelled" ||
      status === "suspended" ||
      status === "inactive"
    );
  }

  window.ArcavSubscriptionsUtils = {
    defaultRenewEndDateFromBillingCycle: defaultRenewEndDateFromBillingCycle,
    esc: esc,
    formatCompanyReference: formatCompanyReference,
    formatCurrency: formatCurrency,
    formatDate: formatDate,
    formatDateTime: formatDateTime,
    isRenewableSubscriptionStatus: isRenewableSubscriptionStatus,
    normalizeAnomalyBadges: normalizeAnomalyBadges,
    subscriptionRouteKey: subscriptionRouteKey,
  };
})(window);
