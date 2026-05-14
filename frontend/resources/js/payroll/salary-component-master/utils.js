(function (window, document) {
    "use strict";

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
        if (window.axios) {
            return window.axios({ method: method, url: url, headers: headers, data: body, withCredentials: true })
                .then(function (res) {
                    return res.data;
                })
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
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
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

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function notify(message, isError) {
        if (window.ApiClient && typeof window.ApiClient.toast === "function") {
            window.ApiClient.toast(message, isError);
            return;
        }
        var c = document.querySelector("[data-hcm-toast-container]") || document.body.appendChild(Object.assign(document.createElement("div"), { style: "position:fixed;top:16px;right:16px;z-index:3000" }));
        c.setAttribute("data-hcm-toast-container", "1");
        var t = document.createElement("div");
        t.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = message;
        c.appendChild(t);
        window.setTimeout(function () {
            t.remove();
        }, 2600);
    }

    function formatApiError(data, status) {
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            return window.ApiErrorHelper.format(data, status);
        }
        if (data && data.error && data.error.message) {
            return data.error.message;
        }
        return status ? "Error " + status : "Request failed";
    }

    function truncate(s, n) {
        s = String(s || "");
        if (s.length <= n) {
            return s;
        }
        return s.slice(0, n - 1) + "…";
    }

    function formatDefaultPercentDisplay(v) {
        if (v == null || v === "") {
            return "";
        }
        var n = parseFloat(String(v).replace(",", "."));
        if (isNaN(n)) {
            return "";
        }
        var t = Math.round(n * 10000) / 10000;
        return String(parseFloat(t.toFixed(4)));
    }

    function readPercentPayload(form) {
        var pctEl = form.querySelector('[data-hcm-field="defaultPercent"]');
        var basisEl = form.querySelector('[data-hcm-field="percentBasis"]');
        var pctRaw = pctEl ? String(pctEl.value || "").trim().replace(",", ".") : "";
        var basis = basisEl ? String(basisEl.value || "").trim() : "";
        if (!pctRaw) {
            return { defaultPercent: null, percentBasis: null };
        }
        var n = parseFloat(pctRaw);
        if (isNaN(n) || n < 0 || n > 100) {
            return { invalid: true, message: "Persen harus antara 0 dan 100." };
        }
        if (!basis) {
            return { invalid: true, message: "Pilih dasar perhitungan jika mengisi persen." };
        }
        return { defaultPercent: n, percentBasis: basis };
    }

    window.ArcavSalaryComponentMasterUtils = {
        apiRequest: apiRequest,
        esc: esc,
        notify: notify,
        formatApiError: formatApiError,
        truncate: truncate,
        formatDefaultPercentDisplay: formatDefaultPercentDisplay,
        readPercentPayload: readPercentPayload,
    };
})(window, document);
