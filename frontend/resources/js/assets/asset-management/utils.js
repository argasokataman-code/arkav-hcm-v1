(function (window, document) {
    "use strict";

    function toDayValue(input) {
        if (!input) {
            return "";
        }

        var date = new Date(String(input).slice(0, 10) + "T00:00:00");
        if (isNaN(date.getTime())) {
            return "";
        }

        return date.toISOString().slice(0, 10);
    }

    function validateAssetPayload(payload) {
        var errors = [];
        var purchaseDate = toDayValue(payload && payload.purchase_date);
        var warrantyStartDate = toDayValue(payload && payload.warranty_start_date);
        var warrantyEndDate = toDayValue(payload && payload.warranty_end_date);

        if (!payload || !payload.asset_category_id) {
            errors.push("Pilih category asset yang valid.");
        }

        if (!payload || !String(payload.name || "").trim()) {
            errors.push("Nama asset wajib diisi.");
        }

        if (!purchaseDate) {
            errors.push("Purchase date wajib diisi.");
        }

        if (warrantyStartDate && purchaseDate && warrantyStartDate < purchaseDate) {
            errors.push("Warranty start date tidak boleh lebih awal dari purchase date.");
        }

        if (warrantyEndDate && warrantyStartDate && warrantyEndDate < warrantyStartDate) {
            errors.push("Warranty end date tidak boleh lebih awal dari warranty start date.");
        } else if (warrantyEndDate && purchaseDate && warrantyEndDate < purchaseDate) {
            errors.push("Warranty end date tidak boleh lebih awal dari purchase date.");
        }

        return errors;
    }

    function validateReturnPayload(payload, currentAssignment) {
        var errors = [];
        var returnedDate = toDayValue(payload && payload.returned_date);
        var assignedDate = toDayValue(currentAssignment && currentAssignment.assignedDate);

        if (returnedDate && assignedDate && returnedDate < assignedDate) {
            errors.push("Returned date tidak boleh lebih awal dari assigned date.");
        }

        return errors;
    }

    function formatAssetApiError(data, status) {
        var code = data && data.error ? data.error.code : "";

        if (code === "ASSET_NOT_AVAILABLE") {
            return "Asset tidak tersedia untuk assignment.";
        }
        if (code === "ASSET_ALREADY_ASSIGNED") {
            return "Asset ini sudah memiliki assignment aktif.";
        }
        if (code === "ASSET_NOT_ASSIGNED") {
            return "Asset ini belum memiliki assignment aktif.";
        }
        if (code === "ASSET_RETURN_DATE_INVALID") {
            return "Returned date tidak boleh lebih awal dari assigned date.";
        }

        if (data && data.error && data.error.message) {
            return data.error.message;
        }

        return status ? "Error " + status : "Request failed";
    }

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
            .replace(/\"/g, "&quot;")
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
        return formatAssetApiError(data, status);
    }

    function formatDate(iso) {
        if (!iso) {
            return "-";
        }
        var d = new Date(iso + "T12:00:00");
        if (isNaN(d.getTime())) {
            return esc(iso);
        }
        return d.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
    }

    function formatCurrency(value) {
        var n = isFinite(value) ? Number(value) : 0;
        return "Rp\u00a0" + n.toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function statusBadge(status) {
        var cls = "bg-secondary";
        var label = status || "unknown";
        if (status === "available") {
            cls = "bg-success";
            label = "Available";
        } else if (status === "assigned") {
            cls = "bg-primary";
            label = "Assigned";
        } else if (status === "maintenance") {
            cls = "bg-warning text-dark";
            label = "Maintenance";
        } else if (status === "retired") {
            cls = "bg-danger";
            label = "Retired";
        }
        return '<span class="badge badge-xs ' + cls + '">' + esc(label) + "</span>";
    }

    window.ArcavAssetManagementUtils = {
        toDayValue: toDayValue,
        validateAssetPayload: validateAssetPayload,
        validateReturnPayload: validateReturnPayload,
        formatAssetApiError: formatAssetApiError,
        apiRequest: apiRequest,
        esc: esc,
        notify: notify,
        formatApiError: formatApiError,
        formatDate: formatDate,
        formatCurrency: formatCurrency,
        statusBadge: statusBadge,
    };
})(window, document);
