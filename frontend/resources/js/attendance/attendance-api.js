export function onAuthFailure(status, data) {
  if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
    return window.AuthApi.handleUnauthorizedFromApi(status, data);
  }
  return false;
}

function getTenantHeaders() {
  var tenant = window.AuthApi && typeof window.AuthApi.getTenantContext === "function" ? window.AuthApi.getTenantContext() : null;
  var headers = {};
  var token = (window.AuthApi && typeof window.AuthApi.getToken === "function" && window.AuthApi.getToken()) || localStorage.getItem("arcav_access_token");
  if (token) { headers["Authorization"] = "Bearer " + token; }
  if (tenant && tenant.companyId !== undefined && tenant.companyId !== null && tenant.companyId !== "") {
    headers["X-Company-Id"] = String(tenant.companyId);
  }
  if (tenant && tenant.companyUuid) {
    headers["X-Company-UUID"] = String(tenant.companyUuid);
  }
  if (tenant && tenant.companyCode) {
    headers["X-Company-Code"] = String(tenant.companyCode);
  }

  return headers;
}

export function apiGet(url) {
  var tenantHeaders = getTenantHeaders();
  if (window.axios) {
    return window
      .axios({
        method: "get",
        url: url,
        headers: Object.assign({ Accept: "application/json" }, tenantHeaders),
        withCredentials: true,
      })
      .then(function (res) {
        return res.data;
      })
      .catch(function (err) {
        var status = err && err.response ? err.response.status : 0;
        var data = err && err.response ? err.response.data : null;
        if (onAuthFailure(status, data)) {
          return null;
        }
        throw err;
      });
  }

  return fetch(url, {
    headers: Object.assign({ Accept: "application/json" }, tenantHeaders),
    credentials: "same-origin",
  }).then(function (res) {
    return res
      .json()
      .catch(function () {
        return {};
      })
      .then(function (data) {
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

export function apiPost(url, body) {
  var payload = body && typeof body === "object" ? body : {};
  var tenantHeaders = getTenantHeaders();

  if (window.axios) {
    return window
      .axios({
        method: "post",
        url: url,
        headers: Object.assign({ Accept: "application/json" }, tenantHeaders),
        data: payload,
        withCredentials: true,
      })
      .then(function (res) {
        return res.data;
      })
      .catch(function (err) {
        var status = err && err.response ? err.response.status : 0;
        var data = err && err.response ? err.response.data : null;
        if (onAuthFailure(status, data)) {
          return null;
        }
        throw err;
      });
  }

  return fetch(url, {
    method: "POST",
    headers: Object.assign({
      Accept: "application/json",
      "Content-Type": "application/json",
    }, tenantHeaders),
    credentials: "same-origin",
    body: JSON.stringify(payload),
  }).then(function (res) {
    return res
      .json()
      .catch(function () {
        return {};
      })
      .then(function (data) {
        if (!res.ok) {
          if (onAuthFailure(res.status, data)) {
            return null;
          }
          var err = new Error("Request failed");
          err.response = { data: data, status: res.status };
          throw err;
        }
        return data;
      });
  });
}

export function apiPut(url, body) {
  var payload = body && typeof body === "object" ? body : {};
  var tenantHeaders = getTenantHeaders();

  if (window.axios) {
    return window
      .axios({
        method: "put",
        url: url,
        headers: Object.assign({
          Accept: "application/json",
          "Content-Type": "application/json",
        }, tenantHeaders),
        data: payload,
        withCredentials: true,
      })
      .then(function (res) {
        return res.data;
      })
      .catch(function (err) {
        var status = err && err.response ? err.response.status : 0;
        var data = err && err.response ? err.response.data : null;
        if (onAuthFailure(status, data)) {
          return null;
        }
        throw err;
      });
  }

  return fetch(url, {
    method: "PUT",
    headers: Object.assign({
      Accept: "application/json",
      "Content-Type": "application/json",
    }, tenantHeaders),
    credentials: "same-origin",
    body: JSON.stringify(payload),
  }).then(function (res) {
    return res
      .json()
      .catch(function () {
        return {};
      })
      .then(function (data) {
        if (!res.ok) {
          if (onAuthFailure(res.status, data)) {
            return null;
          }
          var err = new Error("Request failed");
          err.response = { data: data, status: res.status };
          throw err;
        }
        return data;
      });
  });
}

export function apiDelete(url) {
  var tenantHeaders = getTenantHeaders();
  if (window.axios) {
    return window
      .axios({
        method: "delete",
        url: url,
        headers: Object.assign({ Accept: "application/json" }, tenantHeaders),
        withCredentials: true,
      })
      .then(function (res) {
        return res.data;
      })
      .catch(function (err) {
        var status = err && err.response ? err.response.status : 0;
        var data = err && err.response ? err.response.data : null;
        if (onAuthFailure(status, data)) {
          return null;
        }
        throw err;
      });
  }
  return fetch(url, {
    method: "DELETE",
    headers: Object.assign({ Accept: "application/json" }, tenantHeaders),
    credentials: "same-origin",
  }).then(function (res) {
    return res
      .json()
      .catch(function () {
        return {};
      })
      .then(function (data) {
        if (!res.ok) {
          if (onAuthFailure(res.status, data)) {
            return null;
          }
          var err = new Error("Request failed");
          err.response = { data: data, status: res.status };
          throw err;
        }
        return data;
      });
  });
}

/**
 * Authenticated binary (blob) GET — returns an object URL string that the caller must revoke.
 * Used for private file downloads that require api.token + tenant context headers.
 */
export function apiBlobGet(url) {
  var tenantHeaders = getTenantHeaders();
  if (window.axios) {
    return window
      .axios({
        method: "get",
        url: url,
        responseType: "blob",
        headers: Object.assign({ Accept: "*/*" }, tenantHeaders),
        withCredentials: true,
      })
      .then(function (res) {
        return URL.createObjectURL(res.data);
      })
      .catch(function (err) {
        var status = err && err.response ? err.response.status : 0;
        var data = err && err.response ? err.response.data : null;
        if (onAuthFailure(status, data)) {
          return null;
        }
        throw err;
      });
  }
  return fetch(url, {
    headers: Object.assign({ Accept: "*/*" }, tenantHeaders),
    credentials: "same-origin",
  }).then(function (res) {
    if (!res.ok) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (onAuthFailure(res.status, data)) {
          return null;
        }
        var err = new Error("Request failed: " + url);
        err.response = { data: data, status: res.status };
        throw err;
      });
    }
    return res.blob().then(function (blob) {
      return URL.createObjectURL(blob);
    });
  });
}
