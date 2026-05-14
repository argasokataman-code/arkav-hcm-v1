export const API_BASE = "/v1/saas/packages";
export const API_FEATURE_CATALOG = "/v1/saas/packages/feature-catalog";
export const API_FEATURE_CATALOG_HEALTHCHECK = "/v1/saas/packages/feature-catalog/healthcheck";
export const API_PACKAGE_COMPLIANCE_CHECK = "/v1/saas/packages/check-compliance";
export const API_ADDONS_BASE = "/v1/saas/package-addons";
export const PAGE_SIZE = 10;
export const FEATURE_LIMIT_INPUT_CODE = "max_employees";

const FALLBACK_FEATURE_LIBRARY = [];
const sharedState = {
  apiToken: null,
  featureLibrary: [],
};

export function setFeatureLibrary(nextLibrary) {
  sharedState.featureLibrary = Array.isArray(nextLibrary) ? nextLibrary : [];
}

export function getFeatureLibrary() {
  return sharedState.featureLibrary.length ? sharedState.featureLibrary : FALLBACK_FEATURE_LIBRARY;
}

export function getRuntimeFeatureDisplayName(code, fallbackName) {
  const map = {
    max_employees: "Maximum Employees",
    employee_management: "Employee Directory",
    employee_document_center: "Document Center",
    employee_lifecycle: "Lifecycle Tracking",
    attendance: "Attendance Dashboard",
    attendance_shift_scheduling: "Shift Scheduling",
    leave_management: "Leave Requests",
    leave_approval_flow: "Approval Workflow",
    holiday_calendar: "Holiday Calendar",
    payroll: "Payroll Run",
    payroll_components: "Compensation Components",
    payroll_thr: "THR Management",
    performance_goal_tracking: "Advanced Goal Tracking",
    trial_billing_dashboard: "Trial Billing Dashboard",
    tax_governance: "Tax Governance",
    allowance_governance: "Allowance Governance",
    bpjs_governance: "BPJS Governance",
    spt_masa_pph21: "SPT Masa PPh 21",
    overtime: "Overtime",
    calendar_events: "Calendar Events",
    promotion: "Promotion",
    resignation: "Resignation",
    termination: "Termination",
    data_privacy: "Data Privacy",
    notes: "Notes",
    faq: "FAQ",
  };

  return map[code] || fallbackName || code;
}

export function isRecognizedRuntimeFeatureCode(code) {
  if (!code) return false;

  if (code === "max_employees") return true;
  if (code === "holiday_calendar") return true;
  if (code === "performance") return true;
  if (code === "goal_tracking") return true;
  if (code === "training") return true;
  if (code === "asset_management") return true;
  if (code === "tickets") return true;
  if (code === "notifications") return true;
  if (code === "trial_billing_dashboard") return true;
  if (code === "tax_governance") return true;
  if (code === "allowance_governance") return true;
  if (code === "bpjs_governance") return true;
  if (code === "spt_masa_pph21") return true;
  if (code === "overtime") return true;
  if (code === "calendar_events") return true;
  if (code === "promotion") return true;
  if (code === "resignation") return true;
  if (code === "termination") return true;
  if (code === "data_privacy") return true;
  if (code === "notes") return true;
  if (code === "faq") return true;

  if (code.startsWith("employee_")) return true;
  if (code.startsWith("attendance")) return true;
  if (code.startsWith("leave_")) return true;
  if (code.startsWith("payroll")) return true;
  if (code.startsWith("performance_")) return true;

  return false;
}

export function getDefaultFeatureCatalog(libraryOverride) {
  const source = Array.isArray(libraryOverride) && libraryOverride.length
    ? libraryOverride
    : getFeatureLibrary();

  return source.flatMap(function (group) {
    return (group.features || []).map(function (feature) {
      return feature.code;
    });
  });
}

export function isPackageFeatureIncluded(feature) {
  if (!feature) return false;
  if (typeof feature === "string") return true;

  const hasExplicitZeroLimit = feature.limit !== null && feature.limit !== undefined && Number(feature.limit) === 0;
  return feature.isIncluded !== false && !hasExplicitZeroLimit;
}

export function getIncludedPackageFeatures(features, options) {
  const safeFeatures = Array.isArray(features) ? features : [];
  const included = safeFeatures.filter(isPackageFeatureIncluded);

  if (!options || options.catalogOnly !== true) {
    return included;
  }

  const catalogSet = new Set(getDefaultFeatureCatalog());
  return included.filter(function (feature) {
    const code = typeof feature === "string" ? feature : feature.code;
    return !!code && catalogSet.has(code);
  });
}

export function getApiToken() {
  if (sharedState.apiToken) {
    return Promise.resolve(sharedState.apiToken);
  }

  return fetch("/api-token", {
    method: "GET",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
  })
    .then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok || !data.success) {
          return Promise.reject({
            status: res.status,
            data: data,
          });
        }
        sharedState.apiToken = data.data.token;
        return sharedState.apiToken;
      });
    })
    .catch(function (err) {
      console.error("Failed to fetch API token:", err);
      throw err;
    });
}

export function apiRequest(method, url, body) {
  return getApiToken()
    .then(function (token) {
      const headers = {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "Authorization": "Bearer " + token,
      };

      if (body && typeof body === "object" && !(body instanceof FormData)) {
        headers["Content-Type"] = "application/json";
      }

      const opts = {
        method: method,
        headers: headers,
        credentials: "same-origin",
      };

      if (body && method !== "GET") {
        opts.body = body instanceof FormData ? body : JSON.stringify(body);
      }

      return fetch(url, opts)
        .then(function (res) {
          return res
            .json()
            .catch(function () {
              return {};
            })
            .then(function (data) {
              if (!res.ok) {
                return Promise.reject({
                  status: res.status,
                  data: data,
                });
              }
              return data;
            });
        });
    })
    .catch(function (err) {
      console.error("API request failed:", err);
      throw err;
    });
}

export function esc(v) {
  return String(v || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

export function formatCurrency(amount) {
  if (!amount) return "Rp 0";
  return "Rp " + parseInt(amount).toLocaleString("id-ID");
}
