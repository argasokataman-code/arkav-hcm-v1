(function (window) {
    "use strict";

    var TOKEN_KEY = "arcav_access_token";
    var TENANT_CTX_KEY = "arcav_active_tenant";
    var baseURL = "/v1";
    var authRedirectScheduled = false;
    var forbiddenModalScheduled = false;

    function getToken() {
        try {
            var token = window.localStorage.getItem(TOKEN_KEY);
            return token && typeof token === "string" ? token : null;
        } catch (_e) {
            return null;
        }
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

    function getAppShellDataset() {
        try {
            var wrapper = document.querySelector(".main-wrapper");
            return wrapper && wrapper.dataset ? wrapper.dataset : {};
        } catch (_e) {
            return {};
        }
    }

    function shouldShowUpgradeModal(status, data) {
        if (status !== 403) {
            return false;
        }
        if (!data || typeof data !== "object" || !data.error) {
            return true;
        }
        var code = data.error.code;
        // We intentionally treat generic forbidden as "locked/upgrade" UX.
        return code === "FORBIDDEN"
            || code === "AUTH_FORBIDDEN"
            || code === "TENANT_REQUIRED"
            || code === "SUBSCRIPTION_REQUIRED"
            || code === "SUBSCRIPTION_INACTIVE"
            || code === "PACKAGE_FEATURE_REQUIRED"
            || code === "EMPLOYEE_LIMIT_EXCEEDED";
    }

    function showUpgradeRequiredModal(payload) {
        var el = document.getElementById("arcav_upgrade_required");
        if (!el || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }
        var titleEl = el.querySelector("[data-arcav-upgrade-title]");
        var bodyEl = el.querySelector("[data-arcav-upgrade-body]");
        if (titleEl) {
            titleEl.textContent = (payload && payload.title) || "Akses dibatasi";
        }
        if (bodyEl) {
            bodyEl.textContent = (payload && payload.message) || "Fitur ini terkunci untuk paket saat ini.";
        }
        window.bootstrap.Modal.getOrCreateInstance(el).show();
    }

    function handleForbiddenFromApi(status, data) {
        if (forbiddenModalScheduled) {
            return false;
        }
        if (!shouldShowUpgradeModal(status, data)) {
            return false;
        }

        forbiddenModalScheduled = true;

        var ds = getAppShellDataset();
        var subStatus = ds.subscriptionStatus ? String(ds.subscriptionStatus) : "";
        var roleScope = ds.roleScope ? String(ds.roleScope) : "";

        var isTrial = subStatus === "trial";
        var title = "Akses dibatasi";
        var message = "Fitur ini terkunci untuk paket saat ini. Yuk subscribe sekarang untuk akses lebih lengkap.";

        // If it looks like a role-only restriction, keep the UX friendly but not misleading.
        if (!isTrial && roleScope !== "hcm-admin") {
            message = "Fitur ini hanya bisa diakses oleh HCM Admin. Jika kamu butuh akses, hubungi admin perusahaan.";
        }

        // If API provides a safe message, surface it.
        if (data && data.error && typeof data.error.message === "string" && data.error.message.trim()) {
            message = data.error.message.trim();
            if (isTrial) {
                // Keep the subscribe CTA framing for trial users.
                message += " Yuk subscribe sekarang untuk akses lebih lengkap.";
            }
        }

        showUpgradeRequiredModal({ title: title, message: message });
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

        var token = getToken();
        if (token) {
            headers["Authorization"] = "Bearer " + token;
        }

        var tenantContext = getTenantContext();
        if (tenantContext.companyCode) {
            headers["X-Company-Code"] = tenantContext.companyCode;
        }
        if (tenantContext.companyId) {
            headers["X-Company-Id"] = String(tenantContext.companyId);
        }
        if (tenantContext.companyUuid) {
            headers["X-Company-UUID"] = String(tenantContext.companyUuid);
        }

        if (extraHeaders) {
            Object.keys(extraHeaders).forEach(function (key) {
                headers[key] = extraHeaders[key];
            });
        }

        return headers;
    }

    function getTenantContext() {
        try {
            var raw = window.localStorage.getItem(TENANT_CTX_KEY);
            if (!raw) {
                return {};
            }
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === "object" ? parsed : {};
        } catch (_e) {
            return {};
        }
    }

    function setTenantContext(payload) {
        var data = payload && typeof payload === "object" ? payload : {};
        var normalized = {};

        if (typeof data.companyCode === "string") {
            var code = data.companyCode.trim();
            if (code) {
                normalized.companyCode = code;
            }
        }
        if (data.companyId !== undefined && data.companyId !== null && data.companyId !== "") {
            normalized.companyId = data.companyId;
        }
        if (typeof data.companyUuid === "string") {
            var uuid = data.companyUuid.trim();
            if (uuid) {
                normalized.companyUuid = uuid;
            }
        }

        try {
            if (Object.keys(normalized).length === 0) {
                window.localStorage.removeItem(TENANT_CTX_KEY);
                return;
            }
            window.localStorage.setItem(TENANT_CTX_KEY, JSON.stringify(normalized));
        } catch (_e) {}
    }

    function clearTenantContext() {
        try {
            window.localStorage.removeItem(TENANT_CTX_KEY);
        } catch (_e) {}
    }

    function request(method, path, payload) {
        var url = baseURL + path;
        var m = String(method || "get").toLowerCase();

        if (window.axios) {
            var cfg = {
                method: m,
                url: url,
                headers: buildHeaders(),
                withCredentials: true,
            };
            if ((m === "get" || m === "head") && payload && typeof payload === "object") {
                cfg.params = payload;
            } else if (payload !== undefined && payload !== null) {
                cfg.data = payload;
            }
            return window.axios(cfg).catch(function (error) {
                var status = error.response && error.response.status;
                var data = error.response && error.response.data;
                if (!skipAuthRedirectForAuthFormsPath(path) && handleUnauthorizedFromApi(status, data)) {
                    return new Promise(function () {});
                }
                if (handleForbiddenFromApi(status, data)) {
                    return new Promise(function () {});
                }
                return Promise.reject(error);
            });
        }

        if ((m === "get" || m === "head") && payload && typeof payload === "object") {
            var qs = new URLSearchParams();
            Object.keys(payload).forEach(function (k) {
                var v = payload[k];
                if (v === undefined || v === null) {
                    return;
                }
                if (typeof v === "object") {
                    qs.append(k, JSON.stringify(v));
                } else {
                    qs.append(k, String(v));
                }
            });
            var q = qs.toString();
            if (q) {
                url += (url.indexOf("?") === -1 ? "?" : "&") + q;
            }
        }

        return fetch(url, {
            method: m.toUpperCase(),
            headers: buildHeaders(),
            credentials: "same-origin",
            body: m !== "get" && m !== "head" && payload ? JSON.stringify(payload) : undefined,
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!response.ok) {
                    if (!skipAuthRedirectForAuthFormsPath(path) && handleUnauthorizedFromApi(response.status, data)) {
                        return new Promise(function () {});
                    }
                    if (handleForbiddenFromApi(response.status, data)) {
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

    function parseFilenameFromContentDisposition(header) {
        if (!header || typeof header !== "string") {
            return null;
        }
        var star = header.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);
        if (star && star[1]) {
            try {
                return decodeURIComponent(star[1].trim());
            } catch (_e) {
                return star[1].trim();
            }
        }
        var basic = header.match(/filename\s*=\s*("?)([^";\n]+)\1/i);
        return basic && basic[2] ? basic[2].trim() : null;
    }

    function triggerBlobDownload(blob, filename) {
        var link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = filename || "download";
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function () {
            try {
                URL.revokeObjectURL(link.href);
            } catch (_e) {}
        }, 2000);
    }

    /**
     * GET file/binary under /v1 with same auth + tenant headers as JSON API.
     * @param {string} path path after /v1 e.g. /reconciliation/exports/12/download
     * @param {string} [filename] optional download filename
     * @returns {Promise<void>}
     */
    function downloadV1Binary(path, filename) {
        var url = baseURL + path;
        var headers = buildHeaders({ Accept: "application/octet-stream, */*" });
        delete headers["Content-Type"];

        if (window.axios) {
            return window.axios({
                method: "get",
                url: url,
                responseType: "blob",
                headers: headers,
                withCredentials: true,
            })
                .then(function (res) {
                    var blob = res.data;
                    var ct = (res.headers && (res.headers["content-type"] || res.headers["Content-Type"])) || "";
                    if (typeof ct === "string" && ct.indexOf("application/json") !== -1) {
                        return blob.text().then(function (t) {
                            var data = {};
                            try {
                                data = JSON.parse(t);
                            } catch (_e) {}
                            if (handleUnauthorizedFromApi(res.status, data)) {
                                return;
                            }
                            var err = new Error((data && data.error && data.error.message) || "Download failed");
                            err.response = { status: res.status, data: data };
                            throw err;
                        });
                    }
                    var name =
                        filename ||
                        parseFilenameFromContentDisposition(res.headers && res.headers["content-disposition"]) ||
                        "download";
                    triggerBlobDownload(blob, name);
                })
                .catch(function (error) {
                    var status = error.response && error.response.status;
                    var data = error.response && error.response.data;
                    if (data instanceof Blob) {
                        return data.text().then(function (t) {
                            var parsed = {};
                            try {
                                parsed = JSON.parse(t);
                            } catch (_e) {}
                            if (!skipAuthRedirectForAuthFormsPath(path) && handleUnauthorizedFromApi(status, parsed)) {
                                return;
                            }
                            var err = new Error((parsed && parsed.error && parsed.error.message) || "Download failed");
                            err.response = { status: status, data: parsed };
                            throw err;
                        });
                    }
                    if (!skipAuthRedirectForAuthFormsPath(path) && handleUnauthorizedFromApi(status, data)) {
                        return;
                    }
                    return Promise.reject(error);
                });
        }

        return fetch(url, {
            method: "GET",
            headers: headers,
            credentials: "same-origin",
        }).then(function (response) {
            return response.blob().then(function (blob) {
                var ct = response.headers.get("content-type") || "";
                if (!response.ok) {
                    if (ct.indexOf("application/json") !== -1) {
                        return blob.text().then(function (t) {
                            var data = {};
                            try {
                                data = JSON.parse(t);
                            } catch (_e) {}
                            if (!skipAuthRedirectForAuthFormsPath(path) && handleUnauthorizedFromApi(response.status, data)) {
                                return;
                            }
                            var err = new Error((data && data.error && data.error.message) || "Download failed");
                            err.response = { data: data, status: response.status };
                            throw err;
                        });
                    }
                    var err = new Error("Download failed");
                    err.response = { status: response.status };
                    throw err;
                }
                if (ct.indexOf("application/json") !== -1) {
                    return blob.text().then(function (t) {
                        var data = {};
                        try {
                            data = JSON.parse(t);
                        } catch (_e) {}
                        if (handleUnauthorizedFromApi(response.status, data)) {
                            return;
                        }
                        var err = new Error((data && data.error && data.error.message) || "Download failed");
                        err.response = { data: data, status: response.status };
                        throw err;
                    });
                }
                var name =
                    filename ||
                    parseFilenameFromContentDisposition(response.headers.get("content-disposition")) ||
                    "download";
                triggerBlobDownload(blob, name);
            });
        });
    }

    window.AuthApi = {
        tokenKey: TOKEN_KEY,
        request: request,
        downloadV1Binary: downloadV1Binary,
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
        getTenantContext: getTenantContext,
        setTenantContext: setTenantContext,
        clearTenantContext: clearTenantContext,
        getToken: getToken,
        isUnauthorizedApiPayload: isUnauthorizedApiPayload,
        redirectToLoginAfterAuthFailure: redirectToLoginAfterAuthFailure,
        handleUnauthorizedFromApi: handleUnauthorizedFromApi,
        handleForbiddenFromApi: handleForbiddenFromApi,
    };

    window.ArcavUi = window.ArcavUi || {};

    function ensureInfoModal() {
        var id = "arcav_hcm_info_modal";
        var el = document.getElementById(id);
        if (el) {
            return el;
        }
        el = document.createElement("div");
        el.className = "modal fade";
        el.id = id;
        el.tabIndex = -1;
        el.setAttribute("aria-hidden", "true");
        el.setAttribute("data-bs-backdrop", "static");
        el.innerHTML =
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title" data-arcav-info-title>Info</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            "</div>" +
            '<div class="modal-body">' +
            '<div class="text-muted small" data-arcav-info-body></div>' +
            "</div>" +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>' +
            "</div>" +
            "</div>" +
            "</div>";
        document.body.appendChild(el);
        return el;
    }

    function ensureSelectModal() {
        var id = "arcav_hcm_select_modal";
        var el = document.getElementById(id);
        if (el) {
            return el;
        }
        el = document.createElement("div");
        el.className = "modal fade";
        el.id = id;
        el.tabIndex = -1;
        el.setAttribute("aria-hidden", "true");
        el.setAttribute("data-bs-backdrop", "static");
        el.innerHTML =
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title" data-arcav-select-title>Pilih</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            "</div>" +
            '<div class="modal-body">' +
            '<p class="mb-3 text-muted" data-arcav-select-body></p>' +
            '<div class="list-group" data-arcav-select-options></div>' +
            "</div>" +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>' +
            "</div>" +
            "</div>" +
            "</div>";
        document.body.appendChild(el);
        return el;
    }

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

    /**
     * Template-aligned confirm dialog (reuses confirm delete modal).
     * @param {string} message
     * @param {string} [title]
     * @returns {Promise<boolean>}
     */
    window.ArcavUi.confirm = function (message, title) {
        if (typeof window.ArcavUi.confirmDelete === "function") {
            return window.ArcavUi.confirmDelete(message, title || "Konfirmasi");
        }
        return Promise.resolve(false);
    };

    /**
     * Template-aligned info modal (Bootstrap modal).
     * @param {string} title
     * @param {string} bodyText
     */
    window.ArcavUi.showInfo = function (title, bodyText) {
        var el = ensureInfoModal();
        if (!el || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }
        var titleEl = el.querySelector("[data-arcav-info-title]");
        var bodyEl = el.querySelector("[data-arcav-info-body]");
        if (titleEl) {
            titleEl.textContent = title || "Info";
        }
        if (bodyEl) {
            bodyEl.textContent = bodyText || "";
        }
        window.bootstrap.Modal.getOrCreateInstance(el).show();
    };

    /**
     * Template-aligned select modal (Bootstrap modal).
     * @param {{title?: string, message?: string, options: Array<{value: string, label: string}>}} payload
     * @returns {Promise<string|null>}
     */
    window.ArcavUi.selectOption = function (payload) {
        var opts = payload && Array.isArray(payload.options) ? payload.options : [];
        if (!opts.length) {
            return Promise.resolve(null);
        }
        var el = ensureSelectModal();
        if (!el || !window.bootstrap || !window.bootstrap.Modal) {
            return Promise.resolve(null);
        }

        return new Promise(function (resolve) {
            var titleEl = el.querySelector("[data-arcav-select-title]");
            var bodyEl = el.querySelector("[data-arcav-select-body]");
            var listEl = el.querySelector("[data-arcav-select-options]");
            if (titleEl) {
                titleEl.textContent = (payload && payload.title) || "Pilih";
            }
            if (bodyEl) {
                bodyEl.textContent = (payload && payload.message) || "";
            }
            if (listEl) {
                listEl.innerHTML = "";
                opts.forEach(function (item) {
                    var btn = document.createElement("button");
                    btn.type = "button";
                    btn.className = "list-group-item list-group-item-action";
                    btn.textContent = String(item && item.label ? item.label : item.value);
                    btn.addEventListener(
                        "click",
                        function () {
                            resolve(String(item.value));
                            window.bootstrap.Modal.getOrCreateInstance(el).hide();
                        },
                        { once: true }
                    );
                    listEl.appendChild(btn);
                });
            }

            el.addEventListener(
                "hidden.bs.modal",
                function () {
                    resolve(null);
                },
                { once: true }
            );
            window.bootstrap.Modal.getOrCreateInstance(el).show();
        });
    };
})(window);
