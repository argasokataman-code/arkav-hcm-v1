(function (window, document) {
    "use strict";

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function notify(msg, isError) {
        if (window.ArcavUi && typeof window.ArcavUi.toast === "function") {
            window.ArcavUi.toast(msg, isError ? "danger" : "success");
            return;
        }
        var c = document.querySelector("[data-hcm-toast-container]") || document.body.appendChild(Object.assign(document.createElement("div"), { style: "position:fixed;top:16px;right:16px;z-index:1080" }));
        c.setAttribute("data-hcm-toast-container", "1");
        var t = document.createElement("div");
        t.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = msg;
        c.appendChild(t);
        window.setTimeout(function () { t.remove(); }, 2500);
    }

    function flash(el, msg, isError) {
        if (!el) return;
        el.classList.remove("d-none", "alert-success", "alert-danger");
        el.classList.add(isError ? "alert-danger" : "alert-success");
        el.textContent = msg;
    }

    function clearFlash(el) {
        if (!el) return;
        el.classList.add("d-none");
        el.textContent = "";
    }

    function redirectToEmployeeDashboard() {
        if (window.__ARCAV_DISABLE_REDIRECTS__) {
            window.__ARCAV_LAST_REDIRECT__ = "/employee-dashboard";
            return;
        }
        window.location.replace("/employee-dashboard");
    }

    function hasMePermission(me, permissionCode) {
        var data = me && me.data ? me.data : null;
        if (!data || !permissionCode) {
            return false;
        }
        if (data.hcmGlobalAdmin === true || data.hcmAdmin === true) {
            return true;
        }
        var permissions = data.permissions;
        if (Array.isArray(permissions) && permissions.indexOf(permissionCode) !== -1) {
            return true;
        }
        if (permissions && typeof permissions === "object" && permissions[permissionCode] === true) {
            return true;
        }
        var permissionCodes = Array.isArray(data.permissionCodes) ? data.permissionCodes : [];
        return permissionCodes.indexOf(permissionCode) !== -1;
    }

    function getTenantContext() {
        if (window.AuthApi && typeof window.AuthApi.getTenantContext === "function") {
            return window.AuthApi.getTenantContext() || {};
        }
        return {};
    }

    function buildHeaders() {
        var headers = { Accept: "application/json" };
        var token = window.AuthApi && typeof window.AuthApi.getToken === "function"
            ? window.AuthApi.getToken()
            : null;

        if (token) {
            headers.Authorization = "Bearer " + token;
        }

        var tenant = getTenantContext();
        if (tenant.companyCode) {
            headers["X-Company-Code"] = String(tenant.companyCode);
        }
        if (tenant.companyId !== undefined && tenant.companyId !== null && tenant.companyId !== "") {
            headers["X-Company-Id"] = String(tenant.companyId);
        }
        if (tenant.companyUuid) {
            headers["X-Company-UUID"] = String(tenant.companyUuid);
        }

        return headers;
    }

    function formatSaveError(status, data) {
        if (data && typeof data === "object") {
            if (data.error && data.error.message) {
                return String(data.error.message);
            }
            if (data.message && data.errors && typeof data.errors === "object") {
                var parts = [];
                Object.keys(data.errors).forEach(function (k) {
                    var arr = data.errors[k];
                    if (Array.isArray(arr) && arr.length) {
                        parts.push(arr[0]);
                    }
                });
                if (parts.length) {
                    return parts.join(" ");
                }
                return String(data.message);
            }
            if (data.message) {
                return String(data.message);
            }
        }
        if (status === 422) {
            return "Validasi gagal. Periksa isian form.";
        }
        return "Save failed.";
    }

    function apiRequest(method, url, body) {
        var headers = Object.assign({ "Content-Type": "application/json" }, buildHeaders());
        if (window.axios) {
            return window.axios({
                method: method,
                url: url,
                data: body || null,
                headers: headers,
                withCredentials: true,
            }).then(function (res) {
                return res.data;
            }).catch(function (err) {
                var st = err && err.response ? err.response.status : 0;
                var d = err && err.response ? err.response.data : null;
                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi && window.AuthApi.handleUnauthorizedFromApi(st, d)) {
                    return null;
                }
                return Promise.reject({ status: st, data: d });
            });
        }
        return fetch(url, {
            method: method.toUpperCase(),
            headers: headers,
            credentials: "same-origin",
            body: body ? JSON.stringify(body) : undefined,
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi && window.AuthApi.handleUnauthorizedFromApi(res.status, data)) {
                        return null;
                    }
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    function shortReason(text) {
        var s = String(text || "").trim();
        if (s.length <= 56) return s;
        return s.slice(0, 53) + "…";
    }

    function statusBadgeClass(st) {
        var s = String(st || "").toLowerCase();
        if (s === "finalized") return "info";
        if (s === "approved") return "success";
        if (s === "cancelled") return "secondary";
        return "warning";
    }

    window.ArcavTerminationUtils = {
        esc: esc,
        notify: notify,
        flash: flash,
        clearFlash: clearFlash,
        redirectToEmployeeDashboard: redirectToEmployeeDashboard,
        hasMePermission: hasMePermission,
        getTenantContext: getTenantContext,
        buildHeaders: buildHeaders,
        formatSaveError: formatSaveError,
        apiRequest: apiRequest,
        shortReason: shortReason,
        statusBadgeClass: statusBadgeClass,
    };
})(window, document);
