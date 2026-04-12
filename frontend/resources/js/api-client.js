(function (window) {
    "use strict";

    var TOKEN_KEY = "arcav_access_token";
    var baseURL = "/v1";
    var authRedirectScheduled = false;

    function getToken() {
        return null;
    }

    function isUnauthorizedApiPayload(status, data) {
        if (data && typeof data === "object" && data.error && data.error.code === "AUTH_UNAUTHORIZED") {
            return true;
        }
        if (status === 401) {
            if (data && typeof data === "object" && data.error && data.error.code === "AUTH_INVALID_CREDENTIALS") {
                return false;
            }
            return true;
        }
        return false;
    }

    function redirectToLoginAfterAuthFailure() {
        if (authRedirectScheduled) {
            return;
        }
        authRedirectScheduled = true;
        try {
            window.localStorage.removeItem(TOKEN_KEY);
        } catch (_e) {}
        window.location.replace("/login");
    }

    function handleUnauthorizedFromApi(status, data) {
        if (!isUnauthorizedApiPayload(status, data)) {
            return false;
        }
        redirectToLoginAfterAuthFailure();
        return true;
    }

    function skipAuthRedirectForAuthFormsPath(path) {
        return path.indexOf("/auth/login") !== -1
            || path.indexOf("/auth/register") !== -1
            || path.indexOf("/auth/logout") !== -1;
    }

    function buildHeaders(extraHeaders) {
        var headers = {
            "Content-Type": "application/json",
            Accept: "application/json",
        };

        if (extraHeaders) {
            Object.keys(extraHeaders).forEach(function (key) {
                headers[key] = extraHeaders[key];
            });
        }

        return headers;
    }

    function request(method, path, payload) {
        var url = baseURL + path;

        if (window.axios) {
            return window.axios({
                method: method,
                url: url,
                data: payload,
                headers: buildHeaders(),
                withCredentials: true,
            }).catch(function (error) {
                var status = error.response && error.response.status;
                var data = error.response && error.response.data;
                if (!skipAuthRedirectForAuthFormsPath(path) && handleUnauthorizedFromApi(status, data)) {
                    return new Promise(function () {});
                }
                return Promise.reject(error);
            });
        }

        return fetch(url, {
            method: method.toUpperCase(),
            headers: buildHeaders(),
            credentials: "same-origin",
            body: payload ? JSON.stringify(payload) : undefined,
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!response.ok) {
                    if (!skipAuthRedirectForAuthFormsPath(path) && handleUnauthorizedFromApi(response.status, data)) {
                        return new Promise(function () {});
                    }
                    var error = new Error("Request failed");
                    error.response = { data: data, status: response.status };
                    throw error;
                }
                return { data: data, status: response.status };
            });
        });
    }

    window.AuthApi = {
        tokenKey: TOKEN_KEY,
        login: function (payload) {
            return request("post", "/identity/auth/login", payload);
        },
        register: function (payload) {
            return request("post", "/identity/auth/register", payload);
        },
        me: function () {
            return request("get", "/identity/auth/me");
        },
        logout: function () {
            return request("post", "/identity/auth/logout");
        },
        setToken: function (token) {
            try {
                if (token) {
                    window.localStorage.setItem(TOKEN_KEY, token);
                }
            } catch (_e) {}
        },
        clearToken: function () {
            try {
                window.localStorage.removeItem(TOKEN_KEY);
            } catch (_e) {}
        },
        getToken: getToken,
        isUnauthorizedApiPayload: isUnauthorizedApiPayload,
        redirectToLoginAfterAuthFailure: redirectToLoginAfterAuthFailure,
        handleUnauthorizedFromApi: handleUnauthorizedFromApi,
    };

    window.ArcavUi = window.ArcavUi || {};

    /**
     * Template-aligned confirm dialog (Bootstrap modal #arcav_hcm_confirm_delete).
     * @param {string} message
     * @param {string} [title]
     * @returns {Promise<boolean>}
     */
    window.ArcavUi.confirmDelete = function (message, title) {
        return new Promise(function (resolve) {
            var el = document.getElementById("arcav_hcm_confirm_delete");
            if (!el || !window.bootstrap || !window.bootstrap.Modal) {
                resolve(false);
                return;
            }
            var titleEl = el.querySelector("[data-arcav-confirm-title]");
            var bodyEl = el.querySelector("[data-arcav-confirm-body]");
            if (titleEl) {
                titleEl.textContent = title || "Konfirmasi";
            }
            if (bodyEl) {
                bodyEl.textContent = message || "";
            }

            var inst = window.bootstrap.Modal.getOrCreateInstance(el);
            var yesBtn = el.querySelector("[data-arcav-confirm-yes]");
            var confirmed = false;
            var finished = false;

            function cleanup() {
                if (yesBtn) {
                    yesBtn.removeEventListener("click", onYes);
                }
                el.removeEventListener("hidden.bs.modal", onHidden);
            }

            function done(ok) {
                if (finished) {
                    return;
                }
                finished = true;
                cleanup();
                resolve(ok);
            }

            function onYes() {
                confirmed = true;
                inst.hide();
            }

            function onHidden() {
                done(confirmed);
            }

            finished = false;
            confirmed = false;
            if (yesBtn) {
                yesBtn.addEventListener("click", onYes, { once: true });
            }
            el.addEventListener("hidden.bs.modal", onHidden, { once: true });
            inst.show();
        });
    };
})(window);
