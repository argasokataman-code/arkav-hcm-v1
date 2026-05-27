// API helpers moved out of the big employees-data file
function _bearerHeaders(extra) {
    var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
    var h = Object.assign({ Accept: 'application/json' }, extra || {});
    if (token) { h['Authorization'] = 'Bearer ' + token; }
    return h;
}
export function employeesListUrl(perPage, page) {
    var n = perPage != null ? perPage : 20;
    var p = page != null ? page : 1;
    return "/v1/hcm/employees?perPage=" + encodeURIComponent(n) + "&page=" + encodeURIComponent(p);
}

export function requestAuthMe() {
    var url = "/v1/identity/auth/me";
    if (window.axios) {
        return window.axios({
            method: "get",
            url: url,
            headers: _bearerHeaders(),
            withCredentials: true,
        }).then(function (res) {
            return res.data;
        }).catch(function (error) {
            return Promise.reject({
                status: error && error.response ? error.response.status : 0,
                data: error && error.response ? error.response.data : null,
            });
        });
    }
    return fetch(url, {
        headers: _bearerHeaders(),
        credentials: "same-origin",
    }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
            if (!res.ok) {
                return Promise.reject({ status: res.status, data: data });
            }
            return data;
        });
    });
}

export function requestEmployees(perPage, page) {
    var API_URL = employeesListUrl(perPage, page);

    if (window.axios) {
        return window.axios({
            method: "get",
            url: API_URL,
            headers: _bearerHeaders(),
            withCredentials: true,
        }).then(function (res) {
            return res.data;
        }).catch(function (error) {
            return Promise.reject({
                status: error && error.response ? error.response.status : 0,
                data: error && error.response ? error.response.data : null,
            });
        });
    }

    return fetch(API_URL, {
        headers: _bearerHeaders(),
        credentials: "same-origin",
    }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
            if (!res.ok) {
                return Promise.reject({ status: res.status, data: data });
            }
            return data;
        });
    });
}

export function requestEmployeesByState(state) {
    var params = new URLSearchParams();
    params.set("perPage", String(state.perPage || 20));
    params.set("page", String(state.page || 1));
    if (state.search) {
        params.set("search", state.search);
    }
    if (state.status) {
        params.set("status", state.status);
    }
    if (state.departmentId) {
        params.set("departmentId", state.departmentId);
    }
    if (state.designationId) {
        params.set("designationId", state.designationId);
    }
    if (state.teamId) {
        params.set("teamId", state.teamId);
    }
    if (state.scope) {
        params.set("scope", state.scope);
    }
    return requestJson("get", "/v1/hcm/employees?" + params.toString(), null);
}

export function requestAllEmployeesAggregated(perPage) {
    var size = perPage != null ? perPage : 100;
    function fetchPage(page, accumulated, metaForSummary) {
        return requestEmployees(size, page).then(function (payload) {
            if (!payload || payload.success !== true) {
                return Promise.reject({ status: 0, data: payload });
            }
            var chunk = Array.isArray(payload.data) ? payload.data : [];
            var next = accumulated.concat(chunk);
            var pageMeta = payload.meta || {};
            var summaryMeta = metaForSummary || (pageMeta.summary ? pageMeta : null);
            var total = typeof pageMeta.total === "number" ? pageMeta.total : next.length;
            if (chunk.length < 1 || next.length >= total || page >= 50) {
                return { success: true, data: next, meta: summaryMeta || pageMeta };
            }
            return fetchPage(page + 1, next, summaryMeta || pageMeta);
        });
    }
    return fetchPage(1, [], null);
}

export function requestJson(method, url, payload) {
    var m = String(method || "get").toLowerCase();
    var tenant = window.AuthApi && typeof window.AuthApi.getTenantContext === "function" ? window.AuthApi.getTenantContext() : null;
    var extraHeaders = {};
    var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
    if (token) { extraHeaders['Authorization'] = 'Bearer ' + token; }
    if (tenant && tenant.companyId !== undefined && tenant.companyId !== null && tenant.companyId !== "") {
        extraHeaders["X-Company-Id"] = String(tenant.companyId);
    }
    if (tenant && tenant.companyUuid) {
        extraHeaders["X-Company-UUID"] = String(tenant.companyUuid);
    }
    if (window.axios) {
        var cfg = {
            method: method,
            url: url,
            headers: Object.assign({ Accept: "application/json" }, extraHeaders),
            withCredentials: true,
        };
        if (m !== "get" && m !== "head") {
            cfg.data = payload || {};
        }
        return window.axios(cfg).then(function (res) {
            return res.data;
        }).catch(function (error) {
            return Promise.reject({
                status: error && error.response ? error.response.status : 0,
                data: error && error.response ? error.response.data : null,
            });
        });
    }

    var fetchOpts = {
        method: method.toUpperCase(),
        headers: Object.assign({ Accept: "application/json" }, extraHeaders),
        credentials: "same-origin",
    };
    if (m !== "get" && m !== "head") {
        fetchOpts.headers["Content-Type"] = "application/json";
        fetchOpts.body = JSON.stringify(payload || {});
    }
    return fetch(url, fetchOpts).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
            if (!res.ok) {
                return Promise.reject({ status: res.status, data: data });
            }
            return data;
        });
    });
}

export function requestFormData(method, url, formData) {
    var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
    var authHeader = token ? { 'Authorization': 'Bearer ' + token } : {};
    if (window.axios) {
        return window.axios({
            method: method,
            url: url,
            headers: Object.assign({ Accept: "application/json" }, authHeader),
            data: formData,
            withCredentials: true,
        }).then(function (res) {
            return res.data;
        }).catch(function (error) {
            return Promise.reject({
                status: error && error.response ? error.response.status : 0,
                data: error && error.response ? error.response.data : null,
            });
        });
    }

    return fetch(url, {
        method: method.toUpperCase(),
        headers: Object.assign({ Accept: "application/json" }, authHeader),
        credentials: "same-origin",
        body: formData,
    }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
            if (!res.ok) {
                return Promise.reject({ status: res.status, data: data });
            }
            return data;
        });
    });
}

export function requestEmployeeDetail(employeeId) {
    if (!employeeId) {
        return Promise.resolve(null);
    }
    var apiUrl = "/v1/hcm/employees/" + encodeURIComponent(employeeId);

    if (window.axios) {
        return window.axios({
            method: "get",
            url: apiUrl,
            headers: { Accept: "application/json" },
            withCredentials: true
        }).then(function (res) {
            return res.data;
        }).catch(function (error) {
            return Promise.reject({
                status: error && error.response ? error.response.status : 0,
                data: error && error.response ? error.response.data : null,
            });
        });
    }

    return fetch(apiUrl, {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
    }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
            if (!res.ok) {
                return Promise.reject({ status: res.status, data: data });
            }
            return data;
        });
    });
}
