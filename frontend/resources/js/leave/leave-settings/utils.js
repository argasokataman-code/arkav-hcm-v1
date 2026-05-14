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
        var c =
            document.querySelector("[data-hcm-toast-container]") ||
            document.body.appendChild(
                Object.assign(document.createElement("div"), { style: "position:fixed;top:16px;right:16px;z-index:3000" })
            );
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
        if (data && data.error && Array.isArray(data.error.details) && data.error.details.length) {
            return data.error.details.map(function (d) { return d.message; }).join(" ");
        }
        if (data && data.errors && typeof data.errors === "object") {
            var parts = [];
            var keys = Object.keys(data.errors);
            for (var i = 0; i < keys.length; i++) {
                var msgs = data.errors[keys[i]];
                if (Array.isArray(msgs) && msgs.length) {
                    parts.push(msgs[0]);
                }
            }
            if (parts.length) {
                return parts.join(" ");
            }
        }
        if (data && data.error && data.error.message) {
            return data.error.message;
        }
        if (data && data.message) {
            return data.message;
        }
        return status ? "Error " + status : "Request failed";
    }

    function loadAssigneeSelect(multiEl) {
        if (!multiEl) {
            return Promise.resolve();
        }

        function fetchEmpPage(page, acc) {
            var url = "/v1/hcm/employees?perPage=100&page=" + encodeURIComponent(page);
            return apiRequest("get", url, null).then(function (payload) {
                if (!payload || payload.success !== true) {
                    return acc;
                }
                var chunk = Array.isArray(payload.data) ? payload.data : [];
                var next = acc.concat(chunk);
                var meta = payload.meta || {};
                var total = typeof meta.total === "number" ? meta.total : next.length;
                if (chunk.length < 1 || next.length >= total || page >= 50) {
                    return next;
                }
                return fetchEmpPage(page + 1, next);
            });
        }

        return fetchEmpPage(1, [])
            .then(function (rows) {
                var opts = "";
                for (var i = 0; i < rows.length; i++) {
                    var r = rows[i];
                    opts += '<option value="' + esc(String(r.id)) + '">' + esc(r.fullName + " (" + r.email + ")") + "</option>";
                }
                multiEl.innerHTML = opts;
            })
            .catch(function (err) {
                notify(formatApiError(err && err.data, err && err.status), true);
            });
    }

    window.ArcavLeaveSettingsUtils = Object.freeze({
        apiRequest: apiRequest,
        esc: esc,
        formatApiError: formatApiError,
        loadAssigneeSelect: loadAssigneeSelect,
        notify: notify,
        onAuthFailure: onAuthFailure,
    });
})(window, document);