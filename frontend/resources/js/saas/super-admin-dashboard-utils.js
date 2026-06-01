(function (window) {
  "use strict";

  // Guard to prevent multiple concurrent redirects on auth failure
  var authRedirectScheduled = false;

  function redirectTo(url) {
    if (authRedirectScheduled) {
      return;
    }
    authRedirectScheduled = true;

    try {
      window.__ARCAV_LAST_REDIRECT__ = url;
    } catch (err) {
      console.warn("Failed to record redirect target", err);
    }

    if (window.location && typeof window.location.replace === "function") {
      window.location.replace(url);
    }
  }

  function redirectForAccessError(err) {
    const status = Number(err?.status || 0);
    const errorCode = String(err?.data?.error?.code || "");

    if (status === 401) {
      redirectTo("/lock-screen");
      return true;
    }

    if (status === 403 || errorCode === "ADMIN_REQUIRED") {
      redirectTo("/employee-dashboard");
      return true;
    }

    return false;
  }

  function apiRequest(method, url, body) {
    const headers = {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    };

    if (body && typeof body === "object" && !(body instanceof FormData)) {
      headers["Content-Type"] = "application/json";
    }

    const token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem("arcav_access_token");
    if (token) {
      headers.Authorization = "Bearer " + token;
    }

    const opts = {
      method: method,
      headers: headers,
      credentials: "include",
    };

    if (body && method !== "GET") {
      opts.body = body instanceof FormData ? body : JSON.stringify(body);
    }

    return fetch(url, opts)
      .then(function (res) {
        return res
          .json()
          .catch(function () {
            return { success: false, error: { message: "Invalid JSON response" } };
          })
          .then(function (data) {
            if (!res.ok) {
              const errorMsg = data?.error?.message || data?.message || res.statusText;
              const error = {
                status: res.status,
                message: errorMsg,
                data: data,
              };

              redirectForAccessError(error);
              return Promise.reject(error);
            }
            return data;
          });
      })
      .catch(function (err) {
        console.error("API request failed:", method, url, err);
        throw err;
      });
  }

  function getApiToken() {
    return new Promise(function (resolve, reject) {
      fetch("/api-token", {
        method: "GET",
        headers: {
          Accept: "application/json",
        },
        credentials: "include",
      })
        .then(function (res) {
          if (res.status === 302 || res.status === 401) {
            redirectTo("/lock-screen");
            reject(new Error("Not authenticated. Please login first."));
            return;
          }
          if (res.status === 403) {
            redirectTo("/employee-dashboard");
            reject(new Error("Admin access required."));
            return;
          }
          return res.json();
        })
        .then(function (data) {
          if (data && data.success && data.data && data.data.token) {
            localStorage.setItem("arcav_access_token", data.data.token);
            resolve(data.data.token);
          } else {
            reject(new Error("Failed to get API token: " + JSON.stringify(data)));
          }
        })
        .catch(function (err) {
          console.error("Error getting API token:", err);
          reject(err);
        });
    });
  }

  function esc(v) {
    return String(v || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function formatCurrency(amount) {
    if (!amount) return "Rp 0";
    return "Rp " + parseInt(amount, 10).toLocaleString("id-ID");
  }

  function formatPercentage(value) {
    return (parseFloat(value) || 0).toFixed(2) + "%";
  }

  function formatDate(dateStr) {
    if (!dateStr) return "-";
    const d = new Date(dateStr);
    return (("0" + d.getDate()).slice(-2) + "/" + (("0" + (d.getMonth() + 1)).slice(-2)) + "/" + d.getFullYear());
  }

  window.ArcavSuperAdminDashboardUtils = {
    redirectTo,
    redirectForAccessError,
    apiRequest,
    getApiToken,
    esc,
    formatCurrency,
    formatPercentage,
    formatDate,
  };
})(window);
