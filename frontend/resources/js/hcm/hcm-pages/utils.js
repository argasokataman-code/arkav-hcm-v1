(function (window, document) {
    "use strict";

    function getAuthHeaders() {
        var headers = {};
        var token = (window.AuthApi && typeof window.AuthApi.getToken === "function" ? window.AuthApi.getToken() : null) || localStorage.getItem("arcav_access_token") || "";
        var tenant = window.AuthApi && typeof window.AuthApi.getTenantContext === "function" ? window.AuthApi.getTenantContext() : null;

        if (token) {
            headers.Authorization = "Bearer " + token;
        }
        if (tenant && tenant.companyCode) {
            headers["X-Company-Code"] = String(tenant.companyCode);
        }
        if (tenant && tenant.companyId !== undefined && tenant.companyId !== null && tenant.companyId !== "") {
            headers["X-Company-Id"] = String(tenant.companyId);
        }
        if (tenant && tenant.companyUuid) {
            headers["X-Company-UUID"] = String(tenant.companyUuid);
        }

        return headers;
    }

    function apiGet(url) {
        function onAuthFailure(status, data) {
            if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
                return window.AuthApi.handleUnauthorizedFromApi(status, data);
            }
            return false;
        }

        if (window.axios) {
            var authHeaders = getAuthHeaders();
            return window.axios({
                method: "get",
                url: url,
                headers: Object.assign({ Accept: "application/json" }, authHeaders),
                withCredentials: true,
            }).then(function (res) { return res.data; }).catch(function (err) {
                var status = err && err.response ? err.response.status : 0;
                var data = err && err.response ? err.response.data : null;
                if (onAuthFailure(status, data)) {
                    return null;
                }
                throw err;
            });
        }

        return fetch(url, {
            headers: Object.assign({ Accept: "application/json" }, getAuthHeaders()),
            credentials: "same-origin",
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    if (onAuthFailure(res.status, data)) {
                        return null;
                    }
                    throw new Error("Request failed: " + url);
                }
                return data;
            });
        });
    }

    function apiGetSafe(url, fallbackValue) {
        return apiGet(url).then(function (payload) {
            return payload == null ? fallbackValue : payload;
        }).catch(function () {
            return fallbackValue;
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

    function pathMatches(path, target) {
        return path === target || path.slice(-target.length) === target;
    }

    function formatRupiah(value) {
        var n = Number(value || 0);
        if (!isFinite(n)) {
            n = 0;
        }
        return "Rp" + n.toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function formatEmployeeCode(value) {
        var n = Number(value);
        if (!Number.isFinite(n) || n <= 0) {
            return "-";
        }
        return "EMP-" + String(Math.trunc(n));
    }

    function fillHcmDepartmentDropdown(fieldKey, emptyOptionText, departments) {
        var rows = Array.isArray(departments) ? departments : [];
        var selects = document.querySelectorAll('[data-hcm-field="' + fieldKey + '"]');
        for (var s = 0; s < selects.length; s++) {
            var sel = selects[s];
            var previous = sel.value;
            var opts = '<option value="">' + esc(emptyOptionText) + "</option>";
            for (var i = 0; i < rows.length; i++) {
                var d = rows[i];
                if (!d || d.id == null) {
                    continue;
                }
                opts += '<option value="' + esc(String(d.id)) + '">' + esc(d.name) + "</option>";
            }
            sel.innerHTML = opts;
            if (previous) {
                var foundPrev = false;
                for (var j = 0; j < rows.length; j++) {
                    if (String(rows[j].id) === String(previous)) {
                        foundPrev = true;
                        break;
                    }
                }
                if (foundPrev) {
                    sel.value = previous;
                }
            }
        }
    }

    function fillDesignationDepartmentSelects(departments) {
        fillHcmDepartmentDropdown("designation-department", "Select department", departments);
    }

    function fillPolicyDepartmentSelects(departments) {
        fillHcmDepartmentDropdown("policy-department", "All departments", departments);
    }

    function departmentIdPayloadFromSelect(selectEl) {
        var raw = String(selectEl && selectEl.value != null ? selectEl.value : "").trim();
        if (raw === "") {
            return { departmentId: null };
        }
        var n = parseInt(raw, 10);
        return { departmentId: isNaN(n) ? null : n };
    }

    function policyEffectiveDatePayload(dateInputEl) {
        var v = String(dateInputEl && dateInputEl.value != null ? dateInputEl.value : "").trim();
        if (v === "") {
            return {};
        }
        return { effectiveDate: v };
    }

    function normalizeYesNo(value) {
        var text = String(value || "").toLowerCase();
        return text === "active" || text === "1" || text === "true";
    }

    function formatApiError(data, status) {
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            return window.ApiErrorHelper.format(data, status);
        }
        if (!data || typeof data !== "object") {
            return status ? "Request failed (" + status + ")" : "Request failed";
        }
        if (data.error && typeof data.error.message === "string" && data.error.message) {
            return data.error.message;
        }
        if (typeof data.message === "string" && data.message) {
            return data.message;
        }
        var errs = data.errors;
        if (errs && typeof errs === "object") {
            var first = Object.keys(errs)[0];
            if (first && Array.isArray(errs[first]) && errs[first][0]) {
                return errs[first][0];
            }
        }
        return status ? "Request failed (" + status + ")" : "Request failed";
    }

    function renderHcmShowing(moduleKey, meta, rowCount) {
        var el = document.querySelector('[data-hcm-showing="' + moduleKey + '"]');
        if (!el) {
            return;
        }
        var total = Number(meta && meta.total ? meta.total : 0);
        var page = Number(meta && meta.page ? meta.page : 1);
        var perPage = Number(meta && meta.perPage ? meta.perPage : 20);
        if (!total || !rowCount) {
            el.textContent = "Showing 0 - 0 of 0 entries";
            return;
        }
        var start = ((page - 1) * perPage) + 1;
        var end = Math.min(start + rowCount - 1, total);
        el.textContent = "Showing " + start + " - " + end + " of " + total + " entries";
    }

    function renderHcmPagination(moduleKey, meta) {
        var list = document.querySelector('[data-hcm-pagination="' + moduleKey + '"]');
        if (!list) {
            return;
        }
        var total = Number(meta && meta.total ? meta.total : 0);
        var page = Number(meta && meta.page ? meta.page : 1);
        var perPage = Number(meta && meta.perPage ? meta.perPage : 20);
        var totalPages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));
        if (totalPages <= 1) {
            list.innerHTML = "";
            return;
        }

        var startPage = Math.max(1, page - 2);
        var endPage = Math.min(totalPages, page + 2);
        var html = '';
        html += '<li class="page-item ' + (page <= 1 ? 'disabled' : '') + '"><a href="#" class="page-link" data-hcm-page="' + (page - 1) + '" data-hcm-module="' + moduleKey + '">Prev</a></li>';
        for (var p = startPage; p <= endPage; p += 1) {
            html += '<li class="page-item ' + (p === page ? 'active' : '') + '"><a href="#" class="page-link" data-hcm-page="' + p + '" data-hcm-module="' + moduleKey + '">' + p + '</a></li>';
        }
        html += '<li class="page-item ' + (page >= totalPages ? 'disabled' : '') + '"><a href="#" class="page-link" data-hcm-page="' + (page + 1) + '" data-hcm-module="' + moduleKey + '">Next</a></li>';
        list.innerHTML = html;
    }

    function toCsv(rows, headers) {
        var out = [headers.join(",")];
        rows.forEach(function (row) {
            var line = headers.map(function (key) {
                var value = row[key] == null ? "" : String(row[key]);
                return '"' + value.replace(/"/g, '""') + '"';
            }).join(",");
            out.push(line);
        });
        return out.join("\n");
    }

    function downloadBlob(filename, mimeType, content) {
        var blob = new Blob([content], { type: mimeType });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
    }

    window.ArcavHcmPagesUtils = {
        getAuthHeaders: getAuthHeaders,
        apiGet: apiGet,
        apiGetSafe: apiGetSafe,
        esc: esc,
        pathMatches: pathMatches,
        formatRupiah: formatRupiah,
        formatEmployeeCode: formatEmployeeCode,
        fillHcmDepartmentDropdown: fillHcmDepartmentDropdown,
        fillDesignationDepartmentSelects: fillDesignationDepartmentSelects,
        fillPolicyDepartmentSelects: fillPolicyDepartmentSelects,
        departmentIdPayloadFromSelect: departmentIdPayloadFromSelect,
        policyEffectiveDatePayload: policyEffectiveDatePayload,
        normalizeYesNo: normalizeYesNo,
        formatApiError: formatApiError,
        renderHcmShowing: renderHcmShowing,
        renderHcmPagination: renderHcmPagination,
        toCsv: toCsv,
        downloadBlob: downloadBlob,
    };
})(window, document);
