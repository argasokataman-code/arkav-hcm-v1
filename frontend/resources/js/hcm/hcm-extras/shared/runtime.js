function onAuthFailure(status, data) {
    if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
        return window.AuthApi.handleUnauthorizedFromApi(status, data);
    }
    return false;
}

function getTenantContext() {
    if (window.AuthApi && typeof window.AuthApi.getTenantContext === "function") {
        return window.AuthApi.getTenantContext() || {};
    }

    return {};
}

export function withTenantHeaders(headers) {
    var h = headers || {};
    var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
    if (token) { h['Authorization'] = 'Bearer ' + token; }
    var tenant = getTenantContext();
    if (tenant.companyCode) {
        h["X-Company-Code"] = String(tenant.companyCode);
    }
    if (tenant.companyId !== undefined && tenant.companyId !== null && tenant.companyId !== "") {
        h["X-Company-Id"] = String(tenant.companyId);
    }
    if (tenant.companyUuid) {
        h["X-Company-UUID"] = String(tenant.companyUuid);
    }

    return h;
}

export function apiRequest(method, url, body) {
    var headers = withTenantHeaders({ Accept: "application/json" });
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

export function esc(v) {
    return String(v || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

export function notify(message, isError) {
    var c = document.querySelector("[data-hcm-toast-container]") || document.body.appendChild(Object.assign(document.createElement("div"), { style: "position:fixed;top:16px;right:16px;z-index:1080" }));
    c.setAttribute("data-hcm-toast-container", "1");
    var t = document.createElement("div");
    t.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
    t.textContent = message;
    c.appendChild(t);
    window.setTimeout(function () {
        t.remove();
    }, 2600);
}

export function downloadCsv(filename, headers, rows) {
    var csv = [headers.join(",")].concat(
        (rows || []).map(function (r) {
            return r.map(function (v) {
                var s = String(v == null ? "" : v);
                if (/[",\n]/.test(s)) {
                    return '"' + s.replace(/"/g, '""') + '"';
                }
                return s;
            }).join(",");
        })
    ).join("\n");
    var blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

export function downloadFileFromUrl(url, fallbackFilename) {
    return fetch(url, {
        method: "GET",
        credentials: "same-origin",
        headers: withTenantHeaders({ Accept: "text/csv,application/json" }),
    }).then(function (res) {
        if (!res.ok) {
            return res
                .json()
                .catch(function () {
                    return {};
                })
                .then(function (data) {
                    if (onAuthFailure(res.status, data)) {
                        return null;
                    }
                    return Promise.reject({ status: res.status, data: data });
                });
        }
        return Promise.all([res.blob(), Promise.resolve(res.headers.get("content-disposition") || "")]).then(function (parts) {
            var blob = parts[0];
            var disposition = parts[1];
            var filename = fallbackFilename || "export.csv";
            var match = /filename="?([^";]+)"?/i.exec(disposition);
            if (match && match[1]) {
                filename = match[1];
            }
            var objectUrl = window.URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = objectUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(objectUrl);
            return true;
        });
    });
}

export function formatApiError(data, status) {
    if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
        return window.ApiErrorHelper.format(data, status);
    }
    if (data && data.error && data.error.message) {
        return data.error.message;
    }
    if (data && data.message) {
        return data.message;
    }
    return status ? "Error " + status : "Request failed";
}

export function createEmployeeDirectoryStore(deps) {
    var apiRequestRef = deps.apiRequest;
    var escRef = deps.esc;
    var notifyRef = deps.notify;
    var formatApiErrorRef = deps.formatApiError;
    var employeeCompensationById = {};

    function loadEmployeeOptions(selectEl) {
        if (!selectEl) {
            return Promise.resolve();
        }

        function fetchEmployeePage(page, accumulated) {
            var url = "/v1/hcm/employees?perPage=100&page=" + encodeURIComponent(page);
            return apiRequestRef("get", url, null).then(function (payload) {
                if (!payload || payload.success !== true) {
                    return accumulated;
                }
                var chunk = Array.isArray(payload.data) ? payload.data : [];
                var next = accumulated.concat(chunk);
                var meta = payload.meta || {};
                var total = typeof meta.total === "number" ? meta.total : next.length;
                if (chunk.length < 1 || next.length >= total || page >= 50) {
                    return next;
                }
                return fetchEmployeePage(page + 1, next);
            });
        }

        return fetchEmployeePage(1, [])
            .then(function (rows) {
                var opts = '<option value="">— Pilih karyawan —</option>';
                employeeCompensationById = {};
                for (var i = 0; i < rows.length; i++) {
                    var r = rows[i];
                    employeeCompensationById[String(r.id)] = {
                        baseSalary: Number(r.baseSalary || 0),
                        fixedAllowance: Number(r.fixedAllowance || 0),
                    };
                    opts += '<option value="' + escRef(r.id) + '">' + escRef(r.fullName + " (" + r.email + ")") + "</option>";
                }
                selectEl.innerHTML = opts;
            })
            .catch(function (err) {
                notifyRef(formatApiErrorRef(err && err.data, err && err.status), true);
            });
    }

    function getEmployeeCompensationById() {
        return employeeCompensationById;
    }

    return {
        getEmployeeCompensationById: getEmployeeCompensationById,
        loadEmployeeOptions: loadEmployeeOptions,
    };
}

export function extractPermissionSet(mePayload) {
    var set = {};
    var data = mePayload && mePayload.data ? mePayload.data : null;
    var permissions = data && data.permissions ? data.permissions : null;
    var codes = data && Array.isArray(data.permissionCodes) ? data.permissionCodes : [];

    if (permissions && typeof permissions === "object") {
        Object.keys(permissions).forEach(function (key) {
            if (permissions[key] === true) {
                set[String(key)] = true;
            }
        });
    }

    codes.forEach(function (code) {
        if (typeof code === "string" && code.trim() !== "") {
            set[code.trim()] = true;
        }
    });

    return set;
}

export function canAccessAdminPage(mePayload, requiredPermission) {
    var data = mePayload && mePayload.data ? mePayload.data : null;
    if (!data) {
        return false;
    }

    if (data.hcmGlobalAdmin === true || data.hcmAdmin === true) {
        return true;
    }

    if (!requiredPermission) {
        return true;
    }

    var permissionSet = extractPermissionSet(mePayload);
    return permissionSet[requiredPermission] === true;
}