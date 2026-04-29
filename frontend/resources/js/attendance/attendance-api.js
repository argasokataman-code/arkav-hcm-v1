export function onAuthFailure(status, data) {
  if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
    return window.AuthApi.handleUnauthorizedFromApi(status, data);
  }
  return false;
}

export function apiGet(url) {
  if (window.axios) {
    return window
      .axios({
        method: "get",
        url: url,
        headers: { Accept: "application/json" },
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
    headers: { Accept: "application/json" },
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

  if (window.axios) {
    return window
      .axios({
        method: "post",
        url: url,
        headers: { Accept: "application/json" },
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
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
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

  if (window.axios) {
    return window
      .axios({
        method: "put",
        url: url,
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
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
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
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
  if (window.axios) {
    return window
      .axios({
        method: "delete",
        url: url,
        headers: { Accept: "application/json" },
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
    headers: { Accept: "application/json" },
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
